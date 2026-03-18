<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\PaedDiaryHelperTrait;
use App\Models\PaedDiaryAppointment;
use App\Models\PaedDiaryClassGroup;
use App\Models\Schueler;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaedDiaryAppointmentController extends Controller
{
    use PaedDiaryHelperTrait;

    public function index(Request $request)
    {
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
            'klasse_id'  => ['nullable', 'integer', 'exists:klassen,id'],
            'group_id'   => ['nullable', 'integer', 'exists:paed_diary_class_groups,id'],
        ]);
        if (!$request->filled('klasse_id') && !$request->filled('group_id')) {
            return response()->json(['appointments' => []]);
        }
        $user     = Auth::user();
        $classIds = [];
        $groupId  = null;
        if ($request->filled('klasse_id')) {
            $klasse   = $user->paed_klassen()->where('klassen.id', $data['klasse_id'])->firstOrFail();
            $classIds = [$klasse->id];
        }
        if ($request->filled('group_id')) {
            $group    = PaedDiaryClassGroup::where('id', $data['group_id'])->where('user_id', $user->id)->firstOrFail();
            $groupId  = $group->id;
            $classIds = array_unique(array_merge($classIds, $group->klassen()->pluck('klassen.id')->toArray()));
        }
        if (empty($classIds)) return response()->json(['appointments' => []]);

        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end   = Carbon::parse($data['end_date'])->endOfDay();

        $appointments = PaedDiaryAppointment::with(['klassen:id,name', 'groups:id,name', 'schueler:id,vorname,nachname,klasse_id'])
            ->where(function ($q) use ($classIds, $groupId) {
                $q->whereHas('klassen', fn ($qq) => $qq->whereIn('klassen.id', $classIds))
                  ->orWhereHas('schueler', fn ($qq) => $qq->whereIn('schueler.klasse_id', $classIds));
                if ($groupId) {
                    $q->orWhereHas('groups', fn ($qq) => $qq->where('paed_diary_class_group_id', $groupId));
                }
            })
            ->whereDate('start_date', '<=', $end->toDateString())
            ->get();

        $out = [];
        foreach ($appointments as $app) {
            $occ = $app->getOccurrencesInRange($start->copy(), $end->copy());
            if (empty($occ)) continue;
            $k = $app->klassen->map(fn ($k) => ['id' => $k->id, 'name' => $k->name]);
            $g = $app->groups->map(fn ($gr) => ['id' => $gr->id, 'name' => $gr->name]);
            $s = $app->schueler->map(fn ($st) => ['id' => $st->id, 'name' => $st->vorname . ' ' . $st->nachname, 'klasse_id' => $st->klasse_id]);
            foreach ($occ as $o) {
                $out[] = array_merge($o, ['klassen' => $k, 'groups' => $g, 'schueler' => $s]);
            }
        }
        usort($out, fn ($a, $b) => $a['date'] === $b['date'] ? strcmp($a['start_time'] ?? '', $b['start_time'] ?? '') : strcmp($a['date'], $b['date']));
        return response()->json(['appointments' => $out]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'                => ['required', 'string', 'max:150'],
            'description'          => ['nullable', 'string'],
            'start_date'           => ['required', 'date'],
            'start_time'           => ['nullable', 'date_format:H:i'],
            'end_time'             => ['nullable', 'date_format:H:i', 'after_or_equal:start_time'],
            'is_recurring'         => ['nullable', 'boolean'],
            'recurring_type'       => ['nullable', 'in:daily,weekly,monthly'],
            'recurring_interval'   => ['nullable', 'integer', 'min:1', 'max:365'],
            'recurring_end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
            'klasse_ids'           => ['array'],
            'klasse_ids.*'         => ['integer', 'exists:klassen,id'],
            'group_ids'            => ['array'],
            'group_ids.*'          => ['integer', 'exists:paed_diary_class_groups,id'],
            'schueler_ids'         => ['array'],
            'schueler_ids.*'       => ['integer', 'exists:schueler,id'],
        ]);
        $user        = Auth::user();
        $isRecurring = (bool) ($data['is_recurring'] ?? false);
        if (!$isRecurring) { $data['recurring_type'] = null; $data['recurring_interval'] = 1; $data['recurring_end_date'] = null; } elseif (empty($data['recurring_type'])) {
            return response()->json(['message' => 'recurring_type erforderlich'], 422);
        }
        $appointment = PaedDiaryAppointment::create([
            'user_id'              => $user->id,
            'title'                => trim($data['title']),
            'description'          => $data['description'] ?? null,
            'start_date'           => Carbon::parse($data['start_date'])->toDateString(),
            'start_time'           => !empty($data['start_time']) ? Carbon::parse($data['start_date'] . ' ' . $data['start_time']) : null,
            'end_time'             => !empty($data['end_time'])   ? Carbon::parse($data['start_date'] . ' ' . $data['end_time'])   : null,
            'is_recurring'         => $isRecurring,
            'recurring_type'       => $data['recurring_type'] ?? null,
            'recurring_interval'   => $isRecurring ? ($data['recurring_interval'] ?? 1) : 1,
            'recurring_end_date'   => !empty($data['recurring_end_date']) ? Carbon::parse($data['recurring_end_date'])->toDateString() : null,
            'is_paused'            => false,
        ]);
        $this->syncRelations($appointment, $data, $user);
        return response()->json(['success' => true, 'appointment_id' => $appointment->id]);
    }

    public function update(PaedDiaryAppointment $appointment, Request $request)
    {
        $user = Auth::user();
        abort_unless($appointment->user_id === $user->id, 403);
        $data = $request->validate([
            'title'              => ['required', 'string', 'max:150'],
            'description'        => ['nullable', 'string'],
            'start_date'         => ['required', 'date'],
            'start_time'         => ['nullable', 'date_format:H:i'],
            'end_time'           => ['nullable', 'date_format:H:i', 'after_or_equal:start_time'],
            'is_recurring'       => ['nullable', 'boolean'],
            'recurring_type'     => ['nullable', 'in:daily,weekly,monthly'],
            'recurring_interval' => ['nullable', 'integer', 'min:1', 'max:365'],
            'recurring_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'klasse_ids'         => ['array'],
            'klasse_ids.*'       => ['integer', 'exists:klassen,id'],
            'group_ids'          => ['array'],
            'group_ids.*'        => ['integer', 'exists:paed_diary_class_groups,id'],
            'schueler_ids'       => ['array'],
            'schueler_ids.*'     => ['integer', 'exists:schueler,id'],
        ]);
        $isRecurring = (bool) ($data['is_recurring'] ?? false);
        if (!$isRecurring) { $data['recurring_type'] = null; $data['recurring_interval'] = 1; $data['recurring_end_date'] = null; $appointment->is_paused = false; } elseif (empty($data['recurring_type'])) {
            return response()->json(['message' => 'recurring_type erforderlich'], 422);
        }
        $appointment->update(['title' => trim($data['title']), 'description' => $data['description'] ?? null, 'start_date' => Carbon::parse($data['start_date'])->toDateString(), 'start_time' => !empty($data['start_time']) ? Carbon::parse($data['start_date'] . ' ' . $data['start_time']) : null, 'end_time' => !empty($data['end_time']) ? Carbon::parse($data['start_date'] . ' ' . $data['end_time']) : null, 'is_recurring' => $isRecurring, 'recurring_type' => $data['recurring_type'] ?? null, 'recurring_interval' => $isRecurring ? ($data['recurring_interval'] ?? 1) : 1, 'recurring_end_date' => !empty($data['recurring_end_date']) ? Carbon::parse($data['recurring_end_date'])->toDateString() : null]);
        $this->syncRelations($appointment, $data, $user);
        return response()->json(['success' => true]);
    }

    public function togglePause(PaedDiaryAppointment $appointment)
    {
        $user = Auth::user();
        abort_unless($appointment->user_id === $user->id, 403);
        if (!$appointment->is_recurring) return response()->json(['message' => 'Nur für wiederkehrende Termine'], 422);
        $appointment->is_paused = !$appointment->is_paused;
        $appointment->save();
        return response()->json(['success' => true, 'is_paused' => $appointment->is_paused]);
    }

    public function destroy(PaedDiaryAppointment $appointment)
    {
        $user = Auth::user();
        abort_unless($appointment->user_id === $user->id, 403);
        $appointment->klassen()->detach();
        $appointment->groups()->detach();
        $appointment->schueler()->detach();
        $appointment->delete();
        return response()->json(['success' => true]);
    }

    private function syncRelations(PaedDiaryAppointment $appointment, array $data, $user): void
    {
        $allowedClassIds = $user->paed_klassen()->pluck('klassen.id')->toArray();
        $klasseIds       = array_filter($data['klasse_ids'] ?? [], fn ($id) => in_array($id, $allowedClassIds));
        $appointment->klassen()->sync($klasseIds);
        $groupIds = array_filter($data['group_ids'] ?? [], fn ($gid) => PaedDiaryClassGroup::where('id', $gid)->where('user_id', $user->id)->exists());
        $appointment->groups()->sync($groupIds);
        $rawStu = $data['schueler_ids'] ?? [];
        $appointment->schueler()->sync($rawStu ? Schueler::whereIn('id', $rawStu)->whereIn('klasse_id', $allowedClassIds)->pluck('id')->toArray() : []);
    }
}

