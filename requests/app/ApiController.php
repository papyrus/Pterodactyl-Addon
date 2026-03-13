<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier};

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Services\PapyrusApiService;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Services\CloudflareApiService;

class ApiController extends Controller
{
    /**
     * GET /overview — Overview of all servers and their protection status.
     */
    public function overview(): JsonResponse
    {
        $servers = Server::with('allocation', 'node')->get();
        $proxies = DB::table('papyrus_proxies')->where('enabled', true)->get()->keyBy('server_id');

        $protectedServers = [];
        foreach ($proxies as $proxy) {
            $server = $servers->firstWhere('id', $proxy->server_id);
            $protectedServers[] = [
                'server_id' => $proxy->server_id,
                'server_name' => $server?->name,
                'protected_address' => $proxy->protected_address,
                'backend_address' => $proxy->backend_address,
                'backend_port' => $proxy->backend_port,
                'status' => $proxy->proxy_status,
                'created_at' => $proxy->created_at,
            ];
        }

        return response()->json([
            'total_servers' => $servers->count(),
            'protected_count' => $proxies->count(),
            'protected_servers' => $protectedServers,
        ]);
    }

    /**
     * GET /servers/{server}/status — Check protection status for a server.
     */
    public function status(int $server): JsonResponse
    {
        $serverModel = Server::with('allocation', 'node')->findOrFail($server);

        $proxy = DB::table('papyrus_proxies')
            ->where('server_id', $serverModel->id)
            ->where('enabled', true)
            ->first();

        if (!$proxy) {
            return response()->json(['protected' => false]);
        }

        return response()->json([
            'protected' => true,
            'status' => $proxy->proxy_status,
            'protected_address' => $proxy->protected_address,
            'backend_address' => $proxy->backend_address,
            'backend_port' => $proxy->backend_port,
            'proxy_protocol_disabled' => (bool) $proxy->proxy_protocol_disabled,
            'created_at' => $proxy->created_at,
        ]);
    }

    /**
     * POST /servers/{server}/protect — Enable protection on a server.
     */
    public function protect(Request $request, int $server): JsonResponse
    {
        $serverModel = Server::with('allocation', 'node')->findOrFail($server);
        $settings = DB::table('papyrus_settings')->first();

        if (!$settings || !$settings->papyrus_api_key || !$settings->cloudflare_api_key) {
            return response()->json([
                'success' => false,
                'error' => 'API keys must be configured before enabling protection.',
            ], 422);
        }

        if (!$settings->cloudflare_zone_id || !$settings->base_domain) {
            return response()->json([
                'success' => false,
                'error' => 'Cloudflare Zone ID and Base Domain must be configured before enabling protection.',
            ], 422);
        }

        $existingProxy = DB::table('papyrus_proxies')->where('server_id', $serverModel->id)->first();
        if ($existingProxy) {
            return response()->json([
                'success' => false,
                'error' => 'Server already has protection enabled.',
            ], 409);
        }

        $cnameTarget = $request->input('cname_target', $settings->default_cname_target ?? 'spectrum-01.papyrus.vip');

        return $this->enableProtection($serverModel, $settings, $cnameTarget);
    }

    /**
     * DELETE /servers/{server}/protect — Disable protection on a server.
     */
    public function unprotect(int $server): JsonResponse
    {
        $serverModel = Server::with('allocation', 'node')->findOrFail($server);
        $settings = DB::table('papyrus_settings')->first();

        if (!$settings || !$settings->papyrus_api_key || !$settings->cloudflare_api_key) {
            return response()->json([
                'success' => false,
                'error' => 'API keys must be configured.',
            ], 422);
        }

        $proxy = DB::table('papyrus_proxies')->where('server_id', $serverModel->id)->first();
        if (!$proxy) {
            return response()->json([
                'success' => false,
                'error' => 'Server does not have protection enabled.',
            ], 404);
        }

        return $this->disableProtection($serverModel, $proxy, $settings);
    }

