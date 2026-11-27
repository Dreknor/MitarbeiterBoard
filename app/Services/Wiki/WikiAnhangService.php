<?php

namespace App\Services\Wiki;

use App\Models\User;
use App\Models\WikiSeite;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Bilder und Dateien einer Wiki-Seite (Media Library, private Disk `wiki`).
 *
 * - Anhänge gehören zur Seite, nicht zur Revision – im Text steht nur der relative Link `/wiki/anhang/{id}`.
 * - Bilder werden auf höchstens MAX_KANTE px verkleinert; base64-Bilder im HTML werden beim Speichern zu Anhängen.
 * - Entfernen markiert nur (custom property `entfernt_at`); endgültig gelöscht wird nach AUFBEWAHRUNG_TAGE Tagen
 *   und nur, wenn keine Revision mehr darauf verweist (bereinigen(), täglich).
 */
class WikiAnhangService
{
    public const MAX_KANTE = 1600;
    public const MAX_DATEI_KB = 20480;
    public const AUFBEWAHRUNG_TAGE = 90;
    public const BILD_MIMES = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    public const DATEI_MIMES = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'zip', 'mp3', 'mp4'];

    /**
     * @param  bool  $alsDatei  true = immer in die Download-Liste (Dateien), auch bei Bildern
     */
    public function hochladen(WikiSeite $seite, UploadedFile $datei, ?User $user = null, bool $alsDatei = false): Media
    {
        $istBild = str_starts_with((string) $datei->getMimeType(), 'image/')
            && in_array(strtolower($datei->getClientOriginalExtension()), self::BILD_MIMES, true);

        if ($istBild) {
            $this->verkleinern($datei->getRealPath(), (string) $datei->getMimeType());
        }

        $media = $seite->addMedia($datei)
            ->usingFileName($this->dateiname($datei->getClientOriginalName()))
            ->withCustomProperties(array_filter(['hochgeladen_von' => $user?->id, 'hash' => md5_file($datei->getRealPath())]))
            ->toMediaCollection($istBild && ! $alsDatei ? WikiSeite::BILDER : WikiSeite::DATEIEN);

        return $media;
    }

    /**
     * Ersetzt eingebettete base64-Bilder durch Anhänge der Seite (gleiche Bilder einer Seite nur einmal).
     */
    public function base64Umwandeln(WikiSeite $seite, string $html, ?User $user = null): string
    {
        if (! str_contains($html, 'data:image/')) {
            return $html;
        }

        return preg_replace_callback(
            '#(src\s*=\s*["\'])data:image/(png|jpe?g|gif|webp);base64,([A-Za-z0-9+/=\s]+)(["\'])#i',
            function (array $m) use ($seite, $user) {
                $daten = base64_decode(preg_replace('/\s+/', '', $m[3]), true);
                if ($daten === false || $daten === '') {
                    return $m[1].$m[4];
                }
                $media = $this->bildAusDaten($seite, $daten, strtolower($m[2]) === 'jpeg' ? 'jpg' : strtolower($m[2]), $user);

                return $media ? $m[1].$this->url($media).$m[4] : $m[1].$m[4];
            },
            $html
        ) ?? $html;
    }

    public function bildAusDaten(WikiSeite $seite, string $daten, string $endung, ?User $user = null): ?Media
    {
        $hash = md5($daten);
        $vorhanden = $seite->media()->where('collection_name', WikiSeite::BILDER)->get()
            ->first(fn (Media $m) => $m->getCustomProperty('hash') === $hash);
        if ($vorhanden) {
            return $vorhanden;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'wiki');
        try {
            file_put_contents($tmp, $daten);
            $mime = mime_content_type($tmp) ?: 'image/'.$endung;
            if (! str_starts_with($mime, 'image/')) {
                return null;
            }
            $this->verkleinern($tmp, $mime);

            return $seite->addMedia($tmp)
                ->usingFileName('bild-'.substr($hash, 0, 10).'.'.$endung)
                ->usingName('Bild')
                ->withCustomProperties(array_filter(['hochgeladen_von' => $user?->id, 'hash' => $hash]))
                ->toMediaCollection(WikiSeite::BILDER);
        } catch (Throwable $e) {
            Log::warning('Wiki: base64-Bild konnte nicht übernommen werden', ['seite' => $seite->id, 'fehler' => $e->getMessage()]);

            return null;
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }

    public function url(Media $media): string
    {
        return route('wiki.anhang', $media, false);
    }

    /** Markiert einen Anhang als entfernt (verschwindet aus der Liste, alte Fassungen zeigen ihn weiter). */
    public function entfernen(WikiSeite $seite, Media $media, User $user): void
    {
        abort_unless($media->model_type === WikiSeite::class && (int) $media->model_id === $seite->id, 404);

        $media->setCustomProperty('entfernt_at', now()->toIso8601String());
        $media->setCustomProperty('entfernt_von', $user->id);
        $media->save();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Media>
     */
    public function dateien(WikiSeite $seite)
    {
        return $seite->getMedia(WikiSeite::DATEIEN)
            ->filter(fn (Media $m) => ! $m->getCustomProperty('entfernt_at'))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Täglich: entfernte oder nie verwendete Anhänge löschen, wenn sie älter als AUFBEWAHRUNG_TAGE sind und
     * keine Revision (auch nicht der aktuelle Inhalt) sie mehr verlinkt.
     */
    public function bereinigen(): int
    {
        $grenze = now()->subDays(self::AUFBEWAHRUNG_TAGE);
        $geloescht = 0;

        Media::query()
            ->where('model_type', WikiSeite::class)
            ->where('created_at', '<', $grenze)
            ->chunkById(200, function ($medien) use ($grenze, &$geloescht) {
                foreach ($medien as $media) {
                    /** @var Media $media */
                    $entfernt = $media->getCustomProperty('entfernt_at');
                    $istDatei = $media->collection_name === WikiSeite::DATEIEN;
                    if ($istDatei && (! $entfernt || now()->parse($entfernt)->gt($grenze))) {
                        continue;
                    }
                    if ($entfernt && now()->parse($entfernt)->gt($grenze)) {
                        continue;
                    }
                    if ($this->wirdVerwendet($media)) {
                        continue;
                    }
                    $media->delete();
                    $geloescht++;
                }
            });

        return $geloescht;
    }

    public function wirdVerwendet(Media $media): bool
    {
        $muster = '%/wiki/anhang/'.$media->id.'"%';
        $muster2 = '%/wiki/anhang/'.$media->id.'/%';

        return DB::table('wiki_revisionen')->where('seite_id', $media->model_id)
                ->where(fn ($q) => $q->where('inhalt', 'like', $muster)->orWhere('inhalt', 'like', $muster2))->exists()
            || DB::table('wiki_seiten')->where('id', $media->model_id)
                ->where(fn ($q) => $q->where('inhalt', 'like', $muster)->orWhere('inhalt', 'like', $muster2))->exists();
    }

    private function verkleinern(string $pfad, string $mime): void
    {
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || ! extension_loaded('gd')) {
            return;
        }
        try {
            $groesse = @getimagesize($pfad);
            if (! $groesse || max($groesse[0], $groesse[1]) <= self::MAX_KANTE) {
                return;
            }
            $bild = (new ImageManager(['driver' => 'gd']))->make($pfad);
            if (function_exists('exif_read_data') && $mime === 'image/jpeg') {
                $bild->orientate();
            }
            $bild->resize(self::MAX_KANTE, self::MAX_KANTE, function ($c) {
                $c->aspectRatio();
                $c->upsize();
            });
            $bild->save($pfad, 85, match ($mime) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            });
        } catch (Throwable $e) {
            Log::info('Wiki: Bild konnte nicht verkleinert werden', ['fehler' => $e->getMessage()]);
        }
    }

    private function dateiname(string $original): string
    {
        $endung = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $name = Str::slug(pathinfo($original, PATHINFO_FILENAME), '-', 'de') ?: 'datei';

        return Str::limit($name, 80, '').($endung ? '.'.$endung : '');
    }
}
