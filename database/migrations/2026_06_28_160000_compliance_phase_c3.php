<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_data_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->string('storage_path')->nullable();
            $table->unsignedInteger('lead_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
        });

        Schema::table('marketing_opt_outs', function (Blueprint $table) {
            $table->string('label', 255)->nullable()->after('hash');
        });
    }

    public function down(): void
    {
        Schema::table('marketing_opt_outs', function (Blueprint $table) {
            $table->dropColumn('label');
        });

        Schema::dropIfExists('tenant_data_exports');
    }
};
