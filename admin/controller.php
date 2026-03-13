<?php

namespace Pterodactyl\Http\Controllers\Admin\Extensions\{identifier};

use Illuminate\View\View;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\BlueprintFramework\Libraries\ExtensionLibrary\Admin\BlueprintAdminLibrary as BlueprintExtensionLibrary;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Services\PapyrusApiService;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Services\CloudflareApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class {identifier}ExtensionController extends Controller
{
    public function __construct(
        private BlueprintExtensionLibrary $blueprint,
        private ViewFactory $view,
    ) {}

    /**
     * Admin extension index page.
     */
    public function index(): View
    {
        $settings = DB::table('papyrus_settings')->first();
        $servers = Server::with('allocation', 'node')->get();
        $proxies = DB::table('papyrus_proxies')->get()->keyBy('server_id');
        $eggs = DB::table('eggs')->select('eggs.id', 'eggs.name', 'nests.name as nest_name')
            ->leftJoin('nests', 'eggs.nest_id', '=', 'nests.id')
            ->orderBy('nests.name')->orderBy('eggs.name')->get();

        return $this->view->make('admin.extensions.{identifier}.index', [
            'blueprint' => $this->blueprint,
            'root' => '/admin/extensions/{identifier}',
            'settings' => $settings,
            'servers' => $servers,
            'proxies' => $proxies,
            'eggs' => $eggs,
        ]);
    }

    /**
     * Save admin settings (PATCH).
     */
    public function update(Request $request): RedirectResponse
    {
        $section = $request->input('_section', 'api');

        if ($section === 'api') {
            return $this->updateApiSettings($request);
        }

        return $this->updateProtectionDefaults($request);
    }

    private function updateApiSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'papyrus_api_key' => 'nullable|string',
            'cloudflare_api_key' => 'nullable|string',
            'cloudflare_zone_id' => 'nullable|string|max:255',
            'base_domain' => 'nullable|string|max:255',
            'subdomain_pattern' => 'nullable|string|max:255',
            'default_cname_target' => 'nullable|string|max:255',
        ]);

        if (!empty($validated['papyrus_api_key'])) {
            $validated['papyrus_api_key'] = encrypt($validated['papyrus_api_key']);
        } else {
            unset($validated['papyrus_api_key']);
        }
        if (!empty($validated['cloudflare_api_key'])) {
            $validated['cloudflare_api_key'] = encrypt($validated['cloudflare_api_key']);
        } else {
            unset($validated['cloudflare_api_key']);
        }

        $this->upsertSettings($validated);

        return redirect()->route('admin.extensions.{identifier}.index')
            ->with('success', 'API settings saved.');
    }

    private function updateProtectionDefaults(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_cps_threshold' => 'nullable|integer|min:1|max:1000',
            'default_motd_up' => 'nullable|string',
            'default_motd_down' => 'nullable|string',
        ]);

        $validated['default_captcha_enabled'] = $request->has('default_captcha_enabled');
        $validated['default_limbo_enabled'] = $request->has('default_limbo_enabled');
        $validated['default_chat_reports'] = $request->has('default_chat_reports');
        $validated['hide_real_ip'] = $request->has('hide_real_ip');
        $validated['disable_proxy_protocol'] = $request->has('disable_proxy_protocol');
        $validated['whitelabel_enabled'] = $request->has('whitelabel_enabled');
        if ($request->has('whitelabel_name')) {
            $validated['whitelabel_name'] = $request->input('whitelabel_name');
        }
        if ($request->has('default_domain_limit')) {
            $validated['default_domain_limit'] = (int) $request->input('default_domain_limit', 3);
        }

        $validated['egress_proxy_enabled'] = $request->has('egress_proxy_enabled');
        $validated['egress_proxy_type'] = $request->input('egress_proxy_type', 'socks5');
        $validated['egress_proxy_host'] = $request->input('egress_proxy_host');
        $validated['egress_proxy_port'] = $request->input('egress_proxy_port') ? (int) $request->input('egress_proxy_port') : null;
        $validated['egress_proxy_username'] = $request->input('egress_proxy_username');

        $proxyPassword = $request->input('egress_proxy_password');
        if ($proxyPassword && $proxyPassword !== '•••••••••••') {
            $validated['egress_proxy_password'] = encrypt($proxyPassword);
        } else if (!$proxyPassword) {
            $validated['egress_proxy_password'] = null;
        }

        $validated['sftp_display_mode'] = $request->input('sftp_display_mode', 'show');
        $validated['sftp_custom_host'] = $request->input('sftp_custom_host');

        // Collect kick message defaults from kick_* prefixed fields
        $kickFields = [
            'reconnect_kick', 'invalid_name_kick_v1', 'invalid_name_kick_v2',
            'blacklist_kick', 'blacklist_kick_attempting', 'bad_bytebuf_max_packet_kick',
            'limbo_join_message', 'limbo_enter_code_message', 'limbo_attempts_message',
            'limbo_verified_kick', 'limbo_failed_kick',
        ];
        $kickMessages = [];
        foreach ($kickFields as $field) {
            $value = $request->input('kick_' . $field);
            if ($value !== null) {
                $kickMessages[$field] = $value;
            }
        }
        if (!empty($kickMessages)) {
            $validated['default_kick_messages'] = json_encode($kickMessages);
        }

        $this->upsertSettings($validated);

        return redirect()->route('admin.extensions.{identifier}.index')
            ->with('success', 'Protection defaults saved.');
    }

    private function upsertSettings(array $data): void
    {
        $existing = DB::table('papyrus_settings')->first();
        if ($existing) {
            $data['updated_at'] = now();
            DB::table('papyrus_settings')->where('id', $existing->id)->update($data);
        } else {
            $data['created_at'] = now();
            $data['updated_at'] = now();
            DB::table('papyrus_settings')->insert($data);
        }
    }

    /**
     * Handle POST actions (toggle protection, test connection).
     */
    public function post(Request $request): RedirectResponse
    {
        $action = $request->input('action');

        if ($action === 'test_papyrus') {
            return $this->testPapyrusConnection();
        }

        if ($action === 'test_cloudflare') {
            return $this->testCloudflareConnection();
        }

        if ($action === 'toggle_protection') {
            return $this->toggleProtection($request);
        }

        if ($action === 'update_permissions') {
            return $this->updatePermissions($request);
        }

        if ($action === 'save_auto_protect') {
            return $this->saveAutoProtect($request);
        }

        return redirect()->route('admin.extensions.{identifier}.index')
            ->with('error', 'Unknown action.');
    }

    /**
     * Test Papyrus API connection.
     */
    private function testPapyrusConnection(): RedirectResponse
    {
        $settings = DB::table('papyrus_settings')->first();
        if (!$settings || !$settings->papyrus_api_key) {
            return redirect()->route('admin.extensions.{identifier}.index')
                ->with('error', 'Papyrus API key not configured.');
        }

        try {
            $service = new PapyrusApiService(decrypt($settings->papyrus_api_key));
            $account = $service->getAccount();
            $billing = $service->getBilling();

            $info = [];
            if (!empty($account['email'])) $info[] = $account['email'];
            if (!empty($billing['plan'])) $info[] = 'Plan: ' . $billing['plan'];
            if (!empty($billing['networks_limit'])) $info[] = 'Proxy Limit: ' . $billing['networks_limit'];

            $display = !empty($info) ? implode(' | ', $info) : 'Connected';

            return redirect()->route('admin.extensions.{identifier}.index')
                ->with('success', 'Papyrus API connection successful! ' . $display);
        } catch (\Exception $e) {
            return redirect()->route('admin.extensions.{identifier}.index')
                ->with('error', 'Papyrus API test failed: ' . $e->getMessage());
        }
    }

    /**
     * Test Cloudflare API connection.
     */
    private function testCloudflareConnection(): RedirectResponse
    {
        $settings = DB::table('papyrus_settings')->first();
        if (!$settings || !$settings->cloudflare_api_key || !$settings->cloudflare_zone_id) {
            return redirect()->route('admin.extensions.{identifier}.index')
                ->with('error', 'Cloudflare API key or Zone ID not configured.');
        }

        try {
            $service = new CloudflareApiService(decrypt($settings->cloudflare_api_key), $settings->cloudflare_zone_id);
            $valid = $service->validateAccess();
            if ($valid) {
                return redirect()->route('admin.extensions.{identifier}.index')
                    ->with('success', 'Cloudflare API connection successful!');
            }
            return redirect()->route('admin.extensions.{identifier}.index')
                ->with('error', 'Cloudflare API key or Zone ID is invalid.');
        } catch (\Exception $e) {
            return redirect()->route('admin.extensions.{identifier}.index')
                ->with('error', 'Cloudflare API test failed: ' . $e->getMessage());
        }
    }

    /**
     * Toggle DDoS protection on a server.
     */
    private function toggleProtection(Request $request): RedirectResponse
    {
        $serverId = $request->input('server_id');
        $server = Server::with('allocation', 'node')->findOrFail($serverId);
        $settings = DB::table('papyrus_settings')->first();

        if (!$settings || !$settings->papyrus_api_key || !$settings->cloudflare_api_key) {
            return redirect()->route('admin.extensions.{identifier}.index')
                ->with('error', 'API keys must be configured before enabling protection.');
        }

        $existingProxy = DB::table('papyrus_proxies')->where('server_id', $serverId)->first();

        if ($existingProxy) {
            return $this->disableProtection($server, $existingProxy, $settings);
        }

        if (!$settings->cloudflare_zone_id || !$settings->base_domain) {
            return redirect()->route('admin.extensions.{identifier}.index')
                ->with('error', 'Cloudflare Zone ID and Base Domain must be configured before enabling protection.');
        }

        $cnameTarget = $request->input('cname_target', $settings->default_cname_target ?? 'spectrum-01.papyrus.vip');

        return $this->enableProtection($server, $settings, $cnameTarget);
    }

    /**
     * Update per-server customer edit permissions.
     */
    private function updatePermissions(Request $request): RedirectResponse
    {
        $serverId = $request->input('server_id');
        $proxy = DB::table('papyrus_proxies')->where('server_id', $serverId)->first();

        if (!$proxy) {
            return redirect()->route('admin.extensions.{identifier}.index')
                ->with('error', 'Server does not have protection enabled.');
        }

        $updateData = [
            'allow_motd_edit' => $request->has('allow_motd_edit'),
            'allow_messages_edit' => $request->has('allow_messages_edit'),
            'updated_at' => now(),
        ];
        if ($request->has('domain_limit')) {
            $updateData['domain_limit'] = (int) $request->input('domain_limit');
        }

        DB::table('papyrus_proxies')
            ->where('id', $proxy->id)
            ->update($updateData);

        return redirect()->route('admin.extensions.{identifier}.index')
            ->with('success', 'Permissions updated.');
    }

    /**
     * Enable protection: Create Papyrus proxy → Create DNS → Hide IP.
     */
    private function enableProtection(Server $server, $settings, string $cnameTarget): RedirectResponse
    {
        $papyrus = new PapyrusApiService(decrypt($settings->papyrus_api_key));
        $cloudflare = new CloudflareApiService(decrypt($settings->cloudflare_api_key), $settings->cloudflare_zone_id);

        $allocation = $server->allocation;
        $backendIp = $allocation->ip;
        $backendPort = $allocation->port;

        // Resolve 0.0.0.0 to the node's public IP
        if ($backendIp === '0.0.0.0') {
            $nodeIp = $server->node->fqdn;
            // If FQDN is a hostname, resolve it to an IP
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
            return redirect()->route('admin.extensions.{identifier}.index')
                ->with('error', 'Failed to create proxy: ' . $e->getMessage());
        }

        // Papyrus may nest response under 'data' key
        $proxyData = $proxyResult['data'] ?? $proxyResult;
        $proxyId = $proxyData['_id'] ?? $proxyData['id'] ?? null;

        if (!$proxyId) {
            Log::error('Papyrus API returned no proxy ID', ['result' => $proxyResult]);
            return redirect()->route('admin.extensions.{identifier}.index')
                ->with('error', 'Papyrus returned no proxy ID. Check laravel.log for full response.');
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
            return redirect()->route('admin.extensions.{identifier}.index')
                ->with('error', 'Failed to create DNS record (proxy rolled back): ' . $e->getMessage());
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
                // Prepend JAVA_TOOL_OPTIONS to the startup command
                $server->update(['startup' => 'JAVA_TOOL_OPTIONS="' . $javaOpts . '" ' . $originalStartup]);
                DB::table('papyrus_proxies')
                    ->where('server_id', $server->id)
                    ->update(['original_startup' => $originalStartup]);
            }
        }

        DB::table('papyrus_activity_log')->insert([
            'server_id' => $server->id,
            'user_id' => auth()->id(),
            'action' => 'protection.enabled',
            'metadata' => json_encode(['protected_address' => $protectedAddress, 'server_name' => $server->name]),
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.extensions.{identifier}.index')
            ->with('success', "Protection enabled for {$server->name}. Players connect via {$protectedAddress}");
    }

    /**
     * Disable protection: Delete proxy → Delete DNS → Restore IP.
     */
    private function disableProtection(Server $server, $proxy, $settings): RedirectResponse
    {
        $papyrus = new PapyrusApiService(decrypt($settings->papyrus_api_key));
        $cloudflare = new CloudflareApiService(decrypt($settings->cloudflare_api_key), $settings->cloudflare_zone_id);

        // Step 1: Delete Papyrus proxy
        if ($proxy->papyrus_proxy_id) {
            try {
                $papyrus->deleteProxy($proxy->papyrus_proxy_id);
            } catch (\Exception $e) {
                Log::error('Failed to delete Papyrus proxy', ['error' => $e->getMessage()]);
                return redirect()->route('admin.extensions.{identifier}.index')
                    ->with('error', 'Failed to delete proxy: ' . $e->getMessage());
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
            'user_id' => auth()->id(),
            'action' => 'protection.disabled',
            'metadata' => json_encode(['server_name' => $server->name]),
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.extensions.{identifier}.index')
            ->with('success', "Protection disabled for {$server->name}.");
    }

    /**
     * Save auto-protect settings.
     */
    private function saveAutoProtect(Request $request): RedirectResponse
    {
        $data = [
            'auto_protect_enabled' => $request->has('auto_protect_enabled'),
            'auto_protect_eggs' => json_encode(array_map('intval', $request->input('auto_protect_eggs', []))),
        ];

        $this->upsertSettings($data);

        return redirect()->route('admin.extensions.{identifier}.index')
            ->with('success', 'Auto-protect settings saved.');
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
            // HTTP proxy
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
