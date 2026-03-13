<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('papyrus_proxies', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id')->unique();
            $table->string('papyrus_proxy_id')->nullable();
            $table->string('proxy_status', 50)->default('pending');
            $table->string('protected_address')->nullable();
            $table->string('cloudflare_record_id')->nullable();
            $table->string('original_allocation_ip')->nullable();
            $table->integer('original_allocation_port')->nullable();
            $table->string('backend_address')->nullable();
            $table->integer('backend_port')->nullable();
            $table->boolean('ip_replaced')->default(false);
            $table->boolean('proxy_protocol_disabled')->default(false);
            $table->boolean('enabled')->default(true);
            $table->json('antibot_config')->nullable();
            $table->text('motd_up')->nullable();
            $table->text('motd_down')->nullable();
            $table->json('kick_messages')->nullable();
            $table->timestamps();

            $table->foreign('server_id')
                  ->references('id')
                  ->on('servers')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('papyrus_proxies');
    }
};
