import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { joinCommunity, loadInvite, type CommunityInvite } from '@/lib/communities';
import { Button, Callout, Screen, Text, TitleBar } from '@/ui';

/** The invite to a community on the phone (B17.2), opened from its link or QR code. */
export default function JoinCommunityScreen() {
  const { t } = useTranslation();
  const { code, via } = useLocalSearchParams<{ code: string; via?: string }>();
  const [invite, setInvite] = useState<CommunityInvite | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void loadInvite(code).then(setInvite);
  }, [code]);

  return (
    <Screen>
      <TitleBar title={invite ? t('communities.join.title', { name: invite.name }) : t('communities.mineTitle')} onBack={() => router.back()} />
      {invite ? <Text tone="muted">{t('communities.join.purpose', { owner: invite.owner })}</Text> : null}
      {invite?.about ? <Text>{invite.about}</Text> : null}
      {error ? <Callout tone="danger" title={error} /> : null}
      {invite && invite.member ? <Button label={t('communities.join.open')} onPress={() => router.replace({ pathname: '/communities/[uuid]', params: { uuid: invite.id } })} /> : null}
      {invite && !invite.member && !invite.verified ? <Text>{t('communities.join.notVerified')}</Text> : null}
      {invite && !invite.member && invite.verified ? (
        <Button
          label={t('communities.join.join')}
          onPress={() =>
            void joinCommunity(code, via === 'qr' ? 'qr' : 'link')
              .then((id) => router.replace({ pathname: '/communities/[uuid]', params: { uuid: id } }))
              .catch((e: Error) => setError(e.message))
          }
        />
      ) : null}
    </Screen>
  );
}
