import { CheckCircle2, Eye, EyeOff } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

export type CredentialFieldKind =
    | 'text'
    | 'url'
    | 'email'
    | 'phone'
    | 'multiline';

export type CredentialFieldProps = {
    /** Form field name, for example `values[client_secret]`. */
    name: string;
    label: string;
    kind?: CredentialFieldKind;
    /** Secrets are never sent back to the browser: the box starts empty. */
    secret?: boolean;
    required?: boolean;
    /** Value to show for plain (non-secret) fields. */
    defaultValue?: string | null;
    /** A value is already saved. */
    saved?: boolean;
    /** Last four characters of a saved secret, when it is long enough to show. */
    last4?: string | null;
    error?: string;
    className?: string;
};

const inputTypes: Record<Exclude<CredentialFieldKind, 'multiline'>, string> = {
    text: 'text',
    url: 'url',
    email: 'email',
    phone: 'tel',
};

/**
 * One key or setting for a connected service. Secrets show only "Saved, ends
 * in 1234" and stay blank; leaving them blank keeps the saved value. A
 * Show/Hide button lets people check what they pasted.
 */
export function CredentialField({
    name,
    label,
    kind = 'text',
    secret = false,
    required = false,
    defaultValue,
    saved = false,
    last4,
    error,
    className,
}: CredentialFieldProps) {
    const { t } = useTranslation();
    const id = useId();
    const statusId = `${id}-status`;
    const errorId = `${id}-error`;
    const [revealed, setRevealed] = useState(false);
    const keepSaved = secret && saved;

    const describedBy =
        [keepSaved ? statusId : null, error ? errorId : null]
            .filter(Boolean)
            .join(' ') || undefined;

    const shared = {
        id,
        name,
        'aria-invalid': error ? true : undefined,
        'aria-describedby': describedBy,
        // A saved secret is kept when the box is left blank.
        required: required && !keepSaved,
        placeholder: keepSaved ? t('integrations.keys.keepHint') : undefined,
        autoComplete: 'off',
        spellCheck: false,
        defaultValue: secret ? undefined : (defaultValue ?? undefined),
    } as const;

    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={id}>
                {label}
                {!required ? (
                    <span className="font-normal text-muted-foreground">
                        {' '}
                        ({t('integrations.keys.optional')})
                    </span>
                ) : null}
            </Label>

            {kind === 'multiline' ? (
                <Textarea {...shared} className="font-mono" />
            ) : (
                <div className="relative">
                    <Input
                        {...shared}
                        type={
                            secret && !revealed ? 'password' : inputTypes[kind]
                        }
                        className={cn(
                            secret && 'pr-14 font-mono placeholder:font-sans',
                        )}
                    />
                    {secret ? (
                        <button
                            type="button"
                            onClick={() => setRevealed((value) => !value)}
                            aria-label={t(
                                revealed
                                    ? 'integrations.keys.hide'
                                    : 'integrations.keys.show',
                            )}
                            aria-pressed={revealed}
                            className="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-reos-md text-muted-foreground hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            {revealed ? (
                                <EyeOff aria-hidden className="size-5" />
                            ) : (
                                <Eye aria-hidden className="size-5" />
                            )}
                        </button>
                    ) : null}
                </div>
            )}

            {keepSaved ? (
                <p
                    id={statusId}
                    className="flex items-center gap-1.5 text-sm text-muted-foreground"
                >
                    <CheckCircle2
                        aria-hidden
                        className="size-4 text-reos-success"
                    />
                    {last4
                        ? t('integrations.keys.savedEnding', { last4 })
                        : t('integrations.keys.savedHidden')}
                </p>
            ) : null}

            <InputError id={errorId} message={error} />
        </div>
    );
}
