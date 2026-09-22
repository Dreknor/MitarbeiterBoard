@extends('layouts.app')

@push('css')
    @vite('resources/css/themes.css')
@endpush

@section('content')
<div class="theme-wrapper">
    <div class="mb-4">
        @if($group->use_meetings)
            <a href="{{ url($group->name.'/meetings') }}" class="thm-btn thm-btn-secondary"><i class="fas fa-arrow-left"></i> Zurück</a>
        @else
            <a href="{{ url($group->name.'/themes#'.$date->format('Ymd')) }}" class="thm-btn thm-btn-secondary"><i class="fas fa-arrow-left"></i> Zurück zu den Themen</a>
        @endif
    </div>

    <div class="thm-card">
        <div class="thm-band thm-band-blue">
            <h1 class="text-xl font-bold"><i class="fas fa-user-check mr-1"></i> Anwesenheit zur Besprechung am {{ $date->format('d.m.Y') }}</h1>
        </div>

        @if($date->isToday())
            <form method="post" action="{{ url($group->name.'/presences/add') }}">
                @csrf
                <div class="p-5">
                    <ul class="space-y-2">
                        @foreach($users as $user)
                            <li class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-xl border border-gray-100 bg-gray-50/40">
                                <div class="flex items-center gap-2 font-medium text-gray-800">
                                    @if($user->getMedia('profile')->count() != 0)
                                        <span class="thm-avatar"><img src="{{ $user->photo() }}" alt="" title="{{ $user->name }}"></span>
                                    @endif
                                    {{ $user->name }}
                                </div>
                                <div class="flex flex-wrap gap-4 text-sm">
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" name="presence_{{ $user->id }}" value="presence" class="accent-emerald-600"
                                               @if($presences->where('user_id', $user->id)->where('presence', '1')->count() > 0) checked @endif>
                                        Anwesend
                                    </label>
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" name="presence_{{ $user->id }}" value="online" class="accent-blue-600"
                                               @if($presences->where('user_id', $user->id)->where('online', '1')->count() > 0) checked @endif>
                                        Online
                                    </label>
                                    <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" name="presence_{{ $user->id }}" value="excused" class="accent-red-600"
                                               @if($presences->where('user_id', $user->id)->where('excused', '1')->count() > 0) checked @endif>
                                        Entschuldigt
                                    </label>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <button type="submit" class="thm-btn thm-btn-primary w-full mt-4"><i class="fas fa-save"></i> Speichern</button>
                </div>
            </form>
        @else
            <div class="p-5">
                <ul class="space-y-2">
                    @foreach($users as $user)
                        <li class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-xl border border-gray-100 bg-gray-50/40">
                            <div class="flex items-center gap-2 font-medium text-gray-800">
                                @if($user->getMedia('profile')->count() != 0)
                                    <span class="thm-avatar"><img src="{{ $user->photo() }}" alt="" title="{{ $user->name }}"></span>
                                @endif
                                {{ $user->name }}
                            </div>
                            <div class="flex flex-wrap gap-4 text-sm">
                                @if($presences->where('user_id', $user->id)->where('presence', '1')->count() > 0)
                                    <span class="text-emerald-600"><i class="fas fa-check"></i> anwesend</span>
                                @endif
                                @if($presences->where('user_id', $user->id)->where('online', '1')->count() > 0)
                                    <span class="text-blue-600"><i class="fas fa-wifi"></i> online</span>
                                @endif
                                @if($presences->where('user_id', $user->id)->where('excused', '1')->count() > 0)
                                    <span class="text-red-600"><i class="fas fa-ban"></i> entschuldigt</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Gäste --}}
        <div class="px-5 py-4 border-t border-gray-100 bg-gray-50">
            <h2 class="thm-section-title mb-2">Gäste</h2>
            <ul class="space-y-2">
                @foreach($presences->filter(fn($presence) => $presence->guest_name != null) as $guest)
                    <li class="flex items-center justify-between gap-2 p-2 rounded-lg border border-gray-100 bg-white">
                        <span class="text-gray-800">{{ $guest->guest_name }}</span>
                        @if($date->isToday())
                            <a href="{{ url($group->name.'/presences/'.$guest->id.'/deleteGuest') }}" class="thm-btn-icon w-8 h-8 text-red-500 hover:bg-red-50" title="Gast entfernen">
                                <i class="fas fa-trash"></i>
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if($date->isToday())
                <form method="post" action="{{ url($group->name.'/presences/addGuest') }}" class="mt-3 flex flex-col sm:flex-row gap-2">
                    @csrf
                    <input type="text" name="guest_name" class="thm-input flex-1" placeholder="Name des Gastes">
                    <button type="submit" class="thm-btn thm-btn-primary"><i class="fas fa-plus"></i> Gast hinzufügen</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
