<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Lead purchased</title></head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #0f172a;">
    <p>Hi {{ $buyer->name }},</p>
    <p>A new lead was purchased on your account.</p>
    <ul>
        <li><strong>Lead ID:</strong> {{ $lead->uuid }}</li>
        <li><strong>Campaign:</strong> {{ $lead->campaign?->name ?? '—' }}</li>
        <li><strong>Cost:</strong> {{ $currency }} {{ number_format($revenue, 2) }}</li>
        <li><strong>Received:</strong> {{ $lead->received_at?->toDayDateTimeString() }}</li>
    </ul>
    <p><a href="{{ $portalUrl }}">View in buyer portal →</a></p>
</body>
</html>
