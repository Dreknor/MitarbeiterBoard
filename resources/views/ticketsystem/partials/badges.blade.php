{{-- Status-, Prioritäts- und Kategorie-Badges eines Tickets --}}
@php
    $statusClass = ['open' => 'badge-primary', 'waiting' => 'badge-warning', 'closed' => 'badge-secondary'][$ticket->status] ?? 'badge-light';
    $priorityClass = ['high' => 'badge-danger', 'medium' => 'badge-info', 'low' => 'badge-light border'][$ticket->priority] ?? 'badge-light';
@endphp
<span class="badge {{ $statusClass }}" title="Status">
    {{ $ticket->status_label }}@if($ticket->isWaiting() && $ticket->waiting_until) bis {{ $ticket->waiting_until->format('d.m.') }}@endif
</span>
@if($ticket->isWaitingOverdue())
    <span class="badge badge-danger" title="Wartezeit abgelaufen"><i class="fa fa-clock"></i> überfällig</span>
@endif
<span class="badge {{ $priorityClass }}" title="Priorität">{{ $ticket->priority_label }}</span>
@if($ticket->category)
    <span class="badge badge-light border" title="Kategorie">{{ $ticket->category->name }}</span>
@endif
