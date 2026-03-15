<div x-show="showIcalFeedModal"
     x-cloak
     class="fixed inset-0 z-[200] flex items-center justify-center bg-black/50"
     @click.self="showIcalFeedModal = false"
     @keydown.escape.window="showIcalFeedModal = false">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 p-6" @click.stop>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900">📡 iCal-Feed abonnieren</h2>
            <button type="button"
                    @click="showIcalFeedModal = false"
                    class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form action="{{ route('calendar.ical.store') }}" method="POST">
            @csrf

            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Name <span class="text-red-500">*</span>
                </label>
                <input type="text"
                       name="name"
                       required
                       maxlength="255"
                       placeholder="z. B. Mein Google-Kalender"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm
                              focus:ring-2 focus:ring-blue-300 focus:border-blue-300">
            </div>

            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Feed-URL <span class="text-red-500">*</span>
                </label>
                <input type="url"
                       name="url"
                       required
                       maxlength="2000"
                       placeholder="https://calendar.google.com/calendar/ical/..."
                       class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm
                              focus:ring-2 focus:ring-blue-300 focus:border-blue-300">
                <p class="text-xs text-gray-400 mt-1">
                    iCal-URL (*.ics) aus Google Calendar, Outlook, Nextcloud etc.
                </p>
            </div>

            <div class="mb-5">
                <label class="block text-sm font-medium text-gray-700 mb-1">Farbe</label>
                <input type="color"
                       name="farbe"
                       value="#6366f1"
                       class="calendar-color-input w-10 h-10 rounded border border-gray-300 cursor-pointer">
            </div>

            <div class="flex justify-end gap-2">
                <button type="button"
                        @click="showIcalFeedModal = false"
                        class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 border border-gray-300
                               rounded-md hover:bg-gray-50 transition-colors">
                    Abbrechen
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium
                               rounded-md transition-colors">
                    Feed abonnieren
                </button>
            </div>
        </form>
    </div>
</div>

