import React, { useEffect, useState } from 'react';
import http from '@/api/http';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import tw from 'twin.macro';
import styled from 'styled-components';
import { faShieldAlt, faCopy, faCheckCircle, faTimesCircle, faGlobe, faRobot, faEnvelope, faRedo, faPlus, faTrash, faCheck, faNetworkWired } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';

const API = '/api/client/extensions/papyrusprotection';

const Label = styled.label`${tw`block text-sm font-medium text-neutral-300 mb-1`}`;
const Input = styled.input`${tw`w-full bg-neutral-600 border border-neutral-500 rounded px-3 py-2 text-neutral-100`}`;
const TextArea = styled.textarea`${tw`w-full bg-neutral-600 border border-neutral-500 rounded px-3 py-2 text-neutral-100`}`;
const Btn = styled.button`${tw`px-4 py-2 rounded font-medium text-sm transition-colors mr-2`}`;
const PrimaryBtn = styled(Btn)`${tw`bg-primary-600 text-white hover:bg-primary-500`}`;
const DangerBtn = styled(Btn)`${tw`bg-red-600 text-white hover:bg-red-500`}`;
const SecondaryBtn = styled(Btn)`${tw`bg-neutral-600 text-neutral-200 hover:bg-neutral-500`}`;
const SuccessBtn = styled(Btn)`${tw`bg-green-600 text-white hover:bg-green-500`}`;
const Muted = styled.p`${tw`text-neutral-400 text-sm mt-1`}`;
const Alert = styled.div<{ $type: 'success' | 'error' }>`
    ${tw`rounded p-3 mb-4 text-sm`}
    ${({ $type }) => $type === 'success' ? tw`bg-green-900 text-green-200` : tw`bg-red-900 text-red-200`}
`;

interface ProtectionData {
    protected: boolean;
    status?: string;
    protected_address?: string;
    backend_port?: number;
    proxy_protocol_disabled?: boolean;
    created_at?: string;
    message?: string;
    antibot_config?: Record<string, any>;
    motd_up?: string;
    motd_down?: string;
    kick_messages?: Record<string, string>;
    allow_motd_edit?: boolean;
    allow_messages_edit?: boolean;
    whitelabel?: boolean;
    branding_name?: string;
}

const KICK_MESSAGE_FIELDS: { key: string; label: string; placeholder: string; group?: string; multiline?: boolean }[] = [
    { key: 'reconnect_kick', label: 'Reconnect Kick', placeholder: '&8&l#m------------ ...\n&7Please wait &c3 seconds &7and reconnect\n&7Pending reconnects: &c%remaining%\n&8&l#m------------ ...', group: 'General', multiline: true },
    { key: 'invalid_name_kick_v2', label: 'Invalid Name Kick', placeholder: '&8&l#m------------ ...\n&7Your connection has been disconnected\n&8&l#m------------ ...', group: 'General', multiline: true },
    { key: 'invalid_name_kick_v1', label: 'Invalid Name Kick (Legacy)', placeholder: '&8&l#m------------ ...\n&7Your connection has been disconnected\n&8&l#m------------ ...', group: 'General', multiline: true },
    { key: 'blacklist_kick', label: 'Blacklist Kick', placeholder: '&8&l#m------------ ...\n&7Your connection has been disconnected\n&8&l#m------------ ...', group: 'General', multiline: true },
    { key: 'blacklist_kick_attempting', label: 'Blacklist Kick (Attempting)', placeholder: '&8&l#m------------ ...\n&7Your connection has been disconnected\n&8&l#m------------ ...', group: 'General', multiline: true },
    { key: 'bad_bytebuf_max_packet_kick', label: 'Bad Packet Kick', placeholder: '&8&l#m------------ ...\n&7Your connection has been disconnected\n&8&l#m------------ ...', group: 'General', multiline: true },
    { key: 'limbo_join_message', label: 'Limbo Join Message', placeholder: '&bBlaze &8> &fVerifying your connection... Please wait. :D', group: 'Limbo' },
    { key: 'limbo_enter_code_message', label: 'Limbo Enter Code', placeholder: '&bBlaze &8> &fPlease enter the text in chat that is displayed on the map.', group: 'Limbo' },
    { key: 'limbo_attempts_message', label: 'Limbo Attempts Message', placeholder: '&bBlaze &8> &cYour captcha is invalid. &f(attempts-left) attempts &cleft.', group: 'Limbo' },
    { key: 'limbo_verified_kick', label: 'Limbo Verified Kick', placeholder: '&8&l#m------------ ...\n&fYour connection was verified successfully.\n&fYou may now rejoin the server\n&8&l#m------------ ...', group: 'Limbo', multiline: true },
    { key: 'limbo_failed_kick', label: 'Limbo Failed Kick', placeholder: '&8&l#m------------ ...\n&cYour connection was disconnected.\n&8&l#m------------ ...', group: 'Limbo', multiline: true },
];

