import { useEffect } from 'react';
import http from '@/api/http';
import { ServerContext } from '@/state/server';

const SftpGuard = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);

    useEffect(() => {
        if (!uuid) return;

        let styleEl: HTMLStyleElement | null = null;
        let observer: MutationObserver | null = null;

        http.get(`/api/client/extensions/papyrusprotection/protection/${uuid}`)
            .then((res) => {
                const data = res.data;
                if (!data.protected) return;

                const mode = data.sftp_display_mode;
                if (!mode || mode === 'show') return;

                if (mode === 'hide') {
                    styleEl = document.createElement('style');
                    styleEl.setAttribute('data-papyrus-sftp', 'true');
                    styleEl.textContent = `
                        [class*="SftpContainer"],
                        [class*="sftp-container"],
                        div[class*="ServerError"]:has(> div > label[for*="sftp"]) {
                            display: none !important;
                        }
                    `;
                    document.head.appendChild(styleEl);

                    // Also use DOM scanning as fallback
                    const hideSftp = () => {
                        document.querySelectorAll('input, code, label, p, span, div').forEach((el) => {
                            const text = el.textContent || '';
                            if (text.includes('sftp://') || text.match(/SFTP\s*(Details|Server|Address|Connection)/i)) {
                                const container = el.closest('[class*="grey-box"], [class*="TitledGreyBox"], [class*="inner"], [class*="row"], [class*="InputSpinner"]')
                                    || el.closest('div[class]');
                                if (container && container instanceof HTMLElement) {
                                    container.style.display = 'none';
                                }
                            }
                        });

                        // Hide any input with sftp:// as value
                        document.querySelectorAll('input[readonly]').forEach((el) => {
                            if (el instanceof HTMLInputElement && el.value.includes('sftp://')) {
                                const container = el.closest('[class*="grey-box"], [class*="TitledGreyBox"], [class*="row"]')
                                    || el.parentElement?.parentElement;
                                if (container && container instanceof HTMLElement) {
                                    container.style.display = 'none';
                                }
                            }
                        });
                    };

                    hideSftp();
                    observer = new MutationObserver(() => hideSftp());
                    observer.observe(document.body, { childList: true, subtree: true });
                }

                if (mode === 'rename' && data.sftp_custom_host) {
                    const customHost = data.sftp_custom_host;

                    const renameSftp = () => {
                        document.querySelectorAll('input[readonly], code, span').forEach((el) => {
                            if (el instanceof HTMLInputElement && el.value.includes('sftp://')) {
                                el.value = el.value.replace(/sftp:\/\/[^:\/]+/, 'sftp://' + customHost);
                            } else if (el.textContent && el.textContent.includes('sftp://')) {
                                el.textContent = el.textContent.replace(/sftp:\/\/[^:\/\s]+/, 'sftp://' + customHost);
                            }
                        });
                    };

                    renameSftp();
                    observer = new MutationObserver(() => renameSftp());
                    observer.observe(document.body, { childList: true, subtree: true });
                }
            })
            .catch(() => {});

        return () => {
            if (styleEl) styleEl.remove();
            if (observer) observer.disconnect();
        };
    }, [uuid]);

    return null;
};

export default SftpGuard;
