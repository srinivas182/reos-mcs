import { useTranslation } from 'react-i18next';

import { FLAGS, type Flag } from '@/lib/agent';
import { SwitchRow } from '@/ui';

/** Work-style flags (screen 6): optional, they tune any mode. */
export function FlagList({ value, onChange }: { value: Flag[]; onChange: (flags: Flag[]) => void }) {
  const { t } = useTranslation();

  return (
    <>
      {FLAGS.map((flag) => (
        <SwitchRow
          key={flag}
          label={t(`agents.modes.flags.${flag}`)}
          description={t(`agents.modes.flagsHints.${flag}`)}
          value={value.includes(flag)}
          onValueChange={(on) => onChange(on ? [...value, flag] : value.filter((f) => f !== flag))}
        />
      ))}
    </>
  );
}
