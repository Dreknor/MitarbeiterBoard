<?php

namespace Tests\Unit\Commands;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests für den ox:test-connection Artisan-Command.
 * Entspricht TODO 06 der calendar-ox-Reihe.
 */
class OxTestConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ox-calendar.url'        => 'https://ox.example.com/caldav',
            'ox-calendar.username'   => 'testuser',
            'ox-calendar.password'   => 'testpass',
            'ox-calendar.enabled'    => true,
            'ox-calendar.verify_ssl' => true,
            'ox-calendar.timeout'    => 30,
        ]);
    }

    public function test_ox_test_connection_gibt_Erfolg_bei_gueltiger_Verbindung(): void
    {
        Http::fake([
            '*' => Http::response(
                '<?xml version="1.0"?><d:multistatus xmlns:d="DAV:">'
                . '<d:response><d:href>/caldav</d:href>'
                . '<d:propstat><d:prop><d:displayname>CalDAV</d:displayname></d:prop>'
                . '<d:status>HTTP/1.1 200 OK</d:status></d:propstat></d:response>'
                . '</d:multistatus>',
                207
            ),
        ]);

        $this->artisan('ox:test-connection')
            ->expectsOutputToContain('Verbindung erfolgreich')
            ->assertExitCode(0);
    }

    public function test_ox_test_connection_gibt_Fehler_wenn_deaktiviert(): void
    {
        config(['ox-calendar.enabled' => false]);

        $this->artisan('ox:test-connection')
            ->expectsOutputToContain('nicht aktiviert')
            ->assertExitCode(1);
    }

    public function test_ox_test_connection_zeigt_keine_Klartext_Passwoerter(): void
    {
        Http::fake([
            '*' => Http::response('<?xml version="1.0"?><d:multistatus xmlns:d="DAV:"></d:multistatus>', 207),
        ]);

        $this->artisan('ox:test-connection')
            ->doesntExpectOutputToContain('testpass')
            ->assertExitCode(0);
    }
}

