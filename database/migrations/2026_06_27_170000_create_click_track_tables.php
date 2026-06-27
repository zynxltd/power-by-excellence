<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('token', 32)->unique();
            $table->text('destination_url');
            $table->string('goal')->nullable();
            $table->string('status')->default('active');
            $table->decimal('payout_amount', 10, 2)->nullable();
            $table->decimal('revenue_amount', 10, 2)->nullable();
            $table->json('config')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['campaign_id', 'supplier_id']);
        });

        Schema::create('tracking_impressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracking_link_id')->constrained()->cascadeOnDelete();
            $table->uuid('impression_uuid')->unique();
            $table->string('sub1')->nullable();
            $table->string('sub2')->nullable();
            $table->string('sub3')->nullable();
            $table->string('sub4')->nullable();
            $table->string('sub5')->nullable();
            $table->string('source')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('impressed_at');
            $table->timestamps();

            $table->index(['tracking_link_id', 'impressed_at']);
        });

        Schema::create('tracking_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracking_link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('click_uuid')->unique();
            $table->string('sub1')->nullable();
            $table->string('sub2')->nullable();
            $table->string('sub3')->nullable();
            $table->string('sub4')->nullable();
            $table->string('sub5')->nullable();
            $table->string('source')->nullable();
            $table->string('referrer')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('device')->nullable();
            $table->boolean('is_unique')->default(true);
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('clicked_at');
            $table->timestamps();

            $table->index(['account_id', 'clicked_at']);
            $table->index(['tracking_link_id', 'clicked_at']);
            $table->index(['supplier_id', 'clicked_at']);
        });

        Schema::create('tracking_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracking_link_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tracking_click_id')->nullable()->constrained('tracking_clicks')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('conversion_uuid')->unique();
            $table->string('goal')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('payout', 10, 2)->default(0);
            $table->decimal('revenue', 10, 2)->default(0);
            $table->decimal('sale_amount', 10, 2)->default(0);
            $table->string('external_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejected_reason')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status', 'created_at']);
            $table->index(['campaign_id', 'created_at']);
            $table->index(['supplier_id', 'created_at']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('tracking_click_id')->nullable()->after('sub_supplier_id')->constrained('tracking_clicks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tracking_click_id');
        });

        Schema::dropIfExists('tracking_conversions');
        Schema::dropIfExists('tracking_clicks');
        Schema::dropIfExists('tracking_impressions');
        Schema::dropIfExists('tracking_links');
    }
};
