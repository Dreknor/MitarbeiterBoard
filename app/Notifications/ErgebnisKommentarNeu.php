<?php

namespace App\Notifications;

use App\Models\ErgebnisKommentar;

/**
 * Neuer Kommentar auf einem Board bzw. an einer Karte – oder eine @-Erwähnung darin.
 * Empfänger bestimmt ErgebnisboardKommentarService::benachrichtigen().
 */
class ErgebnisKommentarNeu extends Benachrichtigung
{
    public function __construct(
        public ErgebnisKommentar $kommentar,
        public string $von,
        public string $vorschau,
        public bool $erwaehnung = false,
    ) {
    }

    public function kategorie(): string
    {
        return 'boards';
    }

    public function titel(object $notifiable): string
    {
        $board = $this->kommentar->board;
        $ort = $this->kommentar->karte
            ? 'Karte „'.$this->kommentar->karte->titel.'“'
            : 'Board „'.$board->titel.'“';

        return $this->erwaehnung
            ? $this->von.' hat Sie erwähnt: '.$ort
            : 'Neuer Kommentar: '.$ort;
    }

    public function text(object $notifiable): string
    {
        $text = $this->von.': '.$this->vorschau;

        return $this->kommentar->karte ? $text.' – Board „'.$this->kommentar->board->titel.'“' : $text;
    }

    public function aktionText(): string
    {
        return 'Kommentar ansehen';
    }

    public function url(object $notifiable): ?string
    {
        $ziel = $this->kommentar->karte_id
            ? '#karte-'.$this->kommentar->karte_id.'-kommentare'
            : '#kommentare';

        return route('ergebnisboards.show', $this->kommentar->board_id).$ziel;
    }

    public function zusatzdaten(object $notifiable): array
    {
        return [
            'ergebnisboard_id' => $this->kommentar->board_id,
            'karte_id' => $this->kommentar->karte_id,
            'kommentar_id' => $this->kommentar->id,
        ];
    }
}
