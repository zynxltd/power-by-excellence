<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->foreignId('buyer_id')->nullable()->after('account_id')->constrained()->nullOnDelete();
            $table->json('config')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('buyer_id');
            $table->dropColumn('config');
        });
    }
};
