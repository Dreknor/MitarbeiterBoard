<?php

namespace Tests\Feature\Benachrichtigungen;

use App\Models\User;
use App\Models\Vertretung;
use App\Notifications\VertretungGeaendert;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class VertretungBenachrichtigungTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_neue_vertretung_wird_der_lehrkraft_gemeldet(): void
    {
        $lehrer = User::factory()->create();

        Vertretung::factory()->create(['users_id' => $lehrer->id, 'date' => today()->addDay()]);

        Notification::assertSentTo($lehrer, VertretungGeaendert::class, fn ($n) => $n->art === VertretungGeaendert::NEU);
    }

    public function test_vertretung_in_der_vergangenheit_wird_nicht_gemeldet(): void
    {
        $lehrer = User::factory()->create();

        Vertretung::factory()->create(['users_id' => $lehrer->id, 'date' => today()->subDay()]);

        Notification::assertNothingSentTo($lehrer);
    }

    public function test_aenderung_wird_gemeldet_unveraenderter_import_nicht(): void
    {
        $lehrer = User::factory()->create();
        $vertretung = Vertretung::factory()->create(['users_id' => $lehrer->id, 'date' => today()->addDays(2), 'comment' => null]);

        // Wie beim Import: gleiche Werte → kein Update-Event
        $vertretung->update(['comment' => null]);
        Notification::assertSentToTimes($lehrer, VertretungGeaendert::class, 1);

        $vertretung->update(['comment' => 'Raum: 204']);
        Notification::assertSentTo($lehrer, VertretungGeaendert::class, fn ($n) => $n->art === VertretungGeaendert::GEAENDERT && $n->kommentar === 'Raum: 204');
    }

    public function test_lehrerwechsel_meldet_alte_und_neue_lehrkraft(): void
    {
        $alt = User::factory()->create();
        $neu = User::factory()->create();
        $vertretung = Vertretung::factory()->create(['users_id' => $alt->id, 'date' => today()->addDay()]);

        $vertretung->update(['users_id' => $neu->id]);

        Notification::assertSentTo($alt, VertretungGeaendert::class, fn ($n) => $n->art === VertretungGeaendert::ENTFAELLT);
        Notification::assertSentTo($neu, VertretungGeaendert::class, fn ($n) => $n->art === VertretungGeaendert::NEU);
    }

    public function test_geloeschte_vertretung_wird_gemeldet(): void
    {
        $lehrer = User::factory()->create();
        $vertretung = Vertretung::factory()->create(['users_id' => $lehrer->id, 'date' => today()]);

        $vertretung->delete();

        Notification::assertSentTo($lehrer, VertretungGeaendert::class, fn ($n) => $n->art === VertretungGeaendert::ENTFAELLT);
    }

    public function test_wer_selbst_bearbeitet_bekommt_keine_meldung(): void
    {
        $lehrer = $this->actingAsWithPermission('edit vertretungen');

        Vertretung::factory()->create(['users_id' => $lehrer->id, 'date' => today()->addDay()]);

        Notification::assertNothingSentTo($lehrer);
    }
}
