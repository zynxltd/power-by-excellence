<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('name');
            $table->boolean('multi_geo')->default(false)->after('country');
            $table->json('geo_countries')->nullable()->after('multi_geo');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'multi_geo', 'geo_countries']);
        });
    }
};
