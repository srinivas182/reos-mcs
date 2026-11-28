import { router, useFocusEffect } from 'expo-router';
import { Inbox } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { chooseCategory, loadCategories, loadJoining, lowestPrice, requestCategory, saveAnswers, type Answer, type JoinField, type Joining, type ProviderCategory } from '@/lib/providers';
import { Button, Callout, Card, ChoiceRow, EmptyState, Icon, PillGroup, Screen, SectionTitle, SwitchRow, Text, TextField, TitleBar } from '@/ui';

const dollars = (cents: number, locale: string) => new Intl.NumberFormat(locale, { style: 'currency', currency: 'USD', minimumFractionDigits: cents % 100 === 0 ? 0 : 2 }).format(cents / 100);

/**
 * What do you do? on the phone (B15.1, provider screen 2): the categories
 * from the super admin's settings, each with its licence step and price,
 * the chosen category's profile fields, and asking for a new category.
 */
export default function ProviderJoinScreen() {
  const { t, i18n } = useTranslation();
  const [categories, setCategories] = useState<ProviderCategory[] | null>(null);
  const [joining, setJoining] = useState<Joining | null>(null);
  const [answers, setAnswers] = useState<Record<string, Answer>>({});
  const [ask, setAsk] = useState({ name: '', note: '' });
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<{ tone: 'success' | 'danger'; text: string } | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadCategories().then(setCategories);
      void loadJoining().then((j) => {
        setJoining(j);
        setAnswers(j?.fields ?? {});
      });
    }, []),
  );

  const run = (work: () => Promise<void>) => {
    setBusy(true);
    setMessage(null);
    work()
      .catch((e: Error) => setMessage({ tone: 'danger', text: e.message }))
      .finally(() => setBusy(false));
  };

  const field = (f: JoinField) => {
    const value = answers[f.key];
    const set = (v: Answer) => setAnswers({ ...answers, [f.key]: v });

    if (f.type === 'yes_no') {
      return <SwitchRow key={f.key} label={f.label} value={value === true} onValueChange={set} />;
    }

    if (f.type === 'list' && f.options.length > 0) {
      const chosen = Array.isArray(value) ? value : [];

      return (
        <Card key={f.key}>
          <Text weight="medium">{f.label}</Text>
          {f.options.map((o) => (
            <ChoiceRow key={o} title={o} selected={chosen.includes(o)} onPress={() => set(chosen.includes(o) ? chosen.filter((c) => c !== o) : [...chosen, o])} />
          ))}
        </Card>
      );
    }

    if (f.type === 'list') {
      return <TextField key={f.key} label={f.label} hint={t('providers.join.listHint')} value={Array.isArray(value) ? value.join(', ') : ''} onChangeText={(v) => set(v.split(','))} />;
    }

    return <TextField key={f.key} label={f.label} value={typeof value === 'string' ? value : ''} maxLength={500} onChangeText={set} />;
  };

  const category = joining?.category ?? null;

  return (
    <Screen>
      <TitleBar title={t('providers.onboarding.steps.category')} onBack={() => router.back()} />
      <Text tone="muted">{t('providers.join.purpose')}</Text>
      {message ? <Callout tone={message.tone} title={message.text} /> : null}

      {categories !== null && categories.length === 0 ? <EmptyState icon={<Icon icon={Inbox} color="action" size={32} />} title={t('providers.join.emptyTitle')} description={t('providers.join.empty')} /> : null}
      {categories && categories.length > 0 ? (
        <PillGroup
          label={t('providers.join.categoryTitle')}
          value={category?.key ?? ''}
          options={categories.map((c) => ({ value: c.key, label: c.name }))}
          onChange={(key) =>
            run(async () => {
              const j = await chooseCategory(key);
              setJoining(j);
              setAnswers(j.fields);
            })
          }
        />
      ) : null}

      {category ? (
        <>
          <Card>
            <Text weight="medium">{category.name}</Text>
            <Text tone="muted">{t(`providers.join.licence.${category.licence.kind}`)}</Text>
            <Text>{lowestPrice(category) === null ? t('providers.join.noPrice') : t('providers.join.from', { price: dollars(lowestPrice(category) ?? 0, i18n.language) })}</Text>
          </Card>
          <SectionTitle>{t('providers.join.fieldsTitle', { name: category.name })}</SectionTitle>
          <Text tone="muted">{(joining?.missing.length ?? 0) === 0 ? t('providers.join.complete') : t('providers.join.missing', { count: joining?.missing.length ?? 0 })}</Text>
          {category.fields.map(field)}
          <Button
            label={t('providers.join.save')}
            loading={busy}
            onPress={() =>
              run(async () => {
                const j = await saveAnswers(answers);
                setJoining(j);
                setMessage({ tone: 'success', text: t('providers.join.saved') });
              })
            }
          />
        </>
      ) : null}

      <SectionTitle>{t('providers.join.request.title')}</SectionTitle>
      <Text tone="muted">{t('providers.join.request.hint')}</Text>
      <TextField label={t('providers.join.request.name')} value={ask.name} maxLength={120} onChangeText={(name) => setAsk({ ...ask, name })} />
      <TextField label={t('providers.join.request.note')} value={ask.note} maxLength={300} multiline onChangeText={(note) => setAsk({ ...ask, note })} />
      <Button
        variant="secondary"
        label={t('providers.join.request.send')}
        disabled={busy || ask.name.trim().length < 3}
        onPress={() =>
          run(async () => {
            const sent = await requestCategory(ask.name, ask.note);
            setAsk({ name: '', note: '' });
            setMessage({ tone: 'success', text: t('providers.join.request.sent', { name: sent.name }) });
          })
        }
      />
    </Screen>
  );
}
