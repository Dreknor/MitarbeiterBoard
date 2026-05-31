{{-- Modal: Aufgaben & Rollen (Alpine – erwartet `showTasks` im umgebenden x-data) --}}
<div class="mtg-modal-backdrop" x-show="showTasks" x-transition.opacity
     @keydown.escape.window="showTasks = false" style="display:none;">
    <div class="mtg-modal mtg-modal-lg" @click.outside="showTasks = false">
        <div class="mtg-modal-header">
            <h3 class="mtg-modal-title">Aufgaben &amp; Rollen</h3>
            <button type="button" class="mtg-modal-close" @click="showTasks = false" aria-label="Schließen">&times;</button>
        </div>

        <div class="mtg-modal-body">
            @if($meeting->meetingTasks->count())
                <div class="space-y-2 mb-5">
                    @foreach($meeting->meetingTasks as $task)
                        <div class="flex flex-col sm:flex-row sm:items-end gap-2 p-3 rounded-xl border border-gray-100 bg-gray-50/60">
                            <form action="{{ route('meetings.tasks.update', ['group' => $group->name, 'meeting' => $meeting->id, 'task' => $task->id]) }}"
                                  method="POST" class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-2">
                                @csrf
                                @method('PUT')
                                <select name="user_id" class="mtg-select">
                                    @foreach($group->users as $user)
                                        <option value="{{ $user->id }}" @if($task->user_id == $user->id) selected @endif>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="role" value="{{ $task->role }}" class="mtg-input" placeholder="Rolle">
                                <input type="text" name="notes" value="{{ $task->notes }}" class="mtg-input" placeholder="Notizen">
                                <div class="sm:col-span-3 flex justify-end">
                                    <button type="submit" class="mtg-btn mtg-btn-success mtg-btn-sm">
                                        <i class="fas fa-save"></i> Speichern
                                    </button>
                                </div>
                            </form>
                            <form action="{{ route('meetings.tasks.delete', ['group' => $group->name, 'meeting' => $meeting->id, 'task' => $task->id]) }}"
                                  method="POST" onsubmit="return confirm('Wirklich löschen?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="mtg-btn-icon w-9 h-9 text-red-500 hover:bg-red-50" title="Löschen">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-400 italic mb-5">Noch keine Aufgaben/Rollen vergeben.</p>
            @endif

            <h4 class="text-sm font-semibold text-gray-900 mb-3">Neue Aufgabe / Rolle</h4>
            <form action="{{ route('meetings.tasks.add', ['group' => $group->name, 'meeting' => $meeting->id]) }}" method="POST"
                  class="grid grid-cols-1 sm:grid-cols-4 gap-2 items-end">
                @csrf
                <div class="sm:col-span-2">
                    <label class="mtg-label">Mitarbeiter</label>
                    <select name="user_id" class="mtg-select" required>
                        <option value="">Bitte wählen</option>
                        @foreach($group->users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mtg-label">Rolle</label>
                    <input type="text" name="role" class="mtg-input" placeholder="z. B. Protokollant" required>
                </div>
                <div>
                    <label class="mtg-label">Notizen</label>
                    <input type="text" name="notes" class="mtg-input" placeholder="optional">
                </div>
                <div class="sm:col-span-4 flex justify-end">
                    <button type="submit" class="mtg-btn mtg-btn-primary mtg-btn-sm">
                        <i class="fas fa-plus"></i> Hinzufügen
                    </button>
                </div>
            </form>
        </div>

        <div class="mtg-modal-footer">
            <button type="button" class="mtg-btn mtg-btn-secondary" @click="showTasks = false">Schließen</button>
        </div>
    </div>
</div>
