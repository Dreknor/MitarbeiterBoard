<?php

namespace App\Services\Benachrichtigungen;

use App\Mail\Tagesvorschau;
use App\Models\TagesvorschauEinstellung;
use App\Models\User;
use App\Notifications\TagesvorschauPush;
use App\Services\Benachrichtigungen\Tagesvorschau\TagesvorschauEintrag;
use App\Services\Benachrichtigungen\Tagesvorschau\TagesvorschauQuelle;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Tagesübersicht „Dein Tag“: sammelt pro Person, was am Zieltag ansteht,
 * und verschickt sie zur selbst gewählten Uhrzeit (morgens oder am Vorabend).
 */
class TagesvorschauService
{
    /** So lange nach der gewählten Uhrzeit wird ein verpasster Versand nachgeholt */
    public const NACHHOLEN_MINUTEN = 60;

    /** @var Collection<int, TagesvorschauQuelle>|null */
    private ?Collection $quellen = null;

    /**
     * Gespeicherte Einstellung oder Standardwerte.
     */
    public function einstellung(User $user): TagesvorschauEinstellung
    {
        return $user->tagesvorschauEinstellung ?? TagesvorschauEinstellung::standard($user);
    }

    /**
     * @return Collection<int, TagesvorschauQuelle>
     */
    public function quellen(): Collection
    {
        return $this->quellen ??= collect(config('benachrichtigungen.tagesvorschau.quellen', []))
            ->map(fn (string $klasse) => app($klasse));
    }

    /**
     * @return Collection<int, TagesvorschauQuelle>
     */
    public function sichtbareQuellen(User $user): Collection
    {
        return $this->quellen()->filter(fn (TagesvorschauQuelle $q) => $q->sichtbarFuer($user))->values();
    }

    /**
     * Bereiche mit Einträgen für den Zieltag (leere Bereiche fallen weg).
     *
     * @return Collection<int, array{bereich: string, label: string, icon: string, eintraege: Collection<int, TagesvorschauEintrag>}>
     */
    public function fuer(User $user, Carbon $tag, ?TagesvorschauEinstellung $einstellung = null): Collection
    {
        $einstellung ??= $this->einstellung($user);

        return $this->sichtbareQuellen($user)
            ->filter(fn (TagesvorschauQuelle $q) => $q->aktivFuer($user, $einstellung))
            ->map(function (TagesvorschauQuelle $q) use ($user, $tag, $einstellung) {
                try {
                    $eintraege = $q->eintraege($user, $tag, $einstellung)->values();
                } catch (\Throwable $e) {
                    // Ein defekter Bereich darf die restliche Übersicht nicht verhindern
                    Log::warning('Tagesvorschau: Bereich fehlgeschlagen', [
                        'bereich' => $q->bereich(),
                        'user'    => $user->id,
                        'error'   => $e->getMessage(),
                    ]);
                    $eintraege = collect();
                }

                return [
                    'bereich'   => $q->bereich(),
                    'label'     => $q->label(),
                    'icon'      => $q->icon(),
                    'eintraege' => $eintraege,
                ];
            })
            ->filter(fn (array $b) => $b['eintraege']->isNotEmpty())
            ->values();
    }

    public function istArbeitstag(Carbon $tag): bool
    {
        return $tag->isWeekday() && !is_holiday($tag);
    }

    /**
     * Für welchen Tag gilt die Übersicht, wenn sie jetzt verschickt wird?
     * null = kein Versand (Zieltag ist kein Arbeitstag).
     */
    public function zieltag(TagesvorschauEinstellung $einstellung, Carbon $jetzt): ?Carbon
    {
        $heute = $jetzt->copy()->startOfDay();

        if ($einstellung->zeitpunkt !== TagesvorschauEinstellung::VORABEND) {
            return $this->istArbeitstag($heute) ? $heute : null;
        }

        // Vorabend: nächster Arbeitstag (Freitagabend → Montag, vor Feiertagen weiter)
        $tag = $heute->copy()->addDay();
        for ($i = 0; $i < 14; $i++) {
            if ($this->istArbeitstag($tag)) {
                return $tag;
            }
            $tag->addDay();
        }

        return null;
    }

    /**
     * Ist jetzt das Versandfenster der Person (gewählte Uhrzeit bis +60 Min.)
     * und wurde für den Zieltag noch nichts verschickt? Am Vorabend geht die
     * Montags-Übersicht so genau einmal raus – am Freitagabend.
     */
    public function istFaellig(TagesvorschauEinstellung $einstellung, Carbon $jetzt): bool
    {
        if (!$einstellung->per_mail && !$einstellung->per_push) {
            return false;
        }

        $uhrzeit = Carbon::parse($jetzt->format('Y-m-d').' '.$einstellung->uhrzeitKurz(), $jetzt->getTimezone());

        if ($jetzt->lt($uhrzeit) || $jetzt->gte($uhrzeit->copy()->addMinutes(self::NACHHOLEN_MINUTEN))) {
            return false;
        }

        $zieltag = $this->zieltag($einstellung, $jetzt);

        if ($zieltag === null) {
            return false;
        }

        return $einstellung->zuletzt_fuer_tag === null || !$einstellung->zuletzt_fuer_tag->isSameDay($zieltag);
    }

