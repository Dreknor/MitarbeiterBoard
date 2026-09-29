<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use App\Services\Personal\PersonalScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Vorgesetzte und Stellvertretungen pflegen – auch für viele Personen auf einmal.
 * Grundlage für Urlaubsgenehmigung und Prüfung der Arbeitszeitnachweise (ZeitZugriff).
 */
class VorgesetzteController extends Controller
{
    public function __construct(private readonly PersonalScopeService $scope)
    {
    }

    public function index(Request $request)
    {
        $filter = [
            'group_id' => $request->input('group_id', ''),
            'suche' => trim((string) $request->input('suche', '')),
            'ohne' => $request->boolean('ohne'),
        ];

        $personen = $this->relevantePersonen()
            ->with(['superior', 'groups_rel:groups.id,groups.name'])
            ->when($filter['group_id'] !== '', fn (Builder $q) => $q->whereHas('groups_rel', fn ($g) => $g->where('groups.id', (int) $filter['group_id'])))
            ->when($filter['suche'] !== '', fn (Builder $q) => $q->where('name', 'like', '%'.$filter['suche'].'%'))
            ->when($filter['ohne'], fn (Builder $q) => $q->whereNull('superior_id'))
            ->orderBy('name')
            ->get();

        $leitungIds = User::whereNotNull('superior_id')->distinct()->pluck('superior_id');
        $leitungen = User::query()
            ->where(fn (Builder $q) => $q->whereIn('id', $leitungIds)
                ->orWhereHas('permissions', fn ($p) => $p->whereIn('name', ['approve holidays', 'lock timesheets']))
                ->orWhereHas('roles.permissions', fn ($p) => $p->whereIn('name', ['approve holidays', 'lock timesheets'])))
            ->with('deputies:id,name')
            ->withCount('subordinates')
            ->orderBy('name')
            ->get();

        return view('personal.zeit.vorgesetzte', [
            'personen' => $personen,
            'leitungen' => $leitungen,
            'alle' => User::orderBy('name')->get(['id', 'name']),
            'gruppen' => Group::orderBy('name')->get(['id', 'name']),
            'filter' => $filter,
            'ohneVorgesetzte' => $this->relevantePersonen()->whereNull('superior_id')->count(),
        ]);
    }

    /**
     * Vorgesetzte für mehrere Personen setzen oder entfernen.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'superior_id' => ['required_unless:entfernen,1', 'nullable', 'integer', 'exists:users,id'],
            'entfernen' => ['nullable', 'boolean'],
        ], [
            'user_ids.required' => 'Bitte mindestens eine Person auswählen.',
            'superior_id.required_unless' => 'Bitte eine vorgesetzte Person auswählen.',
        ]);

        $vorgesetzt = !$request->boolean('entfernen') && isset($data['superior_id']) ? User::find($data['superior_id']) : null;
        $kette = $vorgesetzt ? $this->kette($vorgesetzt) : [];
        $gesetzt = 0;
        $uebersprungen = [];

        foreach (User::whereIn('id', $data['user_ids'])->get() as $person) {
            // Niemand kann sich selbst oder einer eigenen (indirekten) Unterstellten unterstellt werden
            if ($vorgesetzt && ($person->id === $vorgesetzt->id || in_array($person->id, $kette, true))) {
                $uebersprungen[] = $person->name;
                continue;
            }
            if ((int) $person->superior_id !== (int) $vorgesetzt?->id) {
                $person->forceFill(['superior_id' => $vorgesetzt?->id])->save();
                $this->scope->invalidateCache($person);
                $gesetzt++;
            }
        }

        if ($vorgesetzt) {
            $this->scope->invalidateCache($vorgesetzt);
        }

        $meldung = $vorgesetzt
            ? $gesetzt.' Person(en) '.$vorgesetzt->name.' zugeordnet.'
            : 'Bei '.$gesetzt.' Person(en) die Zuordnung entfernt.';
        if ($uebersprungen !== []) {
            $meldung .= ' Übersprungen (würde einen Kreis bilden): '.implode(', ', $uebersprungen).'.';
        }

        return redirectBack($uebersprungen === [] ? 'success' : 'warning', $meldung);
    }

    public function addDeputy(Request $request, User $leitung)
    {
        $data = $request->validate(['deputy_id' => ['required', 'integer', 'exists:users,id']]);

        if ((int) $data['deputy_id'] === $leitung->id) {
            return redirectBack('warning', 'Eine Leitung kann nicht ihre eigene Stellvertretung sein.');
        }

        $leitung->deputies()->syncWithoutDetaching([$data['deputy_id']]);
        $vertretung = User::find($data['deputy_id']);

        $hinweis = $vertretung->can('approve holidays') ? '' : ' Hinweis: '.$vertretung->name.' hat noch nicht das Recht „approve holidays“ und kann daher erst nach Rechtevergabe genehmigen.';

        return redirectBack('success', $vertretung->name.' vertritt jetzt '.$leitung->name.'.'.$hinweis);
    }

    public function removeDeputy(User $leitung, User $deputy)
    {
        $leitung->deputies()->detach($deputy->id);

        return redirectBack('success', $deputy->name.' vertritt '.$leitung->name.' nicht mehr.');
    }

    // =========================================================================

    /**
     * Personen mit Vertrag, Urlaubsanspruch oder Arbeitszeitnachweis.
     */
    private function relevantePersonen(): Builder
    {
        return User::query()->where(fn (Builder $q) => $q->whereHas('employments')
            ->orWhereHas('permissions', fn ($p) => $p->whereIn('name', ['has holidays', 'has timesheet']))
            ->orWhereHas('roles.permissions', fn ($p) => $p->whereIn('name', ['has holidays', 'has timesheet'])));
    }

    /**
     * IDs der Vorgesetztenkette oberhalb von $user (inkl. $user) – zur Kreis-Erkennung.
     *
     * @return int[]
     */
    private function kette(User $user): array
    {
        $ids = [$user->id];
        $aktuell = $user->superior;
        while ($aktuell !== null && !in_array($aktuell->id, $ids, true) && count($ids) < 20) {
            $ids[] = $aktuell->id;
            $aktuell = $aktuell->superior;
        }

        return $ids;
    }
}