    /**
     * Enable protection: Create Papyrus proxy → Create DNS → Hide IP.
     */
    private function enableProtection(Server $server, $settings, string $cnameTarget): JsonResponse
    {
        $papyrus = new PapyrusApiService(decrypt($settings->papyrus_api_key));
        $cloudflare = new CloudflareApiService(decrypt($settings->cloudflare_api_key), $settings->cloudflare_zone_id);

        $allocation = $server->allocation;
        $backendIp = $allocation->ip;
        $backendPort = $allocation->port;

        // Resolve 0.0.0.0 to the node's public IP
        if ($backendIp === '0.0.0.0') {
            $nodeIp = $server->node->fqdn;
            if (!filter_var($nodeIp, FILTER_VALIDATE_IP)) {
                $resolved = gethostbyname($nodeIp);
                if ($resolved !== $nodeIp) {
                    $nodeIp = $resolved;
                }
            }
            $backendIp = $nodeIp;
        }

        // Build full antibot config with proper Papyrus API field names
        $antibotConfig = json_decode($settings->default_antibot_config ?? '{}', true) ?: [];
        $antibotConfig = array_merge([
            'tcp_starvation_enabled' => true,
            'tcp_starvation_ban' => true,
            'tcp_starvation_notify' => false,
            'invalid_name_enabled' => true,
            'invalid_name_ban' => true,
            'invalid_name_notify' => false,
            'invalid_name_kick_v1' => "&8&l#m------------&cPapyrus.vip - Blaze Proxy &8&l#m------------\n&7Your connection has been disconnected for security reasons\n&7~ ~ ~ v1 ~ ~ ~\n&8&l#m------------------------------------",
            'invalid_name_kick_v2' => "&8&l#m------------&cPapyrus.vip - Blaze Proxy &8&l#m------------\n&7Your connection has been disconnected for security reasons\n&7~ ~ ~ v2 ~ ~ ~\n&8&l#m------------------------------------",
            'blacklist_enabled' => true,
            'blacklist_ban' => true,
            'blacklist_notify' => false,
            'blacklist_attempting' => false,
            'blacklist_kick' => "&8&l#m------------&cPapyrus.vip - Blaze Proxy &8&l#m------------\n&7Your connection has been disconnected for security reasons\n&7~ ~ ~ v3 ~ ~ ~\n&8&l#m------------------------------------",
            'blacklist_kick_attempting' => "&8&l#m------------&cPapyrus.vip - Blaze Proxy &8&l#m------------\n&7Your connection has been disconnected for security reasons\n&7~ ~ ~ v4 ~ ~ ~\n&8&l#m------------------------------------",
            'invalid_packets_enabled' => true,
            'invalid_packets_ban' => true,
            'invalid_packets_notify' => false,
            'burst_enabled' => true,
            'burst_ban' => true,
            'burst_notify' => false,
            'bad_bytebuf_enabled' => true,
            'bad_bytebuf_ban' => true,
            'bad_bytebuf_notify' => false,
            'bad_bytebuf_violations' => 3,
            'bad_bytebuf_max_packet_size' => 200000,
            'bad_bytebuf_max_packet_kick' => "&8&l#m------------&cPapyrus.vip - Blaze Proxy &8&l#m------------\n&7Your connection has been disconnected for security reasons\n&7~ ~ ~ v5 ~ ~ ~\n&8&l#m------------------------------------",
            'captcha_enabled' => (bool) ($settings->default_captcha_enabled ?? true),
            'captcha_ban' => false,
            'captcha_notify' => false,
            'captcha_use_compression' => false,
            'captcha_compression_threshold' => 256,
            'captcha_violations' => 3,
            'reconnect_enabled' => true,
            'reconnect_ban' => false,
            'reconnect_notify' => false,
            'reconnect_kick' => "&8&l#m------------&cPapyrus.vip - Blaze Proxy &8&l#m------------\n&7Please wait &c3 seconds &7and reconnect\n&7Pending reconnects: &c%remaining%\n&8&l#m------------------------------------",
            'limbo_enabled' => (bool) ($settings->default_limbo_enabled ?? true),
            'limbo_max_attempts' => true,
            'limbo_max_attempts_value' => 3,
            'limbo_ban' => false,
            'limbo_notify' => false,
            'limbo_extra_checks' => false,
            'limbo_join_message' => '&bBlaze &8> &fVerifying your connection... Please wait. :D',
            'limbo_enter_code_message' => '&bBlaze &8> &fPlease enter the text in chat that is displayed on the map.',
            'limbo_attempts_message' => '&bBlaze &8> &cYour captcha is invalid. &f(attempts-left) attempts &cleft.',
            'limbo_verified_kick' => "&8&l#m------------&r Blaze &8&l#m------------\n&fYour connection was verified successfully.\n&fYou may now rejoin the server\n&8&l#m------------------------------------",
            'limbo_failed_kick' => "&8&l#m------------&r Blaze &8&l#m------------\n&cYour connection was disconnected.\n&cPlease check your internet connection before joining again.\n&8&l#m------------------------------------",
        ], $antibotConfig);

        $defaultKickMessages = json_decode($settings->default_kick_messages ?? '{}', true) ?: [];

        // Generate subdomain before proxy creation so it can be passed to dnsToBindArray
        $subdomain = $cloudflare->generateSubdomain(
            $settings->subdomain_pattern ?? '{server_name}',
            $server,
            $settings->base_domain
        );

        // Step 1: Create Papyrus proxy
        try {
            $proxyConfig = $papyrus->buildProxyConfig(
                name: $server->name,
                serverShortId: $server->uuidShort,
                backendIp: $backendIp,
                backendPort: $backendPort,
                disableProxyProtocol: (bool) $settings->disable_proxy_protocol,
                cpsThreshold: $settings->default_cps_threshold ?? 30,
                antibotConfig: $antibotConfig,
                dnsToBindArray: [$subdomain],
                motdUp: $settings->default_motd_up ?? '%center%&bPapyrus.vip - Server Offline',
                motdDown: $settings->default_motd_down ?? '%center%&cUnexpected error.',
                chatReports: (bool) $settings->default_chat_reports,
            );

            $proxyResult = $papyrus->createProxy($proxyConfig);
        } catch (\Exception $e) {
            Log::error('Failed to create Papyrus proxy', ['error' => $e->getMessage(), 'server_id' => $server->id]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to create proxy: ' . $e->getMessage(),
            ], 500);
        }

