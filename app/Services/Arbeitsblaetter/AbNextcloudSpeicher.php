<?php

namespace App\Services\Arbeitsblaetter;

use App\Services\Wochenplan\Nextcloud\WpNextcloudSpeicher;

/**
 * Nextcloud-Ablage der Angepassten Arbeitsblätter (Fassungen, Umwandlung von Word-Dateien).
 * Pfade relativ zum Stammordner des Dienstkontos. In Tests wird
 * {@see \Tests\Fakes\AbNextcloudFake} gebunden.
 */
interface AbNextcloudSpeicher extends WpNextcloudSpeicher
{
}
