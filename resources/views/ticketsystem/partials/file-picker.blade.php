{{-- Dateiauswahl mit Drag & Drop (Alpine-Komponente ticketFiles aus tickets.js) --}}
<div x-data="ticketFiles(10, 20)">
    <label class="tkt-dropzone {{ ($compact ?? false) ? 'is-compact' : '' }} relative"
           :class="{ 'is-dragover': dragover }"
           @dragover.prevent="dragover = true"
           @dragleave.prevent="dragover = false"
           @drop.prevent="onDrop($event)">
        <input type="file" name="files[]" multiple x-ref="input" @change="onChange($event)">
        <i class="fas fa-cloud-upload-alt {{ ($compact ?? false) ? '' : 'text-2xl mb-1' }} text-gray-400"></i>
        <span>
            <span class="font-semibold text-blue-600">Dateien auswählen</span>
            <span class="hidden sm:inline">oder hierher ziehen</span>
        </span>
        @unless($compact ?? false)
            <span class="text-xs text-gray-400">max. 10 Dateien, je 20 MB</span>
        @endunless
    </label>

    <ul class="mt-2 flex flex-col gap-1.5" x-show.important="files.length" x-cloak>
        <template x-for="(file, index) in files" :key="file.name + index">
            <li class="tkt-file" :class="{ 'border-red-300 bg-red-50': file.tooBig }">
                <i class="fas text-gray-400" :class="file.icon"></i>
                <span class="tkt-file-name" x-text="file.name"></span>
                <span class="tkt-file-size" x-text="file.size"></span>
                <button type="button" class="tkt-btn-icon w-8 h-8" @click="remove(index)" title="Entfernen" aria-label="Datei entfernen">
                    <i class="fas fa-times"></i>
                </button>
            </li>
        </template>
    </ul>
    <p class="tkt-error" x-show="error" x-text="error" x-cloak></p>

    @error('files')<p class="tkt-error">{{ $message }}</p>@enderror
    @foreach($errors->get('files.*') as $messages)
        @foreach($messages as $message)<p class="tkt-error">{{ $message }}</p>@endforeach
    @endforeach
</div>
