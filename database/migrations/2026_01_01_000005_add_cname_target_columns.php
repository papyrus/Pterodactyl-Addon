<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('papyrus_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('papyrus_settings', 'default_cname_target')) {
                $table->string('default_cname_target')->default('spectrum-01.papyrus.vip')->after('base_domain');
            }
        });

        Schema::table('papyrus_proxies', function (Blueprint $table) {
            if (!Schema::hasColumn('papyrus_proxies', 'cname_target')) {
                $table->string('cname_target')->nullable()->after('protected_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('papyrus_settings', function (Blueprint $table) {
            $table->dropColumn('default_cname_target');
        });
        Schema::table('papyrus_proxies', function (Blueprint $table) {
            $table->dropColumn('cname_target');
        });
    }
};
