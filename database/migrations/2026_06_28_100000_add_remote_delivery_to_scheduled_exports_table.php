<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_exports', function (Blueprint $table) {
            $table->string('remote_host')->nullable()->after('delivery_method');
            $table->unsignedSmallInteger('remote_port')->nullable()->after('remote_host');
            $table->string('remote_path')->nullable()->default('/')->after('remote_port');
            $table->string('remote_username')->nullable()->after('remote_path');
            $table->text('remote_credentials')->nullable()->after('remote_username');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_exports', function (Blueprint $table) {
            $table->dropColumn([
                'remote_host',
                'remote_port',
                'remote_path',
                'remote_username',
                'remote_credentials',
            ]);
        });
    }
};
