{{-- Benachrichtigungen-Card – nur Body-Inhalt (cardWrapper übernimmt Header) --}}
{{-- Badge-Zähler wird per x-html eingefügt – hier nur der Body --}}
<div class="divide-y divide-gray-100">
    @forelse($notifications as $notification)
        <div class="flex items-start gap-3 px-4 py-3">
            <div class="shrink-0 w-7 h-7 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center mt-0.5">
                <i class="fas fa-bell text-xs"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm text-gray-800">
                    {{ $notification->data['message'] ?? $notification->data['subject'] ?? 'Neue Benachrichtigung' }}
                </div>
                <div class="text-xs text-gray-500 mt-0.5">
                    {{ $notification->created_at->diffForHumans() }}
                </div>
            </div>
        </div>
    @empty
        <div data-card-empty="true" class="px-4 py-8 text-center text-gray-400 text-sm">
            <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <p>Keine neuen Benachrichtigungen 🎉</p>
        </div>
    @endforelse
</div>

@if($notifications->isNotEmpty())
    {{-- "Alle als gelesen markieren"-Button --}}
    <div class="px-4 py-3 border-t border-gray-100">
        <button
            x-data
            @click="
                fetch('/notifications/mark-all-read', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(() => { $el.closest('[x-intersect\\.once]')?.setAttribute('data-loaded', '0'); location.reload(); })
            "
            class="w-full text-sm text-gray-500 hover:text-blue-600 py-1 text-center">
            Alle als gelesen markieren
        </button>
    </div>
@endif

