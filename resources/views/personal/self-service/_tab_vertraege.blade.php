<div class="space-y-4">
    <h3 class="font-semibold text-gray-900">Meine Anstellungen</h3>

    @forelse($rawEmploye->employments as $emp)
    <div class="personal-card">
        <div class="flex items-start justify-between mb-3">
            <div>
                <span class="font-semibold">{{ $emp->employment_type?->label() ?? 'Anstellung' }}</span>
                <span class="badge-{{ $emp->status?->value === 'aktiv' ? 'green' : 'yellow' }} ml-2">
                    {{ $emp->status?->label() }}
                </span>
                @if($emp->contract_type)
                <span class="badge-gray ml-1">{{ $emp->contract_type->label() }}</span>
                @endif
            </div>
        </div>

        <dl class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
            <div>
                <dt class="text-gray-500">Abteilung</dt>
                <dd class="font-medium">{{ $emp->department?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Beginn</dt>
                <dd class="font-medium">{{ $emp->start?->format('d.m.Y') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Ende</dt>
                <dd class="{{ $emp->end && $emp->end->isPast() ? 'text-red-600 font-semibold' : 'font-medium' }}">
                    {{ $emp->end?->format('d.m.Y') ?? 'unbefristet' }}
                </dd>
            </div>
            <div>
                <dt class="text-gray-500">Stunden/Woche</dt>
                <dd class="font-medium">{{ $emp->hours }}h</dd>
            </div>
        </dl>

        {{-- Lehrer-Details --}}
        @if($emp->employment_type?->requiresTeacherDetail() && $emp->currentTeacherDetail)
        @php $detail = $emp->currentTeacherDetail; @endphp
        <div class="mt-3 pt-3 border-t border-gray-100 text-sm">
            <p class="text-gray-500 text-xs mb-2">Lehrer-Details</p>
            <div class="flex gap-4">
                <span><span class="text-gray-500">Schulart:</span> {{ $detail->schoolType?->name }}</span>
                <span><span class="text-gray-500">Deputat:</span> {{ $detail->deputat_hours }}h</span>
                <span class="text-blue-700"><span class="text-gray-500">Effektiv:</span> {{ number_format($detail->effective_hours, 2) }}h</span>
            </div>
        </div>
        @endif

        {{-- Gehalt (nur mit Berechtigung) --}}
        @if($canViewSalary && ($emp->salary_group || $emp->salary_level))
        <div class="mt-3 pt-3 border-t border-gray-100 text-sm">
            <p class="text-gray-500 text-xs mb-2">Vergütung</p>
            <div class="flex gap-4">
                @if($emp->salary_group)
                <span><span class="text-gray-500">Gruppe:</span> <strong>{{ $emp->salary_group }}</strong></span>
                @endif
                @if($emp->salary_level)
                <span><span class="text-gray-500">Stufe:</span> <strong>{{ $emp->salary_level }}</strong></span>
                @endif
            </div>
        </div>
        @endif
    </div>
    @empty
    <div class="personal-card text-center text-gray-400 py-8">
        Keine Anstellungen vorhanden
    </div>
    @endforelse
</div>

