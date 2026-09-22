@extends('layouts.app')

@push('css')
    @vite(['resources/css/dashboard.css'])
    <style>
        /* Markdown-Rendering-Stile für die Hilfe-Seite */
        .dashboard-hilfe-content h1 { font-size: 1.6rem; font-weight: 700; margin-top: 1.5rem; margin-bottom: 0.75rem; color: #111827; border-bottom: 2px solid #e5e7eb; padding-bottom: 0.4rem; }
        .dashboard-hilfe-content h2 { font-size: 1.3rem; font-weight: 700; margin-top: 1.75rem; margin-bottom: 0.5rem; color: #1f2937; }
        .dashboard-hilfe-content h3 { font-size: 1.1rem; font-weight: 600; margin-top: 1.5rem; margin-bottom: 0.4rem; color: #374151; }
        .dashboard-hilfe-content h4 { font-size: 1rem; font-weight: 600; margin-top: 1rem; margin-bottom: 0.3rem; color: #374151; }
        .dashboard-hilfe-content p  { margin-bottom: 0.75rem; line-height: 1.7; color: #374151; }
        .dashboard-hilfe-content ul { list-style: disc; padding-left: 1.5rem; margin-bottom: 0.75rem; }
        .dashboard-hilfe-content ol { list-style: decimal; padding-left: 1.5rem; margin-bottom: 0.75rem; }
        .dashboard-hilfe-content li { margin-bottom: 0.25rem; line-height: 1.65; color: #374151; }
        .dashboard-hilfe-content a  { color: #2563eb; text-decoration: underline; }
        .dashboard-hilfe-content a:hover { color: #1d4ed8; }
        .dashboard-hilfe-content strong { font-weight: 600; }
        .dashboard-hilfe-content em { font-style: italic; }
        .dashboard-hilfe-content code { background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 4px; padding: 0.15rem 0.4rem; font-size: 0.875em; font-family: monospace; color: #1f2937; }
        .dashboard-hilfe-content pre { background: #1e293b; border-radius: 8px; padding: 1rem 1.25rem; overflow-x: auto; margin-bottom: 1rem; }
        .dashboard-hilfe-content pre code { background: transparent; border: none; padding: 0; color: #e2e8f0; font-size: 0.85rem; }
        .dashboard-hilfe-content blockquote { border-left: 4px solid #3b82f6; background: #eff6ff; padding: 0.75rem 1rem; margin-bottom: 0.75rem; border-radius: 0 6px 6px 0; color: #1e40af; }
        .dashboard-hilfe-content blockquote p { margin-bottom: 0; color: inherit; }
        .dashboard-hilfe-content table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; font-size: 0.9rem; }
        .dashboard-hilfe-content thead { background: #f9fafb; }
        .dashboard-hilfe-content th { border: 1px solid #d1d5db; padding: 0.5rem 0.75rem; font-weight: 600; text-align: left; color: #374151; }
        .dashboard-hilfe-content td { border: 1px solid #d1d5db; padding: 0.45rem 0.75rem; color: #374151; }
        .dashboard-hilfe-content tr:nth-child(even) { background: #f9fafb; }
        .dashboard-hilfe-content hr { border: none; border-top: 1px solid #e5e7eb; margin: 1.5rem 0; }
    </style>
@endpush

@section('content')
<div class="dashboard-wrapper p-4 max-w-4xl mx-auto">

    {{-- Zurück-Link --}}
    <a href="{{ url('/') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-4 no-underline">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Zurück zum Dashboard
    </a>

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <div class="shrink-0 w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
            <span class="text-xl">❓</span>
        </div>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Hilfe – Dashboard</h1>
            <p class="text-sm text-gray-500">Anleitung zur Nutzung des neuen Dashboards</p>
        </div>
    </div>

    {{-- Inhaltsbereich --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 md:p-8">
        <div class="dashboard-hilfe-content">
            {!! $html !!}
        </div>
    </div>

</div>
@endsection

