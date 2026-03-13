import React, { useEffect, useState } from 'react';
import http from '@/api/http';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import Button from '@/components/elements/Button';
import tw from 'twin.macro';
import { faRobot } from '@fortawesome/free-solid-svg-icons';

const AntibotConfig = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [config, setConfig] = useState<Record<string, any>>({});
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState('');

    useEffect(() => {
        setLoading(true);
        http.get(`/api/client/extensions/papyrusprotection/antibot/${uuid}`)
            .then((response) => setConfig(response.data.antibot_config || {}))
            .catch(() => setMessage('Failed to load antibot settings.'))
            .finally(() => setLoading(false));
    }, [uuid]);

    const save = () => {
        setSaving(true);
        setMessage('');
        http.put(`/api/client/extensions/papyrusprotection/antibot/${uuid}`, { antibot_config: config })
            .then(() => setMessage('Antibot settings saved.'))
            .catch((e) => setMessage(e.response?.data?.error || 'Failed to save.'))
            .finally(() => setSaving(false));
    };

    const reset = () => {
        if (!confirm('Reset antibot settings to server defaults?')) return;
        setSaving(true);
        http.post(`/api/client/extensions/papyrusprotection/antibot/${uuid}/reset`)
            .then((response) => {
                setConfig(response.data.antibot_config || {});
                setMessage('Settings reset to defaults.');
            })
            .catch((e) => setMessage(e.response?.data?.error || 'Failed to reset.'))
            .finally(() => setSaving(false));
    };

    const toggleField = (field: string) => {
        setConfig({ ...config, [field]: !config[field] });
    };

    if (loading) {
        return (
            <ServerContentBlock title={'Antibot Settings'}>
                <Spinner size={'large'} centered />
            </ServerContentBlock>
        );
    }

    return (
        <ServerContentBlock title={'Antibot Settings'}>
            {message && (
                <div css={tw`bg-neutral-900 text-neutral-200 p-3 rounded mb-4 text-sm`}>
                    {message}
                </div>
            )}

            <TitledGreyBox title={'Antibot Configuration'} icon={faRobot}>
                <div css={tw`space-y-4`}>
                    <label css={tw`flex items-center gap-3 cursor-pointer`}>
                        <input
                            type={'checkbox'}
                            checked={config.captcha ?? false}
                            onChange={() => toggleField('captcha')}
                            css={tw`w-4 h-4`}
                        />
                        <div>
                            <span css={tw`text-neutral-200 font-medium`}>Captcha Verification</span>
                            <p css={tw`text-neutral-400 text-xs`}>Require players to solve a captcha during attacks</p>
                        </div>
                    </label>

                    <label css={tw`flex items-center gap-3 cursor-pointer`}>
                        <input
                            type={'checkbox'}
                            checked={config.limbo ?? false}
                            onChange={() => toggleField('limbo')}
                            css={tw`w-4 h-4`}
                        />
                        <div>
                            <span css={tw`text-neutral-200 font-medium`}>Limbo Mode</span>
                            <p css={tw`text-neutral-400 text-xs`}>Hold players in a waiting state during verification</p>
                        </div>
                    </label>

                    <div css={tw`flex gap-3 pt-3 border-t border-neutral-600`}>
                        <Button onClick={save} disabled={saving}>
                            {saving ? 'Saving...' : 'Save Changes'}
                        </Button>
                        <button
                            onClick={reset}
                            disabled={saving}
                            css={tw`px-4 py-2 rounded bg-neutral-600 hover:bg-neutral-500 text-white text-sm transition-colors`}
                        >
                            Reset to Defaults
                        </button>
                    </div>
                </div>
            </TitledGreyBox>
        </ServerContentBlock>
    );
};

export default AntibotConfig;
