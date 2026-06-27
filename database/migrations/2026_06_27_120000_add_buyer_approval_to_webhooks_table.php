<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->string('approval_status')->nullable()->after('config');
            $table->timestamp('submitted_at')->nullable()->after('approval_status');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            $table->foreignId('reviewed_by_user_id')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->text('submission_notes')->nullable()->after('reviewed_by_user_id');
            $table->text('rejection_reason')->nullable()->after('submission_notes');

            $table->index(['account_id', 'approval_status']);
            $table->index(['buyer_id', 'approval_status']);
        });
    }

    public function down(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by_user_id']);
            $table->dropIndex(['account_id', 'approval_status']);
            $table->dropIndex(['buyer_id', 'approval_status']);
            $table->dropColumn([
                'approval_status',
                'submitted_at',
                'reviewed_at',
                'reviewed_by_user_id',
                'submission_notes',
                'rejection_reason',
            ]);
        });
    }
};
