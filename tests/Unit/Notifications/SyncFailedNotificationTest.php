<?php

namespace Tests\Unit\Notifications;

use App\Models\OxSyncLog;
use App\Models\User;
use App\Notifications\SyncFailedNotification;
use App\Services\OxCalendarService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Unit-Tests für SyncFailedNotification (TODO 21).
 */
class SyncFailedNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        config([
            'ox-calendar.url'      => 'https://ox.example.com/caldav',
            'ox-calendar.username' => 'testuser',
            'ox-calendar.password' => 'testpass',
            'ox-calendar.enabled'  => true,
        ]);
    }

    // =========================================================================
    // Notification-Inhalt
    // =========================================================================

    public function test_SyncFailedNotification_enthaelt_Fehlerdetails(): void
    {
        $notification = new SyncFailedNotification(3, 'Connection refused');
        $user         = User::factory()->create();

        $mail = $notification->toMail($user);

        $this->assertStringContainsString('fehlgeschlagen', $mail->subject);

        // Mail rendern und auf Inhalte prüfen
        $rendered = (string) $mail->render();
        $this->assertStringContainsString('3', $rendered);
        $this->assertStringContainsString('Connection refused', $rendered);
    }

    public function test_SyncFailedNotification_als_Array_enthaelt_Typ(): void
    {
        $notification = new SyncFailedNotification(3, 'Timeout');
        $array        = $notification->toArray(User::factory()->create());

        $this->assertSame('calendar_sync_failed', $array['typ']);
        $this->assertSame(3, $array['fehler_anzahl']);
    }

    // =========================================================================
    // Automatisches Auslösen bei 3+ Fehlern
    // =========================================================================

    public function test_Admins_werden_bei_3_Fehlern_benachrichtigt(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        Permission::findOrCreate('manage calendar');
        $admin->givePermissionTo('manage calendar');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 3 aufeinanderfolgende Fehler anlegen
        OxSyncLog::factory()->fehler()->count(3)->create([
            'created_at' => now()->subMinutes(5),
        ]);

        $service    = new OxCalendarService();
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('checkConsecutiveErrors');
        $method->setAccessible(true);
        $method->invoke($service);

        Notification::assertSentTo($admin, SyncFailedNotification::class);
    }

    public function test_Notification_wird_nicht_bei_weniger_als_3_Fehlern_gesendet(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        Permission::findOrCreate('manage calendar');
        $admin->givePermissionTo('manage calendar');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Nur 2 Fehler, dann ein Erfolg
        OxSyncLog::factory()->create(['aktion' => 'sync_complete', 'created_at' => now()->subMinutes(10)]);
        OxSyncLog::factory()->fehler()->count(2)->create(['created_at' => now()->subMinutes(5)]);

        $service    = new OxCalendarService();
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('checkConsecutiveErrors');
        $method->setAccessible(true);
        $method->invoke($service);

        Notification::assertNotSentTo($admin, SyncFailedNotification::class);
    }

    public function test_Spam_Schutz_Notification_nur_einmal_pro_Stunde(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        Permission::findOrCreate('manage calendar');
        $admin->givePermissionTo('manage calendar');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        OxSyncLog::factory()->fehler()->count(3)->create(['created_at' => now()->subMinutes(5)]);

        $service    = new OxCalendarService();
        $reflection = new \ReflectionClass($service);
        $method     = $reflection->getMethod('checkConsecutiveErrors');
        $method->setAccessible(true);

        // Erster Aufruf → Notification gesendet
        $method->invoke($service);
        Notification::assertSentToTimes($admin, SyncFailedNotification::class, 1);

        // Zweiter Aufruf → Cooldown aktiv, keine erneute Notification
        $method->invoke($service);
        Notification::assertSentToTimes($admin, SyncFailedNotification::class, 1);
    }

    // =========================================================================
    // Via-Channels
    // =========================================================================

    public function test_Notification_nutzt_mail_und_database_Channel(): void
    {
        $notification = new SyncFailedNotification(3, 'Test');
        $user         = User::factory()->create();

        $this->assertSame(['mail', 'database'], $notification->via($user));
    }
}

