import React, { useEffect, useState } from 'react';
import http from '@/api/http';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import Button from '@/components/elements/Button';
import tw from 'twin.macro';
import { faCommentDots, faRedo } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';

const KICK_MESSAGE_FIELDS: { key: string; label: string; placeholder: string; group: string; multiline?: boolean }[] = [
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

const toDisplay = (val: string) => val?.replace(/\\n/g, '\n') ?? '';
const toStorage = (val: string) => val?.replace(/\n/g, '\\n') ?? '';

const MessagesConfig = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [motdUp, setMotdUp] = useState('');
    const [motdDown, setMotdDown] = useState('');
    const [kickMessages, setKickMessages] = useState<Record<string, string>>({});
    const [allowMotdEdit, setAllowMotdEdit] = useState(true);
    const [allowMessagesEdit, setAllowMessagesEdit] = useState(true);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState('');

    useEffect(() => {
        setLoading(true);
        http.get(`/api/client/extensions/papyrusprotection/messages/${uuid}`)
            .then((response) => {
                setMotdUp(response.data.motd_up || '');
                setMotdDown(response.data.motd_down || '');
                setKickMessages(response.data.kick_messages || {});
                setAllowMotdEdit(response.data.allow_motd_edit ?? true);
                setAllowMessagesEdit(response.data.allow_messages_edit ?? true);
            })
            .catch(() => setMessage('Failed to load message settings.'))
            .finally(() => setLoading(false));
    }, [uuid]);

    const save = () => {
        setSaving(true);
        setMessage('');
        http.put(`/api/client/extensions/papyrusprotection/messages/${uuid}`, {
            motd_up: motdUp,
            motd_down: motdDown,
            kick_messages: kickMessages,
        })
            .then(() => setMessage('Messages saved.'))
            .catch((e) => setMessage(e.response?.data?.error || 'Failed to save.'))
            .finally(() => setSaving(false));
    };

    const reset = () => {
        if (!confirm('Reset all messages to server defaults?')) return;
        setSaving(true);
        http.post(`/api/client/extensions/papyrusprotection/messages/${uuid}/reset`)
            .then((response) => {
                setMotdUp(response.data.motd_up || '');
                setMotdDown(response.data.motd_down || '');
                setKickMessages(response.data.kick_messages || {});
                setMessage('Messages reset to defaults.');
            })
            .catch((e) => setMessage(e.response?.data?.error || 'Failed to reset.'))
            .finally(() => setSaving(false));
    };

    if (loading) {
        return (
            <ServerContentBlock title={'Server Messages'}>
                <Spinner size={'large'} centered />
            </ServerContentBlock>
        );
    }

    if (!allowMotdEdit && !allowMessagesEdit) {
        return (
            <ServerContentBlock title={'Server Messages'}>
                <TitledGreyBox title={'Server Messages'} icon={faCommentDots}>
                    <p css={tw`text-neutral-400 text-sm`}>
                        Message editing has been disabled for this server by your hosting provider.
                    </p>
                </TitledGreyBox>
            </ServerContentBlock>
        );
    }

    return (
        <ServerContentBlock title={'Server Messages'}>
            {message && (
                <div css={tw`bg-neutral-900 text-neutral-200 p-3 rounded mb-4 text-sm`}>
                    {message}
                </div>
            )}

            {/* MOTD */}
            {allowMotdEdit && (
                <TitledGreyBox title={'Offline MOTD'} icon={faCommentDots} css={tw`mb-6`}>
                    <p css={tw`text-neutral-400 text-sm mb-4`}>
                        Displayed in the Minecraft server list when the backend is unreachable. Supports &amp; color codes.
                    </p>
                    <div css={tw`grid gap-4`} style={{ gridTemplateColumns: '1fr 1fr' }}>
                        <div>
                            <label css={tw`text-neutral-300 text-sm font-medium block mb-2`}>Line 1</label>
                            <input
                                type={'text'}
                                value={motdUp}
                                onChange={(e) => setMotdUp(e.target.value)}
                                css={tw`w-full bg-neutral-900 border border-neutral-600 rounded px-3 py-2 text-sm text-neutral-200 font-mono`}
                                placeholder={'%center%&bPapyrus.vip - Server Offline'}
                            />
                        </div>
                        <div>
                            <label css={tw`text-neutral-300 text-sm font-medium block mb-2`}>Line 2</label>
                            <input
                                type={'text'}
                                value={motdDown}
                                onChange={(e) => setMotdDown(e.target.value)}
                                css={tw`w-full bg-neutral-900 border border-neutral-600 rounded px-3 py-2 text-sm text-neutral-200 font-mono`}
                                placeholder={'%center%&cUnexpected error.'}
                            />
                        </div>
                    </div>
                    <div css={tw`mt-4`}>
                        <Button onClick={save} disabled={saving}>
                            {saving ? 'Saving...' : 'Save MOTD'}
                        </Button>
                    </div>
                </TitledGreyBox>
            )}

            {/* Kick Messages — each group is its own section */}
            {allowMessagesEdit && (['General', 'Limbo'] as const).map((group) => (
                <TitledGreyBox
                    key={group}
                    title={group === 'General' ? 'Kick Messages' : 'Limbo Messages'}
                    icon={faCommentDots}
                    css={tw`mb-6`}
                >
                    <p css={tw`text-neutral-400 text-sm mb-4`}>
                        {group === 'General'
                            ? 'Messages shown to players when disconnected by antibot protection. Each line in the textarea = one line in-game.'
                            : 'Messages shown during the limbo verification process. Each line = one line in-game.'}
                    </p>
                    <div css={tw`space-y-5`}>
                        {KICK_MESSAGE_FIELDS.filter(f => f.group === group).map((field) => (
                            <div key={field.key}>
                                <label css={tw`text-neutral-300 text-sm font-medium block mb-2`}>
                                    {field.label}
                                </label>
                                {field.multiline ? (
                                    <textarea
                                        rows={5}
                                        value={toDisplay(kickMessages[field.key] ?? '')}
                                        onChange={(e) => setKickMessages({ ...kickMessages, [field.key]: toStorage(e.target.value) })}
                                        css={tw`w-full bg-neutral-900 border border-neutral-600 rounded px-3 py-2 text-neutral-200`}
                                        placeholder={field.placeholder}
                                        style={{ resize: 'vertical', minHeight: '100px', fontFamily: 'monospace', fontSize: '13px', lineHeight: '1.6' }}
                                    />
                                ) : (
                                    <input
                                        type={'text'}
                                        value={kickMessages[field.key] ?? ''}
                                        onChange={(e) => setKickMessages({ ...kickMessages, [field.key]: e.target.value })}
                                        css={tw`w-full bg-neutral-900 border border-neutral-600 rounded px-3 py-2 text-neutral-200`}
                                        placeholder={field.placeholder}
                                        style={{ fontFamily: 'monospace', fontSize: '13px' }}
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                </TitledGreyBox>
            ))}

            <div css={tw`flex gap-3 mb-6`}>
                <Button onClick={save} disabled={saving}>
                    {saving ? 'Saving...' : 'Save All Messages'}
                </Button>
                <button
                    onClick={reset}
                    disabled={saving}
                    css={tw`px-4 py-2 rounded bg-neutral-600 hover:bg-neutral-500 text-white text-sm transition-colors`}
                >
                    <FontAwesomeIcon icon={faRedo} css={tw`mr-1`} />
                    Reset to Defaults
                </button>
            </div>
        </ServerContentBlock>
    );
};

export default MessagesConfig;
