<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_reports', function (Blueprint $table) {
            $table->timestamp('next_run_at')->nullable()->after('last_run_at');
            $table->string('last_run_status')->nullable()->after('next_run_at');
            $table->index(['status', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::table('saved_reports', function (Blueprint $table) {
            $table->dropIndex(['status', 'next_run_at']);
            $table->dropColumn(['next_run_at', 'last_run_status']);
        });
    }
};
