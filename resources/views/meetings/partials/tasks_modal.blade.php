<!-- Modal für Meeting-Aufgaben -->
<div class="modal fade" id="meetingTasksModal" tabindex="-1" aria-labelledby="meetingTasksModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="meetingTasksModalLabel">Aufgaben für dieses Meeting</h5>
      </div>
      <div class="modal-body">
        <!-- Aufgabenliste -->
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>Mitarbeiter</th>
              <th>Rolle</th>
              <th>Notizen</th>
              <th>Aktionen</th>
            </tr>
          </thead>
          <tbody>
            @foreach($meeting->meetingTasks as $task)
              <tr>
                <td>
                  <form action="{{ route('meetings.tasks.update', ['group' => $group->name, 'meeting' => $meeting->id, 'task' => $task->id]) }}" method="POST" class="d-flex align-items-center gap-2">
                    @csrf
                    @method('PUT')
                    <select name="user_id" class="form-select form-select-sm" style="width: 140px;">
                      @foreach($group->users as $user)
                        <option value="{{ $user->id }}" @if($task->user_id == $user->id) selected @endif>{{ $user->name }}</option>
                      @endforeach
                    </select>
                </td>
                <td>
                    <input type="text" name="role" value="{{ $task->role }}" class="form-control form-control-sm" style="width: 120px;">
                </td>
                <td>
                    <input type="text" name="notes" value="{{ $task->notes }}" class="form-control form-control-sm" style="width: 160px;">
                </td>
                <td>
                    <button type="submit" class="btn btn-success btn-sm">Speichern</button>
                  </form>
                  <form action="{{ route('meetings.tasks.delete', ['group' => $group->name, 'meeting' => $meeting->id, 'task' => $task->id]) }}" method="POST" style="display:inline-block">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Wirklich löschen?')">Löschen</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="modal-footer">
        <form action="{{ route('meetings.tasks.add', ['group' => $group->name, 'meeting' => $meeting->id]) }}" method="POST">
          @csrf
          <div class="row align-items-end">
            <div class="col-md-4">
              <label for="user_id" class="form-label">Mitarbeiter</label>
              <select name="user_id" id="user_id" class="custom-select" required>
                <option value="">Bitte wählen</option>
                @foreach($group->users as $user)
                  <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3">
              <label for="role" class="form-label">Rolle</label>
              <input type="text" name="role" id="role" class="form-control" placeholder="z.B. Protokollant" required>
            </div>
            <div class="col-md-4">
              <label for="notes" class="form-label">Notizen</label>
              <input type="text" name="notes" id="notes" class="form-control" placeholder="optional">
            </div>
          </div>
            <div class="row mt-3">
                <div class="col-md-1">
                  <button type="submit" class="btn btn-primary">Hinzufügen</button>
                </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Schließen</button>
      </div>
    </div>
  </div>
</div>
