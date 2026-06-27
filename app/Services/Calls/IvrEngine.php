<?php

namespace App\Services\Calls;

use App\Enums\CallEventType;
use App\Models\CallSession;
use App\Models\IvrFlow;

class IvrEngine
{
    public function __construct(
        protected CallEventLogger $logger,
    ) {}

    /**
     * @return array{message: ?string, gather: bool, route: bool, ivr_data: array<string, mixed>}
     */
    public function processStep(CallSession $session, ?string $digits = null): array
    {
        $flow = $session->ivrFlow;

        if (! $flow || ! $flow->is_active) {
            return ['message' => null, 'gather' => false, 'route' => true, 'ivr_data' => $session->ivr_data ?? []];
        }

        $nodes = $flow->nodes ?? [];
        $currentNode = $session->metadata['ivr_current_node'] ?? $flow->entry_node;
        $node = $nodes[$currentNode] ?? null;

        if (! $node) {
            return ['message' => null, 'gather' => false, 'route' => true, 'ivr_data' => $session->ivr_data ?? []];
        }

        $ivrData = $session->ivr_data ?? [];

        if ($digits !== null && ($node['type'] ?? '') === 'gather') {
            $ivrData[$node['store_as'] ?? 'digits'] = $digits;
            $nextNode = $node['branches'][$digits] ?? $node['default_next'] ?? null;

            if ($nextNode) {
                $session->update([
                    'ivr_data' => $ivrData,
                    'metadata' => array_merge($session->metadata ?? [], ['ivr_current_node' => $nextNode]),
                ]);

                return $this->processStep($session->fresh());
            }
        }

        $type = $node['type'] ?? 'play';

        return match ($type) {
            'play' => [
                'message' => $node['message'] ?? 'Welcome.',
                'gather' => false,
                'route' => ($node['next'] ?? null) === 'route',
                'ivr_data' => $ivrData,
            ],
            'gather' => [
                'message' => $node['prompt'] ?? 'Please enter your selection.',
                'gather' => true,
                'route' => false,
                'ivr_data' => $ivrData,
            ],
            'route' => [
                'message' => $node['message'] ?? null,
                'gather' => false,
                'route' => true,
                'ivr_data' => $ivrData,
            ],
            default => [
                'message' => null,
                'gather' => false,
                'route' => true,
                'ivr_data' => $ivrData,
            ],
        };
    }

    public function resolveFlowForCampaign(?int $campaignId): ?IvrFlow
    {
        if (! $campaignId) {
            return null;
        }

        return IvrFlow::where('campaign_id', $campaignId)
            ->where('is_active', true)
            ->first();
    }

    public function logStep(CallSession $session, string $nodeId, ?string $digits = null): void
    {
        $this->logger->log(
            $session,
            CallEventType::IvrStep,
            'IVR step: '.$nodeId,
            ['node' => $nodeId, 'digits' => $digits],
        );
    }
}
