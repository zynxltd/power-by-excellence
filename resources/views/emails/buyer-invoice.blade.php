<p>Hello,</p>

<p>
    Your invoice from <strong>{{ $platformName }}</strong> for buyer
    <strong>{{ $buyer->name }}</strong> is ready.
</p>

<ul>
    <li>Amount: <strong>{{ number_format((float) $invoice->amount, 2) }} {{ strtoupper($invoice->currency) }}</strong></li>
    <li>Status: <strong>{{ $invoice->status }}</strong></li>
    @if ($invoice->period_start && $invoice->period_end)
        <li>Period: {{ $invoice->period_start->toFormattedDateString() }} — {{ $invoice->period_end->toFormattedDateString() }}</li>
    @endif
</ul>

@if ($invoice->pdf_url)
    <p>
        <a href="{{ $invoice->pdf_url }}">View or download invoice PDF</a>
    </p>
@else
    <p>Your invoice PDF will be available from the buyer portal billing page.</p>
@endif

<p>You can also view past invoices anytime in the buyer portal under Billing.</p>
