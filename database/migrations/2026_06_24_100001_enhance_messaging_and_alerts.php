<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulk_sms_campaigns', function (Blueprint $table) {
            $table->string('channel')->default('sms')->after('name');
            $table->string('subject')->nullable()->after('message');
            $table->string('provider')->nullable()->after('subject');
        });

        Schema::create('event_alert_fires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('metric');
            $table->decimal('value', 12, 2);
            $table->decimal('threshold', 12, 2);
            $table->string('channel');
            $table->string('status')->default('sent');
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_alert_fires');

        Schema::table('bulk_sms_campaigns', function (Blueprint $table) {
            $table->dropColumn(['channel', 'subject', 'provider']);
        });
    }
};
