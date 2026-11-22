import { useTranslation } from 'react-i18next';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { RuleRow } from '@/lib/state-rules';
import { ruleDate, ruleText } from '@/lib/state-rules';

/** Version history (screen 62): every published version of every rule, newest first, with who published it. */
export function RuleVersionsDialog({
    open,
    onOpenChange,
    rules,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    rules: RuleRow[];
}) {
    const { t, i18n } = useTranslation();
    const versioned = rules.filter((rule) => rule.versions.length > 0);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{t('stateRules.history')}</DialogTitle>
                    <DialogDescription>
                        {t('stateRules.historyIntro')}
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-5">
                    {versioned.map((rule) => (
                        <section key={rule.key} className="space-y-2">
                            <h3 className="font-semibold">
                                {t(
                                    `stateRules.keys.${rule.key}` as 'stateRules.keys.ce.hours',
                                )}
                            </h3>
                            <ol className="space-y-2">
                                {rule.versions.map((version) => (
                                    <li
                                        key={version.id}
                                        className="rounded-reos-md border border-border p-3 text-body"
                                    >
                                        <p className="font-medium">
                                            {t('stateRules.versionLine', {
                                                version: version.version,
                                                date: ruleDate(
                                                    version.effective_from,
                                                    i18n.language,
                                                ),
                                            })}
                                        </p>
                                        <p>
                                            {ruleText(
                                                t,
                                                rule.type,
                                                rule.unit,
                                                version.value,
                                            )}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {version.citation}
                                            {version.published_by
                                                ? ` · ${t('stateRules.publishedBy', { name: version.published_by })}`
                                                : ''}
                                            {version.note
                                                ? ` · ${version.note}`
                                                : ''}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </section>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}
