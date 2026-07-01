<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_short_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_send_id')->nullable()->constrained('message_sends')->cascadeOnDelete();
            $table->unsignedBigInteger('automation_sequence_step_id')->nullable();
            $table->string('slug', 16)->unique();
            $table->text('destination_url');
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamps();

            $table->index(['account_id', 'message_send_id']);
            $table->index(['message_send_id', 'destination_url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_short_links');
    }
};
