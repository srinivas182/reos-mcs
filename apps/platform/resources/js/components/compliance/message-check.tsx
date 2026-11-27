import { ShieldCheck, Wand2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import type { ComplianceIssue, ComplianceResult } from './types';

/** One issue in words, with the rule it comes from. */
export function IssueLine({ issue }: { issue: ComplianceIssue }) {
    const { t } = useTranslation();

    return (
        <li className="text-base">
            {t(`compliance.blocked.${issue.code}`, {
                phrase: issue.phrase ?? '',
                citation: issue.citation,
            })}
            {issue.reason ? (
                <span className="block text-sm text-muted-foreground">
                    {issue.reason}
                </span>
            ) : null}
        </li>
    );
}

/**
 * Check an email or text before it goes (B13.1): the compliance engine's
 * findings, with Fix automatically. Nothing is sent from here; the agent
 * sends from their own phone or email.
 */
export function MessageCheck({
    channel,
    text,
    onCheck,
    onFix,
    onFixed,
}: {
    channel: 'email' | 'text';
    text: string;
    onCheck: (
        channel: 'email' | 'text',
        text: string,
    ) => Promise<ComplianceResult>;
    onFix: (
        channel: 'email' | 'text',
        text: string,
    ) => Promise<ComplianceResult & { text: string }>;
    /** The fixed text, to put back in the message box. */
    onFixed: (text: string) => void;
}) {
    const { t } = useTranslation();
    const [result, setResult] = useState<ComplianceResult | null>(null);
    const [busy, setBusy] = useState(false);

    const run = async (work: () => Promise<void>) => {
        setBusy(true);
        try {
            await work();
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="space-y-2" aria-live="polite">
            <Button
                type="button"
                variant="outline"
                disabled={busy || text.trim() === ''}
                onClick={() =>
                    void run(async () => {
                        setResult(await onCheck(channel, text));
                    })
                }
            >
                <ShieldCheck aria-hidden />
                {busy ? t('compliance.checking') : t('compliance.check')}
            </Button>
            {result && result.issues.length === 0 ? (
                <p className="text-base text-reos-tone-success-fg">
                    {t('compliance.clean')}
                </p>
            ) : null}
            {result && result.issues.length > 0 ? (
                <div className="space-y-2 rounded-reos-md border border-reos-tone-warning-fg/30 bg-reos-tone-warning-bg p-3 text-reos-tone-warning-fg">
                    <p className="text-base font-semibold">
                        {t('compliance.found')}
                    </p>
                    <ul className="space-y-1">
                        {result.issues.map((issue, i) => (
                            <IssueLine
                                key={`${issue.code}-${issue.phrase ?? i}`}
                                issue={issue}
                            />
                        ))}
                    </ul>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={busy}
                        onClick={() =>
                            void run(async () => {
                                const answer = await onFix(channel, text);
                                setResult(answer);
                                onFixed(answer.text);
                            })
                        }
                    >
                        <Wand2 aria-hidden />
                        {t('compliance.fixAuto')}
                    </Button>
                </div>
            ) : null}
        </div>
    );
}
