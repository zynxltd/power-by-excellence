<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('channel')->default('lead')->after('type');
            $table->json('call_settings')->nullable()->after('ping_timeout_ms');
        });

        Schema::create('tracking_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone_number')->index();
            $table->string('friendly_name')->nullable();
            $table->string('provider')->default('log');
            $table->string('provider_sid')->nullable();
            $table->string('dni_pool')->nullable();
            $table->json('dni_rules')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'phone_number']);
        });

        Schema::create('ivr_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->json('nodes')->nullable();
            $table->string('entry_node')->default('start');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('call_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tracking_number_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ivr_flow_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sold_to_buyer_id')->nullable()->constrained('buyers')->nullOnDelete();
            $table->foreignId('winning_delivery_id')->nullable()->constrained('deliveries')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('ringing');
            $table->string('caller_number')->nullable();
            $table->string('caller_city')->nullable();
            $table->string('caller_state')->nullable();
            $table->string('caller_country', 2)->nullable();
            $table->string('sid')->nullable();
            $table->string('ssid')->nullable();
            $table->string('provider_call_sid')->nullable();
            $table->decimal('revenue', 10, 2)->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('billable_seconds')->default(0);
            $table->unsignedInteger('min_duration_seconds')->default(0);
            $table->json('ivr_data')->nullable();
            $table->json('metadata')->nullable();
            $table->string('disposition')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'status']);
            $table->index(['campaign_id', 'created_at']);
        });

        Schema::create('call_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_session_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->string('level')->default('info');
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['call_session_id', 'created_at']);
        });

        Schema::create('call_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending');
            $table->string('skipped_reason')->nullable();
            $table->decimal('revenue', 10, 2)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('ping_request')->nullable();
            $table->json('ping_response')->nullable();
            $table->json('transfer_response')->nullable();
            $table->unsignedSmallInteger('tier')->nullable();
            $table->timestamps();
        });

        Schema::create('call_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_session_id')->constrained()->cascadeOnDelete();
            $table->string('provider_recording_sid')->nullable();
            $table->string('url')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_recordings');
        Schema::dropIfExists('call_delivery_logs');
        Schema::dropIfExists('call_events');
        Schema::dropIfExists('call_sessions');
        Schema::dropIfExists('ivr_flows');
        Schema::dropIfExists('tracking_numbers');

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['channel', 'call_settings']);
        });
    }
};
