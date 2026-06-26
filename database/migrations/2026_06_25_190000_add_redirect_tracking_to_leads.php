<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('winning_delivery_id')->nullable()->after('sold_to_buyer_id')->constrained('deliveries')->nullOnDelete();
            $table->string('redirect_url', 2048)->nullable()->after('winning_delivery_id');
            $table->timestamp('redirect_offered_at')->nullable()->after('redirect_url');
            $table->timestamp('redirect_followed_at')->nullable()->after('redirect_offered_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('winning_delivery_id');
            $table->dropColumn(['redirect_url', 'redirect_offered_at', 'redirect_followed_at']);
        });
    }
};
