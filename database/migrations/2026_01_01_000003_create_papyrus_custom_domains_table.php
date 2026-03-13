<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('papyrus_custom_domains', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('proxy_id');
            $table->string('domain');
            $table->string('verification_status', 50)->default('pending');
            $table->string('verification_token')->nullable();
            $table->timestamps();

            $table->foreign('proxy_id')
                  ->references('id')
                  ->on('papyrus_proxies')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('papyrus_custom_domains');
    }
};
