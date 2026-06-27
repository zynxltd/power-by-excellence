<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyers', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('settings');
        });

        Schema::create('saved_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('filters')->nullable();
            $table->json('columns')->nullable();
            $table->string('schedule_cron')->nullable();
            $table->json('email_recipients')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('vertical_field_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('vertical_id');
            $table->string('name');
            $table->json('fields');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('verify_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->string('status')->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->json('results')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verify_batches');
        Schema::dropIfExists('vertical_field_templates');
        Schema::dropIfExists('saved_reports');

        Schema::table('buyers', function (Blueprint $table) {
            $table->dropColumn('stripe_customer_id');
        });
    }
};
