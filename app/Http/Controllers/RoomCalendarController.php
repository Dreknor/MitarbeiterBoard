<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomBooking;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoomCalendarController extends Controller
{
    /**
     * Public iCal feed for a room identified by token.
     * URL: /rooms/{room}/calendar/{token}.ics
     */
    public function feed(Request $request, Room $room, $token)
    {
        // Token must match
        if (!$room->feed_token || !hash_equals($room->feed_token, $token)) {
            return response('Not Found', 404);
        }

        // Check expiry
        if ($room->feed_expires_at && Carbon::now()->greaterThan($room->feed_expires_at)) {
            return response('Feed expired', 410);
        }

        // Collect bookings for next 3 months (configurable)
        $start = Carbon::now()->startOfDay();
        $end = Carbon::now()->addMonths(3)->endOfDay();

        $bookings = RoomBooking::query()
            ->where('room_id', $room->id)
            ->where(function($query) use ($start, $end) {
                // Individual bookings within range
                $query->where(function($q) use ($start, $end) {
                    $q->where('is_recurring', false)
                      ->whereBetween('booking_date', [$start, $end]);
                })
                // or recurring bookings (we'll expand into occurrences)
                ->orWhere('is_recurring', true);
            })
            ->get();

        // Build ICS
        $prodId = '-//'.config('app.name').'//EN';
        $now = Carbon::now()->utc();

        $lines = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:'.$prodId;
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:PUBLISH';

        foreach ($bookings as $booking) {
            if ($booking->is_recurring) {
                // Expand recurring bookings for next 3 months by iterating each date
                $periodStart = $start->copy();
                while ($periodStart->lte($end)) {
                    $week = null; // week type logic not expanded; keep both weeks if week is null
                    if ($booking->appliesToDate($periodStart, $week)) {
                        $lines = array_merge($lines, $this->formatBookingAsEvent($room, $booking, $periodStart));
                    }
                    $periodStart->addDay();
                }
            } else {
                $occurs = $booking->booking_date;
                if ($occurs && $occurs->between($start, $end)) {
                    $lines = array_merge($lines, $this->formatBookingAsEvent($room, $booking, $occurs));
                }
            }
        }

        $lines[] = 'END:VCALENDAR';

        $content = implode("\r\n", $lines) . "\r\n";

        return response($content, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($room->name).'.ics"'
        ]);
    }

    protected function formatBookingAsEvent(Room $room, RoomBooking $booking, Carbon $date)
    {
        $uid = $room->id . '-' . $booking->id . '-' . $date->format('Ymd');
        $dtStart = Carbon::parse($date->format('Y-m-d') . ' ' . $booking->start)->format('Ymd\THis');
        $dtEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $booking->end)->format('Ymd\THis');
        $now = Carbon::now()->utc()->format('Ymd\THis\Z');

        $summary = $booking->name;
        $description = 'Raum: '.$room->name."\n".'Erstellt von: '.($booking->user?->name ?? 'Unbekannt');

        return [
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.$now,
            'DTSTART;TZID=Europe/Berlin:'.$dtStart,
            'DTEND;TZID=Europe/Berlin:'.$dtEnd,
            'SUMMARY:'.preg_replace('/[\r\n]+/', ' ', addslashes($summary)),
            'DESCRIPTION:'.preg_replace('/[\r\n]+/', '\\n', addslashes($description)),
            'END:VEVENT',
        ];
    }
}

