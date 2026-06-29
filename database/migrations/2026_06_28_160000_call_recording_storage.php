<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_recordings', function (Blueprint $table) {
            $table->string('storage_path')->nullable()->after('url');
            $table->timestamp('retention_expires_at')->nullable()->after('duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('call_recordings', function (Blueprint $table) {
            $table->dropColumn(['storage_path', 'retention_expires_at']);
        });
    }
};
