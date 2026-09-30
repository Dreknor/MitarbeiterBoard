<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Dienstpläne</h6>
        <a href="{{ route('roster.mine') }}" class="small">Mein Dienstplan →</a>
    </div>
    <div class="card-body">
        @forelse($wochen as $wochenStart => $plaene)
            @php($start = \Illuminate\Support\Carbon::parse($wochenStart))
            <div class="@if(!$loop->first) mt-3 @endif">
                <div class="small text-muted font-weight-bold mb-1">
                    KW {{ $start->isoWeek() }} · {{ $start->format('d.m.') }}–{{ $start->copy()->endOfWeek()->format('d.m.Y') }}
                    @if($start->isSameDay(now()->startOfWeek()))
                        <span class="badge badge-info">aktuelle Woche</span>
                    @endif
                </div>
                <ul class="list-group">
                    @foreach($plaene as $roster)
                        <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center py-2">
                            <div>
                                <a href="{{ route('roster.export.pdf', $roster->id) }}" target="_blank">
                                    <i class="fas fa-file-pdf"></i> {{ $roster->department?->name ?? 'Dienstplan' }}
                                </a>
                                @if($roster->mit_dienst || $roster->mit_termin)
                                    <span class="badge badge-primary">eingeplant</span>
                                @endif
                                @can('create roster')
                                    @unless($roster->published)
                                        <span class="badge badge-warning">Entwurf</span>
                                    @endunless
                                @endcan
                            </div>
                            <div class="small">
                                @if($roster->published && ($roster->mit_dienst || $roster->mit_termin))
                                    <a href="{{ route('roster.export.employe.pdf', [$roster->id, auth()->id()]) }}" target="_blank" class="mr-2" title="Nur meine Dienste als PDF">
                                        <i class="fas fa-user"></i> meine Dienste
                                    </a>
                                @endif
                                @can('manage', $roster)
                                    <a href="{{ route('roster.show', $roster->id) }}" title="Dienstplan bearbeiten">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="text-muted mb-0">Keine veröffentlichten Dienstpläne ab dieser Woche.</p>
        @endforelse
    </div>
    @if($heute->isNotEmpty())
        <div class="card-footer border-top">
            <h6>Arbeitszeiten heute</h6>
            @foreach($heute as $eintrag)
                @if($heute->count() > 1)
                    <div class="small text-muted font-weight-bold @if(!$loop->first) mt-2 @endif mb-1">{{ $eintrag['roster']->department?->name }}</div>
                @endif
                <ul class="list-group">
                    @foreach($eintrag['zeiten'] as $working_time)
                        <li class="list-group-item d-flex justify-content-between align-items-center @if($working_time->employe_id == auth()->id()) list-group-item-primary @endif">
                            {{ $working_time->employe?->name }}:
                            <div class="d-inline col-4"><b>{{ $working_time->start?->format('H:i') }} - {{ $working_time->end?->format('H:i') }}</b></div>
                            @if($working_time->function)
                                <span class="badge badge-primary badge-pill p-2">{{ $working_time->function }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </div>
    @endif
</div>
