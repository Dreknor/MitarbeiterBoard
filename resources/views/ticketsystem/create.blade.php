{{-- Formular "Neues Ticket" (innerhalb von .ticket-wrapper) --}}
<section class="tkt-card">
    <div class="tkt-card-head">
        <div>
            <h2 class="tkt-card-title"><i class="fas fa-plus-circle"></i> Neues Ticket</h2>
            <p class="text-xs text-gray-500 mt-0.5">Beschreibe dein Anliegen möglichst genau – das Support-Team wird benachrichtigt.</p>
        </div>
    </div>

    <form action="{{ route('tickets.store') }}" method="post" enctype="multipart/form-data"
          class="tkt-card-body flex flex-col gap-5" data-ticket-form>
        @csrf

        <div>
            <label for="title" class="tkt-label">Betreff <span class="tkt-required">*</span></label>
            <input type="text" id="title" name="title" value="{{ old('title') }}" maxlength="255" required
                   class="tkt-input @error('title') is-invalid @enderror" placeholder="z. B. Beamer in Raum 204 zeigt kein Bild"
                   autocomplete="off">
            @error('title')<p class="tkt-error">{{ $message }}</p>@enderror
        </div>

        <div class="grid gap-5 {{ $categories->isNotEmpty() ? 'md:grid-cols-2' : '' }}">
            @if($categories->isNotEmpty())
                <div>
                    <label for="category" class="tkt-label">Kategorie <span class="tkt-required">*</span></label>
                    <select id="category" name="category_id" required class="tkt-select @error('category_id') is-invalid @enderror">
                        <option value="">Bitte wählen …</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<p class="tkt-error">{{ $message }}</p>@enderror
                </div>
            @endif

            <div>
                <span class="tkt-label" id="priority-label">Dringlichkeit <span class="tkt-required">*</span></span>
                <div class="tkt-segment" role="radiogroup" aria-labelledby="priority-label">
                    @foreach(['low' => ['Niedrig', 'fa-arrow-down'], 'medium' => ['Normal', 'fa-minus'], 'high' => ['Hoch', 'fa-arrow-up']] as $value => [$label, $icon])
                        <input type="radio" name="priority" id="priority_{{ $value }}" value="{{ $value }}" @checked(old('priority', 'medium') === $value)>
                        <label for="priority_{{ $value }}" class="is-{{ $value }}"><i class="fas {{ $icon }} text-xs"></i> {{ $label }}</label>
                    @endforeach
                </div>
                @error('priority')<p class="tkt-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label for="description" class="tkt-label">Beschreibung <span class="tkt-required">*</span></label>
            <textarea id="description" name="description" class="tkt-textarea ticket-editor"
                      placeholder="Was ist passiert? Seit wann? Wo genau?">{{ old('description') }}</textarea>
            <p class="tkt-error hidden" data-editor-error>Bitte eine Beschreibung eingeben.</p>
            @error('description')<p class="tkt-error">{{ $message }}</p>@enderror
        </div>

        <div>
            <span class="tkt-label">Anhänge <span class="font-normal text-gray-400">(z. B. Fotos oder Screenshots)</span></span>
            @include('ticketsystem.partials.file-picker')
        </div>

        <div class="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
            <button type="submit" class="tkt-btn tkt-btn-primary w-full sm:w-auto">
                <i class="fas fa-paper-plane"></i> <span data-loading-label="Wird erstellt …">Ticket erstellen</span>
            </button>
        </div>
    </form>
</section>
