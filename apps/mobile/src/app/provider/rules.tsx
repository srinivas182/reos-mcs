import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { acceptRules, loadOnboarding, type Onboarding } from '@/lib/providers';
import { Button, Callout, Card, Screen, SwitchRow, Text, TitleBar } from '@/ui';

const RULES = ['flat_fee', 'no_payments', 'consent', 'advertising', 'no_spam'] as const;

/**
 * Network rules on the phone (B15.2, provider screen 6): five rules,
 * accepted with the date and version, asked again when they change.
 */
export default function ProviderRulesScreen() {
  const { t, i18n } = useTranslation();
  const [o, setO] = useState<Onboarding | null>(null);
  const [agreed, setAgreed] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadOnboarding().then(setO);
    }, []),
  );

  const rules = RULES.filter((r) => o?.rules.keys.includes(r));

  return (
    <Screen>
      <TitleBar title={t('providers.onboarding.rules.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('providers.onboarding.rules.hint')}</Text>
      {error ? <Callout tone="danger" title={error} /> : null}
      <Card>
        {rules.map((r, i) => (
          <Text key={r}>{`${i + 1}. ${t(`providers.onboarding.rules.items.${r}`)}`}</Text>
        ))}
      </Card>
      {o?.rules.current && o.rules.accepted_at ? (
        <Callout tone="success" title={t('providers.onboarding.rules.accepted', { date: new Date(o.rules.accepted_at).toLocaleDateString(i18n.language) })} />
      ) : (
        <>
          <SwitchRow label={t('providers.onboarding.rules.accept')} value={agreed} onValueChange={setAgreed} />
          <Button
            label={t('providers.onboarding.rules.save')}
            loading={busy}
            disabled={!agreed || !o}
            onPress={() => {
              if (!o) return;
              setBusy(true);
              setError(null);
              acceptRules(o.rules.version)
                .then((next) => {
                  setO(next);
                  router.back();
                })
                .catch((e: Error) => setError(e.message))
                .finally(() => setBusy(false));
            }}
          />
        </>
      )}
    </Screen>
  );
}
