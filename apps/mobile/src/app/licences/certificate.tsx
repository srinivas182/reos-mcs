import * as DocumentPicker from 'expo-document-picker';
import { router } from 'expo-router';
import { FileUp } from 'lucide-react-native';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { readCertificate, saveCertificate, type CertificateRead } from '@/lib/certificates';
import { Button, Callout, PillGroup, Screen, Text, TextField, TitleBar, useUndoToast } from '@/ui';

/**
 * Add a CE certificate on the phone (B10.3b): pick a photo or PDF, REOS
 * reads it, the person checks the details, then saves it as a completion
 * for one of their licence states.
 */
export default function CertificateScreen() {
  const { t } = useTranslation();
  const toast = useUndoToast();
  const [found, setFound] = useState<CertificateRead | null>(null);
  const [values, setValues] = useState({ state: '', title: '', hours: '', completed_on: '', provider: '' });
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);

  const pick = async () => {
    const picked = await DocumentPicker.getDocumentAsync({ type: ['image/*', 'application/pdf'], copyToCacheDirectory: true });
    const file = picked.canceled ? undefined : picked.assets[0];

    if (!file) return;

    setBusy(true);
    try {
      const read = await readCertificate({ uri: file.uri, name: file.name, type: file.mimeType ?? 'application/pdf' });
      setFound(read);
      setValues({
        state: read.states[0] ?? '',
        title: read.course ?? '',
        hours: read.hours === null ? '' : String(read.hours),
        completed_on: read.completed_on ?? '',
        provider: read.provider ?? '',
      });
      setProblem(null);
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };

  const save = async () => {
    if (!found) return;
    setBusy(true);
    try {
      await saveCertificate(found.upload, { ...values, hours: Number(values.hours), provider: values.provider === '' ? null : values.provider });
      toast({ message: t('certificates.saved') });
      router.back();
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };

  const set = (key: keyof typeof values) => (text: string) => setValues((v) => ({ ...v, [key]: text }));

  return (
    <Screen>
      <TitleBar title={t('certificates.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('certificates.description')}</Text>
      {problem ? <Callout tone="danger" title={problem} live /> : null}
      {found === null ? (
        <Button icon={FileUp} label={busy ? t('certificates.reading') : t('certificates.read')} loading={busy} onPress={() => void pick()} />
      ) : (
        <>
          <Text weight="semibold">{t('certificates.review')}</Text>
          <Text tone="muted">{found.reader === null ? t('certificates.unread') : t('certificates.reviewHint')}</Text>
          {found.name ? <Text>{t('certificates.nameOnCertificate', { name: found.name })}</Text> : null}
          {found.name_matches === false ? <Callout tone="warning" title={t('certificates.nameMismatch')} live /> : null}
          {found.states.length > 1 ? (
            <PillGroup label={t('certificates.state')} value={values.state} options={found.states.map((s) => ({ value: s, label: s }))} onChange={set('state')} />
          ) : null}
          <TextField label={t('certificates.course')} value={values.title} onChangeText={set('title')} />
          <TextField label={t('certificates.hours')} value={values.hours} onChangeText={set('hours')} keyboardType="decimal-pad" />
          <TextField label={t('certificates.completedOn')} hint="YYYY-MM-DD" value={values.completed_on} onChangeText={set('completed_on')} />
          <TextField label={t('certificates.provider')} value={values.provider} onChangeText={set('provider')} />
          <Button
            label={t('certificates.save')}
            loading={busy}
            disabled={busy || values.title.trim() === '' || values.hours === '' || values.completed_on === ''}
            onPress={() => void save()}
          />
        </>
      )}
    </Screen>
  );
}
