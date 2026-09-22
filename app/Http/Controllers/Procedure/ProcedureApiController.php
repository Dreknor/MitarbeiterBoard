<?php

namespace App\Http\Controllers\Procedure;

use App\Http\Controllers\Controller;
use App\Models\Positions;
use App\Models\Procedure;
use App\Models\Procedure_Category;
use App\Models\Procedure_Step;
use App\Models\ProcedureTemplate;
use App\Models\RecurringProcedure;
use App\Services\Procedure\RecurringProcedureRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON-API für das neue Tailwind/Alpine-Frontend (§4.1 B-01, B-10, B-21, B-25, B-28).
 *
 * Liefert nur Lesezugriffe; Mutationen laufen über dedizierte Controller mit
 * FormRequests und Policy-Checks (Phase 2 / §4.1).
 */
class ProcedureApiController extends Controller
{
    public function __construct()
    {
        $this->middleware(function (Request $request, \Closure $next) {
            $user = $request->user();
            if (!$user || (!$user->can('manage procedures') && !$user->can('view assigned procedures'))) {
                abort(403);
            }
            return $next($request);
        });
    }

    /** B-01: Vorlagen (Liste, neue Tabelle) */
    public function templates(): JsonResponse
    {
        $templates = ProcedureTemplate::with('category', 'author')
            ->withCount('steps')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $templates->map(fn ($t) => [
                'id'           => $t->id,
                'name'         => $t->name,
                'description'  => $t->description,
                'color'        => $t->color,
                'category'     => $t->category ? ['id' => $t->category->id, 'name' => $t->category->name, 'color' => $t->category->color] : null,
                'author'       => $t->author ? ['id' => $t->author->id, 'name' => $t->author->name] : null,
                'steps_count'  => $t->steps_count,
                'updated_at'   => $t->updated_at?->toAtomString(),
                'legacy_id'    => $t->legacy_procedure_id,
            ]),
        ]);
    }

    /** B-10: Aktive Prozesse, gefiltert (kategorie, status, suche, verantwortlich). */
    public function active(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = Procedure::query()
            ->with('category', 'steps.users', 'steps.position')
            ->whereNotNull('started_at')
            ->whereNull('ended_at');

        if ($cat = $request->integer('category_id')) {
            $q->where('category_id', $cat);
        }

        if ($search = trim((string) $request->input('search'))) {
            $q->where(fn ($qq) => $qq->where('name', 'like', "%{$search}%")
                ->orWhereHas('steps', fn ($s) => $s->where('name', 'like', "%{$search}%")));
        }

        // Sichtbarkeit für Nicht-Admins
        if (!$user->can('manage procedures')) {
            $userId     = $user->id;
            $positionId = $user->position_id ?? null;
            $q->where(function ($qq) use ($userId, $positionId) {
                $qq->whereHas('steps.users', fn ($s) => $s->where('users.id', $userId));
                if ($positionId) {
                    $qq->orWhereHas('steps', fn ($s) => $s->where('position_id', $positionId));
                }
            });
        }

        $procedures = $q->orderByDesc('started_at')->limit(200)->get();

        $status = $request->input('status'); // open|due|overdue|done

        $data = $procedures->map(function (Procedure $p) {
            $totalSteps     = $p->steps->count();
            $doneSteps      = $p->steps->where('done', true)->count();
            $overdueSteps   = $p->steps->filter(fn ($s) => !$s->done && $s->endDate && $s->endDate->isPast())->count();
            $dueSoonSteps   = $p->steps->filter(fn ($s) => !$s->done && $s->endDate && $s->endDate->isFuture() && $s->endDate->diffInDays(now()) <= 3)->count();

            return [
                'id'             => $p->id,
                'name'           => $p->name,
                'description'    => $p->description,
                'started_at'     => $p->started_at?->toDateString(),
                'category'       => $p->category ? ['id' => $p->category->id, 'name' => $p->category->name, 'color' => $p->category->color] : null,
                'steps_total'    => $totalSteps,
                'steps_done'     => $doneSteps,
                'steps_overdue'  => $overdueSteps,
                'steps_due_soon' => $dueSoonSteps,
                'progress'       => $totalSteps ? round($doneSteps / $totalSteps * 100) : 0,
            ];
        });

        if ($status) {
            $data = $data->filter(fn ($p) => match ($status) {
                'overdue' => $p['steps_overdue'] > 0,
                'due'     => $p['steps_due_soon'] > 0,
                'open'    => $p['steps_done'] < $p['steps_total'],
                default   => true,
            })->values();
        }

        return response()->json(['data' => $data]);
    }

    /** B-21: Kategorien mit Vorlage-Counter. */
    public function categories(): JsonResponse
    {
        $cats = Procedure_Category::withCount('procedures')->orderBy('name')->get();
        return response()->json([
            'data' => $cats->map(fn ($c) => [
                'id'                => $c->id,
                'name'              => $c->name,
                'color'             => $c->color,
                'procedures_count'  => $c->procedures_count,
            ]),
        ]);
    }

    /** B-25: Positionen + Mitgliederzahl. */
    public function positions(): JsonResponse
    {
        $positions = Positions::with('users')->orderBy('name')->get();
        return response()->json([
            'data' => $positions->map(fn ($p) => [
                'id'            => $p->id,
                'name'          => $p->name,
                'members_count' => $p->users->count(),
                'members'       => $p->users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]),
            ]),
        ]);
    }

    /** B-28: Wiederkehrende Prozesse inkl. berechnetem `next_trigger_at`. */
    public function recurring(RecurringProcedureRunner $runner): JsonResponse
    {
        $rps = RecurringProcedure::with('procedure:id,name,category_id')->get();

        return response()->json([
            'data' => $rps->map(function (RecurringProcedure $rp) use ($runner) {
                $next = $runner->calculateNextTrigger($rp);
                return [
                    'id'                => $rp->id,
                    'name'              => $rp->name,
                    'active'            => $rp->active ?? true,
                    'faelligkeit_typ'   => $rp->faelligkeit_typ,
                    'month'             => $rp->month,
                    'wochen'            => $rp->wochen,
                    'ferien'            => $rp->ferien,
                    'weekday'           => $rp->weekday,
                    'weekday_interval'  => $rp->weekday_interval,
                    'schuljahres_tag'   => $rp->schuljahres_tag,
                    'schuljahres_monat' => $rp->schuljahres_monat,
                    'last_triggered_at' => optional($rp->last_triggered_at)->toAtomString(),
                    'next_trigger_at'   => optional($next)->toAtomString(),
                    'template'          => $rp->procedure ? ['id' => $rp->procedure->id, 'name' => $rp->procedure->name] : null,
                ];
            }),
        ]);
    }

    /** B-20: Schritt-Verlauf (Audit) – Phase 1 minimal: completed_at/by + Kommentare. */
    public function stepHistory(Procedure_Step $step): JsonResponse
    {
        $step->load(['comments.user', 'completedBy']);

        $items = [];

        if ($step->completed_at) {
            $items[] = [
                'type' => 'completed',
                'at'   => $step->completed_at->toAtomString(),
                'by'   => $step->completedBy ? ['id' => $step->completedBy->id, 'name' => $step->completedBy->name] : null,
            ];
        }
        foreach ($step->comments as $c) {
            $items[] = [
                'type' => 'comment',
                'at'   => $c->created_at->toAtomString(),
                'by'   => $c->user ? ['id' => $c->user->id, 'name' => $c->user->name] : null,
                'body' => $c->body,
            ];
        }

        usort($items, fn ($a, $b) => strcmp($b['at'] ?? '', $a['at'] ?? ''));

        return response()->json(['data' => $items]);
    }
}

