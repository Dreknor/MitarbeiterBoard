{{-- Status-, Prioritäts- und Kategorie-Badges eines Tickets --}}
@php
    $statusClass = ['open' => 'tkt-badge-blue', 'waiting' => 'tkt-badge-amber', 'closed' => 'tkt-badge-gray'][$ticket->status] ?? 'tkt-badge-gray';
    $statusIcon = ['open' => 'fa-circle-notch', 'waiting' => 'fa-hourglass-half', 'closed' => 'fa-check'][$ticket->status] ?? 'fa-circle';
    $priorityClass = ['high' => 'tkt-badge-red', 'medium' => 'tkt-badge-outline', 'low' => 'tkt-badge-outline'][$ticket->priority] ?? 'tkt-badge-outline';
    $priorityDot = ['high' => 'bg-red-500', 'medium' => 'bg-blue-500', 'low' => 'bg-slate-300'][$ticket->priority] ?? 'bg-slate-300';
@endphp
<span class="tkt-badge {{ $statusClass }}">
    <i class="fas {{ $statusIcon }} text-[10px]"></i>
    {{ $ticket->status_label }}@if($ticket->isWaiting() && $ticket->waiting_until) bis {{ $ticket->waiting_until->format('d.m.') }}@endif
</span>
@if($ticket->isWaitingOverdue())
    <span class="tkt-badge tkt-badge-red" title="Wartezeit abgelaufen"><i class="fas fa-exclamation-circle text-[10px]"></i> überfällig</span>
@endif
<span class="tkt-badge {{ $priorityClass }}" title="Priorität">
    <span class="tkt-dot {{ $priorityDot }}"></span> {{ ucfirst($ticket->priority_label) }}
</span>
@if($ticket->category && !($hideCategory ?? false))
    <span class="tkt-badge tkt-badge-violet" title="Kategorie"><i class="fas fa-tag text-[10px]"></i> {{ $ticket->category->name }}</span>
@endif
