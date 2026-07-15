<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('trips', 'accepted_at')) {
            Schema::table('trips', function (Blueprint $table) {
                $table->timestamp('accepted_at')->nullable()->after('status');
            });
        }

        DB::statement("ALTER TABLE trip_dispatches MODIFY status ENUM('sent', 'accepted', 'rejected', 'expired') NOT NULL DEFAULT 'sent'");
    }

    public function down(): void
    {
        DB::table('trip_dispatches')->where('status', 'accepted')->update(['status' => 'expired']);
        DB::statement("ALTER TABLE trip_dispatches MODIFY status ENUM('sent', 'rejected', 'expired') NOT NULL DEFAULT 'sent'");

        if (Schema::hasColumn('trips', 'accepted_at')) {
            Schema::table('trips', function (Blueprint $table) {
                $table->dropColumn('accepted_at');
            });
        }
    }
};