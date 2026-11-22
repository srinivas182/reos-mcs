import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import type { StateOption } from '@/lib/content';

/**
 * Chooses the states content is for (A5.3). Live states and those being
 * prepared show first, with the rest behind "Show every state". None ticked
 * means every state. Sends `states[]` (or an empty `states`) with the form.
 */
export function StatePicker({
    states,
    value,
    onChange,
    error,
}: {
    states: StateOption[];
    value: string[];
    onChange: (codes: string[]) => void;
    error?: string;
}) {
    const { t } = useTranslation();
    const id = useId();
    const [all, setAll] = useState(false);
    const shown = states.filter(
        (s) => all || s.active || value.includes(s.code),
    );
    const hidden = states.length - shown.length;

    return (
        <fieldset className="grid gap-2" aria-describedby={`${id}-help`}>
            <legend className="mb-2 text-sm font-medium">
                {t('content.courses.fields.states')}
            </legend>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                {shown.map((state) => (
                    <label
                        key={state.code}
                        className="flex min-h-12 items-center gap-3 rounded-reos-md border border-input px-3"
                    >
                        <Checkbox
                            checked={value.includes(state.code)}
                            onCheckedChange={(on) =>
                                onChange(
                                    on
                                        ? [...value, state.code]
                                        : value.filter((c) => c !== state.code),
                                )
                            }
                        />
                        {state.name}
                    </label>
                ))}
            </div>
            {hidden > 0 || all ? (
                <Button
                    type="button"
                    variant="outline"
                    className="justify-self-start"
                    onClick={() => setAll(!all)}
                >
                    {all
                        ? t('content.courses.fewerStates')
                        : t('content.courses.allStates', { count: hidden })}
                </Button>
            ) : null}
            {value.length === 0 ? (
                <input type="hidden" name="states" value="" />
            ) : (
                value.map((code) => (
                    <input
                        key={code}
                        type="hidden"
                        name="states[]"
                        value={code}
                    />
                ))
            )}
            <p id={`${id}-help`} className="text-sm text-muted-foreground">
                {t('content.courses.statesHelp')}
            </p>
            <InputError message={error} />
        </fieldset>
    );
}
