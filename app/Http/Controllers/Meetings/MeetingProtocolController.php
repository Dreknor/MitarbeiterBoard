<?php

namespace App\Http\Controllers\Meetings;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Presence;
use App\Models\Protocol;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Gesamtprotokoll eines Meetings (gruppengebunden oder frei):
 * Protokolleinträge aller Agenda-Themen vom Meeting-Tag, inkl. PDF-Export.
 */
class MeetingProtocolController extends Controller
{
    public function show(Meeting $meeting)
    {
        $this->authorize('view', $meeting);

        return view('meetings.protocol', $this->data($meeting) + ['meeting' => $meeting]);
    }

    public function pdf(Request $request, Meeting $meeting)
    {
        $this->authorize('view', $meeting);

        $data = $this->data($meeting, [
            'closed'  => $request->boolean('closed'),
            'changed' => $request->boolean('changed'),
        ]);

        $pdf = PDF::loadView('meetings.protocol_pdf', $data + ['meeting' => $meeting]);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOption('enable-local-file-access', true);
        $pdf->setOption('margin-top', '20mm');
        $pdf->setOption('margin-bottom', '20mm');
        $pdf->setOption('margin-left', '15mm');
        $pdf->setOption('margin-right', '15mm');
        $pdf->setOption('encoding', 'UTF-8');
        $pdf->setOption('enable-smart-shrinking', false);
        $pdf->setOption('print-media-type', true);

        $filename = $meeting->date->format('Ymd') . '_Protokoll_' . Str::slug($meeting->title) . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * @param  array{closed?: bool, changed?: bool}  $options
     */
    private function data(Meeting $meeting, array $options = []): array
    {
        $includeClosed  = $options['closed'] ?? false;
        $includeChanged = $options['changed'] ?? false;

        $meeting->load(['group', 'creator', 'meetingTasks.user', 'themes' => fn ($q) => $q->with(['type', 'protocols.ersteller', 'tasks'])]);

        $systemTexts = ['Thema aktiviert', 'Thema in Themenspeicher verschoben', 'Thema geändert', 'Informationen geändert', 'Typ geändert'];

        $themes = $meeting->themes
            ->sortByDesc('priority')
            ->values()
            ->map(function ($theme) use ($meeting, $includeClosed, $includeChanged, $systemTexts) {
                $entries = $theme->protocols
                    ->filter(fn (Protocol $p) => $p->created_at->isSameDay($meeting->date))
                    ->filter(function (Protocol $p) use ($includeClosed, $includeChanged, $systemTexts) {
                        if (in_array(trim(strip_tags($p->protocol)), $systemTexts, true)) {
                            return false;
                        }

                        return (! $p->isClosed() || $includeClosed) && (! $p->isChanged() || $includeChanged);
                    })
                    ->sortBy('created_at')
                    ->values();

                $theme->setRelation('protocols', $entries);
                $theme->setRelation('tasks', $theme->tasks->filter(fn ($t) => $t->created_at->isSameDay($meeting->date))->values());

                return $theme;
            })
            ->filter(fn ($theme) => $theme->protocols->isNotEmpty())
            ->values();

        $authors = $themes->flatMap(fn ($t) => $t->protocols)->pluck('ersteller.name')->unique()->filter()->values();

        $participants = $meeting->resolvedParticipants();
        $presences    = Presence::where('meeting_id', $meeting->id)->with('user')->get();
        $recorded     = $presences->isNotEmpty();

        // Ohne erfasste Anwesenheit gelten alle Eingeladenen als Teilnehmende
        $attendance = [
            'recorded' => $recorded,
            'present'  => $recorded
                ? $presences->where('presence', true)->where('online', false)->whereNotNull('user_id')->pluck('user.name')->filter()->values()
                : $participants->pluck('name')->values(),
            'online'   => $presences->where('online', true)->pluck('user.name')->filter()->values(),
            'excused'  => $presences->where('excused', true)->pluck('user.name')->filter()->values(),
            'guests'   => $presences->whereNull('user_id')->pluck('guest_name')->filter()->values(),
        ];

        return [
            'themes'       => $themes,
            'participants' => $participants,
            'attendance'   => $attendance,
            'authors'      => $authors,
        ];
    }
}



