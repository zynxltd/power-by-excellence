<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('audience'); // super_admin | tenant
            $table->string('type'); // broadcast | activity
            $table->string('severity')->default('info');
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['audience', 'created_at']);
            $table->index(['account_id', 'created_at']);
        });

        Schema::create('platform_notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_notification_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->unique(['user_id', 'platform_notification_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_notification_reads');
        Schema::dropIfExists('platform_notifications');
    }
};
