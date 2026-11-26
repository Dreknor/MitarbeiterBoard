<?php

namespace App\Services\Wiki;

use App\Models\Group;
use App\Models\User;
use App\Models\WikiEinschraenkung;
use App\Models\WikiSeite;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Sichtbarkeit eingeschränkter Wiki-Seiten.
 *
 * Eine Seite ist sichtbar, wenn sie selbst und jede übergeordnete Seite entweder keine Einschränkung hat oder für
 * eine Rolle/Gruppe der Person freigegeben ist (Unterseiten können weiter eingeschränkt, aber nicht wieder geöffnet
 * werden). `manage wiki` sieht alles. Ergebnis wird je Person und Datenstand im Prozess gemerkt; jede Änderung an
 * Seiten/Einschränkungen ruft zuruecksetzen() auf.
 */
class WikiZugriff
{
    /** @var array<int, array<int, true>> */
    private static array $merker = [];

    public static function zuruecksetzen(): void
    {
        self::$merker = [];
    }

    public static function darfSehen(User $user, WikiSeite $seite): bool
    {
        if ($seite->trashed()) {
            return $user->can('manage wiki');
        }

        return isset(self::sichtbareIds($user)[$seite->id]);
    }

    /**
     * IDs aller (nicht gelöschten) Seiten, die die Person sehen darf.
     *
     * @return array<int, true>
     */
    public static function sichtbareIds(User $user): array
    {
        if (isset(self::$merker[$user->id])) {
            return self::$merker[$user->id];
        }

        $eltern = DB::table('wiki_seiten')->whereNull('deleted_at')->pluck('parent_id', 'id')->all();

        if ($user->can('manage wiki')) {
            return self::$merker[$user->id] = array_fill_keys(array_keys($eltern), true);
        }

        $einschraenkungen = self::einschraenkungenJeSeite();
        $rollen = $user->roles()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $gruppen = $user->groups_rel()->pluck('groups.id')->map(fn ($id) => (int) $id)->all();

        $erlaubt = function (int $id) use ($einschraenkungen, $rollen, $gruppen): bool {
            if (! isset($einschraenkungen[$id])) {
                return true;
            }
            foreach ($einschraenkungen[$id] as [$typ, $bid]) {
                if (($typ === Role::class && in_array($bid, $rollen, true)) || ($typ === Group::class && in_array($bid, $gruppen, true))) {
                    return true;
                }
            }

            return false;
        };

        $ergebnis = [];
        $status = [];
        $pruefe = function (int $id, int $tiefe = 0) use (&$pruefe, &$status, $eltern, $erlaubt): bool {
            if (isset($status[$id])) {
                return $status[$id];
            }
            if ($tiefe > 50 || ! array_key_exists($id, $eltern)) {
                return $status[$id] = false;
            }
            $parent = $eltern[$id];
            $ok = $erlaubt($id) && ($parent === null || $pruefe((int) $parent, $tiefe + 1));

            return $status[$id] = $ok;
        };

        foreach (array_keys($eltern) as $id) {
            if ($pruefe((int) $id)) {
                $ergebnis[(int) $id] = true;
            }
        }

        return self::$merker[$user->id] = $ergebnis;
    }

    /**
     * Nächste Seite (sie selbst oder übergeordnet), deren Einschränkung gilt – für die Anzeige „eingeschränkt durch …“.
     */
    public static function einschraenkendeSeite(WikiSeite $seite): ?WikiSeite
    {
        $einschraenkungen = self::einschraenkungenJeSeite();
        $aktuell = $seite;
        $tiefe = 0;
        while ($aktuell && $tiefe++ < 50) {
            if (isset($einschraenkungen[$aktuell->id])) {
                return $aktuell;
            }
            $aktuell = $aktuell->parent_id ? WikiSeite::find($aktuell->parent_id) : null;
        }

        return null;
    }

    /** Freigegebene Rollen/Gruppen einer Seite als lesbare Namen. */
    public static function freigabenText(WikiSeite $seite): Collection
    {
        return $seite->einschraenkungen()->with('berechtigter')->get()
            ->map(fn (WikiEinschraenkung $e) => $e->berechtigter?->name)
            ->filter()
            ->sort()
            ->values();
    }

    /**
     * @return array<int, array<int, array{0: string, 1: int}>>
     */
    private static function einschraenkungenJeSeite(): array
    {
        $liste = [];
        foreach (DB::table('wiki_einschraenkungen')->get(['seite_id', 'berechtigter_type', 'berechtigter_id']) as $e) {
            $liste[(int) $e->seite_id][] = [$e->berechtigter_type, (int) $e->berechtigter_id];
        }

        return $liste;
    }
}
