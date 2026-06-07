@extends('layouts.app')

@push('css')
    @vite('resources/css/themes.css')
@endpush

@section('content')
<div class="theme-wrapper">

    <div class="thm-card mb-6">
        <div class="thm-band thm-band-amber">
            <h1 class="thm-page-title text-xl font-bold"><i class="fas fa-box-archive mr-1"></i> Themenspeicher</h1>
        </div>
        @can('create themes')
            <div class="p-5">
                <a href="{{ url(request()->segment(1).'/themes/create/speicher') }}" class="thm-btn thm-btn-primary w-full">
                    <i class="fas fa-plus"></i> Neues Thema
                </a>
            </div>
        @endcan
    </div>

    @if (count($themes) == 0)
        <div class="thm-card p-8 text-center text-gray-500">
            <i class="far fa-folder-open text-3xl text-gray-300 mb-3 block"></i>
            Es gibt keine gemerkten Themen.
        </div>
    @else
        <div class="thm-card overflow-x-auto">
            <table class="thm-table">
                <thead>
                    <tr>
                        <th>Von</th>
                        <th>Thema</th>
                        <th class="hidden md:table-cell">Datum</th>
                        <th class="text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($themes as $theme)
                        <tr id="{{ $theme->id }}"
                            class="{{ $theme->protocols->where('created_at', '>', \Carbon\Carbon::now()->startOfDay())->count() > 0 ? 'thm-row-protokoll' : '' }}">
                            <td class="text-sm text-gray-600">{{ $theme->ersteller->name }}</td>
                            <td class="font-semibold text-gray-900">{{ $theme->theme }}</td>
                            <td class="hidden md:table-cell text-sm text-gray-600 whitespace-nowrap">{{ $theme->date->format('d.m.Y') }}</td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ url(request()->segment(1).'/themes/'.$theme->id) }}" class="thm-btn thm-btn-secondary thm-btn-sm">
                                    <i class="far fa-eye"></i> <span class="hidden sm:inline">zeigen</span>
                                </a>
                                <a href="{{ url(request()->segment(1).'/themes/'.$theme->id.'/activate') }}" class="thm-btn thm-btn-success thm-btn-sm">
                                    <i class="far fa-arrow-alt-circle-up"></i> aktivieren
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@stop

@push('js')

@endpush
