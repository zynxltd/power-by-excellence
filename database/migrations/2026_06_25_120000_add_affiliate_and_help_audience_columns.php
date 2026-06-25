<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->json('affiliate_settings')->nullable()->after('status');
        });

        Schema::table('help_articles', function (Blueprint $table) {
            $table->string('audience')->default('tenant')->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('affiliate_settings');
        });

        Schema::table('help_articles', function (Blueprint $table) {
            $table->dropColumn('audience');
        });
    }
};
