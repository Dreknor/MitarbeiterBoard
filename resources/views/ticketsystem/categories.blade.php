@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Kategorien</h5>
                        <p class="text-muted">Hier werden alle Kategorien angezeigt, die im Ticketsystem erstellt wurden. Neue Kategorien können ebenfalls hinzugefügt werden.</p>
                    </div>
                    <div class="card-body">
                        <!-- Eingabeformular für neue Kategorie -->
                        <form id="categoryForm" method="post" action="{{ url('tickets/categories') }}">
                            @csrf
                            <div class="form-group">
                                <label for="name">Neue Kategorie hinzufügen:</label>
                                <input type="text" name="name" id="name" class="form-control" placeholder="Name der Kategorie" maxlength="255" value="{{ old('name') }}" required>
                            </div>
                            @error('name')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="fa fa-plus" aria-hidden="true"></i> Hinzufügen
                            </button>
                        </form>
                        <hr>
                        <!-- Tabelle zur Anzeige von Kategorien -->
                        <table class="table table-bordered table-striped mt-4" id="categoriesTable">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Tickets (offen / gesamt)</th>
                                <th>Aktionen</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($categories as $category)
                                <tr>
                                    <td>{{ $category->id }}</td>
                                    <td>{{ $category->name }}</td>
                                    <td>{{ $category->open_tickets_count }} / {{ $category->tickets_count }}</td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#deleteModal" data-id="{{ $category->id }}" data-count="{{ $category->tickets_count }}">
                                            <i class="fa fa-trash" aria-hidden="true"></i> Löschen
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Kategorie löschen</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Möchten Sie die Kategorie wirklich löschen?
                    <p class="text-muted small mb-0" id="deleteModalHint"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <form id="deleteForm" method="post" action="">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Löschen</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')

    <script>
        $('#deleteModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var action = '{{ url('tickets/categories') }}/' + id;
            var modal = $(this);
            modal.find('#deleteForm').attr('action', action);
            var count = button.data('count');
            modal.find('#deleteModalHint').text(count > 0 ? count + ' Ticket(s) verlieren dadurch ihre Kategorie.' : '');
        });
    </script>
@endpush
