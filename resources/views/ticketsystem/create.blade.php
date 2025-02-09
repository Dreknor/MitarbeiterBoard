    <div class="card mb-3">
    <div class="card-header bg-gradient-directional-blue text-white">
        <h6>
            Neues Ticket erstellen
        </h6>
    </div>
    <div class="card-body">
        @if($categories->count() < 1)

                <div class="alert alert-warning">
                    <p>Es sind keine Kategorien vorhanden. Bitte erstellen Sie eine Kategorie, bevor Sie ein Ticket erstellen.</p>
                </div>

        @else
            @if($errors->any())
                <div class = "alert alert-error">
                    @foreach ($errors->all('<p>:message</p>') as $input_error)
                        {{ $input_error }}
                    @endforeach
                </div>
            @endif
        <form action="{{ route('tickets.store') }}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label for="title">Titel<span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="title" name="title" required>
            </div>
            <div class="form-row">
                <div class="col-md-6 col-12">
                    <div class="form-group">
                        <label for="category">Kategorie<span class="text-danger">*</span></label>
                        <select class="form-control" id="category" name="category_id" required>
                            @if($categories->count()>0)
                                <option value="">Bitte wählen</option>
                            @endif
                            @forelse($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @empty
                                <option value="0">Keine Kategorien vorhanden</option>
                            @endforelse
                        </select>

                    </div>
                </div>
                <div class="col-md-6 col-12">
                    <div class="form-group">
                        <label for="priority">Priorität<span class="text-danger">*</span></label>
                        <select class="form-control" id="priority" name="priority" required>
                            <option value="low">Niedrig</option>
                            <option value="medium">Normal</option>
                            <option value="high">Hoch</option>
                        </select>
                    </div>
                </div>
            </div>


            <div class="form-group">
                <label for="description">Beschreibung<span class="text-danger">*</span></label>
                <textarea class="form-control" id="description" name="description" ></textarea>

            </div>

            <div class="form-group">
                <label for="file">Datei anhängen</label>
                <input type="file"  name="files[]" id="customFile" multiple>
            </div>

            <button type="submit" class="btn btn-primary">Ticket erstellen</button>
        </form>
        @endif
    </div>
</div>

