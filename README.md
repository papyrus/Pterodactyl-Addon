<p align="center">
  <img src="data/public/icon-big.png" alt="Papyrus Protection Logo" width="120">
</p>

<h1 align="center">Papyrus Protection — Pterodactyl DDoS Protection Addon</h1>

<p align="center">
  One-click Minecraft DDoS protection for Pterodactyl Panel.<br>
  Integrates <a href="https://papyrus.vip">Papyrus Blaze</a> proxy management and Cloudflare DNS automation directly into your panel.
</p>

<p align="center">
  <a href="https://blueprint.zip"><img src="https://img.shields.io/badge/Framework-Blueprint-blue" alt="Blueprint Framework"></a>
  <a href="https://papyrus.vip"><img src="https://img.shields.io/badge/Powered%20by-Papyrus.vip-00d9ff" alt="Papyrus.vip"></a>
  <img src="https://img.shields.io/badge/version-1.0.0-green" alt="Version 1.0.0">
  <img src="https://img.shields.io/badge/Pterodactyl-1.x-lightgrey" alt="Pterodactyl 1.x">
</p>

---

## What is Papyrus Protection?

**Papyrus Protection** is a [Blueprint](https://blueprint.zip) extension for [Pterodactyl Panel](https://pterodactyl.io) that gives server hosts **one-click DDoS protection** for Minecraft servers. It automates the entire process: creating a [Papyrus Blaze](https://papyrus.vip) DDoS mitigation proxy, configuring Cloudflare DNS records, hiding the real server IP, and injecting egress proxy settings — all from the admin panel or via API.

### Why Papyrus Protection?

- **Zero manual configuration** — Enable protection on any server with one click
- **Automatic DNS management** — Creates and removes Cloudflare DNS records automatically
- **Real IP hiding** — Replaces the server address shown to customers with the protected domain
- **Per-server antibot tuning** — Captcha, limbo verification, reconnect checks, connection throttling
- **Custom domain support** — Customers can add their own domains with CNAME verification
- **WHMCS / billing integration** — Full Application API for automated provisioning
- **Auto-protect by egg** — Automatically protect new servers based on their egg type
- **Whitelabel ready** — Rebrand as your own protection or show Papyrus.vip branding

---

## Features

### Admin Panel
- 📊 **Dashboard** — Real-time overview of protected/unprotected servers, active proxies
- 🔑 **API Configuration** — Papyrus & Cloudflare API keys, base domain, subdomain patterns, CNAME targets
- 🛡️ **Protection Defaults** — CPS threshold, captcha, limbo, chat reports, MOTD, kick messages
- 🔒 **IP Leak Prevention** — Hide real IP, disable proxy protocol, SFTP hostname control
- 🏷️ **Whitelabel** — Custom branding name for customer-facing UI
- 🌐 **Egress Proxy** — SOCKS5/HTTP outbound proxy injection via `JAVA_TOOL_OPTIONS`
- 🪄 **Auto-Protect** — Automatically enable protection on new servers by egg selection
- 📋 **Activity Log** — Full audit trail of all protection actions
- ⚙️ **Per-Server Permissions** — Control MOTD editing, message editing, domain limits per server

### Customer Dashboard (React)
- Protection status with badge indicator
- Antibot configuration (captcha, limbo, reconnect, blacklist, burst, etc.)
- Custom domain management with CNAME verification
- MOTD and kick message customization
- Proxy protocol toggle

### Application API
- `GET /overview` — All servers with protection status summary
- `GET /servers/{id}/status` — Single server protection status
- `POST /servers/{id}/protect` — Enable protection
- `DELETE /servers/{id}/protect` — Disable protection

> Full API documentation: [API.md](data/public/API.md)

---

## Requirements

- [Pterodactyl Panel](https://pterodactyl.io) 1.x
- [Blueprint Framework](https://blueprint.zip)
- [Papyrus.vip](https://papyrus.vip) account with Blaze plan
- Cloudflare account with a configured zone (API token with DNS edit permissions)

---

## Installation

1. **Install Blueprint** on your Pterodactyl panel if you haven't already:
   ```bash
   # See https://blueprint.zip/docs for installation instructions
   ```

2. **Download** the latest release from the [Releases](https://github.com/Papyrus/Pterodactyl-Addon/releases) page.

3. **Install** the extension:
   ```bash
   cd /var/www/pterodactyl
   blueprint -install papyrusprotection
   ```

4. **Configure** in the admin panel:
   - Navigate to **Admin → Extensions → Papyrus Protection**
   - Enter your Papyrus API key (from [dash.papyrus.vip](https://dash.papyrus.vip))
   - Enter your Cloudflare API token and Zone ID
   - Set your base domain and subdomain pattern
   - Test both connections
   - Configure protection defaults

5. **Enable protection** on any server with the **Enable** button in the server list.

---

## Development Setup

```bash
# Clone into Blueprint dev directory
cd /var/www/pterodactyl
git clone git@github.com:Papyrus/Pterodactyl-Addon.git .blueprint/dev

# Build the extension
blueprint -build
```

---

## Auto-Protect

Automatically protect new Minecraft servers when they finish installing:

1. Go to **Admin → Extensions → Papyrus Protection**
2. Scroll to **Auto-Protect by Egg**
3. Enable auto-protect and select which eggs should be auto-protected
4. New servers using those eggs will have protection enabled automatically after installation

---

## WHMCS Integration

Use the Application API to automate protection from your billing system:

```php
// Enable protection when provisioning a server
$response = Http::withHeaders([
    'Authorization' => 'Bearer ' . $applicationApiKey,
    'Accept' => 'application/json',
])->post("https://panel.example.com/api/application/extensions/papyrusprotection/servers/{$serverId}/protect");

// Disable protection when terminating
Http::withHeaders([
    'Authorization' => 'Bearer ' . $applicationApiKey,
    'Accept' => 'application/json',
])->delete("https://panel.example.com/api/application/extensions/papyrusprotection/servers/{$serverId}/protect");
```

See [API.md](data/public/API.md) for full documentation with cURL examples.

---

## How It Works

```
┌─────────────┐     ┌──────────────┐     ┌───────────────┐
│  Pterodactyl │────▶│ Papyrus Blaze│────▶│ Minecraft     │
│  Panel       │     │ DDoS Proxy   │     │ Server        │
└──────┬───────┘     └──────────────┘     └───────────────┘
       │                    ▲
       │              ┌─────┴──────┐
       └─────────────▶│ Cloudflare │
                      │ DNS        │
                      └────────────┘
```

1. **Admin clicks Enable** (or API call / auto-protect trigger)
2. Extension creates a **Blaze proxy** on Papyrus with antibot configuration
3. Extension creates a **Cloudflare DNS record** (CNAME/A) pointing to the proxy
4. Server allocation IP is **replaced with the protected domain** (hides real IP)
5. Egress proxy settings are **injected into the startup command** (if configured)
6. Players connect via `server.play.yourdomain.com` → routed through DDoS protection

---

## Configuration Reference

| Setting | Description | Default |
|---------|-------------|---------|
| `papyrus_api_key` | Papyrus API key from dash.papyrus.vip | — |
| `cloudflare_api_key` | Cloudflare API token with DNS edit permission | — |
| `cloudflare_zone_id` | Cloudflare Zone ID for your domain | — |
| `base_domain` | Base domain for subdomains (e.g. `play.example.com`) | — |
| `subdomain_pattern` | Pattern for subdomain generation | `{server_name}` |
| `default_cname_target` | Papyrus proxy endpoint | `spectrum-01.papyrus.vip` |
| `default_cps_threshold` | Connections per second to trigger antibot | `30` |
| `hide_real_ip` | Replace server IP with protected domain | `true` |
| `disable_proxy_protocol` | Disable proxy protocol for backends that don't support it | `true` |
| `auto_protect_enabled` | Auto-protect new servers by egg | `false` |
| `auto_protect_eggs` | JSON array of egg IDs to auto-protect | `[]` |

---

## Uninstallation

```bash
cd /var/www/pterodactyl
blueprint -remove papyrusprotection
```

> ⚠️ **Important:** Disable protection on all servers before uninstalling. Active proxies will NOT be auto-deleted from Papyrus. Clean up manually at [dash.papyrus.vip](https://dash.papyrus.vip).

---

## Support

- **Papyrus Dashboard**: [dash.papyrus.vip](https://dash.papyrus.vip)
- **Website**: [papyrus.vip](https://papyrus.vip)
- **Issues**: [GitHub Issues](https://github.com/Papyrus/Pterodactyl-Addon/issues)

---

## License

Proprietary — © Papyrus. All rights reserved.
