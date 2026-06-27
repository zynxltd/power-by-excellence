<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->string('address_line1')->nullable()->after('phone_verified_at');
            $table->string('address_line2')->nullable()->after('address_line1');
            $table->string('city')->nullable()->after('address_line2');
            $table->string('region')->nullable()->after('city');
            $table->string('postcode', 16)->nullable()->after('region');
            $table->string('country', 2)->nullable()->after('postcode');
            $table->timestamp('address_verified_at')->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'phone_verified_at',
                'address_line1',
                'address_line2',
                'city',
                'region',
                'postcode',
                'country',
                'address_verified_at',
            ]);
        });
    }
};
