<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\DistributionConfig;
use App\Services\Demo\LargePingTreeBuilder;
use Illuminate\Console\Command;

class SeedLargePingTreeCommand extends Command
{
    protected $signature = 'demo:large-ping-tree
                            {distribution? : Distribution config ID (e.g. 54)}
                            {--account=emea-loans : Account slug}
                            {--campaign= : Campaign reference (defaults to distribution campaign)}
                            {--tiers=35 : Number of ping-tree tiers}';

    protected $description = 'Seed a large ping tree with many buyers, deliveries, and varied pricing';

    public function handle(LargePingTreeBuilder $builder): int
    {
        $account = Account::where('slug', $this->option('account'))->first();
        if (! $account) {
            $this->error('Account not found: '.$this->option('account'));

            return self::FAILURE;
        }

        $distribution = $this->resolveDistribution($account);
        if (! $distribution) {
            return self::FAILURE;
        }

        $campaign = $distribution->campaign;
        if (! $campaign) {
            $this->error('Distribution has no campaign.');

            return self::FAILURE;
        }

        $tierCount = max(30, (int) $this->option('tiers'));

        $this->info("Building {$tierCount}-tier ping tree for {$account->name} - {$campaign->name} (distribution #{$distribution->id})…");

        $result = $builder->build($campaign, $account, $distribution, $tierCount);

        $this->table(
            ['Tiers', 'Deliveries', 'Buyers on account', 'Distribution ID'],
            [[$result['tiers'], $result['deliveries'], $result['buyers'], $result['distribution_id']]]
        );

        $this->info('Done. Open: /distribution/'.$distribution->id.'/edit');

        return self::SUCCESS;
    }

    protected function resolveDistribution(Account $account): ?DistributionConfig
    {
        $id = $this->argument('distribution');

        if ($id) {
            $distribution = DistributionConfig::withoutGlobalScopes()
                ->with('campaign')
                ->find($id);

            if (! $distribution) {
                $this->error("Distribution #{$id} not found.");

                return null;
            }

            if ($distribution->campaign?->account_id !== $account->id) {
                $this->error("Distribution #{$id} does not belong to account {$account->slug}.");

                return null;
            }

            return $distribution;
        }

        $campaignRef = $this->option('campaign');
        $campaign = $campaignRef
            ? Campaign::where('account_id', $account->id)->where('reference', $campaignRef)->first()
            : Campaign::where('account_id', $account->id)->orderBy('id')->first();

        if (! $campaign) {
            $this->error('No campaign found for account.');

            return null;
        }

        return DistributionConfig::firstOrCreate(
            ['campaign_id' => $campaign->id, 'name' => "{$this->option('tiers')}-Tier Enterprise Ping Tree"],
            ['is_active' => true, 'config' => ['groups' => []]]
        );
    }
}
