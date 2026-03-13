<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier};

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\Server;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Services\PapyrusApiService;

class MessagesController extends Controller
{
    /**
     * Get current MOTD and kick messages for a server.
     */
    public function index(Request $request, Server $server): JsonResponse
    {
        $proxy = DB::table('papyrus_proxies')
            ->where('server_id', $server->id)
            ->first();

        if (!$proxy) {
            return response()->json(['error' => 'Protection not enabled.'], 422);
        }

        $antibotConfig = json_decode($proxy->antibot_config ?? '{}', true);
        $kickMessages = json_decode($proxy->kick_messages ?? '{}', true);

        // Merge kick messages from antibot_config (source of truth from Papyrus API)
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
            'motd_up' => $proxy->motd_up,
            'motd_down' => $proxy->motd_down,
            'kick_messages' => $kickMessages,
            'allow_motd_edit' => (bool) ($proxy->allow_motd_edit ?? true),
            'allow_messages_edit' => (bool) ($proxy->allow_messages_edit ?? true),
        ]);
    }

    /**
     * Update MOTD and kick messages, push to Papyrus.
     */
    public function update(Request $request, Server $server): JsonResponse
    {
        $proxy = DB::table('papyrus_proxies')
            ->where('server_id', $server->id)
            ->first();

        if (!$proxy) {
            return response()->json(['error' => 'Protection not enabled.'], 422);
        }

        $validated = $request->validate([
            'motd_up' => 'nullable|string',
            'motd_down' => 'nullable|string',
            'kick_messages' => 'nullable|array',
        ]);

        // Check per-server permissions
        if (array_key_exists('motd_up', $validated) || array_key_exists('motd_down', $validated)) {
            if (!($proxy->allow_motd_edit ?? true)) {
                return response()->json(['error' => 'MOTD editing is disabled for this server by your hosting provider.'], 403);
            }
        }
        if (isset($validated['kick_messages']) && !empty($validated['kick_messages'])) {
            if (!($proxy->allow_messages_edit ?? true)) {
                return response()->json(['error' => 'Message editing is disabled for this server by your hosting provider.'], 403);
            }
        }

        $settings = DB::table('papyrus_settings')->first();
        if (!$settings || !$settings->papyrus_api_key) {
            return response()->json(['error' => 'API not configured.'], 500);
        }

        try {
            $papyrus = new PapyrusApiService(decrypt($settings->papyrus_api_key));

            $updateData = [];

            // Update MOTD fields (top-level Papyrus API fields)
            if (($proxy->allow_motd_edit ?? true) && array_key_exists('motd_up', $validated)) {
                $updateData['motd-up'] = $validated['motd_up'] ?? '';
            }
            if (($proxy->allow_motd_edit ?? true) && array_key_exists('motd_down', $validated)) {
                $updateData['motd-down'] = $validated['motd_down'] ?? '';
            }

            // Update kick messages (these live inside antibot-config in Papyrus API)
            if (($proxy->allow_messages_edit ?? true) && isset($validated['kick_messages'])) {
                $currentAntibot = json_decode($proxy->antibot_config ?? '{}', true);
                $kickFields = [
                    'reconnect_kick', 'invalid_name_kick_v1', 'invalid_name_kick_v2',
                    'blacklist_kick', 'blacklist_kick_attempting', 'bad_bytebuf_max_packet_kick',
                    'limbo_join_message', 'limbo_enter_code_message', 'limbo_attempts_message',
                    'limbo_verified_kick', 'limbo_failed_kick',
                ];
                foreach ($kickFields as $field) {
                    if (isset($validated['kick_messages'][$field])) {
                        $currentAntibot[$field] = $validated['kick_messages'][$field];
                    }
                }
                $updateData['antibot-config'] = $currentAntibot;
            }

            if (!empty($updateData)) {
                $papyrus->updateProxy($proxy->papyrus_proxy_id, $updateData);
            }

            // Save locally
            $dbUpdate = ['updated_at' => now()];
            if (($proxy->allow_motd_edit ?? true)) {
                $dbUpdate['motd_up'] = $validated['motd_up'] ?? $proxy->motd_up;
                $dbUpdate['motd_down'] = $validated['motd_down'] ?? $proxy->motd_down;
            }
            if (($proxy->allow_messages_edit ?? true) && isset($validated['kick_messages'])) {
                $dbUpdate['kick_messages'] = json_encode($validated['kick_messages']);
                if (isset($updateData['antibot-config'])) {
                    $dbUpdate['antibot_config'] = json_encode($updateData['antibot-config']);
                }
            }

            DB::table('papyrus_proxies')
                ->where('id', $proxy->id)
                ->update($dbUpdate);

            DB::table('papyrus_activity_log')->insert([
                'server_id' => $server->id,
                'user_id' => $request->user()->id,
                'action' => 'messages.updated',
                'metadata' => json_encode($validated),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json(['message' => 'Messages updated.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reset messages to admin defaults.
     */
    public function reset(Request $request, Server $server): JsonResponse
    {
        $proxy = DB::table('papyrus_proxies')
            ->where('server_id', $server->id)
            ->first();

        if (!$proxy) {
            return response()->json(['error' => 'Protection not enabled.'], 422);
        }

        if (!($proxy->allow_motd_edit ?? true) && !($proxy->allow_messages_edit ?? true)) {
            return response()->json(['error' => 'Editing is disabled for this server by your hosting provider.'], 403);
        }

        $settings = DB::table('papyrus_settings')->first();
        $defaultKick = json_decode($settings->default_kick_messages ?? '{}', true) ?: [];

        try {
            $papyrus = new PapyrusApiService(decrypt($settings->papyrus_api_key));

            // Rebuild antibot config with default kick messages merged in
            $currentAntibot = json_decode($proxy->antibot_config ?? '{}', true);
            foreach ($defaultKick as $field => $value) {
                $currentAntibot[$field] = $value;
            }

            $papyrus->updateProxy($proxy->papyrus_proxy_id, [
                'motd-up' => $settings->default_motd_up ?? '',
                'motd-down' => $settings->default_motd_down ?? '',
                'antibot-config' => $currentAntibot,
            ]);

            DB::table('papyrus_proxies')
                ->where('id', $proxy->id)
                ->update([
                    'motd_up' => $settings->default_motd_up,
                    'motd_down' => $settings->default_motd_down,
                    'kick_messages' => json_encode($defaultKick),
                    'antibot_config' => json_encode($currentAntibot),
                    'updated_at' => now(),
                ]);

            DB::table('papyrus_activity_log')->insert([
                'server_id' => $server->id,
                'user_id' => $request->user()->id,
                'action' => 'messages.reset',
                'metadata' => json_encode(['defaults' => true]),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'message' => 'Messages reset to defaults.',
                'motd_up' => $settings->default_motd_up,
                'motd_down' => $settings->default_motd_down,
                'kick_messages' => $defaultKick,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to reset: ' . $e->getMessage()], 500);
        }
    }
}
