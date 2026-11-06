@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Systemlogs</h4>
                    </div>
                    <div class="card-body">

                        <div class="row align-items-center mb-2">
                            <div class="col-12 col-md-2">
                                <h6 class="mb-1">Level:</h6>
                            </div>
                            <div class="col">
                                <a href="{{ route('logs.set_filter', ['filter' => 'all']) }}"
                                   class="badge {{ $activeLevel ? 'badge-secondary' : 'badge-primary' }} text-uppercase p-2 mb-1">Alle</a>
                                @foreach($colors as $level => $color)
                                    <a href="{{ route('logs.set_filter', ['filter' => $level]) }}"
                                       class="badge {{ $activeLevel === $level ? 'badge-primary' : 'badge-'.$color }} text-uppercase p-2 mb-1 border">
                                        {{ ucfirst(strtolower($level)) }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                        <div class="row align-items-center">
                            <div class="col-12 col-md-2">
                                <h6 class="mb-1">Kategorie:</h6>
                            </div>
                            <div class="col">
                                <a href="{{ route('logs.set_category', ['category' => 'all']) }}"
                                   class="badge {{ $activeCategory ? 'badge-secondary' : 'badge-primary' }} p-2 mb-1">Alle</a>
                                @foreach($categories as $key => $category)
                                    <a href="{{ route('logs.set_category', ['category' => $key]) }}"
                                       class="badge {{ $activeCategory === $key ? 'badge-primary' : 'badge-light' }} p-2 mb-1 border">
                                        <i class="{{ $category['icon'] }}"></i> {{ $category['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                        @if($activeLevel || $activeCategory)
                            <div class="small text-muted mt-2">
                                Aktiver Filter:
                                @if($activeLevel) Level <strong>{{ ucfirst(strtolower($activeLevel)) }}</strong>@endif
                                @if($activeLevel && $activeCategory) · @endif
                                @if($activeCategory) Kategorie <strong>{{ $categories[$activeCategory]['label'] }}</strong>@endif
                                – {{ $logs->total() }} Einträge
                            </div>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="table">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th style="width: 30px"></th>
                                    <th>Zeitstempel</th>
                                    <th>Level</th>
                                    <th>Kategorie</th>
                                    <th>Nachricht</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($logs as $log)
                                    <tr class="log-header table-{{ $colors[$log->level_name] ?? 'light' }} "
                                        data-log-id="{{ $log->id }}">
                                        <td>
                                            <i class="fas fa-chevron-down toggle-icon"></i>
                                        </td>
                                        <td>{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
                                        <td>
                                            <span class="badge badge-{{ $colors[$log->level_name] ?? 'light' }} text-uppercase">
                                                {{ ucfirst(strtolower($log->level_name)) }}
                                            </span>
                                        </td>
                                        @php($logCategory = \App\Support\LogCategories::categorize($log->message))
                                        <td class="text-nowrap">
                                            <span class="badge badge-light border">
                                                <i class="{{ $categories[$logCategory]['icon'] }}"></i>
                                                {{ $categories[$logCategory]['label'] }}
                                            </span>
                                        </td>
                                        <td>{{ $log->message }}</td>
                                    </tr>
                                    <tr class="details-row d-none" id="details{{ $log->id }}">
                                        <td colspan="5" class="border-top-0">
                                                @if($log->context)
                                                    <div class="mb-3  text-wrap">
                                                        <strong class="d-block mb-2">Kontext Details:</strong>
                                                        <pre class="bg-white p-3 rounded mb-0  text-wrap"><code class="text-wrap">{{ is_array($log->context) ? json_encode($log->context, JSON_PRETTY_PRINT) : $log->context }}</code></pre>
                                                    </div>
                                                @endif

                                                @if($log->file || $log->line)
                                                    <div>
                                                        <strong class="d-block mb-2">Datei:</strong>
                                                        <div class="text-monospace  text-wrap" style="max-width: 100%;">
                                                            {{ $log->file ?? 'N/A' }}
                                                            {{ $log->file }}:{{ $log->line }}
                                                        </div>
                                                    </div>
                                                @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">Keine Logs vorhanden</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                            <div class="d-flex justify-content-center mt-4">
                                {{ $logs->links() }}
                            </div>
                    </div>
                    <div class="card-footer">
                        <form id="downloadForm" action="{{ route('logs.download') }}" method="get" class="px-3 py-2">
                            <div class="form-group">
                                <label for="start_date">Von</label>
                                <input type="date" class="form-control form-control-sm" id="start_date" name="start_date">
                            </div>
                            <div class="form-group">
                                <label for="end_date">Bis</label>
                                <input type="date" class="form-control form-control-sm" id="end_date" name="end_date">
                            </div>
                            <div class="form-group">
                                <label for="level">Level</label>
                                <select class="custom-select form-control-sm" id="level" name="level">
                                    <option value="">Alle</option>
                                    <option value="emergency">Emergency</option>
                                    <option value="error">Error</option>
                                    <option value="warning">Warning</option>
                                    <option value="info">Info</option>
                                    <option value="debug">Debug</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="category">Kategorie</label>
                                <select class="custom-select form-control-sm" id="category" name="category">
                                    <option value="">Alle</option>
                                    @foreach($categories as $key => $category)
                                        <option value="{{ $key }}" @selected($activeCategory === $key)>{{ $category['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm btn-block">
                                Download starten
                            </button>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection


@push('js')
    <script>
        $(document).ready(function() {
            $('.log-header').click(function() {
                const logId = $(this).data('log-id');
                const detailsRow = $('#details' + logId);
                const allDetailsRows = $('.details-row');
                const allHeaders = $('.log-header');

                if (detailsRow.hasClass('d-none')) {
                    allDetailsRows.addClass('d-none');
                    allHeaders.removeClass('expanded');

                    detailsRow.removeClass('d-none');
                    $(this).addClass('expanded');
                } else {
                    detailsRow.addClass('d-none');
                    $(this).removeClass('expanded');
                }
            });
        });
    </script>
@endpush
