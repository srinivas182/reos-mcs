import { ScrollText } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { privacy, terms } from '@/routes/legal';

export type ConsentPurpose =
    | 'terms'
    | 'privacy_notice'
    | 'marketing_email'
    | 'sms'
    | 'session_recording'
    | 'contact_sharing'
    | 'ai_personal_data'
    | 'provider_network'
    | 'recruiting';

export type SwitchPurpose = Extract<
    ConsentPurpose,
    | 'marketing_email'
    | 'sms'
    | 'ai_personal_data'
    | 'provider_network'
    | 'recruiting'
>;

export type ConsentSwitch = {
    purpose: SwitchPurpose;
    on: boolean;
    chosen: boolean;
    at: string | null;
};

export type ConsentRecordRow = {
    id: number;
    purpose: ConsentPurpose;
    subject: string | null;
    granted: boolean;
    version: string;
    method: 'web' | 'app' | 'admin' | 'sms';
    by_admin: boolean;
    at: string;
    current: boolean;
    withdrawable: boolean;
};

const when = (iso: string, language: string) =>
    new Date(iso).toLocaleString(language, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

/**
 * The privacy switches (A6.3): provider network, recruiting, marketing
 * email, texts and AI help with your own data, each with what it means and
 * whether it's your choice or the default.
 */
export function PrivacySwitches({
    switches,
    onChange,
    disabled = false,
    person,
}: {
    switches: ConsentSwitch[];
    onChange: (purpose: SwitchPurpose, on: boolean) => void;
    disabled?: boolean;
    /** Set when an admin changes them on the person's behalf. */
    person?: string;
}) {
    const { t, i18n } = useTranslation();

    return (
        <ul className="divide-y divide-border rounded-reos-lg border border-border bg-card">
            {switches.map((item) => {
                const id = `consent-${item.purpose}`;

                return (
                    <li
                        key={item.purpose}
                        className="flex items-start gap-4 p-4"
                    >
                        <div className="min-w-0 flex-1 space-y-1">
                            <p id={`${id}-label`} className="font-medium">
                                {t(`consents.purposes.${item.purpose}.title`)}
                            </p>
                            <p
                                id={`${id}-hint`}
                                className="text-body text-muted-foreground"
                            >
                                {t(`consents.purposes.${item.purpose}.body`)}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {item.chosen && item.at
                                    ? t('consents.chosenOn', {
                                          date: when(item.at, i18n.language),
                                      })
                                    : t('consents.byDefault')}
                            </p>
                        </div>
                        <Switch
                            checked={item.on}
                            disabled={disabled}
                            onCheckedChange={(on) => onChange(item.purpose, on)}
                            aria-labelledby={person ? undefined : `${id}-label`}
                            aria-describedby={`${id}-hint`}
                            aria-label={
                                person
                                    ? t('consents.switchFor', {
                                          purpose: t(
                                              `consents.purposes.${item.purpose}.title`,
                                          ),
                                          person,
                                      })
                                    : undefined
                            }
                        />
                    </li>
                );
            })}
        </ul>
    );
}

/**
 * "Your consents" (A6.3): every consent given or withdrawn, newest first,
 * with the version of the words, when and how; Withdraw on the ones in
 * force that can be withdrawn (the terms and privacy notice go only with
 * the account).
 */
export function ConsentHistory({
    records,
    onWithdraw,
    disabled = false,
}: {
    records: ConsentRecordRow[];
    onWithdraw?: (record: ConsentRecordRow) => void;
    disabled?: boolean;
}) {
    const { t, i18n } = useTranslation();

    if (records.length === 0) {
        return (
            <EmptyState
                icon={ScrollText}
                title={t('consents.history.empty')}
                description={t('consents.history.emptyBody')}
            />
        );
    }

    return (
        <ul className="divide-y divide-border rounded-reos-lg border border-border bg-card">
            {records.map((record) => {
                const title = t(`consents.purposes.${record.purpose}.title`);

                return (
                    <li
                        key={record.id}
                        className="flex flex-wrap items-center gap-x-4 gap-y-2 p-4"
                    >
                        <div className="min-w-0 flex-1 space-y-1">
                            <p className="font-medium">
                                {title}
                                {record.subject ? (
                                    <span className="text-muted-foreground">
                                        {' · '}
                                        {record.subject}
                                    </span>
                                ) : null}
                            </p>
                            <p className="text-body text-muted-foreground">
                                {[
                                    record.granted
                                        ? t('consents.history.agreed')
                                        : t('consents.history.withdrawn'),
                                    when(record.at, i18n.language),
                                    t(`consents.methods.${record.method}`),
                                    t('consents.history.version', {
                                        version: record.version,
                                    }),
                                ].join(' · ')}
                            </p>
                        </div>
                        {record.current ? (
                            <span className="rounded-full bg-reos-tone-neutral-bg px-3 py-1 text-sm text-reos-tone-neutral-fg">
                                {t('consents.history.current')}
                            </span>
                        ) : null}
                        {onWithdraw && record.withdrawable ? (
                            <Button
                                variant="outline"
                                disabled={disabled}
                                onClick={() => onWithdraw(record)}
                                aria-label={t('consents.history.withdrawFor', {
                                    purpose: title,
                                })}
                            >
                                {t('consents.history.withdraw')}
                            </Button>
                        ) : null}
                    </li>
                );
            })}
        </ul>
    );
}

/** "By continuing, you agree to the Terms and the Privacy notice" under every sign-up option (A6.3). */
export function AgreementNote({ className }: { className?: string }) {
    const { t } = useTranslation();

    return (
        <p className={className ?? 'text-center text-sm text-muted-foreground'}>
            {t('consents.agree.lead')}{' '}
            <a
                href={terms().url}
                target="_blank"
                rel="noreferrer"
                className="underline"
            >
                {t('consents.agree.terms')}
            </a>{' '}
            {t('consents.agree.and')}{' '}
            <a
                href={privacy().url}
                target="_blank"
                rel="noreferrer"
                className="underline"
            >
                {t('consents.agree.privacy')}
            </a>
            .
        </p>
    );
}
