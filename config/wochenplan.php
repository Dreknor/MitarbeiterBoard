<?php

return [
    'pdf_attachments' => [
        // Arbeitsblätter beim PDF-Export anhängen (true/false)
        'enabled' => env('WP_PDF_ATTACHMENTS', true),

        // Maximale Gesamtgröße aller Anhänge in MB
        'max_total_size_mb' => 50,

        // Word-zu-PDF Konvertierungsstrategie
        // 'libreoffice' = Nur LibreOffice CLI
        // 'phpword'     = Nur PhpWord → HTML → DomPDF
        // 'auto'        = LibreOffice versuchen, Fallback auf PhpWord
        'word_converter' => env('WP_WORD_CONVERTER', 'auto'),

        // Erlaubte MIME-Types für den Upload
        'allowed_mime_types' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
        ],
    ],
];

