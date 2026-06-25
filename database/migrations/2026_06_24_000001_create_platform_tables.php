<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable()->unique();
            $table->string('timezone')->default('UTC');
            $table->string('default_currency', 3)->default('GBP');
            $table->string('default_country', 2)->default('GB');
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role')->default('account_admin')->after('email');
        });

        Schema::create('buyers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->index();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->decimal('credit_balance', 12, 2)->default(0);
            $table->json('caps')->nullable();
            $table->json('schedule')->nullable();
            $table->string('portal_password')->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'reference']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->index();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['account_id', 'reference']);
        });

        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('sid')->index();
            $table->string('name');
            $table->json('caps')->nullable();
            $table->decimal('payout_override', 10, 2)->nullable();
            $table->timestamps();
            $table->unique(['supplier_id', 'sid']);
        });

        Schema::create('sub_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->string('ssid')->index();
            $table->string('name');
            $table->timestamps();
            $table->unique(['source_id', 'ssid']);
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('standard');
            $table->string('name');
            $table->string('reference')->index();
            $table->string('country', 2)->default('GB');
            $table->string('currency', 3)->default('GBP');
            $table->string('status')->default('active');
            $table->string('vertical_id')->nullable();
            $table->string('payout_supplier_on')->default('buyer_delivery_accept');
            $table->decimal('payout_amount', 10, 2)->default(0);
            $table->json('caps')->nullable();
            $table->json('dedupe_config')->nullable();
            $table->json('validation_config')->nullable();
            $table->boolean('reference_locked')->default(false);
            $table->boolean('use_advanced_distribution')->default(false);
            $table->string('sell_mode')->default('exclusive');
            $table->unsignedTinyInteger('max_sells')->default(1);
            $table->decimal('floor_price', 10, 2)->default(0);
            $table->unsignedInteger('ping_timeout_ms')->default(1500);
            $table->timestamps();
            $table->unique(['account_id', 'reference']);
        });

        Schema::create('campaign_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('label')->nullable();
            $table->string('type')->default('text');
            $table->boolean('required')->default(false);
            $table->boolean('ping_field')->default(false);
            $table->json('validation')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['campaign_id', 'name']);
        });

        Schema::create('campaign_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->json('caps')->nullable();
            $table->decimal('payout_amount', 10, 2)->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'supplier_id']);
        });

        Schema::create('hybrid_rule_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('rules');
            $table->timestamps();
        });

        Schema::create('distribution_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('config');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('method');
            $table->string('trigger_type')->default('on_lead_arrival');
            $table->string('status')->default('inactive');
            $table->boolean('advanced_distribution_only')->default(false);
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedInteger('weight')->default(100);
            $table->unsignedInteger('tier')->default(1);
            $table->string('routing_mode')->nullable();
            $table->string('revenue_type')->default('fixed');
            $table->decimal('revenue_amount', 10, 2)->default(0);
            $table->json('revenue_rules')->nullable();
            $table->string('cap_type')->default('delivery');
            $table->json('caps')->nullable();
            $table->json('config')->nullable();
            $table->json('eligibility_rules')->nullable();
            $table->json('schedule')->nullable();
            $table->json('location_filter')->nullable();
            $table->foreignId('on_success_delivery_id')->nullable();
            $table->foreignId('on_failure_delivery_id')->nullable();
            $table->timestamps();
        });

        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('key_prefix', 12)->index();
            $table->string('key_hash');
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->json('events');
            $table->string('secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('queue_id')->nullable()->unique();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sub_supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sold_to_buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->string('reject_reason')->nullable();
            $table->json('field_data');
            $table->json('metadata')->nullable();
            $table->string('sid')->nullable();
            $table->string('ssid')->nullable();
            $table->string('source')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('distributed_at')->nullable();
            $table->timestamp('quarantined_until')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamps();
            $table->index(['account_id', 'status', 'received_at']);
            $table->index(['campaign_id', 'received_at']);
        });

        Schema::create('lead_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->string('level')->default('info');
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['lead_id', 'created_at']);
        });

        Schema::create('lead_financials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('revenue', 10, 2)->default(0);
            $table->decimal('payout', 10, 2)->default(0);
            $table->decimal('margin', 10, 2)->default(0);
            $table->string('currency', 3)->default('GBP');
            $table->timestamps();
        });

        Schema::create('delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->string('skipped_reason')->nullable();
            $table->decimal('revenue', 10, 2)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('ping_request')->nullable();
            $table->json('ping_response')->nullable();
            $table->json('post_request')->nullable();
            $table->json('post_response')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestamps();
            $table->index(['delivery_id', 'created_at']);
        });

        Schema::create('cap_counters', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('period');
            $table->string('period_key');
            $table->unsignedInteger('count')->default(0);
            $table->timestamp('reset_at')->nullable();
            $table->timestamps();
            $table->unique(['entity_type', 'entity_id', 'period', 'period_key']);
        });

        Schema::create('dedupe_index', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('field_key');
            $table->string('field_value_hash');
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'field_key', 'field_value_hash']);
        });

        Schema::create('suppression_hashes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('field_type');
            $table->string('hash');
            $table->timestamps();
            $table->index(['account_id', 'field_type', 'hash']);
        });

        Schema::create('buyer_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->boolean('converted')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained()->cascadeOnDelete();
            $table->string('reason');
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('system_error_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel')->default('platform');
            $table->string('level');
            $table->string('context')->nullable();
            $table->text('message');
            $table->json('payload')->nullable();
            $table->string('trace_id')->nullable()->index();
            $table->timestamps();
            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_error_logs');
        Schema::dropIfExists('lead_returns');
        Schema::dropIfExists('buyer_feedback');
        Schema::dropIfExists('suppression_hashes');
        Schema::dropIfExists('dedupe_index');
        Schema::dropIfExists('cap_counters');
        Schema::dropIfExists('delivery_logs');
        Schema::dropIfExists('lead_financials');
        Schema::dropIfExists('lead_events');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('webhooks');
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('distribution_configs');
        Schema::dropIfExists('hybrid_rule_groups');
        Schema::dropIfExists('campaign_suppliers');
        Schema::dropIfExists('campaign_fields');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('sub_suppliers');
        Schema::dropIfExists('sources');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('buyers');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
            $table->dropColumn('role');
        });
        Schema::dropIfExists('accounts');
    }
};
