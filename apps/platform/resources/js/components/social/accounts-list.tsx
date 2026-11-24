import { Link } from '@inertiajs/react';
import { Plug, RefreshCw, Share2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import type { SocialAccount } from './types';

/**
 * Social accounts (B11; agent screen 170): each social channel, connected
 * or not, the pages and profiles it can post to, and Connect or Reconnect
 * (in Connections). Channels still being built say so.
 */
export function AccountsList({
    accounts,
    connectUrl,
    onRefresh,
}: {
    accounts: SocialAccount[];
    connectUrl: string;
    onRefresh: (account: SocialAccount) => void;
}) {
    const { t } = useTranslation();
    const connected = accounts.some((a) => a.connection !== null);

    return (
        <section
            aria-labelledby="social-accounts"
            className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
        >
            <h2 id="social-accounts" className="text-lg font-semibold">
                {t('social.accounts.title')}
            </h2>
            <p className="text-base text-muted-foreground">
                {t('social.accounts.description')}
            </p>
            {!connected ? (
                <EmptyState
                    icon={Plug}
                    title={t('social.accounts.emptyTitle')}
                    description={t('social.accounts.emptyBody')}
                />
            ) : null}
            <ul className="divide-y divide-border">
                {accounts.map((account) => {
                    const reconnect =
                        account.connection?.status === 'reconnect_needed';
                    return (
                        <li
                            key={account.integration}
                            className="flex flex-wrap items-center justify-between gap-3 py-3"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="text-base font-semibold">
                                    {account.name}
                                    {account.launch === 'beta' ? (
                                        <span className="ml-2 rounded-reos-pill bg-reos-tone-info-bg px-2 py-0.5 text-sm text-reos-tone-info-fg">
                                            {t('social.accounts.beta')}
                                        </span>
                                    ) : null}
                                </p>
                                <p className="text-base text-muted-foreground">
                                    {account.connection
                                        ? [
                                              t('social.accounts.connected', {
                                                  label:
                                                      account.connection
                                                          .label ??
                                                      account.name,
                                              }),
                                              ...account.targets.map(
                                                  (x) =>
                                                      `${t(`social.channels.${x.channel}`)}: ${x.name}`,
                                              ),
                                          ].join(' · ')
                                        : account.built
                                          ? account.purpose
                                          : t('social.accounts.coming')}
                                </p>
                                {account.connection &&
                                account.targets.length === 0 ? (
                                    <p className="text-base text-muted-foreground">
                                        {t('social.accounts.none')}
                                    </p>
                                ) : null}
                            </div>
                            {account.connection && !reconnect ? (
                                <Button
                                    variant="outline"
                                    onClick={() => onRefresh(account)}
                                >
                                    <RefreshCw aria-hidden />
                                    {t('social.accounts.refresh')}
                                    <span className="sr-only">
                                        : {account.name}
                                    </span>
                                </Button>
                            ) : account.available ? (
                                <Button
                                    asChild
                                    variant={reconnect ? 'default' : 'outline'}
                                >
                                    <Link href={connectUrl}>
                                        {reconnect
                                            ? t('social.accounts.reconnect')
                                            : t('social.accounts.connect')}
                                        <span className="sr-only">
                                            : {account.name}
                                        </span>
                                    </Link>
                                </Button>
                            ) : null}
                        </li>
                    );
                })}
            </ul>
            <p className="flex items-center gap-2 text-base text-muted-foreground">
                <Share2 aria-hidden className="size-4" />
                {t('social.accounts.instagramNote')}
            </p>
        </section>
    );
}
