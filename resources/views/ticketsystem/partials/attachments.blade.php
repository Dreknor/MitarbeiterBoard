{{-- Liste von Anhängen (Media) mit Download über die geprüfte Ticket-Route --}}
@if($files->isNotEmpty())
    <ul class="grid gap-2 sm:grid-cols-2 {{ $class ?? '' }}">
        @foreach($files as $file)
            @php
                $icon = match (true) {
                    str_starts_with((string) $file->mime_type, 'image/') => 'fa-file-image',
                    $file->mime_type === 'application/pdf' => 'fa-file-pdf',
                    default => 'fa-file',
                };
            @endphp
            <li>
                <a href="{{ route('tickets.files', [$ticket, $file]) }}" target="_blank" rel="noopener" class="tkt-file">
                    <i class="fas {{ $icon }} text-gray-400"></i>
                    <span class="tkt-file-name">{{ $file->file_name }}</span>
                    <span class="tkt-file-size">{{ $file->human_readable_size }}</span>
                </a>
            </li>
        @endforeach
    </ul>
@endif
