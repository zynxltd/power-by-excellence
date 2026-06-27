<?php

namespace App\Services\Demo;

use App\Enums\LeadStatus;
use App\Models\Buyer;
use App\Models\BuyerFeedback;
use App\Models\BuyerTransaction;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\LeadFinancial;
use App\Models\LeadReturn;
use App\Models\Supplier;
use App\Services\Buyers\BuyerConversionService;
use Illuminate\Support\Str;

class BuyerPortalHistorySeeder
{
    /**
     * @return array{leads: int, feedback: int, returns: int, transactions: int}
     */
    public function seed(Buyer $buyer, int $days = 90, bool $replace = false): array
    {
        if ($replace) {
            $this->clearBuyerPortalData($buyer);
        }

        $account = $buyer->account;
        abort_unless($account, 422, 'Buyer has no account.');

        $campaigns = Campaign::where('account_id', $account->id)->get();
        abort_if($campaigns->isEmpty(), 422, 'No campaigns on this account.');

        $suppliers = Supplier::where('account_id', $account->id)->with('sources')->get();
        abort_if($suppliers->isEmpty(), 422, 'No suppliers on this account.');

        $isUs = $account->default_country === 'US';
        $firstNames = $isUs
            ? ['James', 'Sarah', 'Michael', 'Emily', 'David', 'Jessica', 'Robert', 'Ashley']
            : ['Jan', 'Anna', 'Pieter', 'Sophie', 'Lars', 'Emma', 'Marco', 'Elena'];
        $lastNames = $isUs
            ? ['Smith', 'Johnson', 'Williams', 'Brown', 'Davis', 'Miller']
            : ['de Vries', 'Jansen', 'Bakker', 'Visser', 'Smit', 'Meijer'];
        $postcodes = $isUs
            ? ['90210', '10001', '60601', '33101', '73301']
            : ['1012 AB', '3011 AD', '2000', '80331', '75001', 'EC1A 1BB'];

        $createdLeads = collect();
        $conversionService = app(BuyerConversionService::class);

        for ($day = $days - 1; $day >= 0; $day--) {
            $date = now()->subDays($day)->startOfDay();
            $dailyCount = $this->dailyLeadCount($day);

            for ($i = 0; $i < $dailyCount; $i++) {
                $campaign = $campaigns->random();
                $supplier = $suppliers->random();
                $source = $supplier->sources->first();
                $receivedAt = $date->copy()->addHours(random_int(7, 20))->addMinutes(random_int(0, 59));
                $revenue = round(random_int(1400, 4200) / 100, 2);

                $lead = Lead::create([
                    'account_id' => $account->id,
                    'campaign_id' => $campaign->id,
                    'supplier_id' => $supplier->id,
                    'source_id' => $source?->id,
                    'sold_to_buyer_id' => $buyer->id,
                    'status' => LeadStatus::Sold,
                    'field_data' => [
                        'firstname' => $firstNames[array_rand($firstNames)],
                        'lastname' => $lastNames[array_rand($lastNames)],
                        'email' => Str::lower(Str::random(5)).'.b'.$buyer->id.'.'.$day.$i.'@demo-portal.test',
                        'phone1' => $isUs ? '+1'.random_int(2002000000, 9999999999) : '+31'.random_int(600000000, 699999999),
                        'zipcode' => $postcodes[array_rand($postcodes)],
                    ],
                    'sid' => $source?->sid ?? 'demo_source',
                    'received_at' => $receivedAt,
                    'distributed_at' => $receivedAt->copy()->addSeconds(random_int(2, 40)),
                    'processing_ms' => random_int(85, 220),
                ]);

                LeadFinancial::create([
                    'lead_id' => $lead->id,
                    'revenue' => $revenue,
                    'payout' => round($revenue * 0.38, 2),
                    'margin' => round($revenue * 0.62, 2),
                    'currency' => $campaign->currency,
                ]);

                LeadEvent::create([
                    'lead_id' => $lead->id,
                    'event_type' => 'lead.ingested',
                    'level' => 'info',
                    'message' => 'Lead received via API',
                    'created_at' => $receivedAt,
                ]);

                LeadEvent::create([
                    'lead_id' => $lead->id,
                    'event_type' => 'sold',
                    'level' => 'info',
                    'message' => 'Lead sold to '.$buyer->name,
                    'created_at' => $receivedAt->copy()->addSeconds(random_int(3, 25)),
                ]);

                $createdLeads->push(['lead' => $lead, 'revenue' => $revenue, 'at' => $receivedAt]);
            }
        }

        $feedbackCount = 0;
        $returnsCount = 0;

        $feedbackPool = $createdLeads->shuffle()->values();
        $feedbackTarget = (int) max(1, round($createdLeads->count() * 0.28));

        foreach ($feedbackPool->take($feedbackTarget) as $index => $row) {
            /** @var Lead $lead */
            $lead = $row['lead'];
            $statusRoll = $index % 10;

            if ($statusRoll < 4) {
                $status = 'contacted';
                $converted = false;
            } elseif ($statusRoll < 7) {
                $status = 'converted';
                $converted = true;
            } else {
                $status = 'invalid';
                $converted = false;
            }

            $conversionService->recordFeedback(
                $buyer,
                $lead,
                $status,
                $converted,
                $converted ? 'Funded within 48h — demo data.' : 'QA note from buyer portal seed.',
            );
            $feedbackCount++;
        }

        $returnPool = $createdLeads->shuffle()->take(5);
        foreach ($returnPool as $i => $row) {
            /** @var Lead $lead */
            $lead = $row['lead'];
            $status = match (true) {
                $i === 0, $i === 1 => 'pending',
                $i === 2 => 'approved',
                default => 'rejected',
            };

            LeadReturn::create([
                'lead_id' => $lead->id,
                'buyer_id' => $buyer->id,
                'reason' => collect([
                    'Wrong phone number — unreachable after 3 attempts',
                    'Duplicate submission from same consumer',
                    'Postcode outside agreed coverage area',
                    'Lead requested removal — GDPR',
                ])->random(),
                'status' => $status,
                'created_at' => $row['at']->copy()->addDays(random_int(1, 5)),
            ]);
            $returnsCount++;
        }

        $transactionCount = $this->seedLedger($buyer, $createdLeads);

        return [
            'leads' => $createdLeads->count(),
            'feedback' => $feedbackCount,
            'returns' => $returnsCount,
            'transactions' => $transactionCount,
        ];
    }

