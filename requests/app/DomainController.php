<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier};

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\Server;
use Pterodactyl\BlueprintFramework\Extensions\{identifier}\Services\PapyrusApiService;

class DomainController extends Controller
{
    /**
     * List custom domains for a server.
     */
    public function index(Request $request, Server $server): JsonResponse
    {
        $proxy = DB::table('papyrus_proxies')
            ->where('server_id', $server->id)
            ->first();

        if (!$proxy) {
            return response()->json(['domains' => [], 'message' => 'Protection not enabled.']);
        }

        $domains = DB::table('papyrus_custom_domains')
            ->where('proxy_id', $proxy->id)
            ->get();

        $settings = DB::table('papyrus_settings')->first();
        $limit = $proxy->domain_limit ?? $settings->default_domain_limit ?? 3;

        return response()->json([
            'domains' => $domains,
            'domain_limit' => $limit,
        ]);
    }

    /**
     * Add a custom domain.
     */
    public function store(Request $request, Server $server): JsonResponse
    {
        $validated = $request->validate([
            'domain' => 'required|string|max:255',
        ]);

        $proxy = DB::table('papyrus_proxies')
            ->where('server_id', $server->id)
            ->first();

        if (!$proxy) {
            return response()->json(['error' => 'Protection not enabled.'], 422);
        }

        // Check domain limit
        $settings = DB::table('papyrus_settings')->first();
        $limit = $proxy->domain_limit ?? $settings->default_domain_limit ?? 3;
        if ($limit > 0) {
            $currentCount = DB::table('papyrus_custom_domains')
                ->where('proxy_id', $proxy->id)
                ->count();
            if ($currentCount >= $limit) {
                return response()->json(['error' => "Domain limit reached ({$limit}). Contact your hosting provider to increase the limit."], 422);
            }
        }

        $domainId = DB::table('papyrus_custom_domains')->insertGetId([
            'proxy_id' => $proxy->id,
            'domain' => $validated['domain'],
            'verification_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('papyrus_activity_log')->insert([
            'server_id' => $server->id,
            'user_id' => $request->user()->id,
            'action' => 'domain.added',
            'metadata' => json_encode(['domain' => $validated['domain']]),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'id' => $domainId,
            'domain' => $validated['domain'],
            'verification_status' => 'pending',
            'cname_target' => $proxy->protected_address,
            'instructions' => "Add a CNAME record pointing {$validated['domain']} to {$proxy->protected_address}",
        ], 201);
    }

    /**
     * Verify a custom domain's DNS TXT record.
     */
    public function verify(Request $request, Server $server, int $domain): JsonResponse
    {
        $proxy = DB::table('papyrus_proxies')
            ->where('server_id', $server->id)
            ->first();

        if (!$proxy) {
            return response()->json(['error' => 'Protection not enabled.'], 422);
        }

        $domainRecord = DB::table('papyrus_custom_domains')
            ->where('id', $domain)
            ->where('proxy_id', $proxy->id)
            ->first();

        if (!$domainRecord) {
            return response()->json(['error' => 'Domain not found.'], 404);
        }

        // Check CNAME record points to the protected address
        $cnameRecords = dns_get_record($domainRecord->domain, DNS_CNAME);
        $verified = false;
        foreach ($cnameRecords ?: [] as $record) {
            $target = rtrim($record['target'] ?? '', '.');
            if (strcasecmp($target, $proxy->protected_address) === 0) {
                $verified = true;
                break;
            }
        }

        if (!$verified) {
            return response()->json([
                'verified' => false,
                'message' => 'CNAME record not found. Add a CNAME pointing ' . $domainRecord->domain . ' to ' . $proxy->protected_address,
            ]);
        }

        DB::table('papyrus_custom_domains')
            ->where('id', $domain)
            ->update([
                'verification_status' => 'verified',
                'updated_at' => now(),
            ]);

        // Update Papyrus proxy dns-to-bind
        $this->updateProxyDnsBindings($proxy);

        DB::table('papyrus_activity_log')->insert([
            'server_id' => $server->id,
            'user_id' => $request->user()->id,
            'action' => 'domain.verified',
            'metadata' => json_encode(['domain' => $domainRecord->domain]),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'verified' => true,
            'message' => 'Domain verified and added to proxy.',
        ]);
    }

    /**
     * Delete a custom domain.
     */
    public function destroy(Request $request, Server $server, int $domain): JsonResponse
    {
        $proxy = DB::table('papyrus_proxies')
            ->where('server_id', $server->id)
            ->first();

        if (!$proxy) {
            return response()->json(['error' => 'Protection not enabled.'], 422);
        }

        $domainRecord = DB::table('papyrus_custom_domains')
            ->where('id', $domain)
            ->where('proxy_id', $proxy->id)
            ->first();

        if (!$domainRecord) {
            return response()->json(['error' => 'Domain not found.'], 404);
        }

        DB::table('papyrus_custom_domains')
            ->where('id', $domain)
            ->delete();

        // Update Papyrus proxy dns-to-bind
        $this->updateProxyDnsBindings($proxy);

        DB::table('papyrus_activity_log')->insert([
            'server_id' => $server->id,
            'user_id' => $request->user()->id,
            'action' => 'domain.removed',
            'metadata' => json_encode(['domain' => $domainRecord->domain]),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Domain removed.']);
    }

    /**
     * Update the Papyrus proxy's dns-to-bind with all verified custom domains.
     */
    private function updateProxyDnsBindings($proxy): void
    {
        $settings = DB::table('papyrus_settings')->first();
        if (!$settings || !$settings->papyrus_api_key) {
            return;
        }

        // Start with the protected subdomain (always bound)
        $domains = [];
        if ($proxy->protected_address) {
            $domains[] = $proxy->protected_address;
        }

        // Add verified custom domains
        $customDomains = DB::table('papyrus_custom_domains')
            ->where('proxy_id', $proxy->id)
            ->where('verification_status', 'verified')
            ->pluck('domain')
            ->toArray();

        $domains = array_unique(array_merge($domains, $customDomains));

        try {
            $papyrus = new PapyrusApiService(decrypt($settings->papyrus_api_key));
            $papyrus->updateProxy($proxy->papyrus_proxy_id, [
                'dns-to-bind' => array_values($domains),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to update proxy DNS bindings', ['error' => $e->getMessage()]);
        }
    }
}
