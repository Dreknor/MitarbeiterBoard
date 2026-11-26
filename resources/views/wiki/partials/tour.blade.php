{{-- Einführung ins Wiki (startet einmal automatisch auf der Startseite, sonst über das ?-Symbol in der Kopfleiste) --}}
<x-tour id="wiki" :version="1" :auto="request()->routeIs('wiki')" :steps="[
    ['target' => 'wiki-suche', 'title' => 'Suchen', 'text' => 'Hier finden Sie jede Seite – schon beim Tippen erscheinen Treffer. Tipp: Strg+K oder „/“ springt von überall in die Suche.'],
    ['target' => 'wiki-navigation', 'title' => 'Seitenbaum', 'text' => 'Alle Seiten sind in Bereiche gegliedert. Mit dem Pfeil klappen Sie Unterseiten auf; darüber finden Sie die A–Z-Liste, die letzten Änderungen und die Tags.'],
    ['target' => 'wiki-favoriten', 'title' => 'Favoriten', 'text' => 'Seiten, die Sie oft brauchen, markieren Sie auf der Seite mit dem Stern – sie erscheinen dann hier und auf dem Dashboard.'],
    auth()->user()?->can('create', \App\Models\WikiSeite::class)
        ? ['target' => 'wiki-neu', 'title' => 'Mitschreiben', 'text' => 'Jede Seite können Sie ergänzen oder neu anlegen. Bilder einfach per Strg+V oder Drag & Drop einfügen – jede Änderung landet im Verlauf und lässt sich wiederherstellen.']
        : null,
]" />
