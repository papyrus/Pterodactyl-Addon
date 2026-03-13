# Papyrus Protection — Application API

Automate DDoS protection management from WHMCS, billing systems, or custom scripts using the Pterodactyl Application API.

## Authentication

All endpoints use the standard Pterodactyl **Application API key**. Create one at:

**Admin Panel → Application API → Create New**

Include the key in every request as a `Bearer` token:

```
Authorization: Bearer ptla_xxxxxxxxxxxxxxxxxxxx
```

> The API key must belong to an admin account.

## Base URL

```
https://your-panel.com/api/application/extensions/papyrusprotection
```

## Rate Limiting

Application API rate limits apply (default: 240 requests per minute). Configurable in `config/http.php`.

---

## Endpoints

### Get Overview

Returns a summary of all servers and their protection status.

```
GET /overview
```

#### Response `200 OK`

```json
{
  "total_servers": 12,
  "protected_count": 5,
  "protected_servers": [
    {
      "server_id": 1,
      "server_name": "Survival SMP",
      "protected_address": "survival-smp.play.example.com",
      "backend_address": "10.0.0.5",
      "backend_port": 25565,
      "status": "active",
      "created_at": "2026-03-10 14:30:00"
    }
  ]
}
```

---

### Get Server Protection Status

Check whether a specific server has protection enabled.

```
GET /servers/{server_id}/status
```

#### Parameters

| Parameter   | Type | Location | Description              |
|-------------|------|----------|--------------------------|
| `server_id` | int  | URL      | Pterodactyl server ID    |

#### Response `200 OK` — Protected

```json
{
  "protected": true,
  "status": "active",
  "protected_address": "survival-smp.play.example.com",
  "backend_address": "10.0.0.5",
  "backend_port": 25565,
  "proxy_protocol_disabled": true,
  "created_at": "2026-03-10 14:30:00"
}
```

#### Response `200 OK` — Not Protected

```json
{
  "protected": false
}
```

#### Response `404 Not Found`

Returned if the server ID does not exist.

---

### Enable Protection

Creates a Blaze DDoS proxy, Cloudflare DNS record, and configures the server allocation. Uses your admin-configured defaults for antibot, MOTD, kick messages, egress proxy, and IP hiding.

```
POST /servers/{server_id}/protect
```

#### Parameters

| Parameter      | Type   | Location | Required | Description                                                                 |
|----------------|--------|----------|----------|-----------------------------------------------------------------------------|
| `server_id`    | int    | URL      | Yes      | Pterodactyl server ID                                                       |
| `cname_target` | string | Body     | No       | Override the CNAME target (default: your configured `default_cname_target`)  |

#### Request Body (optional)

```json
{
  "cname_target": "spectrum-02.papyrus.vip"
}
```

#### Response `200 OK`

```json
{
  "success": true,
  "protected_address": "survival-smp.play.example.com",
  "proxy_id": "665a1b2c3d4e5f6789012345"
}
```

#### Error Responses

| Status | Condition                                   | Example                                                |
|--------|---------------------------------------------|--------------------------------------------------------|
| `409`  | Server already has protection enabled       | `{"success": false, "error": "Server already has protection enabled."}` |
| `422`  | API keys not configured in admin panel      | `{"success": false, "error": "API keys must be configured before enabling protection."}` |
| `500`  | Papyrus or Cloudflare API call failed       | `{"success": false, "error": "Failed to create proxy: ..."}` |

#### What Happens

1. Resolves the server's backend IP and port from its primary allocation (handles `0.0.0.0` → node FQDN)
2. Generates a unique subdomain via Cloudflare (collision-safe, e.g. `survival-smp.play.example.com`)
3. Creates a Blaze proxy on the Papyrus API with your configured antibot/MOTD/kick defaults
4. Creates a DNS record (CNAME or A) pointing to the Papyrus proxy endpoint
5. If **Hide Real IP** is enabled: replaces the allocation's `ip_alias` so customers see the protected address
6. If **Egress Proxy** is enabled: prepends `JAVA_TOOL_OPTIONS` to the server startup command
7. Logs the action to the activity log (source: `application_api`)

---

### Disable Protection

Removes the Blaze proxy, deletes the DNS record, and restores the server's original configuration.

```
DELETE /servers/{server_id}/protect
```

#### Parameters

| Parameter   | Type | Location | Description           |
|-------------|------|----------|-----------------------|
| `server_id` | int  | URL      | Pterodactyl server ID |

#### Response `200 OK`

```json
{
  "success": true
}
```

#### Error Responses

| Status | Condition                              | Example                                               |
|--------|----------------------------------------|-------------------------------------------------------|
| `404`  | Server does not have protection        | `{"success": false, "error": "Server does not have protection enabled."}` |
| `422`  | API keys not configured                | `{"success": false, "error": "API keys must be configured."}` |
| `500`  | Papyrus API deletion failed            | `{"success": false, "error": "Failed to delete proxy: ..."}` |

#### What Happens

1. Deletes the Blaze proxy from the Papyrus API
2. Deletes the Cloudflare DNS record (continues even if this fails)
3. Restores the original allocation IP alias if it was hidden
4. Restores the original startup command if egress proxy was injected
5. Removes the proxy record from the database
6. Logs the action to the activity log (source: `application_api`)

---

## WHMCS Integration Example

Use these endpoints in your WHMCS provisioning module to automatically manage protection when services are created or terminated.

### On Service Creation (after server is provisioned)

```php
$response = Http::withHeaders([
    'Authorization' => 'Bearer ' . $apiKey,
    'Accept' => 'application/json',
    'Content-Type' => 'application/json',
])->post("https://panel.example.com/api/application/extensions/papyrusprotection/servers/{$serverId}/protect");

$data = $response->json();
if ($data['success']) {
    // Store $data['protected_address'] for the client
}
```

### On Service Termination

```php
Http::withHeaders([
    'Authorization' => 'Bearer ' . $apiKey,
    'Accept' => 'application/json',
])->delete("https://panel.example.com/api/application/extensions/papyrusprotection/servers/{$serverId}/protect");
```

### Check Status (e.g. for client area display)

```php
$response = Http::withHeaders([
    'Authorization' => 'Bearer ' . $apiKey,
    'Accept' => 'application/json',
])->get("https://panel.example.com/api/application/extensions/papyrusprotection/servers/{$serverId}/status");

$data = $response->json();
// $data['protected'] => true/false
// $data['protected_address'] => "server.play.example.com"
```

---

## cURL Examples

### Check protection status

```bash
curl -s -H "Authorization: Bearer ptla_xxxxx" \
  -H "Accept: application/json" \
  "https://panel.example.com/api/application/extensions/papyrusprotection/servers/1/status"
```

### Enable protection

```bash
curl -s -X POST \
  -H "Authorization: Bearer ptla_xxxxx" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  "https://panel.example.com/api/application/extensions/papyrusprotection/servers/1/protect"
```

### Enable with custom CNAME target

```bash
curl -s -X POST \
  -H "Authorization: Bearer ptla_xxxxx" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"cname_target": "spectrum-02.papyrus.vip"}' \
  "https://panel.example.com/api/application/extensions/papyrusprotection/servers/1/protect"
```

### Disable protection

```bash
curl -s -X DELETE \
  -H "Authorization: Bearer ptla_xxxxx" \
  -H "Accept: application/json" \
  "https://panel.example.com/api/application/extensions/papyrusprotection/servers/1/protect"
```

### Get overview

```bash
curl -s -H "Authorization: Bearer ptla_xxxxx" \
  -H "Accept: application/json" \
  "https://panel.example.com/api/application/extensions/papyrusprotection/overview"
```
