<?php

namespace App\Http\Controllers;

use App\Models\MessageSend;
use App\Models\MessageShortLink;
use App\Services\Messaging\MarketingSuppressionService;
use App\Services\Messaging\MessageSendService;
use App\Services\Messaging\SmsShortlinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class MessageTrackingController extends Controller
{
    public function open(string $token): Response
    {
        $send = MessageSend::withoutGlobalScopes()->where('token', $token)->first();

        if ($send) {
            app(MessageSendService::class)->recordEvent($send, 'open');
        }

        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function click(string $token, Request $request): RedirectResponse
    {
        $send = MessageSend::withoutGlobalScopes()->where('token', $token)->first();
        $encoded = $request->query('url', '');
        $url = base64_decode($encoded) ?: '/';

        if ($send) {
            app(MessageSendService::class)->recordEvent($send, 'click', $url);
        }

        return redirect()->away(filter_var($url, FILTER_VALIDATE_URL) ? $url : url('/'));
    }

    public function shortlinkRedirect(string $slug): RedirectResponse
    {
        $link = MessageShortLink::withoutGlobalScopes()->where('slug', $slug)->first();

        if (! $link || ! filter_var($link->destination_url, FILTER_VALIDATE_URL)) {
            abort(404);
        }

        app(SmsShortlinkService::class)->recordClick($link);

        return redirect()->away($link->destination_url, 302);
    }

    public function unsubscribe(string $token): InertiaResponse
    {
        $send = MessageSend::withoutGlobalScopes()->where('token', $token)->first();

        return Inertia::render('Public/Unsubscribe', [
            'token' => $token,
            'recipient' => $send ? $this->maskRecipient($send->recipient) : null,
        ]);
    }

    public function confirmUnsubscribe(string $token): InertiaResponse
    {
        $send = MessageSend::withoutGlobalScopes()->where('token', $token)->first();

        if ($send && $send->channel === 'email') {
            app(MarketingSuppressionService::class)->optOut(
                $send->account_id,
                'email',
                $send->recipient,
            );
        }

        return Inertia::render('Public/Unsubscribe', [
            'token' => $token,
            'confirmed' => true,
            'recipient' => $send ? $this->maskRecipient($send->recipient) : null,
        ]);
    }

    protected function maskRecipient(string $recipient): string
    {
        if (str_contains($recipient, '@')) {
            [$local, $domain] = explode('@', $recipient, 2);

            return substr($local, 0, 2).'***@'.$domain;
        }

        return substr($recipient, 0, 4).'***';
    }
}
