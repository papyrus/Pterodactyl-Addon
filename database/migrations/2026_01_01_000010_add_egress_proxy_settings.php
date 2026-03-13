<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('papyrus_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('papyrus_settings', 'egress_proxy_enabled')) {
                $table->boolean('egress_proxy_enabled')->default(false)->after('default_domain_limit');
                $table->string('egress_proxy_type', 10)->default('socks5')->after('egress_proxy_enabled'); // socks5 or http
                $table->string('egress_proxy_host')->nullable()->after('egress_proxy_type');
                $table->integer('egress_proxy_port')->nullable()->after('egress_proxy_host');
                $table->string('egress_proxy_username')->nullable()->after('egress_proxy_port');
                $table->text('egress_proxy_password')->nullable()->after('egress_proxy_username'); // encrypted
            }
        });

        Schema::table('papyrus_proxies', function (Blueprint $table) {
            if (!Schema::hasColumn('papyrus_proxies', 'original_startup')) {
                $table->text('original_startup')->nullable()->after('domain_limit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('papyrus_settings', function (Blueprint $table) {
            $table->dropColumn([
                'egress_proxy_enabled', 'egress_proxy_type', 'egress_proxy_host',
                'egress_proxy_port', 'egress_proxy_username', 'egress_proxy_password',
            ]);
        });
        Schema::table('papyrus_proxies', function (Blueprint $table) {
            $table->dropColumn('original_startup');
        });
    }
};
