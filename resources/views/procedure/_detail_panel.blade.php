{{-- Detail-Panel – Slide-in von rechts (innerhalb procedureTree-Komponente) --}}
<div class="p-5 h-full flex flex-col min-h-0" x-show="selectedStep">

    {{-- Header --}}
    <div class="flex items-start justify-between mb-3 shrink-0">
        <div class="flex-1 min-w-0 pr-3">
            <h2 class="text-lg font-bold text-gray-900 leading-tight" x-text="selectedStep?.name"></h2>
            <template x-if="selectedStep?.position">
                <p class="text-xs text-gray-400 mt-0.5" x-text="selectedStep?.position?.name"></p>
            </template>
        </div>
        <button @click="closePanel()"
                class="text-gray-400 hover:text-gray-600 text-2xl leading-none shrink-0">×</button>
    </div>

    {{-- Status --}}
    <div class="mb-3 shrink-0">
        <template x-if="selectedStep?.done">
            <span class="badge-step-done">
                ✓ Erledigt
                <span x-text="selectedStep?.completedAt ? ' am ' + selectedStep.completedAt : ''"></span>
            </span>
        </template>
        <template x-if="!selectedStep?.done && isOverdueStep(selectedStep)">
            <span class="badge-step-overdue">
                Überfällig · <span x-text="selectedStep?.endDateFormatted"></span>
            </span>
        </template>
        <template x-if="!selectedStep?.done && !isOverdueStep(selectedStep) && isDueSoonStep(selectedStep)">
            <span class="badge-step-due">
                Bald fällig · <span x-text="selectedStep?.endDateFormatted"></span>
            </span>
        </template>
        <template x-if="!selectedStep?.done && !isOverdueStep(selectedStep) && !isDueSoonStep(selectedStep) && selectedStep?.endDateFormatted">
            <span class="badge-step-active">
                Fällig: <span x-text="selectedStep?.endDateFormatted"></span>
            </span>
        </template>
        <template x-if="!selectedStep?.done && !selectedStep?.endDateFormatted">
            <span class="badge-step-open">Offen – kein Datum</span>
        </template>
    </div>

    {{-- Panel Tabs --}}
    <div class="flex gap-0 border-b border-gray-100 mb-4 shrink-0">
        <button @click="panelTab='info'"
                :class="panelTab==='info' ? 'procedure-tab procedure-tab-active' : 'procedure-tab procedure-tab-inactive'">
            Info
        </button>
        <button @click="panelTab='comments'; loadComments(selectedStep?.id)"
                :class="panelTab==='comments' ? 'procedure-tab procedure-tab-active' : 'procedure-tab procedure-tab-inactive'">
            Kommentare
            <template x-if="comments.length > 0">
                <span class="ml-1 bg-blue-100 text-blue-700 text-xs rounded-full px-1.5 py-0.5 font-medium" x-text="comments.length"></span>
            </template>
        </button>
        <button @click="panelTab='history'; loadHistory(selectedStep?.id)"
                :class="panelTab==='history' ? 'procedure-tab procedure-tab-active' : 'procedure-tab procedure-tab-inactive'">
            Verlauf
        </button>
    </div>

    {{-- ── Info Tab ──────────────────────────────────────── --}}
    <div x-show="panelTab === 'info'" class="flex-1 overflow-y-auto flex flex-col min-h-0">

        {{-- Beschreibung --}}
        <template x-if="selectedStep?.description">
            <div class="mb-4">
                <p class="text-xs text-gray-400 uppercase font-semibold mb-1 tracking-wide">Beschreibung</p>
                <p class="text-sm text-gray-700 break-words" x-text="selectedStep?.description"></p>
            </div>
        </template>

        {{-- Verantwortliche --}}
        <div class="mb-4">
            <p class="text-xs text-gray-400 uppercase font-semibold mb-2 tracking-wide">Verantwortliche</p>
            <template x-if="selectedStep?.users?.length > 0">
                <div class="space-y-1">
                    <template x-for="user in selectedStep.users" :key="user.id">
                        <div class="flex items-center gap-2 p-2 rounded-lg bg-gray-50">
                            <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-700 text-xs font-bold flex items-center justify-center shrink-0"
                                 x-text="user.initial"></div>
                            <span class="text-sm text-gray-800 flex-1" x-text="user.name"></span>
                            <template x-if="selectedStep?.canEdit && !selectedStep?.done">
                                <form :action="selectedStep?.removeUserBase + '/' + user.id" method="post" class="inline">
                                    <input type="hidden" name="_token" :value="csrfToken">
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit"
                                            class="text-gray-400 hover:text-red-500 text-sm px-1"
                                            title="Entfernen">×</button>
                                </form>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
            <template x-if="!selectedStep?.users?.length">
                <p class="text-sm text-gray-400 italic">Keine Verantwortlichen zugewiesen</p>
            </template>
        </div>

        {{-- Person zuweisen --}}
        <template x-if="selectedStep?.canEdit && !selectedStep?.done">
            <div class="mb-4" x-data="{openAssign: false}">
                <button type="button" @click="openAssign = !openAssign"
                        class="btn-procedure-secondary text-xs w-full">
                    <span x-text="openAssign ? '✕ Abbrechen' : '+ Person zuweisen'"></span>
                </button>
                <template x-if="openAssign">
                    <form :action="selectedStep?.addUserUrl" method="post" class="flex gap-2 mt-2">
                        <input type="hidden" name="_token" :value="csrfToken">
                        <input type="hidden" name="step" :value="selectedStep?.id">
                        <select name="person_id" class="input-procedure text-xs flex-1">
                            <option value=""></option>
                            @foreach($users ?? [] as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn-procedure-primary text-xs px-3">Zuweisen</button>
                    </form>
                </template>
            </div>
        </template>

        {{-- Aktionen --}}
        <div class="mt-auto pt-4 border-t border-gray-100 space-y-2 shrink-0">

            {{-- Schritt erledigen (AJAX, Phase 3) --}}
            <template x-if="!selectedStep?.done">
                <button @click="completeStep()"
                        :disabled="completingStep"
                        class="btn-procedure-success text-sm w-full"
                        :class="{'opacity-50 cursor-not-allowed': completingStep}"
                        x-text="completingStep ? 'Wird gespeichert…' : '✓ Als erledigt markieren'">
                </button>
            </template>

            {{-- Wieder öffnen (AJAX, Phase 3, nur manage) --}}
            <template x-if="selectedStep?.done && selectedStep?.canEdit">
                <button @click="reopenStep()"
                        :disabled="reopeningStep"
                        class="btn-procedure-secondary text-sm w-full"
                        :class="{'opacity-50 cursor-not-allowed': reopeningStep}"
                        x-text="reopeningStep ? 'Wird geöffnet…' : '↩ Schritt wieder öffnen'">
                </button>
            </template>

            {{-- Schritt löschen --}}
            <template x-if="selectedStep?.canEdit && !selectedStep?.done">
                <form :action="selectedStep?.deleteUrl" method="post"
                      @submit.prevent="if(confirm('Schritt »' + selectedStep.name + '« wirklich löschen?')) { $el.submit(); }">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="btn-procedure-secondary text-sm w-full"
                            style="color:#dc2626;">🗑 Schritt löschen</button>
                </form>
            </template>

            {{-- Unterschritt hinzufügen --}}
            <template x-if="selectedStep?.canEdit">
                <button @click="openAddStep(selectedStep?.id); closePanel();"
                        class="btn-procedure-secondary text-sm w-full">
                    + Unterschritt hinzufügen
                </button>
            </template>
        </div>
    </div>

    {{-- ── Kommentare Tab ─────────────────────────────────── --}}
    <div x-show="panelTab === 'comments'" class="flex-1 overflow-y-auto flex flex-col min-h-0">

        <template x-if="commentsLoading">
            <div class="flex items-center justify-center py-8">
                <div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
            </div>
        </template>

        <div class="space-y-3 flex-1 overflow-y-auto mb-4">
            <template x-if="!commentsLoading && comments.length === 0">
                <p class="text-xs text-gray-400 italic text-center py-6">Noch keine Kommentare.</p>
            </template>
            <template x-for="c in comments" :key="c.id">
                <div class="bg-gray-50 rounded-xl p-3">
                    <div class="flex items-center gap-2 mb-1.5">
                        <div class="w-6 h-6 rounded-full bg-blue-100 text-blue-700 text-xs font-bold flex items-center justify-center shrink-0"
                             x-text="c.user?.name?.charAt(0) ?? '?'"></div>
                        <span class="text-xs font-semibold text-gray-700 flex-1" x-text="c.user?.name ?? 'Unbekannt'"></span>
                        <span class="text-xs text-gray-400" x-text="c.created_at_formatted"></span>
                        <template x-if="c.canDelete">
                            <button @click="deleteComment(selectedStep?.id, c.id)"
                                    class="text-gray-400 hover:text-red-500 text-xs ml-1" title="Löschen">🗑</button>
                        </template>
                    </div>
                    <p class="text-sm text-gray-700 break-words whitespace-pre-wrap" x-text="c.body"></p>
                </div>
            </template>
        </div>

        {{-- Neuer Kommentar --}}
        <div class="border-t border-gray-100 pt-3 shrink-0">
            <textarea x-model="newComment"
                      class="input-procedure text-sm mb-2 w-full"
                      rows="3"
                      placeholder="Kommentar eingeben… (Strg+Enter zum Speichern)"
                      @keydown.ctrl.enter="addComment(selectedStep?.id)"></textarea>
            <button @click="addComment(selectedStep?.id)"
                    :disabled="submittingComment || !newComment.trim()"
                    class="btn-procedure-primary text-xs w-full"
                    :class="{'opacity-50 cursor-not-allowed': submittingComment || !newComment.trim()}"
                    x-text="submittingComment ? 'Wird gespeichert…' : 'Speichern & benachrichtigen'">
            </button>
        </div>
    </div>

    {{-- ── Verlauf Tab ─────────────────────────────────────── --}}
    <div x-show="panelTab === 'history'" class="flex-1 overflow-y-auto min-h-0">

        <template x-if="historyLoading">
            <div class="flex items-center justify-center py-8">
                <div class="w-6 h-6 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
            </div>
        </template>

        <template x-if="!historyLoading && historyItems.length === 0">
            <p class="text-xs text-gray-400 italic text-center py-6">Kein Verlauf vorhanden.</p>
        </template>

        <div class="space-y-3">
            <template x-for="(item, idx) in historyItems" :key="idx">
                <div class="flex gap-3">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs shrink-0 mt-0.5"
                         :class="{
                             'bg-green-100 text-green-700': item.type === 'completed',
                             'bg-blue-100 text-blue-700':   item.type === 'comment'
                         }"
                         x-text="item.type === 'completed' ? '✓' : '💬'"></div>
                    <div class="flex-1 min-w-0">
                        <template x-if="item.type === 'completed'">
                            <p class="text-sm text-gray-700">
                                Erledigt von <strong x-text="item.by?.name ?? 'Unbekannt'"></strong>
                            </p>
                        </template>
                        <template x-if="item.type === 'comment'">
                            <div>
                                <p class="text-sm text-gray-700">
                                    <strong x-text="item.by?.name ?? 'Unbekannt'"></strong> kommentierte:
                                </p>
                                <p class="text-sm text-gray-500 italic break-words mt-0.5" x-text="item.body"></p>
                            </div>
                        </template>
                        <p class="text-xs text-gray-400 mt-0.5" x-text="formatDate(item.at)"></p>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