// Convert \n literal strings in stored values to real newlines for textarea display
const toDisplay = (val: string) => val?.replace(/\\n/g, '\n') ?? '';
// Convert real newlines back to \n for API storage
const toStorage = (val: string) => val?.replace(/\n/g, '\\n') ?? '';

interface Domain {
    id: number;
    domain: string;
    verification_status: string;
}

const ProtectionTab = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

    const [loading, setLoading] = useState(true);
    const [data, setData] = useState<ProtectionData | null>(null);
    const [domains, setDomains] = useState<Domain[]>([]);
    const [copied, setCopied] = useState(false);
    const [alert, setAlert] = useState<{ type: 'success' | 'error'; msg: string } | null>(null);
    const [saving, setSaving] = useState(false);

    // Domain form
    const [newDomain, setNewDomain] = useState('');
    const [domainLimit, setDomainLimit] = useState(0);

    // Antibot form
    const [antibotConfig, setAntibotConfig] = useState<Record<string, any>>({});

    // Proxy protocol
    const [proxyProtocolDisabled, setProxyProtocolDisabled] = useState(true);

    // Messages form
    const [motdUp, setMotdUp] = useState('');
    const [motdDown, setMotdDown] = useState('');
    const [kickMessages, setKickMessages] = useState<Record<string, string>>({});
    const [showKickMessages, setShowKickMessages] = useState(false);

    const showAlert = (type: 'success' | 'error', msg: string) => {
        setAlert({ type, msg });
        setTimeout(() => setAlert(null), 5000);
    };

    const fetchAll = async () => {
        setLoading(true);
        try {
            const [protRes, domRes] = await Promise.all([
                http.get(`${API}/protection/${uuid}`),
                http.get(`${API}/domains/${uuid}`).catch(() => ({ data: { domains: [] } })),
            ]);

            const prot = protRes.data;
            setData(prot);
            setDomains(domRes.data.domains || []);
            setDomainLimit(domRes.data.domain_limit ?? 0);

            if (prot.protected) {
                setAntibotConfig(prot.antibot_config || {});
                setProxyProtocolDisabled(prot.proxy_protocol_disabled ?? true);
                setMotdUp(prot.motd_up || '');
                setMotdDown(prot.motd_down || '');
                setKickMessages(prot.kick_messages || {});
            }
        } catch {
            setData({ protected: false, message: 'Failed to load protection status.' });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { fetchAll(); }, [uuid]);

    const copyAddress = () => {
        if (!data?.protected_address) return;
        const port = data.backend_port && data.backend_port !== 25565 ? `:${data.backend_port}` : '';
        navigator.clipboard.writeText(`${data.protected_address}${port}`);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    // --- Domains ---
    const addDomain = async () => {
        if (!newDomain.trim()) return;
        setSaving(true);
        try {
            const res = await http.post(`${API}/domains/${uuid}`, { domain: newDomain.trim() });
            setDomains([...domains, res.data]);
            setNewDomain('');
            showAlert('success', 'Domain added. Follow the setup instructions below.');
        } catch (err: any) {
            showAlert('error', err?.response?.data?.error || 'Failed to add domain.');
        } finally {
            setSaving(false);
        }
    };

    const verifyDomain = async (domainId: number) => {
        try {
            const res = await http.post(`${API}/domains/${uuid}/${domainId}/verify`);
            if (res.data.verified) {
                setDomains(domains.map(d => d.id === domainId ? { ...d, verification_status: 'verified' } : d));
                showAlert('success', 'Domain verified!');
            } else {
                showAlert('error', res.data.message || 'Verification failed. Check your DNS records.');
            }
        } catch (err: any) {
            showAlert('error', err?.response?.data?.error || 'Verification failed.');
        }
    };

    const removeDomain = async (domainId: number) => {
        if (!confirm('Remove this domain?')) return;
        try {
            await http.delete(`${API}/domains/${uuid}/${domainId}`);
            setDomains(domains.filter(d => d.id !== domainId));
            showAlert('success', 'Domain removed.');
        } catch (err: any) {
            showAlert('error', err?.response?.data?.error || 'Failed to remove domain.');
        }
    };

    // --- Antibot ---
    const saveAntibot = async () => {
        setSaving(true);
        try {
            await http.put(`${API}/antibot/${uuid}`, { antibot_config: antibotConfig });
            showAlert('success', 'Antibot settings saved.');
        } catch (err: any) {
            showAlert('error', err?.response?.data?.error || 'Failed to save antibot settings.');
        } finally {
            setSaving(false);
        }
    };

    const resetAntibot = async () => {
        setSaving(true);
        try {
            const res = await http.post(`${API}/antibot/${uuid}/reset`);
            setAntibotConfig(res.data.antibot_config || {});
            showAlert('success', 'Antibot settings reset to defaults.');
        } catch (err: any) {
            showAlert('error', err?.response?.data?.error || 'Failed to reset.');
        } finally {
            setSaving(false);
        }
    };

    // --- Proxy Protocol ---
    const toggleProxyProtocol = async (disabled: boolean) => {
        setSaving(true);
        try {
            const res = await http.put(`${API}/antibot/${uuid}/proxy-protocol`, { proxy_protocol_disabled: disabled });
            setProxyProtocolDisabled(res.data.proxy_protocol_disabled);
            showAlert('success', disabled ? 'Proxy protocol disabled.' : 'Proxy protocol enabled.');
        } catch (err: any) {
            showAlert('error', err?.response?.data?.error || 'Failed to update proxy protocol.');
        } finally {
            setSaving(false);
        }
    };

    // --- Messages ---
    const saveMessages = async () => {
        setSaving(true);
        try {
            await http.put(`${API}/messages/${uuid}`, { motd_up: motdUp, motd_down: motdDown, kick_messages: kickMessages });
            showAlert('success', 'Messages saved.');
        } catch (err: any) {
            showAlert('error', err?.response?.data?.error || 'Failed to save messages.');
        } finally {
            setSaving(false);
        }
    };

    const resetMessages = async () => {
        setSaving(true);
        try {
            const res = await http.post(`${API}/messages/${uuid}/reset`);
            setMotdUp(res.data.motd_up || '');
            setMotdDown(res.data.motd_down || '');
            setKickMessages(res.data.kick_messages || {});
            showAlert('success', 'Messages reset to defaults.');
        } catch (err: any) {
            showAlert('error', err?.response?.data?.error || 'Failed to reset.');
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <ServerContentBlock title={'DDoS Protection'}>
                <Spinner size={'large'} centered />
            </ServerContentBlock>
        );
    }

    if (!data?.protected) {
        const unprotectedBrand = data?.branding_name || 'Papyrus.vip';
        return (
            <ServerContentBlock title={`${unprotectedBrand} DDoS Protection`}>
                <TitledGreyBox title={'Protection Status'} icon={faShieldAlt}>
                    <div css={tw`flex items-center mb-3`}>
                        <FontAwesomeIcon icon={faTimesCircle} css={tw`text-red-500 mr-2`} size={'lg'} />
                        <span css={tw`text-neutral-400 font-bold text-lg`}>Not Protected</span>
                    </div>
                    <p css={tw`text-neutral-400 text-sm`}>
                        {data?.message || 'DDoS protection has not been enabled for this server. Contact your hosting provider to enable it.'}
                    </p>
                </TitledGreyBox>
            </ServerContentBlock>
        );
    }

    const displayPort = data.backend_port && data.backend_port !== 25565 ? `:${data.backend_port}` : '';
    const brandName = data.branding_name || 'Papyrus.vip';
    const isWhitelabel = data.whitelabel ?? false;

    return (
        <ServerContentBlock title={`${brandName} DDoS Protection`}>
            {alert && <Alert $type={alert.type}>{alert.msg}</Alert>}

            {/* === Protection Status === */}
            <div css={tw`grid gap-4 mb-6`} style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))' }}>
                <TitledGreyBox title={'Protection Status'} icon={faShieldAlt}>
                    <div css={tw`flex items-center mb-3`}>
                        <FontAwesomeIcon icon={faCheckCircle} css={tw`text-green-500 mr-2`} size={'lg'} />
                        <span css={tw`text-green-400 font-bold text-lg`}>Protected</span>
                    </div>
                    <p css={tw`text-neutral-300 text-sm`}>
                        Your server is protected by {brandName} DDoS protection. All traffic is filtered{!isWhitelabel && ' through the Papyrus network'}.
                    </p>
                    {!isWhitelabel && (
                        <div css={tw`flex items-center mt-3 pt-3 border-t border-neutral-700`}>
                            <picture style={{ flexShrink: 0, marginRight: '10px' }}>
                                <source srcSet="/extensions/papyrusprotection/logo-light.png" media="(prefers-color-scheme: light)" />
                                <img
                                    src="/extensions/papyrusprotection/logo-dark.png"
                                    alt="Papyrus.vip"
                                    style={{ height: '24px', width: 'auto', objectFit: 'contain', opacity: 0.85 }}
                                />
                            </picture>
                            <p css={tw`text-neutral-500 text-xs m-0`}>
                                Powered by <a href="https://papyrus.vip" target="_blank" rel="noopener noreferrer" css={tw`text-primary-400 hover:text-primary-300`}>Papyrus.vip</a> — Enterprise Minecraft DDoS Protection
                            </p>
                        </div>
                    )}
                </TitledGreyBox>

                <TitledGreyBox title={'Connection Address'}>
                    <Muted css={tw`mb-3 mt-0`}>Share this address with your players:</Muted>
                    <div css={tw`flex items-center bg-neutral-900 rounded p-3`}>
                        <code css={tw`text-green-400 text-lg flex-1 font-mono`}>
                            {data.protected_address}{displayPort}
                        </code>
                        <button
                            onClick={copyAddress}
                            css={tw`ml-3 px-3 py-1 rounded bg-neutral-700 hover:bg-neutral-600 transition-colors text-sm`}
                        >
                            <FontAwesomeIcon icon={copied ? faCheck : faCopy} css={tw`mr-1`} />
                            {copied ? 'Copied!' : 'Copy'}
                        </button>
                    </div>
                    {data.created_at && (
                        <p css={tw`text-neutral-500 text-xs mt-2`}>
                            Protected since {new Date(data.created_at).toLocaleDateString()}
                        </p>
                    )}
                </TitledGreyBox>
            </div>

            {/* === Custom Domains === */}
            <TitledGreyBox title={'Custom Domains'} icon={faGlobe} css={tw`mb-6`}>
                {domainLimit > 0 && (
                    <Muted css={tw`mt-0 mb-3`}>
                        {domains.length} / {domainLimit} custom domains used
                    </Muted>
                )}
                <div css={tw`flex gap-2 mb-4`}>
                    <Input
                        type="text"
                        placeholder="play.yourdomain.com"
                        value={newDomain}
                        onChange={(e) => setNewDomain(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && addDomain()}
                        css={tw`flex-1`}
                        disabled={domainLimit > 0 && domains.length >= domainLimit}
                    />
                    <SuccessBtn onClick={addDomain} disabled={saving || !newDomain.trim() || (domainLimit > 0 && domains.length >= domainLimit)}>
                        <FontAwesomeIcon icon={faPlus} css={tw`mr-1`} /> Add
                    </SuccessBtn>
                </div>

                {domains.length > 0 ? domains.map((domain) => (
                    <div key={domain.id} css={tw`bg-neutral-900 rounded p-4 mb-3`}>
                        <div css={tw`flex items-center justify-between mb-2`}>
                            <div css={tw`flex items-center`}>
                                <FontAwesomeIcon
                                    icon={domain.verification_status === 'verified' ? faCheckCircle : faTimesCircle}
                                    css={domain.verification_status === 'verified' ? tw`text-green-400 mr-2` : tw`text-yellow-400 mr-2`}
                                />
                                <span css={tw`text-neutral-100 font-medium`}>{domain.domain}</span>
                                {domain.verification_status === 'verified' && (
                                    <span css={tw`ml-2 text-xs bg-green-800 text-green-200 px-2 py-0.5 rounded`}>Verified</span>
                                )}
                            </div>
                            <div css={tw`flex gap-2`}>
                                {domain.verification_status !== 'verified' && (
                                    <SuccessBtn onClick={() => verifyDomain(domain.id)} css={tw`py-1 px-3`}>
                                        Verify
                                    </SuccessBtn>
                                )}
                                <DangerBtn onClick={() => removeDomain(domain.id)} css={tw`py-1 px-3`}>
                                    <FontAwesomeIcon icon={faTrash} />
                                </DangerBtn>
                            </div>
                        </div>
                        {domain.verification_status !== 'verified' && (
                            <div css={tw`bg-neutral-800 rounded p-3 text-sm mt-2`}>
                                <p css={tw`text-neutral-300 mb-2 font-medium`}>DNS Setup:</p>
                                <p css={tw`text-neutral-400`}>
                                    Add a <code css={tw`text-primary-400`}>CNAME</code> record pointing <code css={tw`text-primary-400`}>{domain.domain}</code> → <code css={tw`text-primary-400`}>{data.protected_address}</code>
                                </p>
                                <Muted>Click Verify once DNS has propagated.</Muted>
                            </div>
                        )}
                    </div>
                )) : (
                    <Muted>No custom domains. Add a domain to let players connect via your own address.</Muted>
                )}
            </TitledGreyBox>

            {/* === Antibot Settings === */}
            <TitledGreyBox title={'Antibot Settings'} icon={faRobot} css={tw`mb-6`}>
                <div css={tw`grid gap-4`} style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(250px, 1fr))' }}>
                    <div>
                        <Label>CPS Threshold</Label>
                        <div css={tw`flex items-center gap-3`}>
                            <input
                                type="range"
                                min="1"
                                max="50"
                                value={antibotConfig['cps-to-trigger-antibot'] ?? 10}
                                onChange={(e) => setAntibotConfig({ ...antibotConfig, 'cps-to-trigger-antibot': parseInt(e.target.value) })}
                                css={tw`flex-1`}
                            />
                            <span css={tw`text-neutral-200 font-mono w-8 text-right`}>{antibotConfig['cps-to-trigger-antibot'] ?? 10}</span>
                        </div>
                        <Muted>Connections/sec to trigger antibot.</Muted>
                    </div>
                    <div css={tw`space-y-3`}>
                        <label css={tw`flex items-center gap-2 text-neutral-200 cursor-pointer`}>
                            <input
                                type="checkbox"
                                checked={antibotConfig.captcha ?? true}
                                onChange={(e) => setAntibotConfig({ ...antibotConfig, captcha: e.target.checked })}
                            />
                            Captcha Verification
                        </label>
                        <label css={tw`flex items-center gap-2 text-neutral-200 cursor-pointer`}>
                            <input
                                type="checkbox"
                                checked={antibotConfig.limbo ?? true}
                                onChange={(e) => setAntibotConfig({ ...antibotConfig, limbo: e.target.checked })}
                            />
                            Limbo Verification
                        </label>
                        <label css={tw`flex items-center gap-2 text-neutral-200 cursor-pointer`}>
                            <input
                                type="checkbox"
                                checked={antibotConfig['enable-prevent-chat-reports-support'] ?? false}
                                onChange={(e) => setAntibotConfig({ ...antibotConfig, 'enable-prevent-chat-reports-support': e.target.checked })}
                            />
                            Prevent Chat Reports
                        </label>
                    </div>
                </div>
                <div css={tw`mt-4`}>
                    <PrimaryBtn onClick={saveAntibot} disabled={saving}>Save Antibot</PrimaryBtn>
                    <SecondaryBtn onClick={resetAntibot} disabled={saving}>
                        <FontAwesomeIcon icon={faRedo} css={tw`mr-1`} /> Reset to Defaults
                    </SecondaryBtn>
                </div>
            </TitledGreyBox>

            {/* === Proxy Protocol === */}
            <TitledGreyBox title={'Proxy Protocol'} icon={faNetworkWired} css={tw`mb-6`}>
                <div css={tw`flex items-center justify-between`}>
                    <div>
                        <p css={tw`text-neutral-200 font-medium`}>
                            Proxy Protocol is currently {proxyProtocolDisabled ? 'disabled' : 'enabled'}
                        </p>
                        <Muted css={tw`mt-0`}>
                            {proxyProtocolDisabled
                                ? 'The proxy does not forward client IPs to your server. Enable if your server supports Proxy Protocol.'
                                : 'The proxy forwards real client IPs to your server via Proxy Protocol headers.'}
                        </Muted>
                    </div>
                    {proxyProtocolDisabled ? (
                        <SuccessBtn onClick={() => toggleProxyProtocol(false)} disabled={saving}>Enable</SuccessBtn>
                    ) : (
                        <DangerBtn onClick={() => toggleProxyProtocol(true)} disabled={saving}>Disable</DangerBtn>
                    )}
                </div>
            </TitledGreyBox>

            {/* === MOTD Settings === */}
            {data.allow_motd_edit && (
                <TitledGreyBox title={'MOTD Settings'} icon={faEnvelope} css={tw`mb-6`}>
                    <Muted css={tw`mt-0 mb-4`}>
                        Displayed in the Minecraft server list when the backend is unreachable. Supports &amp; color codes.
                    </Muted>
                    <div css={tw`grid gap-4`} style={{ gridTemplateColumns: '1fr 1fr' }}>
                        <div>
                            <Label>Offline MOTD — Line 1</Label>
                            <Input
                                value={motdUp}
                                onChange={(e) => setMotdUp(e.target.value)}
                                placeholder="%center%&bPapyrus.vip - Server Offline"
                            />
                        </div>
                        <div>
                            <Label>Offline MOTD — Line 2</Label>
                            <Input
                                value={motdDown}
                                onChange={(e) => setMotdDown(e.target.value)}
                                placeholder="%center%&cUnexpected error."
                            />
                        </div>
                    </div>
                    <div css={tw`mt-4`}>
                        <PrimaryBtn onClick={saveMessages} disabled={saving}>Save MOTD</PrimaryBtn>
                    </div>
                </TitledGreyBox>
            )}

            {/* === Kick & Mitigation Messages === */}
            {data.allow_messages_edit ? (
                <>
                    {(['General', 'Limbo'] as const).map((group) => (
                        <TitledGreyBox
                            key={group}
                            title={group === 'General' ? 'Kick Messages' : 'Limbo Messages'}
                            icon={faEnvelope}
                            css={tw`mb-6`}
                        >
                            <Muted css={tw`mt-0 mb-4`}>
                                {group === 'General'
                                    ? 'Messages shown to players when disconnected by antibot protection. Each line in the textarea = one line in-game.'
                                    : 'Messages shown during the limbo verification process. Each line = one line in-game.'}
                            </Muted>
                            <div css={tw`space-y-5`}>
                                {KICK_MESSAGE_FIELDS.filter(f => f.group === group).map((field) => (
                                    <div key={field.key}>
                                        <Label css={tw`mb-2`}>{field.label}</Label>
                                        {field.multiline ? (
                                            <TextArea
                                                rows={5}
                                                value={toDisplay(kickMessages[field.key] ?? '')}
                                                onChange={(e) => setKickMessages({ ...kickMessages, [field.key]: toStorage(e.target.value) })}
                                                placeholder={field.placeholder}
                                                style={{ resize: 'vertical', minHeight: '100px', fontFamily: 'monospace', fontSize: '13px', lineHeight: '1.6' }}
                                            />
                                        ) : (
                                            <Input
                                                value={kickMessages[field.key] ?? ''}
                                                onChange={(e) => setKickMessages({ ...kickMessages, [field.key]: e.target.value })}
                                                placeholder={field.placeholder}
                                                style={{ fontFamily: 'monospace', fontSize: '13px' }}
                                            />
                                        )}
                                    </div>
                                ))}
                            </div>
                        </TitledGreyBox>
                    ))}
                </>
            ) : (
                !data.allow_motd_edit && (
                    <TitledGreyBox title={'Server Messages'} icon={faEnvelope} css={tw`mb-6`}>
                        <Muted css={tw`mt-0`}>Message editing has been disabled for this server by your hosting provider.</Muted>
                    </TitledGreyBox>
                )
            )}

            {/* === Save Messages === */}
            {(data.allow_motd_edit || data.allow_messages_edit) && (
                <div css={tw`mb-6 flex gap-3`}>
                    <PrimaryBtn onClick={saveMessages} disabled={saving}>Save All Messages</PrimaryBtn>
                    <SecondaryBtn onClick={resetMessages} disabled={saving}>
                        <FontAwesomeIcon icon={faRedo} css={tw`mr-1`} /> Reset to Defaults
                    </SecondaryBtn>
                </div>
            )}
        </ServerContentBlock>
    );
};

export default ProtectionTab;
