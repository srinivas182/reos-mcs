import { useTranslation } from 'react-i18next';
import { Switch } from '@/components/ui/switch';
import { FLAGS, type Flag } from './types';

/** Work-style flags (agent screen 6): they tune any mode; all optional. */
export function FlagsPicker({
    value,
    onChange,
}: {
    value: Flag[];
    onChange: (flags: Flag[]) => void;
}) {
    const { t } = useTranslation();

    return (
        <ul className="divide-y divide-border rounded-reos-lg border border-border bg-card">
            {FLAGS.map((flag) => (
                <li
                    key={flag}
                    className="flex items-center justify-between gap-4 p-4"
                >
                    <label htmlFor={`flag-${flag}`} className="space-y-0.5">
                        <span className="block text-base font-semibold">
                            {t(`agents.modes.flags.${flag}`)}
                        </span>
                        <span className="block text-sm text-muted-foreground">
                            {t(`agents.modes.flagsHints.${flag}`)}
                        </span>
                    </label>
                    <Switch
                        id={`flag-${flag}`}
                        checked={value.includes(flag)}
                        onCheckedChange={(on) =>
                            onChange(
                                on
                                    ? [...value, flag]
                                    : value.filter((f) => f !== flag),
                            )
                        }
                    />
                </li>
            ))}
        </ul>
    );
}