    protected function dailyLeadCount(int $daysAgo): int
    {
        $base = match (true) {
            $daysAgo <= 1 => random_int(3, 7),
            $daysAgo <= 7 => random_int(2, 6),
            $daysAgo <= 30 => random_int(1, 5),
            default => random_int(0, 4),
        };

        if ($daysAgo % 11 === 0) {
            return max(0, $base - 2);
        }

        return $base;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{lead: Lead, revenue: float, at: \Illuminate\Support\Carbon}>  $leads
     */
    protected function seedLedger(Buyer $buyer, $leads): int
    {
        $balance = 500.0;
        $events = collect();

        $start = now()->subDays(90)->startOfDay();
        for ($week = 0; $week <= 13; $week++) {
            $topUp = random_int(800, 2500);
            $balance += $topUp;
            $events->push([
                'type' => 'credit',
                'amount' => $topUp,
                'balance_after' => $balance,
                'description' => 'Credit top-up (admin)',
                'lead_id' => null,
                'at' => $start->copy()->addWeeks($week)->addDays(random_int(0, 2))->addHours(9),
            ]);
        }

        foreach ($leads->sortBy('at') as $row) {
            $balance = max(0, $balance - $row['revenue']);
            $events->push([
                'type' => 'debit',
                'amount' => $row['revenue'],
                'balance_after' => $balance,
                'description' => 'Lead purchase · '.$row['lead']->uuid,
                'lead_id' => $row['lead']->id,
                'at' => $row['at']->copy()->addMinutes(random_int(1, 15)),
            ]);
        }

        if ($balance < 150) {
            $topUp = random_int(1500, 3000);
            $balance += $topUp;
            $events->push([
                'type' => 'credit',
                'amount' => $topUp,
                'balance_after' => $balance,
                'description' => 'Credit top-up (admin)',
                'lead_id' => null,
                'at' => now()->subDays(2)->setHour(10),
            ]);
        }

        $count = 0;
        foreach ($events->sortBy('at') as $event) {
            BuyerTransaction::create([
                'buyer_id' => $buyer->id,
                'lead_id' => $event['lead_id'],
                'type' => $event['type'],
                'amount' => $event['type'] === 'debit' ? -abs($event['amount']) : abs($event['amount']),
                'balance_after' => $event['balance_after'],
                'description' => $event['description'],
                'created_at' => $event['at'],
                'updated_at' => $event['at'],
            ]);
            $count++;
        }

        $buyer->update(['credit_balance' => round($balance, 2)]);

        return $count;
    }

    protected function clearBuyerPortalData(Buyer $buyer): void
    {
        $leadIds = Lead::where('sold_to_buyer_id', $buyer->id)->pluck('id');

        BuyerFeedback::where('buyer_id', $buyer->id)->delete();
        LeadReturn::where('buyer_id', $buyer->id)->delete();
        BuyerTransaction::where('buyer_id', $buyer->id)->delete();
        LeadEvent::whereIn('lead_id', $leadIds)->delete();
        LeadFinancial::whereIn('lead_id', $leadIds)->delete();
        Lead::whereIn('id', $leadIds)->delete();
    }
}
