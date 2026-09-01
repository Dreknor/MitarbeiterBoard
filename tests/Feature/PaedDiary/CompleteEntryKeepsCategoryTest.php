<?php

namespace Tests\Feature\PaedDiary;

use App\Models\Klasse;
use App\Models\PaedDiaryCategory;
use App\Models\PaedDiaryEntry;
use App\Models\Schueler;
use Carbon\Carbon;
use Tests\TestCase;
use Tests\Traits\MocksExternalApis;

/**
 * Testet, dass beim Abschließen einer Notiz zu einem späteren Zeitpunkt
 * als der Erstellung (finalizeEntry() klont den Eintrag auf alle Tage
 * zwischen Erstellung und Abschluss) die Kategorie der Notiz auf allen
 * geklonten Tagen erhalten bleibt und nicht verloren geht.
 */
class CompleteEntryKeepsCategoryTest extends TestCase
{
    use MocksExternalApis;

    private function setupKlasseWithSchuelerAndUser(): array
    {
        $user   = $this->actingAsWithPermission('view paed diary');
        $klasse = Klasse::factory()->create();
        $klasse->paed_users()->attach($user->id);
        $schueler = Schueler::factory()->create(['klasse_id' => $klasse->id]);
        return [$user, $klasse, $schueler];
    }

    /** @test */
    public function kategorie_bleibt_an_folgetagen_erhalten(): void
    {
        [$user, $klasse, $schueler] = $this->setupKlasseWithSchuelerAndUser();
        $category = PaedDiaryCategory::create(['name' => 'Verhalten', 'user_id' => $user->id]);

        $erstellungsdatum = Carbon::today()->subDays(3);

        $entry = PaedDiaryEntry::create([
            'klasse_id'    => $klasse->id,
            'user_id'      => $user->id,
            'datum'        => $erstellungsdatum->toDateString(),
            'content'      => 'Notiz mit Kategorie',
            'completed_at' => null,
            'category_id'  => $category->id,
            'dossier_only' => false,
        ]);
        $entry->schueler()->attach($schueler->id);

        $abschlussdatum = Carbon::today();

        $response = $this->postJson("paed-diary/entry/{$entry->id}/complete", [
            'completed_at' => $abschlussdatum->toDateString(),
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        // Für jeden Tag zwischen Erstellung (exklusive) und Abschluss (inklusive)
        // muss ein Eintrag mit derselben Kategorie existieren.
        for ($d = $erstellungsdatum->copy()->addDay(); $d->lte($abschlussdatum); $d->addDay()) {
            $clone = PaedDiaryEntry::whereDate('datum', $d->toDateString())
                ->where('klasse_id', $klasse->id)
                ->whereHas('schueler', fn($q) => $q->where('schueler.id', $schueler->id))
                ->first();

            $this->assertNotNull($clone, "Kein Eintrag am {$d->toDateString()} gefunden");
            $this->assertEquals(
                $category->id,
                $clone->category_id,
                "Kategorie fehlt am {$d->toDateString()}"
            );
        }
    }
}

