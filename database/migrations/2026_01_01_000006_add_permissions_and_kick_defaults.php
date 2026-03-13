<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('papyrus_proxies', function (Blueprint $table) {
            if (!Schema::hasColumn('papyrus_proxies', 'allow_motd_edit')) {
                $table->boolean('allow_motd_edit')->default(true)->after('kick_messages');
            }
            if (!Schema::hasColumn('papyrus_proxies', 'allow_messages_edit')) {
                $table->boolean('allow_messages_edit')->default(true)->after('allow_motd_edit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('papyrus_proxies', function (Blueprint $table) {
            $table->dropColumn(['allow_motd_edit', 'allow_messages_edit']);
        });
    }
};
