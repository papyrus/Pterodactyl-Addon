{{-- Papyrus Protection Admin View --}}
{{-- This content is appended after the Blueprint admin template, inside @section('content') --}}

@if(session('success'))
  <div class="alert alert-success alert-dismissible">
    <button type="button" class="close" data-dismiss="alert">&times;</button>
    {{ session('success') }}
  </div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible">
    <button type="button" class="close" data-dismiss="alert">&times;</button>
    {{ session('error') }}
  </div>
@endif

{{-- Stats Summary --}}
@php
  $protectedCount = $proxies->where('proxy_status', 'active')->count();
  $unprotectedCount = $servers->count() - $protectedCount;
@endphp
<div class="row">
  <div class="col-md-3 col-sm-6">
    <div class="small-box bg-aqua">
      <div class="inner">
        <h3>{{ $servers->count() }}</h3>
        <p>Total Servers</p>
      </div>
      <div class="icon"><i class="fa fa-server"></i></div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="small-box bg-green">
      <div class="inner">
        <h3>{{ $protectedCount }}</h3>
        <p>Protected</p>
      </div>
      <div class="icon"><i class="fa fa-shield"></i></div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="small-box bg-red">
      <div class="inner">
        <h3>{{ $unprotectedCount }}</h3>
        <p>Unprotected</p>
      </div>
      <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="small-box bg-yellow">
      <div class="inner">
        <h3>{{ $protectedCount }}</h3>
        <p>Active Proxies</p>
      </div>
      <div class="icon"><i class="fa fa-exchange"></i></div>
    </div>
  </div>
</div>

{{-- API Configuration --}}
<div class="row">
  <div class="col-xs-12">
    <div class="box box-primary">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-key"></i> API Configuration</h3>
      </div>
      <form action="{{ $root }}" method="POST" autocomplete="off">
        {{ csrf_field() }}
        <input type="hidden" name="_method" value="PATCH">
        <input type="hidden" name="_section" value="api">
        <div class="box-body">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="papyrus_api_key">Papyrus API Key</label>
                <input type="password" class="form-control" name="papyrus_api_key" id="papyrus_api_key"
                  placeholder="{{ $settings && $settings->papyrus_api_key ? '••••••••••••••• (saved)' : 'pk_...' }}">
                <p class="text-muted small">Get your API key from <a href="https://dash.papyrus.vip" target="_blank">dash.papyrus.vip</a></p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="cloudflare_api_key">Cloudflare API Token</label>
                <input type="password" class="form-control" name="cloudflare_api_key" id="cloudflare_api_key"
                  placeholder="{{ $settings && $settings->cloudflare_api_key ? '••••••••••••••• (saved)' : 'API Token with DNS edit permissions' }}">
                <p class="text-muted small">Needs DNS edit permission for your zone</p>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label for="cloudflare_zone_id">Cloudflare Zone ID</label>
                <input type="text" class="form-control" name="cloudflare_zone_id" id="cloudflare_zone_id"
                  value="{{ $settings->cloudflare_zone_id ?? '' }}">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label for="base_domain">Base Domain</label>
                <input type="text" class="form-control" name="base_domain" id="base_domain"
                  value="{{ $settings->base_domain ?? '' }}" placeholder="play.hostdomain.com">
                <p class="text-muted small">Subdomains will be created under this domain</p>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label for="subdomain_pattern">Subdomain Pattern</label>
                <input type="text" class="form-control" name="subdomain_pattern" id="subdomain_pattern"
                  value="{{ $settings->subdomain_pattern ?? '{server_name}' }}">
                <p class="text-muted small">Variables: {server_name}, {server_id}, {server_uuid}</p>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label for="default_cname_target">Default CNAME Target</label>
                <input type="text" class="form-control" name="default_cname_target" id="default_cname_target"
                  value="{{ $settings->default_cname_target ?? 'spectrum-01.papyrus.vip' }}" placeholder="spectrum-01.papyrus.vip">
                <p class="text-muted small">Papyrus proxy address (CNAME target)</p>
              </div>
            </div>
          </div>
        </div>
        <div class="box-footer">
          <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save"></i> Save Settings</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Test Connection Buttons --}}
