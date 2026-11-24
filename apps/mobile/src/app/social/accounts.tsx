import { router } from 'expo-router';
import { Plug, RefreshCw, Share2 } from 'lucide-react-native';
import { useState } from 'react';
import { Share } from 'react-native';
import { useTranslation } from 'react-i18next';

import { connectTool } from '@/lib/connections';
import { type Channel, refreshTargets, useSocialAccounts } from '@/lib/social';
import { Button, Callout, Chip, ListRow, Screen, Text, TitleBar } from '@/ui';

/**
 * Social accounts (B11; agent screen 170, Me → Connections → Social or the
 * share screen): Instagram, Facebook Page, LinkedIn, TikTok, YouTube Shorts
 * and Google Business Profile, each with Connect (the platform's sign-in),
 * the places it posts to, and the share sheet for anything else.
 */
export default function SocialAccountsScreen() {
  const { t } = useTranslation();
  const { accounts, setAccounts, reload } = useSocialAccounts();
  const [problem, setProblem] = useState<string | null>(null);

  if (accounts === null) return <Screen>{null}</Screen>;

  const connect = async (integration: string) => {
    const done = await connectTool(integration);
    setProblem(done.outcome === 'failed' ? (done.reason ?? t('social.errors.reconnect')) : null);
    await reload();
  };

  return (
    <Screen>
      <TitleBar title={t('social.accounts.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('social.accounts.description')}</Text>
      {problem ? <Callout tone="danger" title={problem} live /> : null}
      {accounts.map((account) => {
        const reconnect = account.connection?.status === 'reconnect_needed';
        return (
          <ListRow
            key={account.integration}
            icon={Plug}
            tone={account.connection ? (reconnect ? 'danger' : 'success') : 'info'}
            title={account.name}
            subtitle={
              account.connection
                ? [t('social.accounts.connected', { label: account.connection.label ?? account.name }), ...account.targets.map((x) => `${t(`social.channels.${x.channel as Channel}`)}: ${x.name}`)].join(' · ')
                : account.built
                  ? account.purpose
                  : t('social.accounts.coming')
            }
            trailing={
              account.launch === 'beta' ? (
                <Chip label={t('social.accounts.beta')} tone="info" />
              ) : account.connection && !reconnect ? (
                <Button
                  variant="secondary"
                  icon={RefreshCw}
                  label={t('social.accounts.refresh')}
                  onPress={() =>
                    void refreshTargets(account.connection?.id ?? 0).then((targets) => setAccounts((all) => (all ?? []).map((a) => (a.integration === account.integration ? { ...a, targets } : a))))
                  }
                />
              ) : account.available ? (
                <Button label={reconnect ? t('social.accounts.reconnect') : t('social.accounts.connect')} onPress={() => void connect(account.integration)} />
              ) : undefined
            }
          />
        );
      })}
      <Text tone="muted">{t('social.accounts.instagramNote')}</Text>
      <Button variant="secondary" icon={Share2} label={t('social.accounts.shareSheet')} onPress={() => void Share.share({ message: 'https://reos.com' })} fullWidth />
    </Screen>
  );
}
