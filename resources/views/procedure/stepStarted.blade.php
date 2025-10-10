<li class="list-group-item p-2 border-0 bg-transparent step_{{$step->id}}" id="step_{{$step->id}}">
    @php
        // Rekursive Closure prüft, ob dieser Schritt oder ein Nachkomme noch nicht erledigt ist
        $hasIncomplete = false;
        $checkIncomplete = function($s) use (&$checkIncomplete, &$hasIncomplete) {
            if (!$s) return;
            if ($s->done == 0) {
                $hasIncomplete = true;
                return;
            }
            foreach ($s->childs as $c) {
                $checkIncomplete($c);
                if ($hasIncomplete) return;
            }
        };
        $checkIncomplete($step);
    @endphp
    {{-- Hauptkarte für den Schritt --}}
    <div class="card mb-2 shadow-sm border @if($step->done == 1) border-left-10-success border-success  @else border-left-10 border-info @endif">
        <div class="card-body d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between">
            <div class="d-flex align-items-start">
                @if ($step->parent != "" )
                    <div class="mr-3 mt-1 text-secondary" aria-hidden="true">
                        <i class="fa fa-arrow-right"></i>
                    </div>
                @endif

                <div>
                    <h5 class="card-title mb-1">
                        {{$step->name}}
                        @if($step->done == 1)
                            <span class="badge badge-success ml-2">Erledigt</span>
                        @elseif($step->endDate && $step->endDate->isPast())
                            <span class="badge badge-danger ml-2">Überfällig</span>
                        @elseif($step->endDate)
                            <span class="badge badge-info ml-2">Bis {{$step->endDate->format('d.m.Y')}}</span>
                        @endif
                    </h5>
                    @if($step->done == 0)
                        @if($step->description)
                            <p class="card-text text-muted small mb-1">{{$step->description}}</p>
                        @endif

                        <div class="d-flex flex-wrap align-items-center small text-muted">
                            {{-- Nutzerliste (kompakt) --}}
                            <div class="mr-3">
                                <strong>Verantwortlich:</strong>
                                @foreach($step->users as $user)
                                    <span class="d-inline-block ml-1">{{$user->name}}</span>
                                @endforeach
                            </div>

                            @if($step->endDate)
                                <div class="mr-3">
                                    <i class="far fa-calendar-alt"></i>
                                    <span class="ml-1">{{$step->endDate->format('d.m.Y')}}</span>
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="card-text text-muted small mb-0">Erledigt am {{$step->updated_at->format('d.m.Y')}}</p>
                    @endif
                </div>
            </div>

            <div class="mt-2 mt-md-0 d-flex align-items-center">
                {{-- Aktionen: Löschen nur wenn keine Kinder und nicht erledigt --}}
                @if(count($step->childs)<1 and !$step->done )
                    <form class="mr-2" action="{{url('procedure/step/'.$step->id."/delete")}}" method="post">
                        @csrf
                        @method('delete')
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Schritt löschen">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                @endif

                {{-- Person hinzufügen link (öffnet Modal) --}}
                @if($step->done == 0)
                    <a href="#" class="btn btn-sm btn-outline-primary mr-2 addUser" data-toggle="modal" data-target="#addUserModal" data-step="{{$step->id}}" title="Person hinzufügen">
                        <i class="fas fa-user-plus"></i>
                    </a>
                @endif

                {{-- Aufgabe als erledigt markieren (nur wenn Zugehörig und nicht erledigt und endDate gesetzt) --}}
                @if($step->users->contains(auth()->user()) and $step->done  == 0 and $step->endDate != null)
                    <form action="{{url('procedure/step/'.$step->id.'/done')}}" method="post" class="mr-2">
                        @csrf
                        @method('put')
                        <button type="submit" class="btn btn-sm btn-success" title="Aufgabe als erledigt markieren">
                            <i class="fas fa-check"></i> Erledigt
                        </button>
                    </form>
                @endif

                {{-- Falls Kinder vorhanden: Toggle-Button --}}
                @if( count($step->childs) > 0)
                    @php $expanded = ($hasIncomplete || $step->parent == ""); @endphp
                    <button type="button" class="btn btn-sm btn-outline-secondary toggle-children-btn" data-target-id="step_children_{{$step->id}}" aria-expanded="{{ $expanded ? 'true' : 'false' }}" aria-controls="step_children_{{$step->id}}" title="{{ $expanded ? 'Schritte ausblenden' : 'Mehr Schritte einblenden' }}">
                        <i class="fa {{ $expanded ? 'fa-minus-circle' : 'fa-plus-circle' }}"></i>
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Rekursive Darstellung der Kind-Schritte (collapse) --}}
    @if (count($step->childs) > 0)

        <div id="step_children_{{$step->id}}" class="ml-4 pl-2 collapse {{ ($hasIncomplete || $step->parent == "") ? 'show' : '' }} step_{{$step->id}}">
            <ul class="list-unstyled">
                @each('procedure.stepStarted',$step->childs, 'step')
            </ul>
        </div>

    @endif

    {{-- Einmaliges JS zur Icon-/State-Synchronisation zwischen Button und Collapse --}}
    @once
    @push('js')
    <script>
    if (!window._procedure_toggle_initialized) {
        window._procedure_toggle_initialized = true;
        (function(){
            function updateBtnIcon(btn, expanded) {
                var icon = btn.querySelector('i');
                if (!icon) return;
                if (expanded) { icon.classList.remove('fa-plus-circle'); icon.classList.add('fa-minus-circle'); }
                else { icon.classList.remove('fa-minus-circle'); icon.classList.add('fa-plus-circle'); }
            }

            function initProcedureToggle() {
                // initiale Synchronisation
                document.querySelectorAll('.toggle-children-btn').forEach(function(btn){
                    var targetId = btn.getAttribute('data-target-id');
                    var target = targetId ? document.getElementById(targetId) : null;
                    var expanded = target && target.classList.contains('show');
                    updateBtnIcon(btn, expanded);
                    btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                });

                // Bootstrap collapse events (benötigt jQuery/Bootstrap JS)
                if (window.jQuery) {
                    jQuery(document).on('show.bs.collapse', '.collapse', function () {
                        var el = this;
                        var id = el.id ? el.id : null;
                        if (!id) return;
                        var btn = document.querySelector('.toggle-children-btn[data-target-id="'+id+'"]');
                        if (btn) { updateBtnIcon(btn, true); btn.setAttribute('aria-expanded','true'); }
                    });
                    jQuery(document).on('hide.bs.collapse', '.collapse', function () {
                        var el = this;
                        var id = el.id ? el.id : null;
                        if (!id) return;
                        var btn = document.querySelector('.toggle-children-btn[data-target-id="'+id+'"]');
                        if (btn) { updateBtnIcon(btn, false); btn.setAttribute('aria-expanded','false'); }
                    });
                }

                // Native event listener fallback (Bootstrap 5 oder ohne jQuery)
                document.addEventListener('show.bs.collapse', function(e){
                    var id = e && e.target && e.target.id ? e.target.id : null;
                    if (!id) return;
                    var btn = document.querySelector('.toggle-children-btn[data-target-id="'+id+'"]');
                    if (btn) { updateBtnIcon(btn, true); btn.setAttribute('aria-expanded','true'); }
                });
                document.addEventListener('hide.bs.collapse', function(e){
                    var id = e && e.target && e.target.id ? e.target.id : null;
                    if (!id) return;
                    var btn = document.querySelector('.toggle-children-btn[data-target-id="'+id+'"]');
                    if (btn) { updateBtnIcon(btn, false); btn.setAttribute('aria-expanded','false'); }
                });
            }

            // Falls DOM bereits geladen ist, sofort initialisieren, sonst auf DOMContentLoaded warten
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initProcedureToggle);
            } else {
                initProcedureToggle();
            }

            // Einheitlicher Click-Handler: nutzt jQuery/Bootstrap-API wenn vorhanden, sonst Fallback per Klasse
            document.querySelectorAll('.toggle-children-btn').forEach(function(btn){
                btn.addEventListener('click', function(e){
                    e.preventDefault();
                    e.stopPropagation();

                    // kurzfristige Sperre, um Doppel-Toggles zu verhindern
                    if (btn._procedureToggleLocked) return;
                    btn._procedureToggleLocked = true;
                    setTimeout(function(){ btn._procedureToggleLocked = false; }, 600);

                    var targetId = btn.getAttribute('data-target-id');
                    if (!targetId) return;
                    var target = document.getElementById(targetId);
                    if (!target) return;

                    // Per-target Sperre: verhindert Doppel-Toggles, wenn Klick-Handler mehrfach feuert
                    if (target._procedureToggleLocked) return;
                    target._procedureToggleLocked = true;
                    setTimeout(function(){ target._procedureToggleLocked = false; }, 800);

                    // Wenn jQuery und Bootstrap collapse plugin vorhanden ist (Bootstrap 3/4)
                    if (window.jQuery && jQuery.fn && jQuery.fn.collapse) {
                        jQuery(target).collapse('toggle');
                        return;
                    }

                    // Wenn Bootstrap 5 JS vorhanden
                    if (window.bootstrap && window.bootstrap.Collapse) {
                        try {
                            var inst = window.bootstrap.Collapse.getOrCreateInstance(target);
                            inst.toggle();
                        } catch (err) {
                            // fallback to class toggle
                            let isShown = target.classList.contains('show');
                            if (isShown) { target.classList.remove('show'); updateBtnIcon(btn, false); btn.setAttribute('aria-expanded','false'); }
                            else { target.classList.add('show'); updateBtnIcon(btn, true); btn.setAttribute('aria-expanded','true'); }
                        }
                        return;
                    }

                    // Fallback: class toggle
                    let isShown = target.classList.contains('show');
                    if (isShown) {
                        target.classList.remove('show');
                        btn.setAttribute('aria-expanded','false');
                        updateBtnIcon(btn, false);
                    } else {
                        target.classList.add('show');
                        btn.setAttribute('aria-expanded','true');
                        updateBtnIcon(btn, true);
                    }
                });
            });

        })();
    }
    </script>
    @endpush
    @endonce
</li>
