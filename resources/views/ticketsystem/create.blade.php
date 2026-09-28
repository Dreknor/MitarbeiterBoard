<div class="card mb-3">
    <div class="card-header bg-gradient-directional-blue text-white">
        <h6 class="mb-0">Neues Ticket erstellen</h6>
    </div>
    <div class="card-body">
        <form action="{{ route('tickets.store') }}" method="post" enctype="multipart/form-data" class="ticket-editor-form">
            @csrf
            <div class="form-group">
                <label for="title">Titel<span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title"
                       value="{{ old('title') }}" maxlength="255" required>
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-row">
                @if($categories->isNotEmpty())
                    <div class="col-md-6 col-12">
                        <div class="form-group">
                            <label for="category">Kategorie<span class="text-danger">*</span></label>
                            <select class="form-control @error('category_id') is-invalid @enderror" id="category" name="category_id" required>
                                <option value="">Bitte wählen</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                @endif
                <div class="col-md-6 col-12">
                    <div class="form-group">
                        <label for="priority">Priorität<span class="text-danger">*</span></label>
                        <select class="form-control @error('priority') is-invalid @enderror" id="priority" name="priority" required>
                            @foreach(\App\Models\Ticket::PRIORITY_LABELS as $value => $label)
                                <option value="{{ $value }}" @selected(old('priority', 'medium') == $value)>{{ ucfirst($label) }}</option>
                            @endforeach
                        </select>
                        @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Beschreibung<span class="text-danger">*</span></label>
                <textarea class="form-control ticket-editor" id="description" name="description">{{ old('description') }}</textarea>
                @error('description')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label for="customFile">Dateien anhängen <small class="text-muted">(max. 10 Dateien, je 20 MB)</small></label>
                <input type="file" name="files[]" id="customFile" multiple>
                @error('files')<div class="text-danger small">{{ $message }}</div>@enderror
                @error('files.*')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-primary">Ticket erstellen</button>
        </form>
    </div>
</div>
