<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('papyrus_settings', function (Blueprint $table) {
            $table->id();
            $table->text('papyrus_api_key')->nullable();
            $table->text('cloudflare_api_key')->nullable();
            $table->string('cloudflare_zone_id')->nullable();
            $table->string('base_domain')->nullable();
            $table->string('subdomain_pattern')->default('{server_name}');
            $table->json('default_antibot_config')->nullable();
            $table->text('default_motd_up')->nullable();
            $table->text('default_motd_down')->nullable();
            $table->integer('default_cps_threshold')->default(10);
            $table->boolean('default_captcha_enabled')->default(true);
            $table->boolean('default_limbo_enabled')->default(true);
            $table->boolean('default_chat_reports')->default(false);
            $table->json('default_kick_messages')->nullable();
            $table->boolean('hide_real_ip')->default(true);
            $table->boolean('disable_proxy_protocol')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('papyrus_settings');
    }
};
