<?php

namespace Tests\Feature\AngepassteArbeitsblaetter;

use App\Support\AbStruktur;
use Tests\TestCase;

/** Baustein-Format: Normalisierung, Grenzen, unbekannte Typen. */
class StrukturTest extends TestCase
{
    public function test_normalisierung_sichert_typen_und_felder(): void
    {
        $s = AbStruktur::normalisieren([
            'seiten' => 2,
            'bausteine' => [
                ['id' => 'b1', 'typ' => 'ueberschrift', 'text' => '  Brüche  ', 'farbe' => 'rot'],
                ['typ' => 'aufgabe', 'nummer' => '1', 'inhalt' => [['typ' => 'text', 'text' => '3/4 = '], ['typ' => 'luecke', 'laenge' => 99], ['typ' => 'text', 'text' => '/8']],
                    'kinder' => [['typ' => 'aufgabe', 'nummer' => 'a)', 'text' => 'x ___ y', 'kinder' => [['typ' => 'text', 'text' => 'zu tief']]]]],
                ['typ' => 'tabelle', 'zeilen' => [['a', 'b', 'c'], ['d']], 'kopfzeile' => 1],
                ['typ' => 'bild', 'media_id' => '12', 'quelle' => ['seite' => 1, 'box' => [0.1, 0.4, 1.5, 0.7]]],
                ['typ' => 'schreiblinien', 'anzahl' => 500],
            ],
        ]);

        $this->assertSame(2, $s['seiten']);
        $this->assertSame(['id' => 'b1', 'typ' => 'ueberschrift', 'text' => 'Brüche'], $s['bausteine'][0]);
        $aufgabe = $s['bausteine'][1];
        $this->assertSame(40, $aufgabe['inhalt'][1]['laenge']);
        $this->assertSame('3/4 = ', $aufgabe['inhalt'][0]['text']);
        $this->assertCount(1, $aufgabe['kinder']);
        $this->assertSame([], $aufgabe['kinder'][0]['kinder'], 'nur eine Ebene Unterbausteine');
        $this->assertSame('luecke', $aufgabe['kinder'][0]['inhalt'][1]['typ']);
        $this->assertSame([['a', 'b', 'c'], ['d', '', '']], $s['bausteine'][2]['zeilen']);
        $this->assertTrue($s['bausteine'][2]['kopfzeile']);
        $this->assertSame(12, $s['bausteine'][3]['media_id']);
        $this->assertSame([0.1, 0.4, 1.0, 0.7], $s['bausteine'][3]['quelle']['box']);
        $this->assertSame(30, $s['bausteine'][4]['anzahl']);
        $this->assertSame([12], AbStruktur::mediaIds($s));
    }

    public function test_unbekannte_typen_werden_text_und_ids_eindeutig(): void
    {
        $s = AbStruktur::normalisieren(['bausteine' => [
            ['id' => 'x', 'typ' => 'video', 'text' => 'Hallo'],
            ['id' => 'x', 'typ' => 'text', 'text' => 'Welt'],
            'Nur ein String',
        ]]);

        $this->assertSame('text', $s['bausteine'][0]['typ']);
        $this->assertSame([['typ' => 'text', 'text' => 'Hallo']], $s['bausteine'][0]['inhalt']);
        $this->assertNotSame($s['bausteine'][0]['id'], $s['bausteine'][1]['id']);
        $this->assertSame('text', $s['bausteine'][2]['typ']);
        $this->assertSame(['seiten' => 1, 'bausteine' => []], AbStruktur::normalisieren('kein json'));
    }

    public function test_grenzen(): void
    {
        $viele = array_fill(0, 400, ['typ' => 'text', 'text' => str_repeat('a', 5000)]);
        $s = AbStruktur::normalisieren(['bausteine' => $viele]);

        $this->assertCount(AbStruktur::MAX_BAUSTEINE, $s['bausteine']);
        $this->assertSame(AbStruktur::MAX_TEXT, mb_strlen($s['bausteine'][0]['inhalt'][0]['text']));
        $this->assertNotEmpty(AbStruktur::pruefen(['bausteine' => $viele]));
        $this->assertNotEmpty(AbStruktur::pruefen(['bausteine' => [['typ' => 'video']]]));
        $this->assertSame([], AbStruktur::pruefen(['bausteine' => [['typ' => 'text']]]));
    }
}