<div class="row">
  <div class="col-md-6">
    <form action="{{ $root }}" method="POST" style="display:inline">
      {{ csrf_field() }}
      <input type="hidden" name="action" value="test_papyrus">
      <button type="submit" class="btn btn-sm btn-info"><i class="fa fa-plug"></i> Test Papyrus Connection</button>
    </form>
  </div>
  <div class="col-md-6">
    <form action="{{ $root }}" method="POST" style="display:inline">
      {{ csrf_field() }}
      <input type="hidden" name="action" value="test_cloudflare">
      <button type="submit" class="btn btn-sm btn-info"><i class="fa fa-cloud"></i> Test Cloudflare Connection</button>
    </form>
  </div>
</div>
<br>

{{-- Protection Defaults --}}
<div class="row">
  <div class="col-xs-12">
    <div class="box box-info">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-shield"></i> Protection Defaults</h3>
      </div>
      <form action="{{ $root }}" method="POST" autocomplete="off">
        {{ csrf_field() }}
        <input type="hidden" name="_method" value="PATCH">
        <input type="hidden" name="_section" value="defaults">
        <div class="box-body">
          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label for="default_cps_threshold">CPS Threshold</label>
                <input type="number" class="form-control" name="default_cps_threshold" id="default_cps_threshold"
                  value="{{ $settings->default_cps_threshold ?? 30 }}" min="1" max="1000">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <div class="checkbox checkbox-primary no-margin-bottom">
                  <input type="checkbox" name="default_captcha_enabled" value="1" id="pCaptchaEnabled"
                    {{ ($settings->default_captcha_enabled ?? true) ? 'checked' : '' }}>
                  <label for="pCaptchaEnabled" class="strong">Enable Captcha</label>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <div class="checkbox checkbox-primary no-margin-bottom">
                  <input type="checkbox" name="default_limbo_enabled" value="1" id="pLimboEnabled"
                    {{ ($settings->default_limbo_enabled ?? true) ? 'checked' : '' }}>
                  <label for="pLimboEnabled" class="strong">Enable Limbo</label>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <div class="checkbox checkbox-primary no-margin-bottom">
                  <input type="checkbox" name="default_chat_reports" value="1" id="pChatReports"
                    {{ ($settings->default_chat_reports ?? false) ? 'checked' : '' }}>
                  <label for="pChatReports" class="strong">Prevent Chat Reports</label>
                </div>
              </div>
            </div>
          </div>
          <h4>Offline MOTD</h4>
          <p class="text-muted small">Displayed in the server list when the backend server is unreachable. Minecraft MOTDs have two lines.
            <span data-toggle="tooltip" data-placement="top" title="&0 Black, &1 Dark Blue, &2 Dark Green, &3 Dark Aqua, &4 Dark Red, &5 Dark Purple, &6 Gold, &7 Gray, &8 Dark Gray, &9 Blue, &a Green, &b Aqua, &c Red, &d Light Purple, &e Yellow, &f White — &l Bold, &o Italic, &n Underline, &m Strikethrough, &k Obfuscated, &r Reset" style="cursor:help; border-bottom:1px dotted #777;">
              <i class="fa fa-question-circle"></i> Supports &amp; color codes
            </span>
          </p>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="default_motd_up">Offline MOTD - Line 1</label>
                <input type="text" class="form-control" name="default_motd_up" id="default_motd_up"
                  value="{{ $settings->default_motd_up ?? '%center%&bPapyrus.vip - Server Offline' }}" placeholder="%center%&bPapyrus.vip - Server Offline">
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="default_motd_down">Offline MOTD - Line 2</label>
                <input type="text" class="form-control" name="default_motd_down" id="default_motd_down"
                  value="{{ $settings->default_motd_down ?? '%center%&cUnexpected error.' }}" placeholder="%center%&cUnexpected error.">
              </div>
            </div>
          </div>
          <hr>
          <h4>IP Leak Prevention</h4>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <div class="checkbox checkbox-primary no-margin-bottom">
                  <input type="checkbox" name="hide_real_ip" value="1" id="pHideRealIp"
                    {{ ($settings->hide_real_ip ?? true) ? 'checked' : '' }}>
                  <label for="pHideRealIp" class="strong">Hide Real IP</label>
                </div>
                <p class="text-muted small">Replace server endpoint in customer panel with protected domain</p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <div class="checkbox checkbox-primary no-margin-bottom">
                  <input type="checkbox" name="disable_proxy_protocol" value="1" id="pDisableProxyProtocol"
                    {{ ($settings->disable_proxy_protocol ?? true) ? 'checked' : '' }}>
                  <label for="pDisableProxyProtocol" class="strong">Disable ProxyProtocol</label>
                </div>
                <p class="text-muted small">For backend servers that don't support ProxyProtocol</p>
              </div>
            </div>
          </div>
          <div class="row" style="margin-top:10px">
            <div class="col-md-4">
              <div class="form-group">
                <label for="sftp_display_mode">SFTP Endpoint Display</label>
                <select class="form-control" name="sftp_display_mode" id="sftp_display_mode">
                  <option value="show" {{ ($settings->sftp_display_mode ?? 'show') === 'show' ? 'selected' : '' }}>Show (default)</option>
                  <option value="hide" {{ ($settings->sftp_display_mode ?? '') === 'hide' ? 'selected' : '' }}>Hide from customers</option>
                  <option value="rename" {{ ($settings->sftp_display_mode ?? '') === 'rename' ? 'selected' : '' }}>Replace hostname</option>
                </select>
                <p class="text-muted small">Controls how the SFTP connection address is shown to customers on protected servers. Hiding or replacing it prevents backend IP leaks via the SFTP host.</p>
              </div>
            </div>
            <div class="col-md-4" id="sftpCustomHostGroup" style="{{ ($settings->sftp_display_mode ?? 'show') === 'rename' ? '' : 'display:none' }}">
              <div class="form-group">
                <label for="sftp_custom_host">Custom SFTP Hostname</label>
                <input type="text" class="form-control" name="sftp_custom_host" id="sftp_custom_host"
                  value="{{ $settings->sftp_custom_host ?? '' }}" placeholder="sftp.yourdomain.com">
                <p class="text-muted small">Replaces the real node hostname in the SFTP connection string shown to customers.</p>
              </div>
            </div>
          </div>
          <script>
            document.getElementById('sftp_display_mode').addEventListener('change', function() {
              document.getElementById('sftpCustomHostGroup').style.display = this.value === 'rename' ? '' : 'none';
            });
          </script>
          <hr>
          <h4>Branding</h4>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <div class="checkbox checkbox-primary no-margin-bottom">
                  <input type="checkbox" name="whitelabel_enabled" value="1" id="pWhitelabel"
                    {{ ($settings->whitelabel_enabled ?? false) ? 'checked' : '' }}>
                  <label for="pWhitelabel" class="strong">Whitelabel Mode</label>
                </div>
                <p class="text-muted small">
                  <span data-toggle="tooltip" data-placement="top" title="Choose between advertising Papyrus.vip as an established DDoS protection provider to build customer trust, or enable whitelabel to rebrand everything under your own company name and make it part of your ecosystem." style="cursor:help; border-bottom:1px dotted #777;">
                    <i class="fa fa-question-circle"></i>
                  </span>
                  When disabled, the customer panel shows "Papyrus.vip DDoS Protection" branding and links to papyrus.vip. When enabled, all references are replaced with your custom name.
                </p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group" id="whitelabelNameGroup" style="{{ ($settings->whitelabel_enabled ?? false) ? '' : 'display:none' }}">
                <label for="whitelabel_name">Custom Protection Name</label>
                <input type="text" class="form-control" name="whitelabel_name" id="whitelabel_name"
                  value="{{ $settings->whitelabel_name ?? '' }}" placeholder="e.g. MyHost Protection">
                <p class="text-muted small">Shown as "[Your Name] DDoS Protection" in the customer dashboard</p>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <label for="default_domain_limit">Custom Domain Limit (per server)</label>
                <input type="number" class="form-control" name="default_domain_limit" id="default_domain_limit"
                  value="{{ $settings->default_domain_limit ?? 3 }}" min="0" max="100">
                <p class="text-muted small">0 = unlimited. Can be overridden per server.</p>
              </div>
            </div>
          </div>
          <script>
            document.getElementById('pWhitelabel').addEventListener('change', function() {
              document.getElementById('whitelabelNameGroup').style.display = this.checked ? '' : 'none';
            });
          </script>
          <hr>
          <h4>Egress Proxy</h4>
          <p class="text-muted small">Route all outbound Java connections through a proxy to prevent backend IP leaks from plugins (e.g., update checkers, IP APIs).</p>
          <div class="row">
            <div class="col-md-3">
              <div class="form-group">
                <div class="checkbox checkbox-primary no-margin-bottom">
                  <input type="checkbox" name="egress_proxy_enabled" value="1" id="pEgressProxy"
                    {{ ($settings->egress_proxy_enabled ?? false) ? 'checked' : '' }}>
                  <label for="pEgressProxy" class="strong">Enable Egress Proxy</label>
                </div>
                <p class="text-muted small">Injects JVM flags via <code>JAVA_TOOL_OPTIONS</code> on protected servers. Invisible to customers.</p>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label for="egress_proxy_type">Proxy Type</label>
                <select class="form-control" name="egress_proxy_type" id="egress_proxy_type">
                  <option value="socks5" {{ ($settings->egress_proxy_type ?? 'socks5') === 'socks5' ? 'selected' : '' }}>SOCKS5</option>
                  <option value="http" {{ ($settings->egress_proxy_type ?? '') === 'http' ? 'selected' : '' }}>HTTP</option>
                </select>
                <p class="text-muted small" id="proxyTypeWarning" style="display:none; color:#c0392b">
                  <i class="fa fa-exclamation-triangle"></i> HTTP proxy only covers Java HTTP clients. Plugins using raw sockets will bypass it. SOCKS5 is recommended.
                </p>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label for="egress_proxy_host">Proxy Host</label>
                <input type="text" class="form-control" name="egress_proxy_host" id="egress_proxy_host"
                  value="{{ $settings->egress_proxy_host ?? '' }}" placeholder="proxy.example.com">
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label for="egress_proxy_port">Proxy Port</label>
                <input type="number" class="form-control" name="egress_proxy_port" id="egress_proxy_port"
                  value="{{ $settings->egress_proxy_port ?? '' }}" placeholder="1080" min="1" max="65535">
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label for="egress_proxy_username">Username <small class="text-muted">(optional)</small></label>
                <input type="text" class="form-control" name="egress_proxy_username" id="egress_proxy_username"
                  value="{{ $settings->egress_proxy_username ?? '' }}" placeholder="Proxy username">
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label for="egress_proxy_password">Password <small class="text-muted">(optional)</small></label>
                <input type="password" class="form-control" name="egress_proxy_password" id="egress_proxy_password"
                  placeholder="{{ $settings->egress_proxy_password ? '••••••••••• (saved)' : 'Proxy password' }}">
                <p class="text-muted small">
                  @if(($settings->egress_proxy_type ?? 'socks5') === 'http')
                    <span style="color:#c0392b"><i class="fa fa-exclamation-triangle"></i> HTTP proxy authentication via JVM system properties is unreliable in modern Java (17+). Consider using SOCKS5 instead, or a proxy that doesn't require auth.</span>
                  @else
                    SOCKS5 auth uses <code>java.net.socks.username</code> / <code>java.net.socks.password</code> system properties.
                  @endif
                </p>
              </div>
            </div>
          </div>
          <script>
            document.getElementById('egress_proxy_type').addEventListener('change', function() {
              document.getElementById('proxyTypeWarning').style.display = this.value === 'http' ? '' : 'none';
            });
            // Show warning on load if HTTP is selected
            if (document.getElementById('egress_proxy_type').value === 'http') {
              document.getElementById('proxyTypeWarning').style.display = '';
            }
          </script>
          <hr>
          @php
            $kickDefaults = json_decode($settings->default_kick_messages ?? '{}', true) ?: [];
          @endphp
          <h4>
            <a data-toggle="collapse" href="#kickMessageDefaults" style="color:inherit;text-decoration:none">
              <i class="fa fa-caret-right" id="kickMessageCaret"></i> Default Kick &amp; Mitigation Messages
            </a>
          </h4>
          <p class="text-muted small">These messages are used when creating new proxies. Supports Minecraft &amp; color codes. Multi-line messages use \n as newline.</p>
          <div class="collapse" id="kickMessageDefaults">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Reconnect Kick</label>
                  <textarea class="form-control" name="kick_reconnect_kick" rows="4">{{ $kickDefaults['reconnect_kick'] ?? "&8&l#m------------&cPapyrus.vip - Blaze Proxy &8&l#m------------\n&7Please wait &c3 seconds &7and reconnect\n&7Pending reconnects: &c%remaining%\n&8&l#m------------------------------------" }}</textarea>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Invalid Name Kick</label>
                  <textarea class="form-control" name="kick_invalid_name_kick_v2" rows="4">{{ $kickDefaults['invalid_name_kick_v2'] ?? "&8&l#m------------&cPapyrus.vip - Blaze Proxy &8&l#m------------\n&7Your connection has been disconnected for security reasons\n&7~ ~ ~ v2 ~ ~ ~\n&8&l#m------------------------------------" }}</textarea>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Invalid Name Kick (Legacy)</label>
                  <textarea class="form-control" name="kick_invalid_name_kick_v1" rows="4">{{ $kickDefaults['invalid_name_kick_v1'] ?? "&8&l#m------------&cPapyrus.vip - Blaze Proxy &8&l#m------------\n&7Your connection has been disconnected for security reasons\n&7~ ~ ~ v1 ~ ~ ~\n&8&l#m------------------------------------" }}</textarea>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Blacklist Kick</label>
                  <textarea class="form-control" name="kick_blacklist_kick" rows="4">{{ $kickDefaults['blacklist_kick'] ?? "&8&l#m------------&cPapyrus.vip - Blaze Proxy &8&l#m------------\n&7Your connection has been disconnected for security reasons\n&7~ ~ ~ v3 ~ ~ ~\n&8&l#m------------------------------------" }}</textarea>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Blacklist Kick (Attempting)</label>
                  <textarea class="form-control" name="kick_blacklist_kick_attempting" rows="4">{{ $kickDefaults['blacklist_kick_attempting'] ?? "&8&l#m------------&cPapyrus.vip - Blaze Proxy &8&l#m------------\n&7Your connection has been disconnected for security reasons\n&7~ ~ ~ v4 ~ ~ ~\n&8&l#m------------------------------------" }}</textarea>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Bad Packet Kick</label>
                  <textarea class="form-control" name="kick_bad_bytebuf_max_packet_kick" rows="4">{{ $kickDefaults['bad_bytebuf_max_packet_kick'] ?? "&8&l#m------------&cPapyrus.vip - Blaze Proxy &8&l#m------------\n&7Your connection has been disconnected for security reasons\n&7~ ~ ~ v5 ~ ~ ~\n&8&l#m------------------------------------" }}</textarea>
                </div>
              </div>
            </div>
            <h5>Limbo Messages</h5>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Limbo Join Message</label>
                  <input type="text" class="form-control" name="kick_limbo_join_message"
                    value="{{ $kickDefaults['limbo_join_message'] ?? '&bBlaze &8> &fVerifying your connection... Please wait. :D' }}">
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Limbo Enter Code Message</label>
                  <input type="text" class="form-control" name="kick_limbo_enter_code_message"
                    value="{{ $kickDefaults['limbo_enter_code_message'] ?? '&bBlaze &8> &fPlease enter the text in chat that is displayed on the map.' }}">
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Limbo Attempts Message</label>
                  <input type="text" class="form-control" name="kick_limbo_attempts_message"
                    value="{{ $kickDefaults['limbo_attempts_message'] ?? '&bBlaze &8> &cYour captcha is invalid. &f(attempts-left) attempts &cleft.' }}">
                  <p class="text-muted small">Use (attempts-left) for remaining attempts count</p>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Limbo Verified Kick</label>
                  <textarea class="form-control" name="kick_limbo_verified_kick" rows="4">{{ $kickDefaults['limbo_verified_kick'] ?? "&8&l#m------------&r Blaze &8&l#m------------\n&fYour connection was verified successfully.\n&fYou may now rejoin the server\n&8&l#m------------------------------------" }}</textarea>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Limbo Failed Kick</label>
                  <textarea class="form-control" name="kick_limbo_failed_kick" rows="4">{{ $kickDefaults['limbo_failed_kick'] ?? "&8&l#m------------&r Blaze &8&l#m------------\n&cYour connection was disconnected.\n&cPlease check your internet connection before joining again.\n&8&l#m------------------------------------" }}</textarea>
                </div>
              </div>
            </div>
          </div>
          <script>
            $('#kickMessageDefaults').on('show.bs.collapse', function () {
              $('#kickMessageCaret').removeClass('fa-caret-right').addClass('fa-caret-down');
            }).on('hide.bs.collapse', function () {
              $('#kickMessageCaret').removeClass('fa-caret-down').addClass('fa-caret-right');
            });
          </script>
        </div>
        <div class="box-footer">
          <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save"></i> Save Defaults</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>$(function () { $('[data-toggle="tooltip"]').tooltip(); });</script>

