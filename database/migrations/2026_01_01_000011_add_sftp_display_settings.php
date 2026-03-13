<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('papyrus_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('papyrus_settings', 'sftp_display_mode')) {
                $table->string('sftp_display_mode', 10)->default('show')->after('egress_proxy_password'); // show, hide, rename
                $table->string('sftp_custom_host')->nullable()->after('sftp_display_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('papyrus_settings', function (Blueprint $table) {
            $table->dropColumn(['sftp_display_mode', 'sftp_custom_host']);
        });
    }
};
