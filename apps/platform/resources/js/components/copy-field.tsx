import { Check, Copy } from 'lucide-react';
import { useEffect, useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type CopyFieldProps = {
    label: string;
    value: string;
    /** One sentence on where to paste it. */
    hint?: string;
    className?: string;
};

/**
 * A value people copy into another system (a redirect address, a webhook
 * address). Read-only, selectable, with a Copy button that says "Copied".
 */
export function CopyField({ label, value, hint, className }: CopyFieldProps) {
    const { t } = useTranslation();
    const id = useId();
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (!copied) {
            return;
        }

        const timer = window.setTimeout(() => setCopied(false), 2_500);

        return () => window.clearTimeout(timer);
    }, [copied]);

    const copy = async (): Promise<void> => {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
        } catch {
            // Clipboard blocked (for example an insecure context): select the
            // text so the person can copy it themselves.
            (document.getElementById(id) as HTMLInputElement | null)?.select();
        }
    };

    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={id}>{label}</Label>
            {hint ? (
                <p id={`${id}-hint`} className="text-sm text-muted-foreground">
                    {hint}
                </p>
            ) : null}
            <div className="flex gap-2">
                <Input
                    id={id}
                    value={value}
                    readOnly
                    aria-describedby={hint ? `${id}-hint` : undefined}
                    onFocus={(event) => event.currentTarget.select()}
                    className="font-mono"
                />
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => void copy()}
                    className="shrink-0"
                >
                    {copied ? (
                        <Check aria-hidden className="size-5" />
                    ) : (
                        <Copy aria-hidden className="size-5" />
                    )}
                    {copied
                        ? t('integrations.redirect.copied')
                        : t('integrations.redirect.copy')}
                </Button>
            </div>
            <span aria-live="polite" className="sr-only">
                {copied ? t('integrations.redirect.copied') : ''}
            </span>
        </div>
    );
}
