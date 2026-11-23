import { router } from 'expo-router';
import { Database } from 'lucide-react-native';
import { useTranslation } from 'react-i18next';

import { useCrms } from '@/lib/crm';
import { EmptyState, Icon, OptionGroup, Screen, Text, TitleBar, useUndoToast } from '@/ui';

const NONE = 'none';

/**
 * Using two CRMs (Connections and Sync journey, screen 24, B8.3b): pick
 * where new contacts go. The same person in both is merged into one, and
 * one simple rule settles conflicts: the newest change wins.
 */
export default function CrmsScreen() {
  const { t } = useTranslation();
  const toast = useUndoToast();
  const { crms, choose } = useCrms();
  const takers = (crms ?? []).filter((crm) => crm.takes_new_people);
  const others = (crms ?? []).filter((crm) => !crm.takes_new_people);
  const current = takers.find((crm) => crm.is_default);

  const pick = async (value: string) => {
    const previous = current?.connection ?? null;
    if (await choose(value === NONE ? null : Number(value))) {
      toast({ message: t('crm.crms.saved'), onUndo: () => void choose(previous) });
    }
  };

  return (
    <Screen>
      <TitleBar title={t('crm.crms.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('crm.crms.description')}</Text>
      {crms !== null && crms.length === 0 ? (
        <EmptyState icon={<Icon icon={Database} color="action" size={28} />} title={t('crm.crms.none')} description={t('crm.crms.noneBody')} />
      ) : null}
      {takers.length > 0 ? (
        <OptionGroup
          label={t('crm.add.goesTo')}
          value={current ? String(current.connection) : NONE}
          onChange={(value) => void pick(value)}
          options={[
            ...takers.map((crm) => ({ value: String(crm.connection), label: crm.name, description: crm.account ?? undefined })),
            { value: NONE, label: t('crm.crms.stayInReos') },
          ]}
        />
      ) : null}
      {others.map((crm) => (
        <Text key={crm.connection} tone="muted">{`${crm.name}: ${t('crm.crms.cantTake')}`}</Text>
      ))}
      <Text tone="muted">{t('crm.crms.rule')}</Text>
    </Screen>
  );
}
