<div class="p-4 animate-pulse">
    {{-- Tabellen-Header --}}
    <div class="flex gap-2 mb-2">
        <div class="h-3 bg-gray-200 rounded w-1/4"></div>
        <div class="h-3 bg-gray-200 rounded w-1/4"></div>
        <div class="h-3 bg-gray-200 rounded w-1/4"></div>
        <div class="h-3 bg-gray-200 rounded w-1/4"></div>
    </div>
    {{-- Tabellenzeilen 3×4 --}}
    @for($row = 0; $row < 3; $row++)
    <div class="flex gap-2 mb-2">
        <div class="h-8 bg-gray-100 rounded w-1/4"></div>
        <div class="h-8 bg-gray-100 rounded w-1/4"></div>
        <div class="h-8 bg-gray-100 rounded w-1/4"></div>
        <div class="h-8 bg-gray-100 rounded w-1/4"></div>
    </div>
    @endfor
</div>

