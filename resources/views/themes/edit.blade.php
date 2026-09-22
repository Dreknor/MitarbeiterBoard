@extends('layouts.app')

@push('css')
    @vite('resources/css/themes.css')
@endpush

@section('content')
<div class="theme-wrapper">
    <div class="mb-4">
        <a href="{{ url(request()->segment(1).'/themes/'.$theme->id) }}" class="thm-btn thm-btn-secondary">
            <i class="fas fa-arrow-left"></i> Zurück
        </a>
    </div>

    <div class="thm-card">
        <div class="thm-band thm-band-amber">
            <h1 class="thm-page-title text-xl font-bold"><i class="fas fa-pen mr-1"></i> Thema bearbeiten</h1>
        </div>

        <form method="post" action="{{ url(request()->segment(1).'/themes/'.$theme->id) }}" id="editForm" enctype="multipart/form-data">
            @csrf
            @method('put')
            <div class="p-5 sm:p-6 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label for="theme" class="thm-label">Thema</label>
                        <input type="text" class="thm-input" id="theme" name="theme" required autofocus value="{{ old('theme', $theme->theme) }}" @if($theme->priority) readonly @endif>
                    </div>
                    <div>
                        <label for="date" class="thm-label">Datum</label>
                        <input type="date" class="thm-input" id="date" name="date" required value="{{ old('date', $theme->date->format('Y-m-d')) }}">
                    </div>
                    <div>
                        <label for="type" class="thm-label">Typ</label>
                        <select name="type" id="type" class="thm-select" required>
                            <option value="" disabled>-- wählen --</option>
                            @foreach($types as $type)
                                <option value="{{ $type->id }}" data-needsprotocol="{{ $type->needsProtocol }}" @if(old('type', $theme->type_id) == $type->id) selected @endif>{{ $type->type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="duration" class="thm-label">Dauer (Min.)</label>
                        <input type="number" class="thm-input" id="duration" name="duration" required min="5" max="240" step="5" value="{{ old('duration', $theme->duration) }}">
                    </div>
                </div>

                <div>
                    <label for="goal" class="thm-label">Ziel</label>
                    <input type="text" class="thm-input" id="goal" name="goal" required value="{{ old('goal', $theme->goal) }}">
                </div>

                <div>
                    <label for="information" class="thm-label">Informationen</label>
                    <textarea class="thm-textarea" id="information" name="information">{{ old('information', $theme->information) }}</textarea>
                </div>

                <div>
                    <label for="customFile" class="thm-label">Zusätzliche Dateien</label>
                    <input type="file" name="files[]" id="customFile" multiple
                           class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
                </div>
            </div>

            <div class="px-5 py-4 border-t border-gray-100 bg-gray-50">
                <button type="submit" class="thm-btn thm-btn-warning w-full">
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
            height: 300,
            width: '100%',
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
