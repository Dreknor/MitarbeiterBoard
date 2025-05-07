<!DOCTYPE html>
<html>
<head>
    <title>Ticket Assigned</title>
</head>
<body>
<h1>Ticket Assigned: {{ $ticket->title }}</h1>
<p>You have been assigned to a new ticket with the following details:</p>
<ul>
    <li><strong>Title:</strong> {{ $ticket->title }}</li>
    <li><strong>Description:</strong> {{ $ticket->description }}</li>
    <li><strong>Created by:</strong> {{ $ticket->user->name }}</li>
    <li><strong>Category:</strong> {{ $ticket->category->name }}</li>
</ul>
@if($ticket->waiting_until)
    <p><strong>Waiting until:</strong> {{ $ticket->waiting_until->format('d.m.Y H:i') }}</p>
@endif
</body>
</html>
