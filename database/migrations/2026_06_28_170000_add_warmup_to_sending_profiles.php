<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sending_profiles', function (Blueprint $table) {
            $table->boolean('warmup_enabled')->default(false)->after('is_default');
            $table->timestamp('warmup_started_at')->nullable()->after('warmup_enabled');
            $table->unsignedInteger('warmup_day_one_limit')->default(50)->after('warmup_started_at');
            $table->unsignedInteger('warmup_target_limit')->default(1000)->after('warmup_day_one_limit');
            $table->unsignedSmallInteger('warmup_ramp_days')->default(14)->after('warmup_target_limit');
        });

        Schema::table('message_sends', function (Blueprint $table) {
            $table->foreignId('sending_profile_id')
                ->nullable()
                ->after('bulk_sms_campaign_id')
                ->constrained('sending_profiles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('message_sends', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sending_profile_id');
        });

        Schema::table('sending_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'warmup_enabled',
                'warmup_started_at',
                'warmup_day_one_limit',
                'warmup_target_limit',
                'warmup_ramp_days',
            ]);
        });
    }
};
