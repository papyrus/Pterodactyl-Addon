<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('papyrus_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('papyrus_settings', 'auto_protect_enabled')) {
                $table->boolean('auto_protect_enabled')->default(false)->after('sftp_custom_host');
                $table->text('auto_protect_eggs')->nullable()->after('auto_protect_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('papyrus_settings', function (Blueprint $table) {
            $table->dropColumn(['auto_protect_enabled', 'auto_protect_eggs']);
        });
    }
};
