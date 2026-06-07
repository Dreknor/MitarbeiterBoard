@extends('layouts.app')

@push('css')
    @vite('resources/css/themes.css')
@endpush

@section('content')
<div class="theme-wrapper">
    <div class="thm-card mb-4">
        <div class="thm-band thm-band-blue">
            <h1 class="text-xl font-bold">{{ $theme->theme }}</h1>
        </div>

        <div class="p-5 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3">
                    <span class="thm-section-title sm:w-32 shrink-0">Priorität</span>
                    <div class="thm-progress max-w-xs"><span style="width: {{ 100-$theme->priority }}%"></span></div>
                </div>
                <div class="flex flex-col sm:flex-row gap-1 sm:gap-3">
                    <span class="thm-section-title sm:w-32 shrink-0">Von</span>
                    <span class="text-gray-800">{{ $theme->ersteller->name }}</span>
                </div>
                <div class="flex flex-col sm:flex-row gap-1 sm:gap-3">
                    <span class="thm-section-title sm:w-32 shrink-0">Typ</span>
                    <span><span class="thm-badge thm-badge-blue">{{ $theme->type->type }}</span></span>
                </div>
                <div class="flex flex-col sm:flex-row gap-1 sm:gap-3">
                    <span class="thm-section-title sm:w-32 shrink-0">Ziel</span>
                    <span class="text-gray-800">{{ $theme->goal }}</span>
                </div>
                <div class="flex flex-col sm:flex-row gap-1 sm:gap-3">
                    <span class="thm-section-title sm:w-32 shrink-0">Erstellt</span>
                    <span class="text-gray-600">{{ $theme->created_at->format('d.m.Y H:i') }} Uhr</span>
                </div>
                <div class="flex flex-col sm:flex-row gap-1 sm:gap-3">
                    <span class="thm-section-title sm:w-32 shrink-0">Dauer</span>
                    <span class="text-gray-600">{{ $theme->duration }} Minuten</span>
                </div>
                <div class="flex flex-col sm:flex-row gap-1 sm:gap-3">
                    <span class="thm-section-title sm:w-32 shrink-0">Informationen</span>
                    <div class="thm-prose flex-1 text-gray-800">{!! $theme->information !!}</div>
                </div>
                @if (count($theme->getMedia()) > 0)
                    <div class="flex flex-col sm:flex-row gap-1 sm:gap-3 pt-2">
                        <span class="thm-section-title sm:w-32 shrink-0">Dateien</span>
                        <div class="flex-1">
                            <ul class="space-y-2">
                                @foreach($theme->getMedia()->sortBy('name') as $media)
                                    <li class="p-2 rounded-lg border border-gray-100 bg-gray-50/60">
                                        <a href="{{ url('/image/'.$media->id) }}" target="_blank" class="text-blue-600 hover:underline text-sm">
                                            <i class="fas fa-file-download"></i> {{ $media->name }}
                                            <span class="text-gray-400">({{ $media->created_at->format('d.m.Y H:i') }})</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </div>

            <div class="lg:border-l lg:border-gray-100 lg:pl-6">
                <h3 class="thm-section-title mb-2">Aufgaben</h3>
                <ul class="space-y-2">
                    @forelse($theme->tasks->sortByDate('date', 'desc') as $task)
                        <li class="p-3 rounded-lg border border-gray-100 bg-gray-50/60">
                            <div class="flex items-center gap-2 text-sm font-medium text-gray-800">
                                @if($task->completed)<i class="far fa-check-square text-emerald-500"></i>@endif
                                {{ $task->date->format('d.m.Y') }} – {{ optional($task->taskable)->name }}
                            </div>
                            <p class="text-sm text-gray-600 mt-1">{{ $task->task }}</p>
                        </li>
                    @empty
                        <li class="text-sm text-gray-400 italic">Keine Aufgaben</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    @if(!$share->readonly)
        <div class="thm-card mb-4">
            <div class="p-5">
                <h2 class="thm-section-title mb-3"><i class="far fa-sticky-note mr-1"></i> Schnelles Protokoll</h2>
                <form action="{{ url('share/'.$share->uuid.'/protocol') }}" method="post" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <input type="text" required name="name" placeholder="Name" value="{{ old('name') }}" class="thm-input">
                    <textarea name="protocol" id="quickProtocol" class="thm-textarea">{{ old('protocol') }}</textarea>
                    <button type="submit" class="thm-btn thm-btn-success w-full"><i class="fas fa-save"></i> Speichern</button>
                </form>
            </div>
        </div>
    @endif

    <div class="thm-card">
        <div class="p-5">
            <h2 class="thm-section-title mb-3"><i class="fas fa-clipboard-list mr-1"></i> Protokoll</h2>
            @if ($theme->protocols->count() == 0)
                <p class="text-sm text-gray-400 italic">Kein Protokoll vorhanden</p>
            @else
                <ul class="space-y-3">
                    @foreach($theme->protocols->sortDesc() as $protocol)
                        <li class="p-4 rounded-xl border border-gray-100 bg-gray-50/40">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <span class="text-sm text-gray-600">{{ $protocol->created_at->format('d.m.Y H:i') }} · {{ $protocol->ersteller->name }}</span>
                                @if($protocol->creator_id == auth()->id() and $protocol->created_at->greaterThan(\Carbon\Carbon::now()->subMinutes(config('config.protocols.editableTime'))))
                                    <a href="{{ url(request()->segment(1).'/protocols/'.$protocol->id.'/edit') }}" class="thm-btn thm-btn-secondary thm-btn-sm"><i class="fas fa-pen"></i> bearbeiten</a>
                                @endif
                            </div>
                            <div class="thm-prose text-gray-800">{!! $protocol->protocol !!}</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@stop

@push('js')
    <script src="{{ asset('js/plugins/tinymce/jquery.tinymce.min.js') }}"></script>
    <script src="{{ asset('js/plugins/tinymce/tinymce.min.js') }}"></script>
    <script src="{{ asset('js/plugins/tinymce/langs/de.js') }}"></script>
    <script>
        tinymce.init({
            selector: '#quickProtocol',
            lang: 'de',
            height: 250,
            menubar: false,
            plugins: [
                'advlist autolink lists link charmap',
                'searchreplace visualblocks code',
                'insertdatetime table paste code wordcount',
            ],
            toolbar: 'undo redo | bold italic backcolor forecolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | link',
            table_default_attributes: { border: '1' }
        });
    </script>
@endpush
