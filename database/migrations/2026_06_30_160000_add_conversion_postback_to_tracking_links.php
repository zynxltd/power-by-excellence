<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_links', function (Blueprint $table) {
            $table->text('conversion_postback_url')->nullable()->after('config');
            $table->json('conversion_postback_macros')->nullable()->after('conversion_postback_url');
        });
    }

    public function down(): void
    {
        Schema::table('tracking_links', function (Blueprint $table) {
            $table->dropColumn(['conversion_postback_url', 'conversion_postback_macros']);
        });
    }
};
