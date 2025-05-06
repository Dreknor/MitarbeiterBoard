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
                        <div class="table">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th style="width: 30px"></th>
                                    <th>Zeitstempel</th>
                                    <th>Level</th>
                                    <th>Nachricht</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($logs as $log)
                                    <tr class="log-header {{ $log->level === 'error' ? 'table-danger' : ($log->level === 'warning' ? 'table-warning' : '') }}"
                                        data-log-id="{{ $log->id }}">
                                        <td>
                                            <i class="fas fa-chevron-down toggle-icon"></i>
                                        </td>
                                        <td>{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
                                        <td>
                                            <span class="badge {{ $log->level === 'error' ? 'badge-danger' : ($log->level === 'warning' ? 'badge-warning' : 'badge-info') }}">
                                                {{ ucfirst($log->level) }}
                                            </span>
                                        </td>
                                        <td>{{ $log->message }}</td>
                                    </tr>
                                    <tr class="details-row d-none" id="details{{ $log->id }}">
                                        <td colspan="4" class="border-top-0">
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
                                        <td colspan="4" class="text-center">Keine Logs vorhanden</td>
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
                                    <option value="error">Error</option>
                                    <option value="warning">Warning</option>
                                    <option value="info">Info</option>
                                    <option value="debug">Debug</option>
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
