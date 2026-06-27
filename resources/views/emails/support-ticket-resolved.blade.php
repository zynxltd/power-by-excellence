<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Support ticket resolved</title></head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #0f172a;">
    <p>Hi {{ $recipient->name }},</p>
    <p>Your support ticket has been marked as resolved.</p>
    <ul>
        <li><strong>Subject:</strong> {{ $ticket->subject }}</li>
        <li><strong>Ticket #:</strong> {{ $ticket->id }}</li>
        <li><strong>Resolved by:</strong> {{ $resolvedBy->name }}</li>
    </ul>
    <p><a href="{{ $ticketUrl }}">View ticket in your platform →</a></p>
</body>
</html>
