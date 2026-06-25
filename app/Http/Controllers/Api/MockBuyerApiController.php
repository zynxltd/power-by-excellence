<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MockBuyerApiController
{
    public function ping(Request $request, int $tier): JsonResponse
    {
        abort_unless($tier >= 1 && $tier <= 10, 404);

        return match ($tier) {
            1 => $this->acceptPing($request, 25.00),
            2 => $this->floorPing($request),
            3 => response()->json(['Success' => false, 'Reason' => 'No coverage in area']),
            4 => $this->acceptPing($request, 18.50, slow: true),
            5 => $this->randomPing($request),
            6 => $this->acceptPing($request, 20.00, postFails: true),
            7 => $this->dynamicZipPing($request),
            8 => $this->capPing($request),
            9 => response()->json(['ok' => true, 'bid' => 'invalid']),
            10 => $this->auctionPing($request),
            default => abort(404),
        };
    }

    public function post(Request $request, int $tier): JsonResponse
    {
        abort_unless($tier >= 1 && $tier <= 10, 404);

        if ($tier === 6) {
            $pingId = $request->input('PingID') ?? $request->input('ping_id');
            if ($pingId && Cache::get("mock_buyer_post_fail:{$pingId}")) {
                return response()->json(['Success' => false, 'Approved' => false, 'Reason' => 'Post rejected after ping']);
            }
        }

        if ($tier === 9) {
            return response()->json(['status' => 'error'], 500);
        }

        if ($tier === 3) {
            return response()->json(['Success' => false, 'Approved' => false]);
        }

        return response()->json([
            'Success' => true,
            'Approved' => true,
            'LeadID' => 'mock_lead_'.uniqid(),
            'tier' => $tier,
        ]);
    }

    public function docs(): JsonResponse
    {
        return response()->json([
            'base_path' => '/api/mock/buyers/{tier}/ping|post',
            'tiers' => [
                1 => 'Premium — always accepts, bid £25',
                2 => 'Floor — accepts only when bid >= floor',
                3 => 'Reject — ping and post always decline',
                4 => 'Standard — accepts at £18.50 (simulates slower buyer)',
                5 => 'Random — 50% accept based on phone hash',
                6 => 'Ping-ok-post-fail — ping accepts, post rejects',
                7 => 'Dynamic — bid scales with zipcode prefix',
                8 => 'Cap — rejects when X-Mock-Cap-Exceeded header set',
                9 => 'Malformed — invalid response shapes',
                10 => 'Auction — competitive bid for parallel auction tests',
            ],
        ]);
    }

    protected function acceptPing(Request $request, float $cost, bool $slow = false, bool $postFails = false): JsonResponse
    {
        $pingId = 'ping_t'.uniqid();
        if ($postFails) {
            Cache::put("mock_buyer_post_fail:{$pingId}", true, now()->addHour());
        }

        return response()->json([
            'Success' => true,
            'Cost' => $cost,
            'PingID' => $pingId,
            'slow' => $slow,
        ]);
    }

    protected function floorPing(Request $request): JsonResponse
    {
        $floor = (float) $request->input('floor', $request->input('Floor', 10));
        $hint = (float) $request->input('bid_hint', $request->input('BidHint', 0));
        $bid = $hint > 0 ? $hint : $floor + 1;

        if ($bid < $floor) {
            return response()->json(['Success' => false, 'Reason' => 'Below floor']);
        }

        return response()->json([
            'Success' => true,
            'Cost' => $bid,
            'PingID' => 'ping_floor_'.uniqid(),
        ]);
    }

    protected function randomPing(Request $request): JsonResponse
    {
        $phone = (string) ($request->input('phone1') ?? $request->input('phone') ?? '000');
        $accept = (crc32($phone) % 2) === 0;

        if (! $accept) {
            return response()->json(['Success' => false, 'Reason' => 'Random reject']);
        }

        return response()->json([
            'Success' => true,
            'Cost' => 15.00,
            'PingID' => 'ping_rand_'.uniqid(),
        ]);
    }

    protected function dynamicZipPing(Request $request): JsonResponse
    {
        $zip = (string) ($request->input('zipcode') ?? $request->input('postcode') ?? 'SW1');
        $prefix = strtoupper(substr(preg_replace('/\s+/', '', $zip), 0, 2));
        $cost = match ($prefix) {
            'SW', 'EC', 'W1' => 22.00,
            'M1', 'B1' => 18.00,
            default => 12.00,
        };

        return response()->json([
            'Success' => true,
            'Cost' => $cost,
            'PingID' => 'ping_dyn_'.uniqid(),
        ]);
    }

    protected function capPing(Request $request): JsonResponse
    {
        if ($request->header('X-Mock-Cap-Exceeded') === '1') {
            return response()->json(['Success' => false, 'Reason' => 'Daily cap reached']);
        }

        return response()->json([
            'Success' => true,
            'Cost' => 14.00,
            'PingID' => 'ping_cap_'.uniqid(),
        ]);
    }

    protected function auctionPing(Request $request): JsonResponse
    {
        $floor = (float) $request->input('floor', 10);
        $competition = (int) ($request->input('tier_weight', 50));
        $bid = $floor + ($competition / 10);

        return response()->json([
            'Success' => true,
            'Cost' => round($bid, 2),
            'PingID' => 'ping_auction_'.uniqid(),
            'Auction' => true,
        ]);
    }
}
