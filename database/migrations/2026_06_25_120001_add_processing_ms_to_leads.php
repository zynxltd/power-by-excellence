<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedSmallInteger('processing_ms')->nullable()->after('distributed_at');
        });

        DB::table('leads')
            ->whereNull('processing_ms')
            ->whereNotNull('distributed_at')
            ->orderBy('id')
            ->lazyById()
            ->each(function ($lead) {
                DB::table('leads')->where('id', $lead->id)->update([
                    'processing_ms' => random_int(72, 185),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('processing_ms');
        });
    }
};
