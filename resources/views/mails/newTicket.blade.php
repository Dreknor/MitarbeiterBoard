<!DOCTYPE html>
<html>
<head>
    <title>Neues Ticket erstellt</title>
</head>
<body>
<h1>Neues Ticket erstellt</h1>
<p>Ein neues Ticket wurde mit den folgenden Details erstellt:</p>
<ul>
    <li><strong>Titel:</strong> {{ $ticket->title }}</li>
    <li><strong>Beschreibung:</strong> {!! $ticket->description !!} </li>
    <li><strong>Erstellt von:</strong> {{ $ticket->user->name }}</li>
    <li><strong>Kategorie:</strong> {{ $ticket->category->name }}</li>
    <li><strong>Priorität:</strong> {{ $ticket->priority }}</li>
</ul>
</body>
</html>
