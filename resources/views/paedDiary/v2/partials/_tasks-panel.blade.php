{{--
    PaedDiary v2 – Tasks-Panel Partial
    Seitenpanel für offene Aufgaben und offene Notizen
--}}
<div class="col-lg-6 order-lg-5" x-data="taskPanel()" x-show="hasOpenItems" x-cloak>
    <div class="card mb-3">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <span class="font-weight-bold small">Offene Aufgaben</span>
            <button class="btn btn-link btn-sm p-0" @click="$store.diary.loadWeek()" title="Aktualisieren">
                <i class="fas fa-sync"></i>
            </button>
        </div>
        <div class="card-body p-2">
            <template x-for="group in itemsByStudent" :key="group.stuId">
                <div class="mb-3">
                    <strong class="small d-block border-bottom mb-1" x-text="group.name"></strong>

                    <template x-for="item in group.items" :key="item.id">
                        <div>
                            {{-- Offene Notiz --}}
                            <template x-if="item.is_entry">
                                <div class="d-flex justify-content-between align-items-start text-info mb-1 open-entry-large">
                                    <div class="flex-grow-1">
                                        <i class="fas fa-comment-alt mr-1"></i>
                                        <span x-text="trimText(item.title, 60)"></span>
                                        <template x-if="item.user">
                                            <span class="text-muted small" x-text="'(' + item.user + ')'"></span>
                                        </template>
                                    </div>
                                    <button class="diary-btn diary-btn-complete ml-2" title="Notiz abschließen"
                                            @click="completeEntryFromPanel(item.entry_id, group.stuId)">✔</button>
                                </div>
                            </template>

                            {{-- Aufgabe --}}
                            <template x-if="!item.is_entry">
                                <div class="d-flex justify-content-between align-items-start mb-1"
                                     :class="{ 'text-danger font-weight-bold': item.highlighted }">
                                    <div class="flex-grow-1">
                                        <i class="fas fa-tasks mr-1"></i>
                                        <span x-text="item.title"></span>
                                        <template x-if="item.due_date">
                                            <span class="text-muted small ml-1"
                                                  x-text="'(' + (new Date(item.due_date) < new Date() ? 'Fällig: ' : '') + new Date(item.due_date).toLocaleDateString('de-DE') + ')'"></span>
                                        </template>
                                    </div>
                                    <div class="ml-2">
                                        <button class="diary-btn diary-btn-edit" title="Aufgabe bearbeiten"
                                                @click="openEditModal(item)">✎</button>
                                        <button class="diary-btn diary-btn-complete" title="Aufgabe ausblenden"
                                                @click="closeTask(item.id)">✕</button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>

