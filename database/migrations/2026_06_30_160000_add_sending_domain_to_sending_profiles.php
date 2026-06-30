<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sending_profiles', function (Blueprint $table) {
            $table->string('sending_domain')->nullable()->after('domain_match');
            $table->unique(['account_id', 'sending_domain']);
        });
    }

    public function down(): void
    {
        Schema::table('sending_profiles', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'sending_domain']);
            $table->dropColumn('sending_domain');
        });
    }
};
