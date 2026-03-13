import React, { useEffect, useState } from 'react';
import http from '@/api/http';
import tw from 'twin.macro';

interface Props {
    serverUuid?: string;
    uuid?: string;
}

const ProtectionBadge = ({ serverUuid, uuid }: Props) => {
    const [isProtected, setIsProtected] = useState(false);
    const serverIdentifier = serverUuid || uuid;

    useEffect(() => {
        if (!serverIdentifier) return;

        http.get(`/api/client/extensions/papyrusprotection/protection/${serverIdentifier}`)
            .then((response) => setIsProtected(response.data.protected === true))
            .catch(() => setIsProtected(false));
    }, [serverIdentifier]);

    if (!isProtected) return null;

    return (
        <span
            title={'DDoS Protected'}
            css={tw`inline-flex items-center ml-2 text-green-400`}
            style={{ fontSize: '14px' }}
        >
            🛡️
        </span>
    );
};

export default ProtectionBadge;
