<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('body');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('portal_role')->default('admin');
            $table->string('subject');
            $table->string('status')->default('open');
            $table->string('priority')->default('normal');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_staff')->default(false);
            $table->timestamps();
        });

        Schema::create('event_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('metric');
            $table->string('operator')->default('lt');
            $table->decimal('threshold', 12, 2);
            $table->string('channel')->default('email');
            $table->string('status')->default('active');
            $table->json('config')->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bulk_sms_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('message');
            $table->json('filter')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();
        });

        Schema::create('automation_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('trigger_event');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('automation_sequence_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_sequence_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->string('channel');
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('account_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('scheduled_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('format')->default('csv');
            $table->string('delivery_method')->default('email');
            $table->string('cron')->default('0 8 * * *');
            $table->json('config')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(false)->after('remember_token');
            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->json('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->json('allowed_ips')->nullable()->after('two_factor_recovery_codes');
        });

        Schema::table('api_keys', function (Blueprint $table) {
            $table->json('allowed_ips')->nullable()->after('permissions');
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', fn (Blueprint $table) => $table->dropColumn('allowed_ips'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_enabled', 'two_factor_secret', 'two_factor_recovery_codes', 'allowed_ips']);
        });
        Schema::dropIfExists('scheduled_exports');
        Schema::dropIfExists('account_audit_logs');
        Schema::dropIfExists('automation_sequence_steps');
        Schema::dropIfExists('automation_sequences');
        Schema::dropIfExists('bulk_sms_campaigns');
        Schema::dropIfExists('event_alerts');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('help_articles');
    }
};
