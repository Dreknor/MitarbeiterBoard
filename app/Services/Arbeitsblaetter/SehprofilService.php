<?php

namespace App\Services\Arbeitsblaetter;

use App\Models\Arbeitsblaetter\AbFassung;
use App\Models\Arbeitsblaetter\AbProfilVorlage;
use App\Models\Arbeitsblaetter\Sehprofil;
use App\Models\Klasse;
use App\Models\Schueler;
use App\Models\User;
use App\Support\AbProfil;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sehprofile der Kinder und Profil-Vorlagen.
 *
 * - Ein Profil = Vorlage + Abweichungen; gespeichert werden nur die Abweichungen.
 * - Ändern sich die wirksamen Werte, werden die Fassungen des Kindes `veraltet`; an
 *   Wochenplänen hängende Fassungen werden ohne KI neu gesetzt ({@see AbWochenplanService}).
 * - Keine Diagnose, kein Visus – nur Darstellungswerte ({@see AbProfil}).
 */
class SehprofilService
{
    public function profilFuer(Schueler|int $schueler): ?Sehprofil
    {
        $id = $schueler instanceof Schueler ? $schueler->id : $schueler;

        return Sehprofil::with('vorlage')->where('schueler_id', $id)->first();
    }

    /** Wirksame Werte eines aktiven Profils, sonst null. */
    public function wirksameWerte(Schueler|int $schueler): ?array
    {
        $profil = $this->profilFuer($schueler);

        return $profil && $profil->aktiv ? $profil->wirksameWerte() : null;
    }

    public function profilHash(Schueler|int $schueler): ?string
    {
        $werte = $this->wirksameWerte($schueler);

        return $werte ? AbProfil::hash($werte) : null;
    }

    /**
     * Speichert das Profil eines Kindes. `$werte` sind die gewünschten (vollständigen oder
     * teilweisen) Werte; gespeichert wird nur, was von der Vorlage abweicht.
     */
    public function speichern(Schueler $schueler, ?int $vorlageId, array $werte, bool $aktiv, ?User $user): Sehprofil
    {
        $vorlage = $vorlageId ? AbProfilVorlage::find($vorlageId) : null;
        $basis = $vorlage?->wirksameWerte() ?? AbProfil::standard();

        $profil = Sehprofil::with('vorlage')->firstOrNew(['schueler_id' => $schueler->id]);
        $alterHash = $profil->exists && $profil->aktiv ? $profil->profilHash() : null;
        $warAktiv = $profil->exists && $profil->aktiv;

        $profil->fill([
            'vorlage_id' => $vorlage?->id,
            'werte' => AbProfil::abweichungen($werte, $basis),
            'aktiv' => $aktiv,
            'geaendert_von' => $user?->id,
        ]);
        $profil->setRelation('vorlage', $vorlage);
        $profil->save();

        $neuerHash = $aktiv ? $profil->profilHash() : null;
        if ($alterHash !== $neuerHash) {
            $this->fassungenVeralten($schueler->id, $neuerHash);
        }

        if ($aktiv && (! $warAktiv || $alterHash !== $neuerHash)) {
            app(AbWochenplanService::class)->kindNachziehenAnfordern($schueler->id);
        }

        return $profil;
    }

    public function entfernen(Schueler $schueler): void
    {
        Sehprofil::where('schueler_id', $schueler->id)->delete();
        $this->fassungenVeralten($schueler->id, null);
    }

    /** Markiert Fassungen eines Kindes mit anderem Profil-Hash als veraltet. */
    public function fassungenVeralten(int $schuelerId, ?string $aktuellerHash): int
    {
        return AbFassung::where('schueler_id', $schuelerId)
            ->where('status', AbFassung::AKTUELL)
            ->when($aktuellerHash, fn ($q) => $q->where('profil_hash', '!=', $aktuellerHash))
            ->update(['status' => AbFassung::VERALTET, 'updated_at' => now()]);
    }

    // ─── Vorlagen ─────────────────────────────────────────────────────────────

    public function vorlagen(): Collection
    {
        return AbProfilVorlage::orderBy('sortierung')->orderBy('name')->get();
    }

    public function vorlageSpeichern(?AbProfilVorlage $vorlage, string $name, array $werte): AbProfilVorlage
    {
        return DB::transaction(function () use ($vorlage, $name, $werte) {
            $vorlage ??= new AbProfilVorlage(['sortierung' => (int) AbProfilVorlage::max('sortierung') + 1]);
            $alt = $vorlage->exists ? AbProfil::hash($vorlage->wirksameWerte()) : null;
            $vorlage->fill(['name' => trim($name), 'werte' => AbProfil::normalisieren($werte)])->save();

            // Kinder mit dieser Vorlage: Fassungen veralten lassen und neu setzen
            if ($alt && $alt !== AbProfil::hash($vorlage->wirksameWerte())) {
                Sehprofil::with('vorlage')->where('vorlage_id', $vorlage->id)->where('aktiv', true)->get()
                    ->each(function (Sehprofil $profil) {
                        $this->fassungenVeralten($profil->schueler_id, $profil->profilHash());
                        app(AbWochenplanService::class)->kindNachziehenAnfordern($profil->schueler_id);
                    });
            }

            return $vorlage;
        });
    }

    /** Löschen nur, wenn kein Profil die Vorlage nutzt. Liefert eine Fehlermeldung oder null. */
    public function vorlageLoeschen(AbProfilVorlage $vorlage): ?string
    {
        $anzahl = Sehprofil::where('vorlage_id', $vorlage->id)->count();
        if ($anzahl > 0) {
            return 'Die Vorlage wird noch von '.$anzahl.' Profil'.($anzahl === 1 ? '' : 'en').' genutzt.';
        }
        $vorlage->delete();

        return null;
    }

    // ─── Abfragen ─────────────────────────────────────────────────────────────

    /** Kinder mit aktivem Profil (optional nur bestimmter Klassen), nach Klasse und Name. */
    public function kinderMitProfil(?array $klassenIds = null): Collection
    {
        return Schueler::query()
            ->with(['klasse'])
            ->whereIn('id', Sehprofil::query()->where('aktiv', true)->select('schueler_id'))
            ->when($klassenIds !== null, fn ($q) => $q->whereIn('klasse_id', $klassenIds))
            ->orderBy('nachname')->orderBy('vorname')
            ->get();
    }

    /** Hat die Klasse Kinder mit aktivem Profil? */
    public function klasseHatProfilkinder(?int $klasseId): bool
    {
        if (! $klasseId) {
            return false;
        }

        return Sehprofil::query()->where('aktiv', true)
            ->whereIn('schueler_id', Schueler::query()->where('klasse_id', $klasseId)->select('id'))
            ->exists();
    }

    /** Klassen, die die Person auf der Profilseite sieht. */
    public function klassenFuer(User $user): Collection
    {
        $alle = $user->can('manage', Sehprofil::class) || \App\Models\Wochenplan\WpPlan::darfAlles($user);
        $ids = $alle ? null : $user->paedKlassenIds()->all();

        return Klasse::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('name')
            ->get();
    }
}