    /**
     * Verschickt alle fälligen Übersichten. Gibt die Anzahl versendeter Übersichten zurück.
     */
    public function versendeFaellige(?Carbon $jetzt = null): int
    {
        $jetzt ??= now();
        $anzahl = 0;

        User::query()
            ->with('tagesvorschauEinstellung')
            ->where(fn ($q) => $q->whereNotNull('email')->where('email', '!=', '')->orWhereHas('pushSubscriptions'))
            ->chunkById(100, function ($users) use ($jetzt, &$anzahl) {
                foreach ($users as $user) {
                    $einstellung = $this->einstellung($user);

                    if (!$this->istFaellig($einstellung, $jetzt)) {
                        continue;
                    }

                    try {
                        if ($this->senden($user, $einstellung, $jetzt)) {
                            $anzahl++;
                        }
                    } catch (\Throwable $e) {
                        Log::error('Tagesvorschau: Versand fehlgeschlagen', ['user' => $user->id, 'error' => $e->getMessage()]);
                    }
                }
            });

        return $anzahl;
    }

    /**
     * Erstellt und verschickt die Übersicht einer Person.
     * Gibt false zurück, wenn nichts ansteht oder die Person abwesend ist.
     */
    public function senden(User $user, TagesvorschauEinstellung $einstellung, Carbon $jetzt): bool
    {
        $zieltag = $this->zieltag($einstellung, $jetzt);

        if ($zieltag === null) {
            return false;
        }

        // Zuerst merken – verhindert doppelte Mails, falls der Versand unten abbricht
        $this->merkeVersand($user, $einstellung, $zieltag);

        // Wer am Zieltag selbst fehlt, bekommt nichts (außer ausdrücklich gewünscht)
        if (!$user->send_mails_if_absence && ($user->hasAbsence($zieltag) || $user->hasHoliday($zieltag))) {
            return false;
        }

        $bereiche = $this->fuer($user, $zieltag, $einstellung);

        if ($bereiche->isEmpty()) {
            return false;
        }

        $vorabend = $einstellung->zeitpunkt === TagesvorschauEinstellung::VORABEND;

        if ($einstellung->per_mail && filled($user->email)) {
            Mail::to($user)->queue(new Tagesvorschau(
                name: $user->vorname ?? $user->name,
                zieltag: $zieltag,
                vorabend: $vorabend,
                bereiche: $this->alsArray($bereiche),
            ));
        }

        if ($einstellung->per_push && $user->pushSubscriptions()->exists()) {
            $user->notify(new TagesvorschauPush($zieltag, $vorabend, $this->kurzfassung($bereiche)));
        }

        return true;
    }

    /**
     * „2 Vertretungen · 1 Meetings · 3 Termine“
     */
    public function kurzfassung(Collection $bereiche): string
    {
        return $bereiche
            ->map(fn (array $b) => $b['eintraege']->count().' '.$b['label'])
            ->implode(' · ');
    }

    /**
     * Serialisierbare Form für die Mail (Queue).
     */
    public function alsArray(Collection $bereiche): array
    {
        return $bereiche->map(fn (array $b) => [
            'label'     => $b['label'],
            'eintraege' => $b['eintraege']->map(fn (TagesvorschauEintrag $e) => [
                'titel'       => $e->titel,
                'zeit'        => $e->zeit,
                'details'     => $e->details,
                'url'         => $e->url,
                'hervorheben' => $e->hervorheben,
            ])->all(),
        ])->all();
    }

    private function merkeVersand(User $user, TagesvorschauEinstellung $einstellung, Carbon $zieltag): void
    {
        if ($einstellung->exists) {
            $einstellung->update(['zuletzt_fuer_tag' => $zieltag->toDateString()]);

            return;
        }

        $neu = TagesvorschauEinstellung::create(array_merge(
            TagesvorschauEinstellung::standardWerte(),
            ['user_id' => $user->id, 'zuletzt_fuer_tag' => $zieltag->toDateString()]
        ));
        $user->setRelation('tagesvorschauEinstellung', $neu);
    }

    /**
     * Speichert die Einstellungen aus dem Formular (bereits validiert).
     */
    public function speichern(User $user, array $daten): TagesvorschauEinstellung
    {
        $sichtbar = $this->sichtbareQuellen($user)->map(fn (TagesvorschauQuelle $q) => $q->bereich());

        $bereiche = collect($daten['bereiche'] ?? [])
            ->intersect($sichtbar)
            ->values()
            ->all();

        return TagesvorschauEinstellung::updateOrCreate(
            ['user_id' => $user->id],
            [
                'per_mail'            => (bool) ($daten['per_mail'] ?? false),
                'per_push'            => (bool) ($daten['per_push'] ?? false),
                'zeitpunkt'           => $daten['zeitpunkt'] ?? TagesvorschauEinstellung::MORGENS,
                'uhrzeit'             => ($daten['uhrzeit'] ?? '06:30').':00',
                'bereiche'            => $bereiche,
                'kalender_ids'        => array_values(array_map('intval', $daten['kalender_ids'] ?? [])),
                'eingeladene_termine' => (bool) ($daten['eingeladene_termine'] ?? false),
            ]
        );
    }
}