        // Papyrus may nest response under 'data' key
        $proxyData = $proxyResult['data'] ?? $proxyResult;
        $proxyId = $proxyData['_id'] ?? $proxyData['id'] ?? null;

        if (!$proxyId) {
            Log::error('Papyrus API returned no proxy ID', ['result' => $proxyResult]);
            return response()->json([
                'success' => false,
                'error' => 'Papyrus returned no proxy ID. Check laravel.log for full response.',
            ], 500);
        }

        // Step 2: Create Cloudflare DNS record pointing to the CNAME target
        try {
            $isIp = filter_var($cnameTarget, FILTER_VALIDATE_IP);
            $dnsResult = $cloudflare->createDnsRecord($subdomain, $cnameTarget, $isIp ? 'A' : 'CNAME');
        } catch (\Exception $e) {
            // Rollback: delete the Papyrus proxy
            Log::error('Cloudflare DNS creation failed, rolling back Papyrus proxy', ['error' => $e->getMessage()]);
            try {
                $papyrus->deleteProxy($proxyId);
            } catch (\Exception $rollbackError) {
                Log::error('Rollback also failed', ['error' => $rollbackError->getMessage()]);
            }
            return response()->json([
                'success' => false,
                'error' => 'Failed to create DNS record (proxy rolled back): ' . $e->getMessage(),
            ], 500);
        }

