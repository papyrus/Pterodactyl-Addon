<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('papyrus_proxies', function (Blueprint $table) {
            if (!Schema::hasColumn('papyrus_proxies', 'original_allocation_port')) {
                $table->integer('original_allocation_port')->nullable()->after('original_allocation_ip');
            }
            if (!Schema::hasColumn('papyrus_proxies', 'ip_replaced')) {
                $table->boolean('ip_replaced')->default(false)->after('backend_port');
            }
            if (!Schema::hasColumn('papyrus_proxies', 'proxy_protocol_disabled')) {
                $table->boolean('proxy_protocol_disabled')->default(false)->after('ip_replaced');
            }
            if (!Schema::hasColumn('papyrus_proxies', 'enabled')) {
                $table->boolean('enabled')->default(true)->after('proxy_protocol_disabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('papyrus_proxies', function (Blueprint $table) {
            $table->dropColumn(['original_allocation_port', 'ip_replaced', 'proxy_protocol_disabled', 'enabled']);
        });
    }
};
