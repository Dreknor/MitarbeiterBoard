<div class="p-6 h-full flex flex-col">
    {{-- Header --}}
    <div class="flex items-start justify-between mb-4">
        <h2 class="text-xl font-bold text-gray-900 leading-tight" x-text="selectedPosition?.name"></h2>
        <button @click="closePanel()"
                class="text-gray-400 hover:text-gray-600 text-2xl leading-none ml-3">×</button>
    </div>

    {{-- Farb-Indikator --}}
    <div class="w-8 h-1 rounded-full mb-4"
         :style="selectedPosition?.color ? 'background-color:' + selectedPosition.color : 'background-color:#e5e7eb'"></div>

    {{-- Stelleninhaber --}}
    <div class="mb-4">
        <p class="text-xs text-gray-400 uppercase font-semibold mb-2">Stelleninhaber</p>
        <template x-if="selectedPosition?.users?.length > 0">
            <div class="space-y-2">
                <template x-for="user in selectedPosition.users" :key="user.id">
                    <div class="flex items-center gap-3 p-2 rounded-lg bg-gray-50">
                        <template x-if="user.hasConsent_foto_organigramm && user.avatar">
                            <img :src="user.avatar" :alt="user.name"
                                 class="w-8 h-8 rounded-full object-cover">
                        </template>
                        <template x-if="!user.hasConsent_foto_organigramm || !user.avatar">
                            <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-semibold text-sm"
                                 x-text="user.name.charAt(0)"></div>
                        </template>
                        <div>
                            <p class="font-medium text-sm text-gray-900" x-text="user.name"></p>
                            <p class="text-xs text-gray-400" x-text="user.email"></p>
                        </div>
                        <a :href="'mailto:' + user.email" class="ml-auto text-blue-500 hover:text-blue-700 text-xs">✉</a>
                    </div>
                </template>
            </div>
        </template>
        <template x-if="!selectedPosition?.users?.length">
            <p class="text-sm text-gray-400 italic">Keine Stelleninhaber</p>
        </template>
    </div>

    {{-- Stellvertreter --}}
    <template x-if="selectedPosition?.deputy?.length > 0">
        <div class="mb-4">
            <p class="text-xs text-gray-400 uppercase font-semibold mb-2">Stellvertreter</p>
            <template x-for="d in selectedPosition.deputy" :key="d.id">
                <div class="flex items-center gap-2 p-2 rounded-lg bg-gray-50">
                    <div class="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 text-xs"
                         x-text="d.name.charAt(0)"></div>
                    <span class="text-sm text-gray-700" x-text="d.name"></span>
                </div>
            </template>
        </div>
    </template>

    {{-- Leitung-Markierung --}}
    <template x-if="selectedPosition?.leadership">
        <div class="mb-4">
            <span class="badge-yellow">★ Führungsposition</span>
        </div>
    </template>

    {{-- Admin-Aktionen --}}
    @can('manage orgchart')
    <div class="mt-auto pt-4 border-t border-gray-100">
        <a :href="'/personal/orgchart/positions/' + selectedPosition?.id + '/edit'"
           class="btn-personal-secondary text-sm w-full text-center block mb-2">
            ✏️ Position bearbeiten
        </a>
    </div>
    @endcan
</div>

