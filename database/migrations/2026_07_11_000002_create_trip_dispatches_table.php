<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trip_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->onDelete('cascade');
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['sent', 'rejected', 'expired'])->default('sent');
            $table->timestamps();

            $table->unique(['trip_id', 'driver_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_dispatches');
    }
};
