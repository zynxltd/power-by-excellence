<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->string('status', 32)->default('pending');
            $table->decimal('credit_amount', 12, 2)->nullable();
            $table->foreignId('refund_transaction_id')->nullable()->constrained('buyer_transactions')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique('call_session_id');
            $table->index(['buyer_id', 'status']);
        });

        Schema::table('call_sessions', function (Blueprint $table) {
            $table->timestamp('refunded_at')->nullable()->after('buyer_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->dropColumn('refunded_at');
        });

        Schema::dropIfExists('call_returns');
    }
};
