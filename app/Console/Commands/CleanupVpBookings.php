<?php

namespace App\Console\Commands;

use App\Models\RoomBooking;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CleanupVpBookings extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'room-bookings:cleanup-vp
                            {--days= : VP-Buchungen älter als X Tage löschen (Standard aus Einstellungen)}
                            {--dry-run : Nur anzeigen, nicht löschen}';

    protected $description = 'Löscht alte Vertretungsplan-Raumbuchungen (source=indiware_vp)';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: settings('vp_room_cleanup_days', 28));

        if ($days < 1) {
            $this->error('--days muss mindestens 1 sein.');
            return Command::FAILURE;
        }

        $cutoff = Carbon::now()->subDays($days);

        $query = RoomBooking::withTrashed()
            ->fromVertretungsplan()
            ->where('booking_date', '<', $cutoff);

        $count = $query->count();

        if ($this->option('dry-run')) {
            $this->info("[Dry-Run] Es würden {$count} VP-Buchungen vor dem {$cutoff->format('d.m.Y')} gelöscht.");
            return Command::SUCCESS;
        }

        $deleted = $query->forceDelete();

        $this->info("VP-Raumbuchungen bereinigt: {$deleted} Einträge gelöscht (älter als {$days} Tage).");

        return Command::SUCCESS;
    }
}

