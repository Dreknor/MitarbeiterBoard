<div class="personal-card mb-4 {{ isset($readonly) && $readonly ? 'opacity-75' : '' }}">
    <div class="flex items-start justify-between">
        <div class="flex-1">
            <div class="flex items-center gap-3 mb-2">
                <span class="font-semibold text-gray-900">
                    {{ $employment->employment_type?->label() ?? 'Anstellung' }}
                </span>
                <span class="badge-{{ $employment->status?->value === 'aktiv' ? 'green' : ($employment->status?->value === 'ruhend' ? 'yellow' : 'red') }}">
                    {{ $employment->status?->label() ?? '—' }}
                </span>
                @if($employment->contract_type)
                <span class="badge-gray">{{ $employment->contract_type->label() }}</span>
                @endif
            </div>

            <dl class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                <div>
                    <dt class="text-gray-500">Abteilung</dt>
                    <dd class="font-medium">{{ $employment->department?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Von</dt>
                    <dd class="font-medium">{{ $employment->start?->format('d.m.Y') }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Bis</dt>
                    <dd class="font-medium {{ $employment->end && $employment->end->isPast() ? 'text-red-600' : '' }}">
                        {{ $employment->end?->format('d.m.Y') ?? 'unbefristet' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Stunden/Woche</dt>
                    <dd class="font-medium">{{ $employment->hours }}h
                        <span class="text-gray-400">({{ number_format($employment->percent, 1) }}%)</span>
                    </dd>
                </div>
                @if($employment->probation_end)
                <div>
                    <dt class="text-gray-500">Probezeit bis</dt>
                    <dd class="font-medium">{{ $employment->probation_end->format('d.m.Y') }}</dd>
                </div>
                @endif
                @if($employment->termination_reason)
                <div>
                    <dt class="text-gray-500">Austrittsgrund</dt>
                    <dd class="font-medium">{{ $employment->termination_reason->label() }}</dd>
                </div>
                @endif
            </dl>

            {{-- Gehalt (nur mit Berechtigung) --}}
            @can('view salary')
            @if($employment->salary_group || $employment->salary_level)
            <div class="mt-3 pt-3 border-t border-gray-100">
                <p class="text-xs text-gray-400 mb-1">Vergütung</p>
                <div class="flex gap-4 text-sm">
                    @if($employment->salary_group)
                    <span><span class="text-gray-500">Gruppe:</span> <strong>{{ $employment->salary_group }}</strong></span>
                    @endif
                    @if($employment->salary_level)
                    <span><span class="text-gray-500">Stufe:</span> <strong>{{ $employment->salary_level }}</strong></span>
                    @endif
                    @if($employment->salaryTable)
                    <span><span class="text-gray-500">Tarifwerk:</span> <strong>{{ $employment->salaryTable->name }}</strong></span>
                    @endif
                </div>
            </div>
            @endif
            @endcan

        </div>

        @if(!isset($readonly) || !$readonly)
        @can('edit contracts')
        <div class="ml-4 flex flex-col gap-2">
            <a href="{{ route('personal.contracts.edit', $employment->id) }}"
               class="btn-personal-secondary text-xs">Bearbeiten</a>

            @if($employment->status?->value === 'aktiv')
            <button onclick="document.getElementById('modal-ruhend-{{ $employment->id }}').showModal()"
                    class="btn-personal-secondary text-xs text-yellow-700">Ruhend setzen</button>
            @endif

            @if($employment->status?->value === 'ruhend')
            <form method="POST" action="{{ route('personal.contracts.setAktiv', $employment->id) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn-personal-primary text-xs">Reaktivieren</button>
            </form>
            @endif

            @if(in_array($employment->status?->value, ['aktiv', 'ruhend']))
            <button onclick="document.getElementById('modal-beendet-{{ $employment->id }}').showModal()"
                    class="btn-personal-danger text-xs">Beenden</button>
            @endif
        </div>
        @endcan
        @endif
    </div>
</div>

{{-- Dialog: Ruhend setzen --}}
@can('edit contracts')
<dialog id="modal-ruhend-{{ $employment->id }}" class="rounded-xl shadow-2xl p-6 w-full max-w-md backdrop:bg-black/40">
    <form method="POST" action="{{ route('personal.contracts.setRuhend', $employment->id) }}">
        @csrf @method('PATCH')
        <h3 class="font-bold text-lg mb-4">Anstellung auf ruhend setzen</h3>
        <label class="block text-sm font-medium text-gray-700 mb-1">Grund</label>
        <select name="reason" class="input-personal mb-4" required>
            @foreach(\App\Enums\EmploymentStatusReason::cases() as $reason)
            <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
            @endforeach
        </select>
        <div class="flex gap-3 justify-end">
            <button type="button" onclick="document.getElementById('modal-ruhend-{{ $employment->id }}').close()"
                    class="btn-personal-secondary text-sm">Abbrechen</button>
            <button type="submit" class="btn-personal-primary text-sm">Bestätigen</button>
        </div>
    </form>
</dialog>

{{-- Dialog: Beenden --}}
<dialog id="modal-beendet-{{ $employment->id }}" class="rounded-xl shadow-2xl p-6 w-full max-w-md backdrop:bg-black/40">
    <form method="POST" action="{{ route('personal.contracts.setBeendet', $employment->id) }}">
        @csrf @method('PATCH')
        <h3 class="font-bold text-lg mb-4">Anstellung beenden</h3>
        <label class="block text-sm font-medium text-gray-700 mb-1">Austrittsgrund</label>
        <select name="reason" class="input-personal mb-3" required>
            @foreach(\App\Enums\TerminationReason::cases() as $reason)
            <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
            @endforeach
        </select>
        <label class="block text-sm font-medium text-gray-700 mb-1">Austrittsdatum (optional)</label>
        <input type="date" name="end_date" class="input-personal mb-4"
               value="{{ $employment->end?->format('Y-m-d') }}">
        <div class="flex gap-3 justify-end">
            <button type="button" onclick="document.getElementById('modal-beendet-{{ $employment->id }}').close()"
                    class="btn-personal-secondary text-sm">Abbrechen</button>
            <button type="submit" class="btn-personal-danger text-sm">Anstellung beenden</button>
        </div>
    </form>
</dialog>
@endcan

