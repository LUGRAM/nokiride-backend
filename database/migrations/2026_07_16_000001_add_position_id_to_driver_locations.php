<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_locations', function (Blueprint $table) {
            $table->string('position_id', 100)->nullable()->unique()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('driver_locations', function (Blueprint $table) {
            $table->dropUnique(['position_id']);
            $table->dropColumn('position_id');
        });
    }
};
