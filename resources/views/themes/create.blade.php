@extends('layouts.app')

@push('css')
    @vite('resources/css/themes.css')
@endpush

@section('content')
<div class="theme-wrapper">
    <div class="mb-4">
        <a href="{{ url(request()->segment(1).'/themes') }}" class="thm-btn thm-btn-secondary">
            <i class="fas fa-arrow-left"></i> Zurück
        </a>
    </div>

    <div class="thm-card">
        <div class="thm-band thm-band-blue">
            <h1 class="thm-page-title text-xl font-bold"><i class="fas fa-plus mr-1"></i> Neues Thema</h1>
        </div>

        <form method="post" action="{{ url(request()->segment(1).'/themes') }}" id="createForm" enctype="multipart/form-data">
            @csrf
            <div class="p-5 sm:p-6 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="lg:col-span-1">
                        <label for="theme" class="thm-label">Thema <span class="thm-required">*</span></label>
                        <input type="text" class="thm-input" id="theme" name="theme" required autofocus value="{{ old('theme') }}">
                    </div>
                    <div>
                        <label for="date" class="thm-label">Datum der Besprechung <span class="thm-required">*</span></label>
                        @if(\Carbon\Carbon::now()->next($group->weekday_name())->diffInDays(\Carbon\Carbon::now()) >= $group->InvationDays)
                            <input type="date" class="thm-input" id="date" name="date" required value="{{ old('date', \Carbon\Carbon::now()->next($group->weekday_name())->format('Y-m-d')) }}" min="{{ \Carbon\Carbon::now()->format('Y-m-d') }}">
                        @else
                            <input type="date" class="thm-input" id="date" name="date" required value="{{ old('date', \Carbon\Carbon::now()->next($group->weekday_name())->addWeek()->format('Y-m-d')) }}" min="{{ \Carbon\Carbon::now()->format('Y-m-d') }}">
                        @endif
                        <p class="thm-hint mt-1">Sollte mind. {{ $group->InvationDays }} Tage in der Zukunft liegen (Themenversand vor der Sitzung).</p>
                    </div>
                    <div>
                        <label for="type" class="thm-label">Typ <span class="thm-required">*</span></label>
                        <select name="type" id="type" class="thm-select" required>
                            <option value="" disabled selected>-- wählen --</option>
                            @foreach($types as $type)
                                <option value="{{ $type->id }}" data-needsprotocol="{{ $type->needsProtocol }}" @if(old('type') == $type->id) selected @endif>{{ $type->type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="duration" class="thm-label">Dauer (Min.) <span class="thm-required">*</span></label>
                        <input type="number" class="thm-input" id="duration" name="duration" required min="5" max="240" step="5" value="{{ old('duration') }}">
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                    <div class="lg:col-span-8">
                        <label for="goal" class="thm-label">Ziel <span class="thm-required">*</span></label>
                        <input type="text" class="thm-input" id="goal" name="goal" required value="{{ old('goal') }}">
                        <p class="thm-hint mt-1">Spezifisch, messbar, akzeptiert, realistisch und terminiert.</p>
                    </div>
                    <div class="lg:col-span-2">
                        <label for="change_protokoll" class="thm-label">Protokolle veränderbar</label>
                        <select name="change_protokoll" id="change_protokoll" class="thm-select">
                            <option value="0">nein</option>
                            <option value="1">ja</option>
                        </select>
                    </div>
                    <div class="lg:col-span-2">
                        <label for="memory" class="thm-label">In Themenspeicher?</label>
                        <select name="memory" id="memory" class="thm-select">
                            <option value="0">nein</option>
                            <option value="1" @if($speicher) selected @endif>ja</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="information" class="thm-label">Informationen</label>
                    <textarea class="thm-textarea" id="information" name="information">{{ old('information', $group->information_template) }}</textarea>
                </div>

                <div>
                    <label for="customFile" class="thm-label">Zusätzliche Dateien</label>
                    <input type="file" name="files[]" id="customFile" multiple
                           class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                </div>
            </div>

            <div class="px-5 py-4 border-t border-gray-100 bg-gray-50">
                <button type="submit" class="thm-btn thm-btn-success w-full">
                    <i class="fas fa-save"></i> Speichern
                </button>
            </div>
        </form>
    </div>
</div>
@stop

@push('js')
    <script src="{{ asset('js/plugins/tinymce/jquery.tinymce.min.js') }}"></script>
    <script src="{{ asset('js/plugins/tinymce/tinymce.min.js') }}"></script>
    <script src="{{ asset('js/plugins/tinymce/langs/de.js') }}"></script>
    <script>
        tinymce.init({
            selector: '#information',
            lang: 'de',
            height: 400,
            menubar: true,
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