{{-- Auto-Protect by Egg --}}
@php
  $autoProtectEggs = json_decode($settings->auto_protect_eggs ?? '[]', true) ?: [];
@endphp
<div class="row">
  <div class="col-xs-12">
    <div class="box box-warning">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-magic"></i> Auto-Protect by Egg</h3>
      </div>
      <form action="{{ $root }}" method="POST">
        {{ csrf_field() }}
        <input type="hidden" name="action" value="save_auto_protect">
        <div class="box-body">
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <div class="checkbox checkbox-primary no-margin-bottom">
                  <input type="checkbox" name="auto_protect_enabled" value="1" id="pAutoProtectEnabled"
                    {{ ($settings->auto_protect_enabled ?? false) ? 'checked' : '' }}>
                  <label for="pAutoProtectEnabled" class="strong">Enable Auto-Protect</label>
                </div>
                <p class="text-muted small">Automatically enable DDoS protection when a new server finishes installing, if its egg matches the selected eggs below.</p>
              </div>
            </div>
          </div>
          <div class="row" id="autoProtectEggSelect" style="{{ ($settings->auto_protect_enabled ?? false) ? '' : 'display:none' }}">
            <div class="col-md-8">
              <div class="form-group">
                <label>Select Eggs to Auto-Protect</label>
                <p class="text-muted small">New servers created with these eggs will have protection enabled automatically after installation completes. Uses your configured defaults for all protection settings.</p>
                <select name="auto_protect_eggs[]" multiple class="form-control" size="{{ min(max(count($eggs ?? []), 5), 15) }}" style="height:auto">
                  @foreach($eggs as $egg)
                    <option value="{{ $egg->id }}" {{ in_array($egg->id, $autoProtectEggs) ? 'selected' : '' }}>
                      [{{ $egg->nest_name }}] {{ $egg->name }}
                    </option>
                  @endforeach
                </select>
                <p class="text-muted small" style="margin-top:5px">Hold Ctrl/Cmd to select multiple eggs</p>
              </div>
            </div>
          </div>
        </div>
        <div class="box-footer">
          <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save"></i> Save Auto-Protect Settings</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
  document.getElementById('pAutoProtectEnabled').addEventListener('change', function() {
    document.getElementById('autoProtectEggSelect').style.display = this.checked ? '' : 'none';
  });
