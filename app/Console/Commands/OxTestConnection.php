<?php

namespace App\Console\Commands;

use App\Services\OxCalendarService;
use Illuminate\Console\Command;

class OxTestConnection extends Command
{
    protected $signature = 'ox:test-connection';
    protected $description = 'Testet die CalDAV-Verbindung zum Open-Xchange-Server';

    public function handle(OxCalendarService $service): int
    {
        $this->info('Teste CalDAV-Verbindung...');
        $this->newLine();

        // Config anzeigen (ohne Passwort!)
        $this->table(['Einstellung', 'Wert'], [
            ['URL',        config('ox-calendar.url')      ?: '(nicht konfiguriert)'],
            ['Username',   config('ox-calendar.username') ?: '(nicht konfiguriert)'],
            ['Passwort',   config('ox-calendar.password') ? '***' : '(nicht konfiguriert)'],
            ['SSL-Verify', config('ox-calendar.verify_ssl') ? 'Ja' : 'Nein'],
            ['Timeout',    config('ox-calendar.timeout') . 's'],
            ['Aktiviert',  config('ox-calendar.enabled') ? 'Ja' : 'Nein'],
        ]);

        $this->newLine();

        if (!$service->isEnabled()) {
            $this->error('Kalender-Modul ist nicht aktiviert. Prüfe .env-Konfiguration.');
            return Command::FAILURE;
        }

        $result = $service->testConnection();

        if ($result['success']) {
            $this->info('✅ ' . $result['message']);
            if (isset($result['status'])) {
                $this->line('   HTTP-Status: ' . $result['status']);
            }
            return Command::SUCCESS;
        }

        $this->error('❌ ' . $result['message']);
        return Command::FAILURE;
    }
}

