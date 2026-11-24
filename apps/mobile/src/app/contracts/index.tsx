import * as DocumentPicker from 'expo-document-picker';
import { router, useFocusEffect } from 'expo-router';
import { FileSearch, FileText, FileUp, Images, Sparkles } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { loadContracts, startReview, type ContractsOverview } from '@/lib/contracts';
import { Button, Callout, EmptyState, Icon, ListRow, Screen, SectionTitle, Text, TitleBar } from '@/ui';

/**
 * Review a contract: choose (agent screen 63): upload a PDF, photos of the
 * pages, a document from one of my deals, or the sample TREC 20-18. The
 * cost in credits shows before anything is spent; earlier reviews below.
 */
export default function ContractsScreen() {
  const { t } = useTranslation();
  const [overview, setOverview] = useState<ContractsOverview | null>(null);
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadContracts().then(setOverview);
    }, []),
  );

  const start = async (source: Parameters<typeof startReview>[0]) => {
    setBusy(true);
    setProblem(null);
    try {
      const review = await startReview(source);
      router.push({ pathname: '/contracts/[id]', params: { id: review.id } });
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };
  const pick = async (type: string[]) => {
    const picked = await DocumentPicker.getDocumentAsync({ type, copyToCacheDirectory: true });
    const file = picked.canceled ? undefined : picked.assets[0];

    if (file) {
      await start({ file: { uri: file.uri, name: file.name, type: file.mimeType ?? 'application/pdf' } });
    }
  };

  return (
    <Screen>
      <TitleBar title={t('contracts.web.choose')} onBack={() => router.back()} />
      <Text tone="muted">{t('contracts.web.purpose')}</Text>
      {overview ? <Text variant="caption" tone="muted">{t('contracts.web.cost', { credits: overview.cost, left: overview.credits_left })}</Text> : null}
      {problem ? <Callout tone="danger" title={problem} live /> : null}
      <Button icon={FileUp} label={t('contracts.web.upload')} fullWidth loading={busy} onPress={() => void pick(['application/pdf'])} />
      <Button variant="secondary" icon={Images} label={t('contracts.web.photos')} fullWidth disabled={busy} onPress={() => void pick(['image/jpeg', 'image/png'])} />
      {overview?.sample ? <Button variant="secondary" icon={Sparkles} label={t('contracts.web.sample')} fullWidth disabled={busy} onPress={() => void start({ sample: true })} /> : null}

      {overview && overview.files.length > 0 ? <SectionTitle>{t('contracts.web.fromDeals')}</SectionTitle> : null}
      {overview?.files.map((f) => <ListRow key={f.uuid} icon={FileText} title={f.name} subtitle={f.deal} onPress={busy ? undefined : () => void start({ dealFile: f.uuid })} />)}

      <SectionTitle>{t('contracts.web.reviews')}</SectionTitle>
      {overview && overview.reviews.length === 0 ? (
        <EmptyState icon={<Icon icon={FileSearch} color="action" size={32} />} title={t('contracts.web.empty')} description={t('contracts.web.emptyBody')} />
      ) : (
        overview?.reviews.map((r) => (
          <ListRow
            key={r.id}
            icon={FileSearch}
            tone={r.status === 'failed' ? 'danger' : r.findings.some((f) => f.severity === 'high') ? 'warning' : 'success'}
            title={r.form_name ?? r.file_name}
            subtitle={[t(`contracts.web.status.${r.status}`), r.deal?.property].filter(Boolean).join(' · ')}
            onPress={() => router.push({ pathname: '/contracts/[id]', params: { id: r.id } })}
          />
        ))
      )}
    </Screen>
  );
}
