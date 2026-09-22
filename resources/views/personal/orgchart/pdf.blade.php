<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; margin: 0; padding: 10px; }
        h1 { text-align: center; font-size: 14px; margin-bottom: 4px; }
        .subtitle { text-align: center; color: #888; font-size: 9px; margin-bottom: 16px; }
        .level { display: flex; gap: 16px; justify-content: center; margin: 8px 0; }
        .node { border: 1px solid #ccc; border-radius: 6px; padding: 6px 10px;
                display: inline-block; text-align: center; min-width: 110px; max-width: 150px; vertical-align: top; }
        .node-name { font-weight: bold; font-size: 10px; }
        .node-person { font-size: 8px; color: #555; margin-top: 2px; }
        .node-deputy { font-size: 7px; color: #888; }
        .connector { display: flex; flex-direction: column; align-items: center; }
        .line-v { width: 1px; height: 12px; background: #ccc; margin: 0 auto; }
        footer { position: fixed; bottom: 0; width: 100%; text-align: center;
                 font-size: 8px; color: #999; border-top: 1px solid #eee; padding: 4px 0; }
        .node-empty { color: #bbb; font-style: italic; }
    </style>
</head>
<body>
    <h1>Organigramm – Ev. Schulzentrum Radebeul</h1>
    <p class="subtitle">Stand: {{ $generatedAt->format('d.m.Y') }}</p>

    @if($rootPosition)
        @include('personal.orgchart._pdf_node', ['position' => $rootPosition, 'depth' => 0])
    @else
        <p style="text-align:center; color:#aaa;">Kein Organigramm vorhanden.</p>
    @endif

    <footer>Vertraulich – Erstellt am {{ $generatedAt->format('d.m.Y H:i') }} Uhr</footer>
</body>
</html>

