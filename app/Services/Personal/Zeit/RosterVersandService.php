<?php

namespace App\Services\Personal\Zeit;

use App\Mail\SendRosterMail;
use App\Models\personal\Roster;
use App\Models\User;
use App\Services\NextcloudTalkService;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * PDF-Erzeugung und Versand eines Dienstplans (E-Mail, Nextcloud Talk).
 * Gemeinsam genutzt vom Web (RosterController) und der API (RosterApiController).
 */
class RosterVersandService
{
    public function __construct(private readonly RosterService $rosters)
    {
    }

    public function pdf(Roster $roster)
    {
        return PDF::loadView('personal.rosters.pdf.pdf', [
            'roster' => $roster,
            'employes' => $this->rosters->mitarbeitende($roster),
            'working_times' => $roster->working_times,
            'events' => $roster->events,
            'department' => $roster->department,
        ])->setOptions([
            'encoding' => 'utf-8',
            'margin-top' => '8',
            'page-size' => 'A3',
            'orientation' => 'Landscape',
        ]);
    }

    public function pdfPerson(Roster $roster, User $employe)
    {
        return PDF::loadView('personal.rosters.pdf.pdfEmploye', [
            'roster' => $roster,
            'working_times' => $roster->working_times()->where('employe_id', $employe->id)->get(),
            'events' => $roster->events()->where('employe_id', $employe->id)->get(),
            'employe' => $employe,
        ])->setOptions([
            'encoding' => 'utf-8',
            'margin-top' => '10',
            'margin-bottom' => '10',
            'page-size' => 'A4',
            'orientation' => 'Landscape',
        ]);
    }

    /**
     * Gesamtplan + persönlichen Plan an alle Mitarbeitenden mit E-Mail-Adresse senden.
     *
     * @return int Anzahl der Empfänger
     */
    public function perMail(Roster $roster, User $absender): int
    {
        $gesamt = $this->pdf($roster)->output();
        $anzahl = 0;

        foreach ($this->rosters->mitarbeitende($roster) as $employe) {
            if (!$employe->email) {
                continue;
            }
            $dateien = [
                'dienstplan_'.$roster->start_date->format('Y_m_d').'.pdf' => $gesamt,
                'dienstplan_'.Str::slug($employe->name).'.pdf' => $this->pdfPerson($roster, $employe)->output(),
            ];
            Mail::to($employe->email)->queue(new SendRosterMail($employe->vorname, $employe->nachname, $roster->start_date->format('d.m.Y'), $absender->name, $dateien));
            $anzahl++;
        }

        return $anzahl;
    }

    /** Ist der Versand an Nextcloud Talk eingerichtet? */
    public function talkVerfuegbar(): bool
    {
        return (bool) config('nextcloud.enabled')
            && !empty(config('nextcloud.roster_chat_token'))
            && (new NextcloudTalkService())->isEnabled();
    }

    /**
     * Gesamtplan als PDF in den Dienstplan-Chat von Nextcloud Talk stellen.
     *
     * @return array{0: bool, 1: string} [Erfolg, Meldung]
     */
    public function anTalk(Roster $roster): array
    {
        $nextcloud = new NextcloudTalkService();
        if (!$nextcloud->isEnabled()) {
            return [false, 'Nextcloud Talk ist nicht aktiviert oder nicht konfiguriert.'];
        }

        $chatToken = config('nextcloud.roster_chat_token');
        if (empty($chatToken)) {
            return [false, 'Kein Nextcloud Talk Chat-Token konfiguriert.'];
        }

        $roster->loadMissing('department');
        $stand = now()->format('Y_m_d_H_i_s');
        $pfad = storage_path('app/tmp/dienstplan_'.$roster->id.'_'.Str::random(12).'.pdf');
        File::ensureDirectoryExists(dirname($pfad));

        try {
            $this->pdf($roster)->save($pfad, true);
            $nachricht = sprintf("📅 **Dienstplan %s**\n\nWoche vom %s bis %s\n", $roster->department->name, $roster->start_date->format('d.m.Y'), $roster->weekEnd()->format('d.m.Y'));
            $erfolg = $nextcloud->uploadAndShare($chatToken, $pfad, '/Dienstpläne/'.$roster->start_date->format('Y_m_d').'_dienstplan_Stand_'.$stand.'.pdf', $nachricht);
        } finally {
            File::delete($pfad);
        }

        return $erfolg
            ? [true, 'Dienstplan wurde an Nextcloud Talk gesendet.']
            : [false, 'Fehler beim Senden an Nextcloud Talk. Bitte Logs prüfen.'];
    }
}
