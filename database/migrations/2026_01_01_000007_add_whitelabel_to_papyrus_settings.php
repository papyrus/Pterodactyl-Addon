<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('papyrus_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('papyrus_settings', 'whitelabel_enabled')) {
                $table->boolean('whitelabel_enabled')->default(false)->after('disable_proxy_protocol');
            }
            if (!Schema::hasColumn('papyrus_settings', 'whitelabel_name')) {
                $table->string('whitelabel_name')->nullable()->after('whitelabel_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('papyrus_settings', function (Blueprint $table) {
            $table->dropColumn(['whitelabel_enabled', 'whitelabel_name']);
        });
    }
};
