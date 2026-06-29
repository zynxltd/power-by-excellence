<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_sequences', function (Blueprint $table) {
            $table->foreignId('segment_id')->nullable()->after('campaign_id')->constrained()->nullOnDelete();
        });

        Schema::table('automation_sequence_steps', function (Blueprint $table) {
            $table->string('action', 32)->default('send')->after('sort_order');
        });

        Schema::create('automation_sequence_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_sequence_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('current_step_order')->default(0);
            $table->timestamp('next_run_at')->nullable();
            $table->string('status', 32)->default('active');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['automation_sequence_id', 'lead_id']);
            $table->index(['status', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_sequence_enrollments');

        Schema::table('automation_sequence_steps', function (Blueprint $table) {
            $table->dropColumn('action');
        });

        Schema::table('automation_sequences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('segment_id');
        });
    }
};
