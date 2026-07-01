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
     * @return array{
     *     message: ?string,
     *     gather: bool,
     *     route: bool,
     *     hangup: bool,
     *     ivr_data: array<string, mixed>
     * }
     */
    public function processStep(CallSession $session, ?string $digits = null): array
    {
        $flow = $session->ivrFlow;

        if (! $flow || ! $flow->is_active) {
            return $this->result(route: true, ivrData: $session->ivr_data ?? []);
        }

        $nodes = IvrFlow::normalizeNodes($flow->nodes ?? []);
        $currentNode = $session->metadata['ivr_current_node'] ?? $flow->entry_node;

        return $this->resolveNode($session, $currentNode, $nodes, $session->ivr_data ?? [], $digits);
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

    /**
     * @param  array<string, array<string, mixed>>  $nodes
     * @param  array<string, mixed>  $ivrData
     * @return array{
     *     message: ?string,
     *     gather: bool,
     *     route: bool,
     *     hangup: bool,
     *     ivr_data: array<string, mixed>
     * }
     */
    protected function resolveNode(
        CallSession $session,
        string $nodeId,
        array $nodes,
        array $ivrData,
        ?string $digits,
        array $messages = [],
    ): array {
        $node = $nodes[$nodeId] ?? null;

        if (! $node) {
            return $this->result(route: true, ivrData: $ivrData, message: $this->joinMessages($messages));
        }

        $type = $node['type'] === 'play' ? 'say' : ($node['type'] ?? 'say');

        if ($digits !== null && $type === 'gather') {
            $ivrData[$node['store_as'] ?? 'digits'] = $digits;
            $nextNode = $node['branches'][$digits] ?? $node['default_next'] ?? null;

            if ($nextNode) {
                $session->update([
                    'ivr_data' => $ivrData,
                    'metadata' => array_merge($session->metadata ?? [], ['ivr_current_node' => $nextNode]),
                ]);

                return $this->resolveNode($session->fresh(), $nextNode, $nodes, $ivrData, null, $messages);
            }
        }

        return match ($type) {
            'say' => $this->handleSay($session, $node, $nodeId, $nodes, $ivrData, $messages),
            'gather' => $this->result(
                message: $this->joinMessages($messages, $node['prompt'] ?? 'Please enter your selection.'),
                gather: true,
                ivrData: $ivrData,
            ),
            'redirect' => $this->handleRedirect($session, $node, $nodes, $ivrData, $messages),
            'hangup' => $this->result(
                hangup: true,
                message: $this->joinMessages($messages, $node['message'] ?? 'Goodbye.'),
                ivrData: $ivrData,
            ),
            'route' => $this->result(
                route: true,
                message: $this->joinMessages($messages, $node['message'] ?? null),
                ivrData: $ivrData,
            ),
            default => $this->result(route: true, ivrData: $ivrData, message: $this->joinMessages($messages)),
        };
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodes
     * @param  array<string, mixed>  $ivrData
     */
    protected function handleSay(
        CallSession $session,
        array $node,
        string $nodeId,
        array $nodes,
        array $ivrData,
        array $messages,
    ): array {
        if (! empty($node['message'])) {
            $messages[] = $node['message'];
        }

        $next = $node['next'] ?? null;

        if ($next && isset($nodes[$next])) {
            $session->update([
                'metadata' => array_merge($session->metadata ?? [], ['ivr_current_node' => $next]),
            ]);

            return $this->resolveNode($session->fresh(), $next, $nodes, $ivrData, null, $messages);
        }

        return $this->result(message: $this->joinMessages($messages), ivrData: $ivrData);
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodes
     * @param  array<string, mixed>  $ivrData
     */
    protected function handleRedirect(
        CallSession $session,
        array $node,
        array $nodes,
        array $ivrData,
        array $messages,
    ): array {
        $next = $node['next'] ?? null;

        if ($next && isset($nodes[$next])) {
            $session->update([
                'metadata' => array_merge($session->metadata ?? [], ['ivr_current_node' => $next]),
            ]);

            return $this->resolveNode($session->fresh(), $next, $nodes, $ivrData, null, $messages);
        }

        return $this->result(route: true, ivrData: $ivrData, message: $this->joinMessages($messages));
    }

    /**
     * @param  list<string>  $messages
     */
    protected function joinMessages(array $messages, ?string $extra = null): ?string
    {
        if ($extra) {
            $messages[] = $extra;
        }

        $joined = trim(implode(' ', array_filter($messages)));

        return $joined !== '' ? $joined : null;
    }

    /**
     * @param  array<string, mixed>  $ivrData
     * @return array{
     *     message: ?string,
     *     gather: bool,
     *     route: bool,
     *     hangup: bool,
     *     ivr_data: array<string, mixed>
     * }
     */
    protected function result(
        ?string $message = null,
        bool $gather = false,
        bool $route = false,
        bool $hangup = false,
        array $ivrData = [],
    ): array {
        return [
            'message' => $message,
            'gather' => $gather,
            'route' => $route,
            'hangup' => $hangup,
            'ivr_data' => $ivrData,
        ];
    }
}
