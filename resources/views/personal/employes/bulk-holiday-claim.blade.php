@extends('layouts.app')

@section('title')
    Urlaubsanspruch - Massenverwaltung
@endsection

@section('site-title')
    Urlaubsanspruch - Massenverwaltung nach Gruppen
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-users"></i> Urlaubsanspruch für Gruppen festlegen
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Hinweis:</strong> Mit dieser Funktion können Sie den Urlaubsanspruch für alle Mitarbeiter einer Gruppe auf einmal festlegen.
                            Der neue Urlaubsanspruch wird nur für Mitarbeiter aktualisiert, bei denen sich der Wert ändert.
                        </div>

                        <form method="POST" action="{{ route('employes.bulk-holiday-claim.update') }}" id="bulkHolidayClaimForm">
                            @csrf

                            <div class="form-group">
                                <label for="group_id" class="font-weight-bold">
                                    <i class="fas fa-users"></i> Gruppe auswählen *
                                </label>
                                <select name="group_id" id="group_id" class="form-control @error('group_id') is-invalid @enderror" required>
                                    <option value="">-- Bitte wählen Sie eine Gruppe --</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group->id }}"
                                                data-member-count="{{ $group->users->count() }}"
                                                {{ old('group_id') == $group->id ? 'selected' : '' }}>
                                            {{ $group->name }} ({{ $group->users->count() }} Mitarbeiter)
                                        </option>
                                    @endforeach
                                </select>
                                @error('group_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">
                                    Wählen Sie die Gruppe aus, für deren Mitarbeiter der Urlaubsanspruch festgelegt werden soll.
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="holiday_claim" class="font-weight-bold">
                                    <i class="fas fa-umbrella-beach"></i> Urlaubsanspruch (Tage) *
                                </label>
                                <input type="number"
                                       name="holiday_claim"
                                       id="holiday_claim"
                                       class="form-control @error('holiday_claim') is-invalid @enderror"
                                       min="1"
                                       value="{{ old('holiday_claim', 30) }}"
                                       required>
                                @error('holiday_claim')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">
                                    Geben Sie die Anzahl der Urlaubstage an, die den Mitarbeitern zustehen.
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="date_start" class="font-weight-bold">
                                    <i class="fas fa-calendar-alt"></i> Gültig ab *
                                </label>
                                <input type="date"
                                       name="date_start"
                                       id="date_start"
                                       class="form-control @error('date_start') is-invalid @enderror"
                                       value="{{ old('date_start', \Carbon\Carbon::now()->format('Y-m-d')) }}"
                                       required>
                                @error('date_start')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">
                                    Ab welchem Datum soll der neue Urlaubsanspruch gelten?
                                </small>
                            </div>

                            <div id="preview-section" class="alert alert-secondary d-none">
                                <h5><i class="fas fa-eye"></i> Vorschau</h5>
                                <p class="mb-1">
                                    <strong>Gruppe:</strong> <span id="preview-group">-</span>
                                </p>
                                <p class="mb-1">
                                    <strong>Betroffene Mitarbeiter:</strong> <span id="preview-count">0</span>
                                </p>
                                <p class="mb-1">
                                    <strong>Neuer Urlaubsanspruch:</strong> <span id="preview-days">0</span> Tage
                                </p>
                                <p class="mb-0">
                                    <strong>Gültig ab:</strong> <span id="preview-date">-</span>
                                </p>
                            </div>

                            <div class="form-group mb-0">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-save"></i> Urlaubsanspruch festlegen
                                </button>
                                <a href="{{ route('employes.index') }}" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-times"></i> Abbrechen
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                @if($groups->isEmpty())
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle"></i>
                        Es sind keine Gruppen vorhanden. Bitte erstellen Sie zuerst eine Gruppe.
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            function updatePreview() {
                const groupSelect = $('#group_id');
                const selectedOption = groupSelect.find('option:selected');
                const holidayClaim = $('#holiday_claim').val();
                const dateStart = $('#date_start').val();

                if (groupSelect.val() && holidayClaim && dateStart) {
                    $('#preview-section').removeClass('d-none');
                    $('#preview-group').text(selectedOption.text());
                    $('#preview-count').text(selectedOption.data('member-count') || 0);
                    $('#preview-days').text(holidayClaim);

                    // Datum formatieren
                    if (dateStart) {
                        const date = new Date(dateStart);
                        const formattedDate = date.toLocaleDateString('de-DE', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric'
                        });
                        $('#preview-date').text(formattedDate);
                    }
                } else {
                    $('#preview-section').addClass('d-none');
                }
            }

            // Update preview on input change
            $('#group_id, #holiday_claim, #date_start').on('change input', updatePreview);

            // Form validation
            $('#bulkHolidayClaimForm').on('submit', function(e) {
                const groupId = $('#group_id').val();
                const holidayClaim = $('#holiday_claim').val();

                if (!groupId) {
                    e.preventDefault();
                    alert('Bitte wählen Sie eine Gruppe aus.');
                    return false;
                }

                if (!holidayClaim || holidayClaim < 1) {
                    e.preventDefault();
                    alert('Bitte geben Sie einen gültigen Urlaubsanspruch ein (mindestens 1 Tag).');
                    return false;
                }

                // Bestätigung vor dem Absenden
                const selectedOption = $('#group_id option:selected');
                const groupName = selectedOption.text();
                const memberCount = selectedOption.data('member-count');

                const confirmMessage = `Möchten Sie wirklich den Urlaubsanspruch für ${memberCount} Mitarbeiter der Gruppe "${groupName}" auf ${holidayClaim} Tage festlegen?`;

                if (!confirm(confirmMessage)) {
                    e.preventDefault();
                    return false;
                }
            });

            // Initial preview update
            updatePreview();
        });
    </script>
@endpush