        // Step 3: Store proxy record
        $protectedAddress = $subdomain;
        DB::table('papyrus_proxies')->insert([
            'server_id' => $server->id,
            'papyrus_proxy_id' => $proxyId,
            'proxy_status' => 'active',
            'protected_address' => $protectedAddress,
            'cname_target' => $cnameTarget,
            'cloudflare_record_id' => $dnsResult['id'] ?? null,
            'original_allocation_ip' => $allocation->ip_alias,
            'original_allocation_port' => $backendPort,
            'backend_address' => $backendIp,
            'backend_port' => $backendPort,
            'ip_replaced' => false,
            'proxy_protocol_disabled' => (bool) $settings->disable_proxy_protocol,
            'enabled' => true,
            'antibot_config' => json_encode($antibotConfig),
            'motd_up' => $settings->default_motd_up,
            'motd_down' => $settings->default_motd_down,
            'kick_messages' => json_encode($defaultKickMessages),
            'allow_motd_edit' => true,
            'allow_messages_edit' => true,
            'domain_limit' => $settings->default_domain_limit ?? 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Step 4: Hide real IP by setting allocation ip_alias
        if ($settings->hide_real_ip) {
            $allocation->update(['ip_alias' => $protectedAddress]);
            DB::table('papyrus_proxies')
                ->where('server_id', $server->id)
                ->update(['ip_replaced' => true]);
        }

        // Step 5: Inject egress proxy via JAVA_TOOL_OPTIONS
        if ($settings->egress_proxy_enabled && $settings->egress_proxy_host) {
            $originalStartup = $server->startup;
            $javaOpts = $this->buildEgressProxyArgs($settings);

            if ($javaOpts) {
                $server->update(['startup' => 'JAVA_TOOL_OPTIONS="' . $javaOpts . '" ' . $originalStartup]);
                DB::table('papyrus_proxies')
                    ->where('server_id', $server->id)
                    ->update(['original_startup' => $originalStartup]);
            }
        }

        DB::table('papyrus_activity_log')->insert([
            'server_id' => $server->id,
            'user_id' => null,
            'action' => 'protection.enabled',
            'metadata' => json_encode(['protected_address' => $protectedAddress, 'server_name' => $server->name, 'source' => 'application_api']),
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'protected_address' => $protectedAddress,
            'proxy_id' => $proxyId,
        ]);
    }

    /**
     * Disable protection: Delete proxy → Delete DNS → Restore IP.
     */
    private function disableProtection(Server $server, $proxy, $settings): JsonResponse
    {
        $papyrus = new PapyrusApiService(decrypt($settings->papyrus_api_key));
        $cloudflare = new CloudflareApiService(decrypt($settings->cloudflare_api_key), $settings->cloudflare_zone_id);

        // Step 1: Delete Papyrus proxy
        if ($proxy->papyrus_proxy_id) {
            try {
                $papyrus->deleteProxy($proxy->papyrus_proxy_id);
            } catch (\Exception $e) {
                Log::error('Failed to delete Papyrus proxy', ['error' => $e->getMessage()]);
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to delete proxy: ' . $e->getMessage(),
                ], 500);
            }
        }

        // Step 2: Delete Cloudflare DNS record
        if ($proxy->cloudflare_record_id) {
            try {
                $cloudflare->deleteDnsRecord($proxy->cloudflare_record_id);
            } catch (\Exception $e) {
                Log::warning('Failed to delete DNS record (continuing anyway)', ['error' => $e->getMessage()]);
            }
        }

        // Step 3: Restore allocation IP alias if it was replaced
        if ($proxy->ip_replaced ?? $settings->hide_real_ip) {
            $allocation = $server->allocation;
            $allocation->update(['ip_alias' => $proxy->original_allocation_ip]);
        }

        // Restore original startup if it was modified for egress proxy
        if ($proxy->original_startup) {
            $server->update(['startup' => $proxy->original_startup]);
        }

        // Step 4: Remove proxy record
        DB::table('papyrus_proxies')->where('id', $proxy->id)->delete();

        DB::table('papyrus_activity_log')->insert([
            'server_id' => $server->id,
            'user_id' => null,
            'action' => 'protection.disabled',
            'metadata' => json_encode(['server_name' => $server->name, 'source' => 'application_api']),
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Build JVM arguments for egress proxy.
     */
    private function buildEgressProxyArgs($settings): string
    {
        $args = [];

        if ($settings->egress_proxy_type === 'socks5') {
            $args[] = '-DsocksProxyHost=' . $settings->egress_proxy_host;
            $args[] = '-DsocksProxyPort=' . ($settings->egress_proxy_port ?? 1080);
            if ($settings->egress_proxy_username) {
                $args[] = '-Djava.net.socks.username=' . $settings->egress_proxy_username;
            }
            if ($settings->egress_proxy_password) {
                $args[] = '-Djava.net.socks.password=' . decrypt($settings->egress_proxy_password);
            }
        } else {
            $host = $settings->egress_proxy_host;
            $port = $settings->egress_proxy_port ?? 3128;
            $args[] = '-Dhttp.proxyHost=' . $host;
            $args[] = '-Dhttp.proxyPort=' . $port;
            $args[] = '-Dhttps.proxyHost=' . $host;
            $args[] = '-Dhttps.proxyPort=' . $port;
        }

        return implode(' ', $args);
    }
}
