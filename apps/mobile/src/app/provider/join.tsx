import { router, useFocusEffect } from 'expo-router';
import { CheckCircle2, Circle } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { loadOnboarding, nextStep, STEP_SCREENS, welcomeDone, type Onboarding } from '@/lib/providers';
import { Button, Callout, Card, ListRow, Screen, SectionTitle, Text, TitleBar } from '@/ui';

const STATUSES = ['joining', 'waiting', 'live', 'hidden'] as const;
const HIDDEN = ['licence_expired', 'licence_inactive', 'licence_rejected', 'licence_review', 'licence_missing'] as const;
const pick = <T extends string>(list: readonly T[], v: string | null): T | null => list.find((x) => x === v) ?? null;

/**
 * Joining the provider network on the phone (B15.2, provider screen 1 and
 * the steps): the welcome, then each step with whether it's done, and the
 * profile's status at the top.
 */
export default function ProviderJoinScreen() {
  const { t } = useTranslation();
  const [o, setO] = useState<Onboarding | null>(null);
  const [busy, setBusy] = useState(false);

  useFocusEffect(
    useCallback(() => {
      void loadOnboarding().then(setO);
    }, []),
  );

  const status = pick(STATUSES, o?.status ?? null) ?? 'joining';
  const hidden = pick(HIDDEN, o?.hidden_reason ?? null);
  const welcomed = o?.steps.find((s) => s.key === 'welcome')?.done ?? false;

  return (
    <Screen>
      <TitleBar title={t('providers.onboarding.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('providers.onboarding.purpose')}</Text>
      {o ? (
        <>
          <Callout tone={status === 'live' ? 'success' : status === 'hidden' ? 'danger' : 'info'} title={t(`providers.onboarding.status.${status}`, { reason: hidden ? t(`providers.onboarding.hidden.${hidden}`) : '' })} />
          {welcomed ? null : (
            <Card>
              <Text variant="title" heading>
                {t('providers.onboarding.welcome.title')}
              </Text>
              <Text>{t('providers.onboarding.welcome.body')}</Text>
              {(['verified', 'areas', 'flat'] as const).map((p) => (
                <Text key={p}>{`• ${t(`providers.onboarding.welcome.points.${p}`)}`}</Text>
              ))}
              <Button
                label={t('providers.onboarding.welcome.start')}
                loading={busy}
                onPress={() => {
                  setBusy(true);
                  welcomeDone()
                    .then((next) => {
                      setO(next);
                      const step = nextStep(next);
                      if (step && step !== 'welcome') router.push(STEP_SCREENS[step] as never);
                    })
                    .finally(() => setBusy(false));
                }}
              />
            </Card>
          )}
          <SectionTitle>{t('providers.onboarding.progress', { done: o.done, total: o.steps.length })}</SectionTitle>
          {o.steps
            .filter((s) => s.key !== 'welcome')
            .map((s) => (
              <ListRow key={s.key} icon={s.done ? CheckCircle2 : Circle} tone={s.done ? 'success' : 'info'} title={t(`providers.onboarding.steps.${s.key}`)} onPress={() => router.push(STEP_SCREENS[s.key] as never)} />
            ))}
        </>
      ) : null}
    </Screen>
  );
}
