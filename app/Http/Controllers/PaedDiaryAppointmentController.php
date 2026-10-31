<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\PaedDiaryHelperTrait;
use App\Models\PaedDiaryAppointment;
use App\Models\PaedDiaryAppointmentException;
use App\Models\PaedDiaryClassGroup;
use App\Services\PaedDiaryAppointmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        // Logik in PaedDiaryCalendarService (gemeinsam genutzt mit API v1)
        $out = app(\App\Services\PaedDiaryCalendarService::class)->appointments(
            array_values($classIds),
            $groupId,
            Carbon::parse($data['start_date']),
            Carbon::parse($data['end_date'])
        );
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
            'pause_entries'        => ['nullable', 'boolean'],
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
        if (!$this->appointments()->hasAssignments($data)) {
            return response()->json(['message' => 'Bitte mindestens eine Klasse, Kopplung oder einen Schüler auswählen.'], 422);
        }
        // $request->boolean() wandelt '0'→false, '1'→true korrekt um
        // (PHP-Cast (bool)'0' wäre true, da nicht-leerer String!)
        // Logik in PaedDiaryAppointmentService (gemeinsam genutzt mit API v1)
        $appointment = $this->appointments()->create($data, $request->boolean('pause_entries'), $user);

        return response()->json(['success' => true, 'appointment_id' => $appointment->id]);
    }

    public function update(PaedDiaryAppointment $appointment, Request $request)
    {
        $user = Auth::user();
        $hasAccess = $this->appointments()->canAccess($appointment, $user);
        abort_unless($hasAccess, 403);
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
            'pause_entries'      => ['nullable', 'boolean'],
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
        if (!$this->appointments()->hasAssignments($data)) {
            return response()->json(['message' => 'Bitte mindestens eine Klasse, Kopplung oder einen Schüler auswählen.'], 422);
        }
        $this->appointments()->update($appointment, $data, $request->boolean('pause_entries'), $user);

        return response()->json(['success' => true]);
    }

    public function togglePause(PaedDiaryAppointment $appointment)
    {
        $user = Auth::user();
        $hasAccess = $this->appointments()->canAccess($appointment, $user);
        abort_unless($hasAccess, 403);
        if (!$appointment->is_recurring) return response()->json(['message' => 'Nur für wiederkehrende Termine'], 422);
        $appointment->is_paused = !$appointment->is_paused;
        $appointment->save();
        return response()->json(['success' => true, 'is_paused' => $appointment->is_paused]);
    }

    public function destroy(PaedDiaryAppointment $appointment, Request $request)
    {
        $user = Auth::user();

        // Zugriff: Ersteller ODER Nutzer mit Zugang zu mind. einer zugeordneten Klasse
        $hasAccess = $this->appointments()->canAccess($appointment, $user);

        abort_unless($hasAccess, 403);

        $data = $request->validate([
            'delete_mode'     => ['nullable', 'in:all,only_this,this_and_future'],
            'occurrence_date' => ['nullable', 'date', 'required_if:delete_mode,only_this,this_and_future'],
            'schueler_ids'    => ['nullable', 'array'],
            'schueler_ids.*'  => ['integer'],
        ]);

        // Logik in PaedDiaryAppointmentService (gemeinsam genutzt mit API v1)
        $ok = DB::transaction(fn () => $this->appointments()->deleteFor(
            $appointment,
            $user,
            $data['delete_mode'] ?? 'all',
            $data['occurrence_date'] ?? null,
            $data['schueler_ids'] ?? []
        ));

        if (!$ok) {
            return response()->json(['message' => 'Der Termin gehört auch zu anderen Klassen. Einzelne Vorkommen kann nur ändern, wer alle Zuordnungen verwaltet.'], 403);
        }

        return response()->json(['success' => true]);
    }

    private function appointments(): PaedDiaryAppointmentService
    {
        return app(PaedDiaryAppointmentService::class);
    }
}
