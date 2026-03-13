import React, { useEffect, useState } from 'react';
import http from '@/api/http';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Spinner from '@/components/elements/Spinner';
import Button from '@/components/elements/Button';
import tw from 'twin.macro';
import { faGlobe, faTrash, faPlus, faCheck, faClock } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';

interface Domain {
    id: number;
    domain: string;
    verification_status: string;
    verification_token?: string;
    instructions?: string;
}

const CustomDomains = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [domains, setDomains] = useState<Domain[]>([]);
    const [loading, setLoading] = useState(true);
    const [newDomain, setNewDomain] = useState('');
    const [adding, setAdding] = useState(false);
    const [error, setError] = useState('');

    const fetchDomains = () => {
        setLoading(true);
        http.get(`/api/client/extensions/papyrusprotection/domains/${uuid}`)
            .then((response) => setDomains(response.data.domains || []))
            .catch(() => setError('Failed to load domains.'))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        fetchDomains();
    }, [uuid]);

    const addDomain = () => {
        if (!newDomain.trim()) return;
        setAdding(true);
        setError('');
        http.post(`/api/client/extensions/papyrusprotection/domains/${uuid}`, { domain: newDomain.trim() })
            .then((response) => {
                setDomains([...domains, response.data]);
                setNewDomain('');
            })
            .catch((e) => setError(e.response?.data?.error || 'Failed to add domain.'))
            .finally(() => setAdding(false));
    };

    const verifyDomain = (domainId: number) => {
        http.post(`/api/client/extensions/papyrusprotection/domains/${uuid}/${domainId}/verify`)
            .then((response) => {
                if (response.data.verified) {
                    fetchDomains();
                } else {
                    setError(response.data.message || 'Verification failed.');
                }
            })
            .catch((e) => setError(e.response?.data?.error || 'Verification request failed.'));
    };

    const deleteDomain = (domainId: number) => {
        if (!confirm('Remove this custom domain?')) return;
        http.delete(`/api/client/extensions/papyrusprotection/domains/${uuid}/${domainId}`)
            .then(() => setDomains(domains.filter((d) => d.id !== domainId)))
            .catch((e) => setError(e.response?.data?.error || 'Failed to delete domain.'));
    };

    if (loading) {
        return (
            <ServerContentBlock title={'Custom Domains'}>
                <Spinner size={'large'} centered />
            </ServerContentBlock>
        );
    }

    return (
        <ServerContentBlock title={'Custom Domains'}>
            {error && (
                <div css={tw`bg-red-900 bg-opacity-50 text-red-200 p-3 rounded mb-4 text-sm`}>
                    {error}
                </div>
            )}

            <TitledGreyBox title={'Add Custom Domain'} icon={faGlobe} css={tw`mb-4`}>
                <p css={tw`text-neutral-400 text-sm mb-3`}>
                    Add a custom domain that players can use to connect to your server. You&apos;ll need to verify ownership via a DNS TXT record.
                </p>
                <div css={tw`flex gap-2`}>
                    <input
                        type={'text'}
                        value={newDomain}
                        onChange={(e) => setNewDomain(e.target.value)}
                        placeholder={'play.example.com'}
                        css={tw`flex-1 bg-neutral-900 border border-neutral-600 rounded px-3 py-2 text-sm text-neutral-200`}
                        onKeyDown={(e) => e.key === 'Enter' && addDomain()}
                    />
                    <Button onClick={addDomain} disabled={adding || !newDomain.trim()}>
                        <FontAwesomeIcon icon={faPlus} css={tw`mr-1`} />
                        {adding ? 'Adding...' : 'Add Domain'}
                    </Button>
                </div>
            </TitledGreyBox>

            {domains.length > 0 && (
                <TitledGreyBox title={'Your Domains'}>
                    <div css={tw`space-y-3`}>
                        {domains.map((domain) => (
                            <div key={domain.id} css={tw`flex items-center justify-between bg-neutral-900 rounded p-3`}>
                                <div>
                                    <code css={tw`text-neutral-200`}>{domain.domain}</code>
                                    <div css={tw`mt-1`}>
                                        {domain.verification_status === 'verified' ? (
                                            <span css={tw`text-green-400 text-xs`}>
                                                <FontAwesomeIcon icon={faCheck} css={tw`mr-1`} /> Verified
                                            </span>
                                        ) : (
                                            <span css={tw`text-yellow-400 text-xs`}>
                                                <FontAwesomeIcon icon={faClock} css={tw`mr-1`} /> Pending verification
                                            </span>
                                        )}
                                    </div>
                                    {domain.verification_status === 'pending' && domain.verification_token && (
                                        <div css={tw`mt-2 text-xs text-neutral-400`}>
                                            <p>Add a TXT record:</p>
                                            <code css={tw`text-neutral-300 block mt-1`}>
                                                _papyrus-verify.{domain.domain} → {domain.verification_token}
                                            </code>
                                        </div>
                                    )}
                                </div>
                                <div css={tw`flex gap-2`}>
                                    {domain.verification_status === 'pending' && (
                                        <button
                                            onClick={() => verifyDomain(domain.id)}
                                            css={tw`px-3 py-1 rounded bg-blue-600 hover:bg-blue-500 text-white text-xs transition-colors`}
                                        >
                                            Verify
                                        </button>
                                    )}
                                    <button
                                        onClick={() => deleteDomain(domain.id)}
                                        css={tw`px-3 py-1 rounded bg-red-600 hover:bg-red-500 text-white text-xs transition-colors`}
                                    >
                                        <FontAwesomeIcon icon={faTrash} />
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </TitledGreyBox>
            )}
        </ServerContentBlock>
    );
};

export default CustomDomains;