</script>

{{-- Server List --}}
<div class="row">
  <div class="col-xs-12">
    <div class="box box-success">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-server"></i> Servers</h3>
      </div>
      <div class="box-body table-responsive no-padding">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>ID</th>
              <th>Server Name</th>
              <th>Node</th>
              <th>Backend Address</th>
              <th>Protection Status</th>
              <th>Protected Address</th>
              <th>CNAME Target</th>
              <th>Permissions</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @foreach($servers as $server)
              @php
                $proxy = $proxies->get($server->id);
                $isProtected = $proxy && $proxy->proxy_status === 'active';
              @endphp
              <tr>
                <td>{{ $server->id }}</td>
                <td>
                  <a href="/admin/servers/view/{{ $server->id }}">{{ $server->name }}</a>
                </td>
                <td>{{ $server->node->name ?? 'N/A' }}</td>
                <td>
                  @if(($server->allocation->ip ?? '') === '0.0.0.0')
                    <code>{{ $server->node->fqdn ?? '?' }}:{{ $server->allocation->port ?? '?' }}</code>
                    <span class="label label-warning" data-toggle="tooltip" title="Allocation is 0.0.0.0 — using node IP ({{ $server->node->fqdn }})"><i class="fa fa-exclamation-triangle"></i></span>
                  @else
                    <code>{{ $server->allocation->ip ?? '?' }}:{{ $server->allocation->port ?? '?' }}</code>
                  @endif
                </td>
                <td>
                  @if($isProtected)
                    <span class="label label-success"><i class="fa fa-shield"></i> Protected</span>
                  @else
                    <span class="label label-default">Unprotected</span>
                  @endif
                </td>
                <td>
                  @if($isProtected)
                    <code>{{ $proxy->protected_address }}</code>
                  @else
                    —
                  @endif
                </td>
                <td>
                  @if($isProtected)
                    <code style="font-size:11px">{{ $proxy->cname_target ?? $settings->default_cname_target ?? 'spectrum-01.papyrus.vip' }}</code>
                  @else
                    <span class="text-muted" style="font-size:11px">{{ $settings->default_cname_target ?? 'spectrum-01.papyrus.vip' }}</span>
                  @endif
                </td>
                <td>
                  @if($isProtected)
                    <form action="{{ $root }}" method="POST" style="display:inline">
                      {{ csrf_field() }}
                      <input type="hidden" name="action" value="update_permissions">
                      <input type="hidden" name="server_id" value="{{ $server->id }}">
                      <label style="display:block;font-weight:normal;margin:0;font-size:11px;white-space:nowrap">
                        <input type="checkbox" name="allow_motd_edit" value="1"
                          {{ ($proxy->allow_motd_edit ?? true) ? 'checked' : '' }}
                          onchange="this.form.submit()"> MOTD
                      </label>
                      <label style="display:block;font-weight:normal;margin:0;font-size:11px;white-space:nowrap">
                        <input type="checkbox" name="allow_messages_edit" value="1"
                          {{ ($proxy->allow_messages_edit ?? true) ? 'checked' : '' }}
                          onchange="this.form.submit()"> Messages
                      </label>
                      <div style="margin-top:3px">
                        <label style="font-weight:normal;margin:0;font-size:11px;white-space:nowrap">
                          Domains:
                          <input type="number" name="domain_limit" value="{{ $proxy->domain_limit ?? $settings->default_domain_limit ?? 3 }}"
                            min="0" max="100" style="width:45px;font-size:11px;padding:1px 3px" onchange="this.form.submit()">
                        </label>
                      </div>
                    </form>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>
                  <form action="{{ $root }}" method="POST" style="display:inline"
                    onsubmit="return confirm('{{ $isProtected ? 'Disable protection for ' . $server->name . '?' : 'Enable protection for ' . $server->name . '?' }}')">
                    {{ csrf_field() }}
                    <input type="hidden" name="action" value="toggle_protection">
                    <input type="hidden" name="server_id" value="{{ $server->id }}">
                    @if(!$isProtected)
                      <div class="input-group input-group-sm" style="width:220px;display:inline-table;margin-bottom:0">
                        <input type="text" class="form-control" name="cname_target"
                          value="{{ $settings->default_cname_target ?? 'spectrum-01.papyrus.vip' }}"
                          placeholder="spectrum-01.papyrus.vip" style="font-size:11px">
                        <span class="input-group-btn">
                          <button type="submit" class="btn btn-success btn-sm"><i class="fa fa-shield"></i> Enable</button>
                        </span>
                      </div>
                    @else
                      <button type="submit" class="btn btn-danger btn-xs"><i class="fa fa-power-off"></i> Disable</button>
                    @endif
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

