<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Listeners;

use Pterodactyl\Events\Server\Installed;
use Pterodactyl\Models\Server;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Services\PapyrusApiService;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Services\CloudflareApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ServerInstalledListener
{
    public function handle(Installed $event): void
    {
        try {
            $server = $event->server;
            $server->load('allocation', 'node');

            $settings = DB::table('papyrus_settings')->first();

            if (!$settings || !$settings->auto_protect_enabled) {
                return;
            }

            $autoProtectEggs = json_decode($settings->auto_protect_eggs ?? '[]', true) ?: [];
            if (empty($autoProtectEggs) || !in_array($server->egg_id, $autoProtectEggs)) {
                return;
            }

            if (!$settings->papyrus_api_key || !$settings->cloudflare_api_key) {
                Log::warning('Papyrus auto-protect: API keys not configured, skipping server', ['server_id' => $server->id]);
                return;
            }

            $existingProxy = DB::table('papyrus_proxies')->where('server_id', $server->id)->first();
            if ($existingProxy) {
                return;
            }

            $this->enableProtection($server, $settings);
        } catch (\Exception $e) {
            Log::error('Papyrus auto-protect failed', [
                'server_id' => $event->server->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function enableProtection(Server $server, $settings): void
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

        $cnameTarget = $settings->default_cname_target ?? 'spectrum-01.papyrus.vip';

        // Generate subdomain before proxy creation so it can be passed to dnsToBindArray
        $subdomain = $cloudflare->generateSubdomain(
            $settings->subdomain_pattern ?? '{server_name}',
            $server,
            $settings->base_domain
        );

        // Step 1: Create Papyrus proxy
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

        $proxyData = $proxyResult['data'] ?? $proxyResult;
        $proxyId = $proxyData['_id'] ?? $proxyData['id'] ?? null;

        if (!$proxyId) {
            Log::error('Papyrus auto-protect: API returned no proxy ID', ['result' => $proxyResult, 'server_id' => $server->id]);
            return;
        }

        // Step 2: Create Cloudflare DNS record pointing to the CNAME target
        try {
            $isIp = filter_var($cnameTarget, FILTER_VALIDATE_IP);
            $dnsResult = $cloudflare->createDnsRecord($subdomain, $cnameTarget, $isIp ? 'A' : 'CNAME');
        } catch (\Exception $e) {
            Log::error('Papyrus auto-protect: Cloudflare DNS creation failed, rolling back proxy', [
                'error' => $e->getMessage(),
                'server_id' => $server->id,
            ]);
            try {
                $papyrus->deleteProxy($proxyId);
            } catch (\Exception $rollbackError) {
                Log::error('Papyrus auto-protect: Rollback also failed', ['error' => $rollbackError->getMessage()]);
            }
            return;
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
            'action' => 'protection.auto_enabled',
            'metadata' => json_encode(['protected_address' => $protectedAddress, 'server_name' => $server->name]),
            'ip_address' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('Papyrus auto-protect: Protection enabled', [
            'server_id' => $server->id,
            'server_name' => $server->name,
            'protected_address' => $protectedAddress,
        ]);
    }

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
