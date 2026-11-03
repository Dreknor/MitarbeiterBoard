<?php

namespace Tests\Unit;

use App\Support\LogCategories;
use PHPUnit\Framework\TestCase;

class LogCategoriesTest extends TestCase
{
    /**
     * @dataProvider messages
     */
    public function test_log_messages_are_categorized(string $message, string $expected): void
    {
        $this->assertSame($expected, LogCategories::categorize($message));
    }

    public static function messages(): array
    {
        return [
            ['Mail erfolgreich versendet (SMTP bestätigt)', 'email'],
            ['Prozesse: Mailversand fehlgeschlagen', 'email'],
            ['OIDC: User logged in by KeyCloak: max', 'login'],
            ['Login: Anmeldung fehlgeschlagen', 'login'],
            ['Benutzer: Benutzer importiert', 'benutzer'],
            ['Termin erstellen fehlgeschlagen', 'kalender'],
            ['VP-Raum: Raumkonflikt erkannt', 'vertretungsplan'],
            ['Personal: Rückwirkende Vertragsänderung erkannt', 'personal'],
            ['Ticketsystem: Ticket konnte nicht erstellt werden: x', 'prozesse'],
            ['Nextcloud file upload error: x', 'nextcloud'],
            ['Job failed: App\\Jobs\\Foo', 'system'],
            ['SQLSTATE[42S02]: Base table not found', LogCategories::OTHER],
            ['', LogCategories::OTHER],
        ];
    }

    public function test_unknown_category_does_not_exist(): void
    {
        $this->assertTrue(LogCategories::exists('email'));
        $this->assertTrue(LogCategories::exists(LogCategories::OTHER));
        $this->assertFalse(LogCategories::exists('gibtsnicht'));
        $this->assertFalse(LogCategories::exists(null));
    }
}