{{-- Activity Log --}}
<div class="row">
  <div class="col-xs-12">
    <div class="box box-default">
      <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-history"></i> Recent Activity</h3>
      </div>
      <div class="box-body table-responsive no-padding">
        @php
          $activities = \Schema::hasTable('papyrus_activity_log')
            ? \DB::table('papyrus_activity_log')
              ->leftJoin('servers', 'papyrus_activity_log.server_id', '=', 'servers.id')
              ->leftJoin('users', 'papyrus_activity_log.user_id', '=', 'users.id')
              ->select('papyrus_activity_log.*', 'servers.name as server_name', 'users.username as username')
              ->orderByDesc('papyrus_activity_log.created_at')
              ->limit(25)
              ->get()
            : collect();
        @endphp
        @if($activities->count() > 0)
          <table class="table table-hover">
            <thead>
              <tr>
                <th>Time</th>
                <th>User</th>
                <th>Action</th>
                <th>Server</th>
                <th>Details</th>
              </tr>
            </thead>
            <tbody>
              @foreach($activities as $activity)
                <tr>
                  <td>{{ \Carbon\Carbon::parse($activity->created_at)->diffForHumans() }}</td>
                  <td>{{ $activity->username ?? 'System' }}</td>
                  <td>
                    @switch($activity->action)
                      @case('protection.enabled')
                        <span class="label label-success">Enabled</span>
                        @break
                      @case('protection.disabled')
                        <span class="label label-danger">Disabled</span>
                        @break
                      @case('protection.auto_enabled')
                        <span class="label label-success"><i class="fa fa-magic"></i> Auto-Enabled</span>
                        @break
                      @case('antibot.updated')
                        <span class="label label-info">Antibot Updated</span>
                        @break
                      @case('antibot.reset')
                        <span class="label label-warning">Antibot Reset</span>
                        @break
                      @case('messages.updated')
                        <span class="label label-info">Messages Updated</span>
                        @break
                      @case('messages.reset')
                        <span class="label label-warning">Messages Reset</span>
                        @break
                      @case('domain.added')
                        <span class="label label-primary">Domain Added</span>
                        @break
                      @case('domain.verified')
                        <span class="label label-success">Domain Verified</span>
                        @break
                      @case('domain.removed')
                        <span class="label label-danger">Domain Removed</span>
                        @break
                      @default
                        <span class="label label-default">{{ $activity->action }}</span>
                    @endswitch
                  </td>
                  <td>
                    @if($activity->server_name)
                      <a href="/admin/servers/view/{{ $activity->server_id }}">{{ $activity->server_name }}</a>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  <td>
                    @if($activity->metadata)
                      @php $meta = json_decode($activity->metadata, true); @endphp
                      @if(is_array($meta))
                        <small class="text-muted">
                          @foreach(array_slice($meta, 0, 3) as $k => $v)
                            {{ $k }}: {{ is_string($v) ? Str::limit($v, 30) : json_encode($v) }}{{ !$loop->last ? ' | ' : '' }}
                          @endforeach
                        </small>
                      @endif
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @else
          <div class="box-body">
            <p class="text-muted">No activity recorded yet.</p>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
