import {
    BookOpen,
    ExternalLink,
    Gavel,
    MessageSquareQuote,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { PolicySource } from './types';

const icons = { manual: BookOpen, faq: MessageSquareQuote, rule: Gavel };

/**
 * Where an answer came from (A12.1): the manual page, the brokerage FAQ or
 * the TREC rule with its citation and date, each with the words it said.
 */
export function SourcesList({ sources }: { sources: PolicySource[] }) {
    const { t, i18n } = useTranslation();

    if (sources.length === 0) {
        return null;
    }

    const date = (value: string | null) =>
        value
            ? new Intl.DateTimeFormat(i18n.language, {
                  dateStyle: 'medium',
              }).format(new Date(`${value}T12:00:00`))
            : null;

    return (
        <section aria-label={t('virtualBroker.sources')} className="space-y-2">
            <h3 className="text-base font-semibold">
                {t('virtualBroker.sources')}
            </h3>
            <ul className="space-y-2">
                {sources.map((s, i) => {
                    const Icon = icons[s.kind];
                    const label =
                        s.kind === 'rule'
                            ? (s.citation ?? s.title)
                            : s.kind === 'faq'
                              ? t('virtualBroker.source.faq')
                              : s.page !== null
                                ? t('virtualBroker.source.page', {
                                      page: s.page,
                                  })
                                : t('virtualBroker.source.manual');
                    const when = date(s.effective_from);

                    return (
                        <li
                            key={`${s.kind}-${i}`}
                            className="rounded-reos-md border border-border bg-muted/40 p-3"
                        >
                            <p className="flex flex-wrap items-center gap-2 text-base font-medium">
                                <Icon
                                    aria-hidden
                                    className="size-4 text-muted-foreground"
                                />
                                {label}
                                {s.kind !== 'rule' && s.title ? (
                                    <span className="text-muted-foreground">
                                        · {s.title}
                                    </span>
                                ) : null}
                                {when ? (
                                    <span className="text-sm text-muted-foreground">
                                        {s.kind === 'rule'
                                            ? t(
                                                  'virtualBroker.source.checked',
                                                  { date: when },
                                              )
                                            : t('virtualBroker.source.added', {
                                                  date: when,
                                              })}
                                    </span>
                                ) : null}
                            </p>
                            <p className="mt-1 text-base text-muted-foreground">
                                {s.excerpt}
                            </p>
                            {s.url ? (
                                <a
                                    href={s.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="mt-1 inline-flex min-h-12 items-center gap-1 text-base font-medium text-primary underline-offset-4 hover:underline"
                                >
                                    {t('virtualBroker.source.open')}
                                    <ExternalLink
                                        aria-hidden
                                        className="size-4"
                                    />
                                </a>
                            ) : null}
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
