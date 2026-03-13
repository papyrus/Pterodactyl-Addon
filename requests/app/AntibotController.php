<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier};

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\Server;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Services\PapyrusApiService;

class AntibotController extends Controller
{
    /**
     * Get current antibot config for a server.
     */
    public function index(Request $request, Server $server): JsonResponse
    {
        $proxy = DB::table('papyrus_proxies')
            ->where('server_id', $server->id)
            ->first();

        if (!$proxy) {
            return response()->json(['error' => 'Protection not enabled.'], 422);
        }

        return response()->json([
            'antibot_config' => json_decode($proxy->antibot_config, true) ?? [],
        ]);
    }

    /**
     * Update antibot settings and push to Papyrus.
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
            'antibot_config' => 'required|array',
        ]);

        $settings = DB::table('papyrus_settings')->first();
        if (!$settings || !$settings->papyrus_api_key) {
            return response()->json(['error' => 'API not configured.'], 500);
        }

        try {
            $papyrus = new PapyrusApiService(decrypt($settings->papyrus_api_key));
            $papyrus->updateProxy($proxy->papyrus_proxy_id, [
                'antibot-config' => $validated['antibot_config'],
            ]);

            DB::table('papyrus_proxies')
                ->where('id', $proxy->id)
                ->update([
                    'antibot_config' => json_encode($validated['antibot_config']),
                    'updated_at' => now(),
                ]);

            DB::table('papyrus_activity_log')->insert([
                'server_id' => $server->id,
                'user_id' => $request->user()->id,
                'action' => 'antibot.updated',
                'metadata' => json_encode($validated['antibot_config']),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json(['message' => 'Antibot settings updated.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Toggle proxy protocol on/off and push to Papyrus.
     */
    public function toggleProxyProtocol(Request $request, Server $server): JsonResponse
    {
        $proxy = DB::table('papyrus_proxies')
            ->where('server_id', $server->id)
            ->first();

        if (!$proxy) {
            return response()->json(['error' => 'Protection not enabled.'], 422);
        }

        $validated = $request->validate([
            'proxy_protocol_disabled' => 'required|boolean',
        ]);

        $settings = DB::table('papyrus_settings')->first();
        if (!$settings || !$settings->papyrus_api_key) {
            return response()->json(['error' => 'API not configured.'], 500);
        }

        try {
            $papyrus = new PapyrusApiService(decrypt($settings->papyrus_api_key));
            $papyrus->updateProxy($proxy->papyrus_proxy_id, [
                'instances' => [
                    [
                        'name' => 'primary',
                        'host' => $proxy->backend_address,
                        'port' => $proxy->backend_port,
                        'disable-proxy-protocol' => (bool) $validated['proxy_protocol_disabled'],
                    ],
                ],
            ]);

            DB::table('papyrus_proxies')
                ->where('id', $proxy->id)
                ->update([
                    'proxy_protocol_disabled' => (bool) $validated['proxy_protocol_disabled'],
                    'updated_at' => now(),
                ]);

            return response()->json([
                'message' => 'Proxy protocol setting updated.',
                'proxy_protocol_disabled' => (bool) $validated['proxy_protocol_disabled'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reset antibot config to admin defaults.
     */
    public function reset(Request $request, Server $server): JsonResponse
    {
        $proxy = DB::table('papyrus_proxies')
            ->where('server_id', $server->id)
            ->first();

        if (!$proxy) {
            return response()->json(['error' => 'Protection not enabled.'], 422);
        }

        $settings = DB::table('papyrus_settings')->first();
        $defaults = json_decode($settings->default_antibot_config ?? '{}', true) ?: [
            'captcha_enabled' => (bool) ($settings->default_captcha_enabled ?? true),
            'limbo_enabled' => (bool) ($settings->default_limbo_enabled ?? true),
        ];

        try {
            $papyrus = new PapyrusApiService(decrypt($settings->papyrus_api_key));
            $papyrus->updateProxy($proxy->papyrus_proxy_id, [
                'antibot-config' => $defaults,
            ]);

            DB::table('papyrus_proxies')
                ->where('id', $proxy->id)
                ->update([
                    'antibot_config' => json_encode($defaults),
                    'updated_at' => now(),
                ]);

            DB::table('papyrus_activity_log')->insert([
                'server_id' => $server->id,
                'user_id' => $request->user()->id,
                'action' => 'antibot.reset',
                'metadata' => json_encode(['defaults' => $defaults]),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json(['message' => 'Antibot settings reset to defaults.', 'antibot_config' => $defaults]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to reset: ' . $e->getMessage()], 500);
        }
    }
}
