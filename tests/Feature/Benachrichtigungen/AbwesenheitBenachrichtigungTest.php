<?php

namespace Tests\Feature\Benachrichtigungen;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\AbwesenheitGemeldet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AbwesenheitBenachrichtigungTest extends TestCase
{
    public function test_sofortmeldung_nur_an_personen_mit_eingeschalteter_kategorie(): void
    {
        Notification::fake();

        $meldende = $this->actingAsWithPermission('view absences', 'create absences');
        $abonniert = $this->createUserWithPermission('view absences');
        NotificationPreference::create(['user_id' => $abonniert->id, 'kategorie' => 'abwesenheiten', 'push' => false, 'mail' => 'sofort']);
        $nichtAbonniert = $this->createUserWithPermission('view absences');
        $ohneRecht = User::factory()->create();
        NotificationPreference::create(['user_id' => $ohneRecht->id, 'kategorie' => 'abwesenheiten', 'push' => true, 'mail' => 'sofort']);

        $this->post('absences', [
            'users_id' => $meldende->id,
            'reason'   => 'Krankheit',
            'start'    => today()->toDateString(),
            'end'      => today()->addDay()->toDateString(),
        ])->assertSessionHas('type', 'success');

        Notification::assertSentTo($abonniert, AbwesenheitGemeldet::class);
        Notification::assertNotSentTo($nichtAbonniert, AbwesenheitGemeldet::class);
        Notification::assertNotSentTo($ohneRecht, AbwesenheitGemeldet::class);
    }

    public function test_migration_uebernimmt_bisherige_abos(): void
    {
        $migration = require database_path('migrations/2026_10_06_000003_ersetze_abwesenheits_abo_durch_benachrichtigungen.php');

        // Zustand vor der Umstellung herstellen
        $migration->down();
        $this->assertTrue(Schema::hasColumn('users', 'absence_abo_now'));

        $sofort = User::factory()->create();
        $taeglich = User::factory()->create();
        $beides = User::factory()->create();
        DB::table('users')->where('id', $sofort->id)->update(['absence_abo_now' => 1]);
        DB::table('users')->where('id', $taeglich->id)->update(['absence_abo_daily' => 1]);
        DB::table('users')->where('id', $beides->id)->update(['absence_abo_now' => 1, 'absence_abo_daily' => 1]);
        // Bestehende eigene Auswahl wird ergänzt, nicht überschrieben
        DB::table('tagesvorschau_einstellungen')->insert([
            'user_id' => $beides->id, 'per_mail' => false, 'per_push' => true, 'zeitpunkt' => 'vorabend',
            'uhrzeit' => '19:00:00', 'bereiche' => json_encode(['meetings']), 'kalender_ids' => json_encode([]),
            'eingeladene_termine' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertFalse(Schema::hasColumn('users', 'absence_abo_now'));
        $this->assertFalse(Schema::hasColumn('users', 'absence_abo_daily'));

        $this->assertDatabaseHas('notification_preferences', ['user_id' => $sofort->id, 'kategorie' => 'abwesenheiten', 'mail' => 'sofort']);
        $this->assertDatabaseHas('notification_preferences', ['user_id' => $beides->id, 'kategorie' => 'abwesenheiten', 'mail' => 'sofort']);
        $this->assertDatabaseMissing('notification_preferences', ['user_id' => $taeglich->id, 'kategorie' => 'abwesenheiten']);

        $neu = $taeglich->fresh()->tagesvorschauEinstellung;
        $this->assertContains('abwesenheiten', $neu->bereiche);
        $this->assertContains('vertretungen', $neu->bereiche);
        $this->assertSame('07:30', $neu->uhrzeitKurz());

        $ergaenzt = $beides->fresh()->tagesvorschauEinstellung;
        $this->assertSame(['meetings', 'abwesenheiten'], $ergaenzt->bereiche);
        $this->assertSame('vorabend', $ergaenzt->zeitpunkt);
        $this->assertTrue($ergaenzt->per_mail);

        $this->assertNull($sofort->fresh()->tagesvorschauEinstellung);
    }
}
