<div class="p-4 animate-pulse">
    {{-- 3 Stat-Kästchen --}}
    <div class="flex gap-3 mb-4">
        @for($i = 0; $i < 3; $i++)
        <div class="flex-1 bg-gray-100 rounded-xl p-3 space-y-2">
            <div class="h-6 bg-gray-200 rounded w-2/3 mx-auto"></div>
            <div class="h-3 bg-gray-200 rounded w-1/2 mx-auto"></div>
        </div>
        @endfor
    </div>
    {{-- Beschreibungstext --}}
    <div class="space-y-2">
        <div class="h-3 bg-gray-100 rounded w-full"></div>
        <div class="h-3 bg-gray-100 rounded w-5/6"></div>
        <div class="h-3 bg-gray-100 rounded w-4/6"></div>
    </div>
</div>

