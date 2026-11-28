import * as WebBrowser from 'expo-web-browser';
import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { loadOnboarding, startPlan, type Onboarding, type Plan } from '@/lib/providers';
import { Button, Callout, ChoiceRow, Screen, Text, TitleBar } from '@/ui';

const dollars = (cents: number, locale: string) => new Intl.NumberFormat(locale, { style: 'currency', currency: 'USD', minimumFractionDigits: cents % 100 === 0 ? 0 : 2 }).format(cents / 100);

/**
 * Choose your plan on the phone (B15.2, provider screen 5): Listed, Pro or
 * Partner at the category's flat monthly price, paid on reos.com.
 */
export default function ProviderPlanScreen() {
  const { t, i18n } = useTranslation();
  const [o, setO] = useState<Onboarding | null>(null);
  const [tier, setTier] = useState<Plan['tier'] | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadOnboarding().then((next) => {
        setO(next);
        setTier((next?.plans.find((p) => p.tier === next.tier) ?? next?.plans.find((p) => p.areas >= (next?.areas.length ?? 0)))?.tier ?? null);
      });
    }, []),
  );

  const names: Record<Plan['tier'], string> = { basic: t('providers.onboarding.plan.names.basic'), pro: t('providers.onboarding.plan.names.pro'), premium: t('providers.onboarding.plan.names.premium') };

  return (
    <Screen>
      <TitleBar title={t('providers.onboarding.plan.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('providers.onboarding.plan.hint')}</Text>
      {error ? <Callout tone="danger" title={error} /> : null}
      {o && o.plans.length === 0 ? <Callout tone="info" title={t('providers.onboarding.plan.none')} /> : null}
      {o?.plans.map((p) => (
        <ChoiceRow
          key={p.tier}
          title={`${names[p.tier]} · ${t('providers.onboarding.plan.monthly', { price: dollars(p.monthly, i18n.language) })}`}
          detail={`${t('providers.onboarding.plan.areas', { count: p.areas })}${o.tier === p.tier ? ` · ${t('providers.onboarding.plan.current')}` : ''}`}
          selected={tier === p.tier}
          onPress={() => setTier(p.tier)}
        />
      ))}
      {o && o.tier === null && o.plans.length > 0 ? (
        <Button
          label={t('providers.onboarding.plan.buy')}
          loading={busy}
          disabled={tier === null}
          onPress={() => {
            if (!tier) return;
            setBusy(true);
            setError(null);
            startPlan(tier)
              .then((url) => WebBrowser.openBrowserAsync(url))
              .then(() => loadOnboarding().then(setO))
              .catch((e: Error) => setError(e.message))
              .finally(() => setBusy(false));
          }}
        />
      ) : null}
    </Screen>
  );
}
