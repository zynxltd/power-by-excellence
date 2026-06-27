<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auto_responders', function (Blueprint $table) {
            $table->unsignedInteger('delay_minutes')->default(0)->after('trigger_event');
        });
    }

    public function down(): void
    {
        Schema::table('auto_responders', function (Blueprint $table) {
            $table->dropColumn('delay_minutes');
        });
    }
};
