<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('papyrus_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('papyrus_settings', 'default_domain_limit')) {
                $table->integer('default_domain_limit')->default(3)->after('whitelabel_name');
            }
        });

        Schema::table('papyrus_proxies', function (Blueprint $table) {
            if (!Schema::hasColumn('papyrus_proxies', 'domain_limit')) {
                $table->integer('domain_limit')->nullable()->after('allow_messages_edit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('papyrus_settings', function (Blueprint $table) {
            $table->dropColumn('default_domain_limit');
        });
        Schema::table('papyrus_proxies', function (Blueprint $table) {
            $table->dropColumn('domain_limit');
        });
    }
};
