<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Ticket-Anhänge lagen bisher auf der "public"-Disk und waren damit ohne
 * Anmeldung über /storage/{id}/{datei} abrufbar. Verschiebt sie auf die
 * private Disk "tickets" (Pfadschema der Media-Library bleibt {id}/...).
 */
return new class extends Migration
{
    private const MODELS = ['App\\Models\\Ticket', 'App\\Models\\TicketComment'];

    public function up(): void
    {
        $this->move('public', 'tickets');
    }

    public function down(): void
    {
        $this->move('tickets', 'public');
    }

    private function move(string $from, string $to): void
    {
        $fromRoot = config("filesystems.disks.$from.root");
        $toRoot = config("filesystems.disks.$to.root");

        if (!$fromRoot || !$toRoot) {
            return;
        }

        File::ensureDirectoryExists($toRoot);

        DB::table('media')
            ->whereIn('model_type', self::MODELS)
            ->where('disk', $from)
            ->orderBy('id')
            ->each(function ($media) use ($from, $to, $fromRoot, $toRoot) {
                $source = $fromRoot.DIRECTORY_SEPARATOR.$media->id;
                $target = $toRoot.DIRECTORY_SEPARATOR.$media->id;

                if (is_dir($source) && !file_exists($target) && !@rename($source, $target)) {
                    Log::warning('Ticketsystem: Anhang konnte nicht verschoben werden', ['media' => $media->id]);

                    return;
                }

                DB::table('media')->where('id', $media->id)->update([
                    'disk' => $to,
                    'conversions_disk' => $media->conversions_disk === $from ? $to : $media->conversions_disk,
                ]);
            });
    }
};
