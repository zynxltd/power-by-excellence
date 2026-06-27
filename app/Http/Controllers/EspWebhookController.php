<?php

namespace App\Http\Controllers;

use App\Models\MessageSend;
use App\Services\Messaging\MessageSendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EspWebhookController extends Controller
{
    public function sendgrid(Request $request): JsonResponse
    {
        foreach ($request->all() as $event) {
            if (! is_array($event)) {
                continue;
            }

            $this->handleEvent(
                $event['email'] ?? null,
                $this->mapSendGridEvent($event['event'] ?? ''),
                $event,
            );
        }

        return response()->json(['ok' => true]);
    }

    public function mailgun(Request $request): JsonResponse
    {
        $eventData = $request->input('event-data', []);
        $recipient = $eventData['recipient'] ?? null;
        $event = $eventData['event'] ?? '';

        $this->handleEvent($recipient, $this->mapMailgunEvent($event), $eventData);

        return response()->json(['ok' => true]);
    }

    public function postmark(Request $request): JsonResponse
    {
        $type = $request->input('RecordType', '');
        $recipient = $request->input('Email') ?? $request->input('Recipient');

        $this->handleEvent($recipient, $this->mapPostmarkEvent($type), $request->all());

        return response()->json(['ok' => true]);
    }

    protected function handleEvent(?string $recipient, ?string $type, array $meta): void
    {
        if (! $recipient || ! $type) {
            return;
        }

        $send = MessageSend::withoutGlobalScopes()
            ->where('recipient', $recipient)
            ->where('channel', 'email')
            ->orderByDesc('sent_at')
            ->first();

        if ($send) {
            app(MessageSendService::class)->recordEvent($send, $type, null, $meta);
        }
    }

    protected function mapSendGridEvent(string $event): ?string
    {
        return match ($event) {
            'delivered' => 'delivered',
            'open' => 'open',
            'click' => 'click',
            'bounce', 'dropped' => 'bounce',
            'spamreport' => 'complaint',
            default => null,
        };
    }

    protected function mapMailgunEvent(string $event): ?string
    {
        return match ($event) {
            'delivered' => 'delivered',
            'opened' => 'open',
            'clicked' => 'click',
            'failed', 'bounced' => 'bounce',
            'complained' => 'complaint',
            default => null,
        };
    }

    protected function mapPostmarkEvent(string $type): ?string
    {
        return match ($type) {
            'Delivery' => 'delivered',
            'Open' => 'open',
            'Click' => 'click',
            'Bounce' => 'bounce',
            'SpamComplaint' => 'complaint',
            default => null,
        };
    }
}
