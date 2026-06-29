<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_numbers', function (Blueprint $table) {
            $table->string('webhook_status', 32)->nullable()->after('provider_sid');
        });
    }

    public function down(): void
    {
        Schema::table('tracking_numbers', function (Blueprint $table) {
            $table->dropColumn('webhook_status');
        });
    }
};
