<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->timestamp('billed_at')->nullable()->after('revenue');
            $table->decimal('billed_amount', 10, 2)->nullable()->after('billed_at');
            $table->foreignId('buyer_transaction_id')->nullable()->after('billed_amount')
                ->constrained('buyer_transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('buyer_transaction_id');
            $table->dropColumn(['billed_at', 'billed_amount']);
        });
    }
};
