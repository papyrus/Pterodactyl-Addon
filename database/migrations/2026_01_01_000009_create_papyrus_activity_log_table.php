<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('papyrus_activity_log')) {
            return;
        }

        Schema::create('papyrus_activity_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('action');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['server_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('papyrus_activity_log');
    }
};
