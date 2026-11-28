import * as DocumentPicker from 'expo-document-picker';
import { router, useFocusEffect } from 'expo-router';
import { Sparkles } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { loadBuilder, saveProfile, splitList, uploadProfilePhoto, writeBio, type ProfileBuilder } from '@/lib/providers';
import { Button, Callout, PillGroup, Screen, SectionTitle, Text, TextField, TitleBar } from '@/ui';

/**
 * Build your profile on the phone (B15.3, provider screen 7): photo and
 * logo, a bio in English and Spanish with "Write it for me", services,
 * specialities and a response-time goal. Saving checks the wording first;
 * resources and sessions are edited on reos.com.
 */
export default function ProviderProfileScreen() {
  const { t } = useTranslation();
  const [builder, setBuilder] = useState<ProfileBuilder | null>(null);
  const [bio, setBio] = useState('');
  const [bioEs, setBioEs] = useState('');
  const [services, setServices] = useState('');
  const [specialities, setSpecialities] = useState('');
  const [hours, setHours] = useState('');
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<{ tone: 'success' | 'danger' | 'warning'; text: string } | null>(null);

  const fill = (b: ProfileBuilder | null) => {
    setBuilder(b);
    setBio(b?.draft.bio ?? '');
    setBioEs(b?.draft.bio_es ?? '');
    setServices(b?.draft.services.join(', ') ?? '');
    setSpecialities(b?.draft.specialities.join(', ') ?? '');
    setHours(b?.draft.response_hours ? String(b.draft.response_hours) : '');
  };

  useFocusEffect(
    useCallback(() => {
      void loadBuilder().then(fill);
    }, []),
  );

  const run = (work: () => Promise<void>) => {
    setBusy(true);
    setMessage(null);
    work()
      .catch((e: Error) => setMessage({ tone: 'danger', text: e.message }))
      .finally(() => setBusy(false));
  };

  const pick = (kind: 'photo' | 'logo') =>
    run(async () => {
      const picked = await DocumentPicker.getDocumentAsync({ type: ['image/jpeg', 'image/png', 'image/webp'], copyToCacheDirectory: true });
      const file = picked.canceled ? undefined : picked.assets[0];
      if (file) setBuilder(await uploadProfilePhoto(kind, { uri: file.uri, name: file.name, type: file.mimeType ?? 'image/jpeg' }));
    });

  return (
    <Screen>
      <TitleBar title={t('providers.profile.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('providers.profile.purpose')}</Text>
      {message ? <Callout tone={message.tone} title={message.text} /> : null}
      {builder?.issues.length ? <Callout tone="danger" title={t('providers.profile.blocked')} body={builder.issues.map((i) => `${i.phrase ? `"${i.phrase}" · ` : ''}${i.citation}`).join('\n')} /> : null}
      <Button variant="secondary" label={`${t('providers.profile.photo')}: ${builder?.preview?.photo_url ? t('providers.profile.replace') : t('providers.profile.upload')}`} disabled={busy} onPress={() => pick('photo')} />
      <Button variant="secondary" label={`${t('providers.profile.logo')}: ${builder?.preview?.logo_url ? t('providers.profile.replace') : t('providers.profile.upload')}`} disabled={busy} onPress={() => pick('logo')} />

      <SectionTitle>{t('providers.profile.bio')}</SectionTitle>
      <Button
        variant="secondary"
        icon={Sparkles}
        label={busy ? t('providers.profile.writing') : t('providers.profile.write')}
        disabled={busy}
        onPress={() =>
          run(async () => {
            const draft = await writeBio();
            setBio(draft.bio);
            setBioEs(draft.bio_es);
            setMessage({ tone: 'success', text: t('providers.profile.written') });
          })
        }
      />
      <TextField label={t('providers.profile.bio')} hint={t('providers.profile.bioHint')} value={bio} maxLength={1200} multiline onChangeText={setBio} />
      <TextField label={t('providers.profile.bioEs')} value={bioEs} maxLength={1200} multiline onChangeText={setBioEs} />
      <TextField label={t('providers.profile.services')} hint={t('providers.profile.listHint')} value={services} onChangeText={setServices} />
      <TextField label={t('providers.profile.specialities')} hint={t('providers.profile.listHint')} value={specialities} onChangeText={setSpecialities} />
      {builder ? (
        <PillGroup
          label={t('providers.profile.response')}
          value={hours}
          options={[{ value: '', label: t('providers.profile.responseNone') }, ...builder.response_choices.map((h) => ({ value: String(h), label: t('providers.profile.responseHours', { count: h }) }))]}
          onChange={setHours}
        />
      ) : null}
      {builder?.published_at ? <Text tone="muted">{t('providers.profile.lastPublished', { date: new Date(builder.published_at).toLocaleString() })}</Text> : null}
      <Button
        label={t('providers.profile.save')}
        loading={busy}
        disabled={!builder}
        onPress={() =>
          run(async () => {
            if (!builder) return;
            const next = await saveProfile({ ...builder.draft, bio: bio.trim() || null, bio_es: bioEs.trim() || null, services: splitList(services), specialities: splitList(specialities), response_hours: hours === '' ? null : Number(hours) });
            fill(next);
            setMessage(next.issues.some((i) => i.severity === 'block') ? { tone: 'warning', text: t('providers.profile.blocked') } : { tone: 'success', text: t('providers.profile.published') });
          })
        }
      />
      {builder?.preview ? <Button variant="secondary" label={t('providers.profile.preview')} onPress={() => router.push({ pathname: '/providers/[id]', params: { id: String(builder.preview?.id ?? '') } })} /> : null}
    </Screen>
  );
}
