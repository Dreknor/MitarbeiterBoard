<div class="grid grid-cols-1 md:grid-cols-2 gap-4">

    {{-- Stammdaten --}}
    <div class="personal-card">
        <h3 class="font-semibold text-gray-900 mb-3">Meine Daten</h3>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
                <dt class="text-gray-500">Name</dt>
                <dd class="font-medium">{{ auth()->user()->name }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">E-Mail</dt>
                <dd>{{ auth()->user()->email }}</dd>
            </div>
            @if(auth()->user()->kuerzel)
            <div class="flex justify-between">
                <dt class="text-gray-500">Kürzel</dt>
                <dd class="font-mono">{{ auth()->user()->kuerzel }}</dd>
            </div>
            @endif
            @if($employe['geburtstag'] ?? null)
            <div class="flex justify-between">
                <dt class="text-gray-500">Geburtstag</dt>
                <dd>{{ \Carbon\Carbon::parse($employe['geburtstag'])->format('d.m.Y') }}</dd>
            </div>
            @endif
            @if($employe['eintrittsdatum'] ?? null)
            <div class="flex justify-between">
                <dt class="text-gray-500">Eintrittsdatum</dt>
                <dd>{{ $employe['eintrittsdatum'] }}</dd>
            </div>
            @endif
        </dl>
    </div>

    {{-- Aktuelle Anstellung --}}
    <div class="personal-card" data-tour="anstellung">
        <h3 class="font-semibold text-gray-900 mb-3">Meine Anstellung</h3>
        @if(count($employe['employments']) > 0)
        @php $currentEmp = $employe['employments']->first(); @endphp
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
                <dt class="text-gray-500">Art</dt>
                <dd class="font-medium">{{ $currentEmp['employment_type'] ?? '—' }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Vertragsart</dt>
                <dd>{{ $currentEmp['contract_type'] ?? '—' }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Status</dt>
                <dd>
                    <span class="badge-green">{{ $currentEmp['status'] ?? '—' }}</span>
                </dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Abteilung</dt>
                <dd>{{ $currentEmp['department'] ?? '—' }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Stunden/Woche</dt>
                <dd>{{ $currentEmp['hours'] ?? '—' }}h</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-gray-500">Seit</dt>
                <dd>{{ $currentEmp['start'] ?? '—' }}</dd>
            </div>
            @if($currentEmp['end'] ?? null)
            <div class="flex justify-between">
                <dt class="text-gray-500">Befristet bis</dt>
                <dd class="text-yellow-600 font-medium">{{ $currentEmp['end'] }}</dd>
            </div>
            @endif
        </dl>
        @else
        <p class="text-gray-400 text-sm">Keine aktive Anstellung</p>
        @endif

        {{-- Anstellungshistorie: alle Vertragsversionen, neueste zuerst --}}
        @if(($anstellungshistorie ?? collect())->isNotEmpty())
        @php $aktuelleIds = $rawEmploye->employments->pluck('id'); @endphp
        <div x-data="{ offen: false }" class="mt-4 pt-3 border-t border-gray-100">
            <button type="button" @click="offen = !offen" class="text-sm text-blue-600 hover:underline" :aria-expanded="offen">
                <span x-text="offen ? 'Anstellungshistorie ausblenden' : 'Anstellungshistorie anzeigen'"></span>
                ({{ $anstellungshistorie->count() }})
            </button>
            <ol x-show="offen" class="mt-3 space-y-3 border-l-2 border-gray-200 pl-4">
                @foreach($anstellungshistorie as $emp)
                @php
                    $aktuell = $aktuelleIds->contains($emp->id);
                    $beendet = $emp->end && $emp->end->isPast();
                @endphp
                <li class="relative text-sm">
                    <span class="absolute -left-[1.4rem] top-1.5 w-2.5 h-2.5 rounded-full {{ $aktuell ? 'bg-green-500' : 'bg-gray-300' }}"></span>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium text-gray-900">
                            {{ $emp->start?->format('d.m.Y') }} – {{ $emp->end?->format('d.m.Y') ?? 'unbefristet' }}
                        </span>
                        @if($aktuell)
                            <span class="badge-green">aktuell</span>
                        @elseif($beendet)
                            <span class="badge-gray">beendet</span>
                        @elseif($emp->start?->isFuture())
                            <span class="badge-blue">künftig</span>
                        @else
                            <span class="badge-{{ match($emp->status?->value) { 'ruhend' => 'yellow', default => 'gray' } }}">{{ $emp->status?->label() }}</span>
                        @endif
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ $emp->employment_type?->label() ?? 'Anstellung' }}@if($emp->contract_type) · {{ $emp->contract_type->label() }}@endif
                        · {{ $emp->hours }}h/Woche
                        @if($emp->department) · {{ $emp->department->name }}@endif
                    </div>
                    @include('personal.partials._vertrag_kennzeichen', ['employment' => $emp])
                </li>
                @endforeach
            </ol>
        </div>
        @endif
    </div>

    {{-- Vorgesetzter --}}
    @if(auth()->user()->superior)
    <div class="personal-card">
        <h3 class="font-semibold text-gray-900 mb-3">Mein Vorgesetzter</h3>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 font-semibold">
                {{ substr(auth()->user()->superior->name, 0, 1) }}
            </div>
            <div>
                <p class="font-medium text-sm">{{ auth()->user()->superior->name }}</p>
                <a href="mailto:{{ auth()->user()->superior->email }}"
                   class="text-xs text-blue-600 hover:underline">{{ auth()->user()->superior->email }}</a>
            </div>
        </div>
    </div>
    @endif

</div>

