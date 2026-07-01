<?php

namespace Tests\Feature;

use App\Models\AutomationSequence;
use App\Models\AutomationSequenceStep;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use App\Services\Automation\AutomationSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationSequenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
    }

    public function test_sequence_update_persists_canvas_step_order(): void
    {
        $campaign = Campaign::first();
        $account = $this->admin->resolveAccount();

        $sequence = AutomationSequence::create([
            'account_id' => $account->id,
            'campaign_id' => $campaign->id,
            'name' => 'Reorder canvas test',
            'trigger_event' => 'on_lead_received',
            'status' => 'active',
        ]);

        AutomationSequenceStep::create([
            'automation_sequence_id' => $sequence->id,
            'sort_order' => 0,
            'action' => 'send',
            'delay_minutes' => 0,
            'channel' => 'email',
            'config' => ['subject' => 'First', 'body' => 'First body', 'to_field' => 'email'],
        ]);

        AutomationSequenceStep::create([
            'automation_sequence_id' => $sequence->id,
            'sort_order' => 1,
            'action' => 'wait',
            'delay_minutes' => 30,
            'channel' => 'email',
            'config' => [],
        ]);

        AutomationSequenceStep::create([
            'automation_sequence_id' => $sequence->id,
            'sort_order' => 2,
            'action' => 'send',
            'delay_minutes' => 0,
            'channel' => 'email',
            'config' => ['subject' => 'Second', 'body' => 'Second body', 'to_field' => 'email'],
        ]);

        $this->actingAs($this->admin)
            ->patch(route('automation.sequences.update', $sequence), [
                'name' => 'Reorder canvas test',
                'campaign_id' => $campaign->id,
                'trigger_event' => 'on_lead_received',
                'status' => 'active',
                'steps' => [
                    [
                        'sort_order' => 0,
                        'action' => 'send',
                        'delay_minutes' => 0,
                        'channel' => 'email',
                        'config' => ['subject' => 'Second', 'body' => 'Second body', 'to_field' => 'email'],
                    ],
                    [
                        'sort_order' => 1,
                        'action' => 'wait',
                        'delay_minutes' => 30,
                        'channel' => 'email',
                        'config' => [],
                    ],
                    [
                        'sort_order' => 2,
                        'action' => 'send',
                        'delay_minutes' => 0,
                        'channel' => 'email',
                        'config' => ['subject' => 'First', 'body' => 'First body', 'to_field' => 'email'],
                    ],
                ],
            ])
            ->assertRedirect();

        $ordered = $sequence->fresh('steps')->steps->values();

        $this->assertSame('Second', $ordered[0]->config['subject']);
        $this->assertSame('wait', $ordered[1]->action);
        $this->assertSame('First', $ordered[2]->config['subject']);
        $this->assertSame(0, (int) $ordered[0]->sort_order);
        $this->assertSame(1, (int) $ordered[1]->sort_order);
        $this->assertSame(2, (int) $ordered[2]->sort_order);
    }

    public function test_reordered_sequence_steps_execute_in_canvas_order(): void
    {
        $account = $this->admin->resolveAccount();
        $lead = Lead::first();
        $lead->update([
            'field_data' => array_merge($lead->field_data ?? [], [
                'firstname' => 'Alex',
                'email' => 'alex@example.com',
            ]),
        ]);

        $sequence = AutomationSequence::create([
            'account_id' => $account->id,
            'name' => 'Execution order test',
            'trigger_event' => 'on_lead_received',
            'status' => 'active',
        ]);

        AutomationSequenceStep::create([
            'automation_sequence_id' => $sequence->id,
            'sort_order' => 0,
            'action' => 'send',
            'delay_minutes' => 0,
            'channel' => 'email',
            'config' => [
                'subject' => 'Second in journey',
                'body' => 'This should send first after canvas reorder.',
                'to_field' => 'email',
            ],
        ]);

        AutomationSequenceStep::create([
            'automation_sequence_id' => $sequence->id,
            'sort_order' => 1,
            'action' => 'send',
            'delay_minutes' => 0,
            'channel' => 'email',
            'config' => [
                'subject' => 'First in journey',
                'body' => 'This should send second.',
                'to_field' => 'email',
            ],
        ]);

        app(AutomationSequenceService::class)->enrollLead($lead, $sequence->fresh('steps'));

        $this->assertDatabaseHas('message_sends', [
            'lead_id' => $lead->id,
            'subject' => 'Second in journey',
        ]);

        $this->assertDatabaseMissing('message_sends', [
            'lead_id' => $lead->id,
            'subject' => 'First in journey',
        ]);

        $this->artisan('automation:process-sequences')->assertSuccessful();

        $this->assertDatabaseHas('message_sends', [
            'lead_id' => $lead->id,
            'subject' => 'First in journey',
        ]);
    }

    public function test_branch_rule_persisted_from_canvas_payload(): void
    {
        $campaign = Campaign::first();

        $this->actingAs($this->admin)
            ->post(route('automation.sequences.store'), [
                'name' => 'Branch journey',
                'campaign_id' => $campaign->id,
                'trigger_event' => 'on_lead_received',
                'steps' => [
                    [
                        'sort_order' => 0,
                        'action' => 'send',
                        'delay_minutes' => 0,
                        'channel' => 'email',
                        'config' => [
                            'subject' => 'Welcome',
                            'body' => 'Hello',
                            'to_field' => 'email',
                        ],
                    ],
                    [
                        'sort_order' => 1,
                        'action' => 'send',
                        'delay_minutes' => 60,
                        'channel' => 'email',
                        'config' => [
                            'subject' => 'Follow-up',
                            'body' => 'Only if opened',
                            'to_field' => 'email',
                            'branch' => 'opened',
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $step = AutomationSequence::where('name', 'Branch journey')->first()
            ?->steps()
            ->where('sort_order', 1)
            ->first();

        $this->assertNotNull($step);
        $this->assertSame('opened', $step->config['branch'] ?? null);
    }
}
