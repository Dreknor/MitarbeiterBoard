<?php

namespace App\Http\Controllers\Personal;

use App\Enums\TrainingStatus;
use App\Enums\ParticipantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Personal\StoreTrainingRequest;
use App\Http\Requests\Personal\UpdateTrainingRequest;
use App\Models\personal\Training;
use App\Models\personal\TrainingParticipant;
use App\Models\personal\QualificationType;
use App\Models\User;
use App\Services\Personal\QualificationService;
use Illuminate\Http\Request;

class TrainingController extends Controller
{
    public function index()
    {
        $this->authorize('view trainings');

        $trainings = Training::where('status', '!=', TrainingStatus::Abgesagt->value)
            ->where('end_date', '>=', now())
            ->with([
                'qualificationType',
                'participants' => fn ($q) => $q->where('employe_id', auth()->id()),
            ])
            ->orderBy('start_date')
            ->get();

        return view('personal.trainings.index', compact('trainings'));
    }

    public function show(Training $training)
    {
        $this->authorize('view', $training);

        $training->load(['qualificationType', 'participants.employe', 'createdBy']);
        $myParticipation = $training->participants
            ->firstWhere('employe_id', auth()->id());

        return view('personal.trainings.show', compact('training', 'myParticipation'));
    }

    public function create()
    {
        $this->authorize('create', Training::class);

        $qualificationTypes = QualificationType::where('is_active', true)->orderBy('name')->get();

        return view('personal.trainings.create', compact('qualificationTypes'));
    }

    public function store(StoreTrainingRequest $request)
    {
        $this->authorize('create', Training::class);


        Training::create(array_merge($request->only([
            'title', 'description', 'provider', 'start_date', 'end_date',
            'location', 'cost', 'max_participants', 'qualification_type_id',
        ]), ['created_by' => auth()->id(), 'status' => TrainingStatus::Geplant]));

        return redirect()->route('personal.trainings.index')
            ->with('Meldung', 'Fortbildung wurde angelegt.')
            ->with('type', 'success');
    }

    public function edit(Training $training)
    {
        $this->authorize('update', $training);

        $qualificationTypes = QualificationType::where('is_active', true)->orderBy('name')->get();

        return view('personal.trainings.edit', compact('training', 'qualificationTypes'));
    }

    public function update(UpdateTrainingRequest $request, Training $training)
    {
        $this->authorize('update', $training);


        $training->update($request->only([
            'title', 'description', 'provider', 'start_date', 'end_date',
            'location', 'cost', 'max_participants', 'qualification_type_id', 'status',
        ]));

        return redirectBack()
            ->with('Meldung', 'Fortbildung wurde aktualisiert.')
            ->with('type', 'success');
    }

    public function destroy(Training $training)
    {
        $this->authorize('delete', $training);

        $training->delete();

        return redirect()->route('personal.trainings.index')
            ->with('Meldung', 'Fortbildung wurde gelöscht.')
            ->with('type', 'success');
    }

    public function register(Training $training)
    {
        $this->authorize('register', $training);

        if ($training->isFull()) {
            return redirectBack()
                ->with('Meldung', 'Diese Fortbildung ist bereits ausgebucht.')
                ->with('type', 'danger');
        }

        // Doppel-Anmeldung verhindern
        $exists = TrainingParticipant::where('training_id', $training->id)
            ->where('employe_id', auth()->id())
            ->exists();

        if ($exists) {
            return redirectBack()
                ->with('Meldung', 'Sie sind bereits für diese Fortbildung angemeldet.')
                ->with('type', 'warning');
        }

        TrainingParticipant::create([
            'training_id' => $training->id,
            'employe_id'  => auth()->id(),
            'status'      => ParticipantStatus::Angemeldet,
        ]);

        return redirectBack()
            ->with('Meldung', 'Anmeldung erfolgreich. Bestätigung folgt.')
            ->with('type', 'success');
    }

    public function cancel(Training $training)
    {
        $participant = TrainingParticipant::where('training_id', $training->id)
            ->where('employe_id', auth()->id())
            ->firstOrFail();

        $participant->update(['status' => ParticipantStatus::Abgesagt]);

        return redirectBack()
            ->with('Meldung', 'Anmeldung wurde zurückgezogen.')
            ->with('type', 'success');
    }

    public function approve(Training $training, User $employe)
    {
        $this->authorize('approve trainings');

        $participant = TrainingParticipant::where('training_id', $training->id)
            ->where('employe_id', $employe->id)
            ->firstOrFail();

        $participant->update([
            'status'      => ParticipantStatus::Bestaetigt,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return redirectBack()
            ->with('Meldung', 'Teilnahme wurde bestätigt.')
            ->with('type', 'success');
    }

    public function markCompleted(Training $training, User $employe)
    {
        $this->authorize('manage trainings');

        $participant = TrainingParticipant::where('training_id', $training->id)
            ->where('employe_id', $employe->id)
            ->firstOrFail();

        $participant->update(['status' => ParticipantStatus::Teilgenommen]);

        // Qualifikation automatisch erneuern
        app(QualificationService::class)->renewFromTraining($participant);

        return redirectBack()
            ->with('Meldung', 'Teilnahme vermerkt. Qualifikation wurde erneuert.')
            ->with('type', 'success');
    }
}

