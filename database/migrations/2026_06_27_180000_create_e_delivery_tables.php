<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bulk_sms_campaign_id')->nullable()->constrained('bulk_sms_campaigns')->nullOnDelete();
            $table->uuid('token')->unique();
            $table->string('channel', 16);
            $table->string('provider', 32)->nullable();
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('recipient', 255);
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('ab_variant', 8)->nullable();
            $table->string('status', 32)->default('sent');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'channel', 'sent_at']);
            $table->index(['bulk_sms_campaign_id', 'status']);
        });

        Schema::create('message_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_send_id')->constrained('message_sends')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('url')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['account_id', 'type', 'occurred_at']);
            $table->index(['message_send_id', 'type']);
        });

        Schema::create('marketing_opt_outs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('field_type', 16);
            $table->string('hash', 64);
            $table->string('source', 32)->default('unsubscribe');
            $table->timestamps();

            $table->unique(['account_id', 'field_type', 'hash']);
        });

        Schema::create('lead_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->string('tag', 64);
            $table->timestamps();

            $table->unique(['lead_id', 'tag']);
            $table->index(['account_id', 'tag']);
        });

        Schema::create('segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type', 16)->default('static');
            $table->json('rules')->nullable();
            $table->timestamps();
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('channel', 16)->default('email');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->text('html_body')->nullable();
            $table->timestamps();
        });

        Schema::create('sending_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('provider', 32)->default('smtp');
            $table->string('domain_match')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('reply_to')->nullable();
            $table->json('config')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::table('bulk_sms_campaigns', function (Blueprint $table) {
            $table->text('html_body')->nullable()->after('message');
            $table->foreignId('segment_id')->nullable()->after('filter')->constrained('segments')->nullOnDelete();
            $table->foreignId('sending_profile_id')->nullable()->after('segment_id')->constrained('sending_profiles')->nullOnDelete();
            $table->json('ab_test')->nullable()->after('sending_profile_id');
            $table->unsignedSmallInteger('throttle_per_minute')->nullable()->after('ab_test');
        });
    }

    public function down(): void
    {
        Schema::table('bulk_sms_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('segment_id');
            $table->dropConstrainedForeignId('sending_profile_id');
            $table->dropColumn(['html_body', 'ab_test', 'throttle_per_minute']);
        });

        Schema::dropIfExists('sending_profiles');
        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('segments');
        Schema::dropIfExists('lead_tags');
        Schema::dropIfExists('marketing_opt_outs');
        Schema::dropIfExists('message_events');
        Schema::dropIfExists('message_sends');
    }
};
