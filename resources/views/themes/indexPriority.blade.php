@extends('layouts.app')

@push('css')
    @vite('resources/css/themes.css')
@endpush

@section('content')
<div class="theme-wrapper">

    <div class="thm-card thm-card-visible mb-6">
        <div class="p-5">
            @include('themes.element.header')
        </div>
        @can('create themes')
            <div class="px-5 pb-5">
                <a href="{{ url(request()->segment(1).'/themes/create') }}" class="thm-btn thm-btn-primary w-full">
                    <i class="fas fa-plus"></i> Neues Thema
                </a>
            </div>
        @endcan
    </div>

    @if (count($themes) == 0)
        <div class="thm-card p-8 text-center text-gray-500">
            <i class="far fa-folder-open text-3xl text-gray-300 mb-3 block"></i>
            Es gibt keine offenen Themen.
        </div>
    @else
        <div class="thm-card">
            <div class="thm-band thm-band-blue">
                <h2 class="text-lg font-bold"><i class="fas fa-sort-amount-down mr-1"></i> Nach Priorität</h2>
            </div>
            <div class="p-4 overflow-x-auto">
                <table class="thm-table">
                    <thead>
                        <tr>
                            <th>Von</th>
                            <th>Thema</th>
                            <th class="hidden md:table-cell">Datum</th>
                            <th class="hidden md:table-cell">Typ</th>
                            @if($group->hasAllocations)<th>Zugewiesen</th>@endif
                            <th class="hidden md:table-cell w-40">Priorität</th>
                            <th class="text-right">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($themes as $theme)
                            <tr id="{{ $theme->id }}" data-priority="{{ $theme->priority }}"
                                class="{{ $theme->protocols->where('created_at', '>', \Carbon\Carbon::now()->startOfDay())->count() > 0 ? 'thm-row-protokoll' : '' }} {{ $theme->zugewiesen_an?->id === auth()->id() ? 'thm-row-assigned' : '' }}">
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span class="thm-avatar" title="{{ $theme->ersteller->name }}">
                                            @if($theme->ersteller->getMedia('profile')->count() != 0)
                                                <img src="{{ $theme->ersteller->photo() }}" alt="{{ $theme->ersteller->name }}">
                                            @else
                                                {{ \Illuminate\Support\Str::of($theme->ersteller->name)->explode(' ')->map(fn($p)=>\Illuminate\Support\Str::substr($p,0,1))->take(2)->implode('') }}
                                            @endif
                                        </span>
                                        <span class="text-sm text-gray-600 hidden sm:inline">{{ $theme->ersteller->name }}</span>
                                    </div>
                                </td>
                                <td class="font-semibold text-gray-900">{{ $theme->theme }}</td>
                                <td class="hidden md:table-cell text-sm text-gray-600 whitespace-nowrap">{{ $theme->date->format('d.m.Y') }}</td>
                                <td class="hidden md:table-cell"><span class="thm-badge thm-badge-blue">{{ $theme->type->type }}</span></td>
                                @if($group->hasAllocations)
                                    <td>@if($theme->zugewiesen_an != null)<span class="thm-badge thm-badge-amber">{{ $theme->zugewiesen_an?->name }}</span>@endif</td>
                                @endif
                                <td id="priority_{{ $theme->id }}" class="hidden md:table-cell">
                                    @if ($theme->priorities->where('creator_id', auth()->id())->first())
                                        <div class="thm-progress"><span style="width: {{ 100-$theme->priority }}%"></span></div>
                                    @else
                                        <input type="range" id="theme_{{ $theme->id }}" min="1" max="100" value="0" data-theme="{{ $theme->id }}" title="Priorität festlegen">
                                    @endif
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <a href="{{ url(request()->segment(1).'/themes/'.$theme->id) }}" class="thm-btn thm-btn-secondary thm-btn-sm">
                                        <i class="far fa-eye"></i> <span class="hidden sm:inline">zeigen</span>
                                    </a>
                                    <a href="{{ url(request()->segment(1).'/protocols/'.$theme->id) }}" class="thm-btn thm-btn-secondary thm-btn-sm hidden md:inline-flex">
                                        <i class="far fa-sticky-note"></i> Protokoll
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@stop

@push('js')
    @vite('resources/js/themes.js')
@endpush
