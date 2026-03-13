<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier};

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\Server;

class ProtectionController extends Controller
{
    public function index(Request $request, Server $server): JsonResponse
    {
        $proxy = DB::table('papyrus_proxies')
            ->where('server_id', $server->id)
            ->where('enabled', true)
            ->first();

        if (!$proxy || $proxy->proxy_status !== 'active') {
            return response()->json([
                'protected' => false,
                'message' => 'DDoS protection is not enabled for this server.',
            ]);
        }

        $settings = DB::table('papyrus_settings')->first();
        $antibotConfig = json_decode($proxy->antibot_config ?? '{}', true);
        $kickMessages = json_decode($proxy->kick_messages ?? '{}', true);

        // Merge kick messages from antibot_config (where Papyrus API stores them)
        $kickFields = [
            'reconnect_kick', 'invalid_name_kick_v1', 'invalid_name_kick_v2',
            'blacklist_kick', 'blacklist_kick_attempting', 'bad_bytebuf_max_packet_kick',
            'limbo_join_message', 'limbo_enter_code_message', 'limbo_attempts_message',
            'limbo_verified_kick', 'limbo_failed_kick',
        ];
        foreach ($kickFields as $field) {
            if (isset($antibotConfig[$field]) && !isset($kickMessages[$field])) {
                $kickMessages[$field] = $antibotConfig[$field];
            }
        }

        return response()->json([
            'protected' => true,
            'status' => $proxy->proxy_status,
            'protected_address' => $proxy->protected_address,
            'backend_port' => $proxy->backend_port,
            'proxy_protocol_disabled' => (bool) $proxy->proxy_protocol_disabled,
            'antibot_config' => $antibotConfig,
            'motd_up' => $proxy->motd_up ?? '',
            'motd_down' => $proxy->motd_down ?? '',
            'kick_messages' => $kickMessages,
            'allow_motd_edit' => (bool) ($proxy->allow_motd_edit ?? true),
            'allow_messages_edit' => (bool) ($proxy->allow_messages_edit ?? true),
            'whitelabel' => (bool) ($settings->whitelabel_enabled ?? false),
            'branding_name' => ($settings->whitelabel_enabled ?? false) ? ($settings->whitelabel_name ?? 'DDoS Protection') : 'Papyrus.vip',
            'created_at' => $proxy->created_at,
            'sftp_display_mode' => $settings->sftp_display_mode ?? 'show',
            'sftp_custom_host' => $settings->sftp_custom_host ?? '',
        ]);
    }
}
