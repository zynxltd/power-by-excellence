<p>Hello,</p>

<p>
    Buyer <strong>{{ $buyer->name }}</strong> ({{ $buyer->reference }}) on
    <strong>{{ $platformName }}</strong> has fallen to or below the low-credit threshold.
</p>

<ul>
    <li>Current balance: <strong>{{ number_format($balance, 2) }} {{ $currency }}</strong></li>
    <li>Alert threshold: <strong>{{ number_format($threshold, 2) }} {{ $currency }}</strong></li>
</ul>

<p>Top up the buyer ledger to resume routing when prepay is required.</p>
