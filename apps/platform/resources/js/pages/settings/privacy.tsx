import { Head, Link, router } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type {
    ConsentRecordRow,
    ConsentSwitch,
    SwitchPurpose,
} from '@/components/consents';
import { ConsentHistory, PrivacySwitches } from '@/components/consents';
import Heading from '@/components/heading';
import { undoToast } from '@/lib/undo-toast';
import { update } from '@/routes/consents';
import { privacy, terms } from '@/routes/legal';
import { edit as profile } from '@/routes/profile';

/**
 * Settings › Privacy (A6.3, every role): the privacy switches and "Your
 * consents" with Withdraw. The terms and privacy notice in force, and where
 * to download or correct your data (Profile).
 */
export default function PrivacySettings({
    switches,
    records,
    versions,
}: {
    switches: ConsentSwitch[];
    records: ConsentRecordRow[];
    versions: { terms: string; privacy_notice: string };
}) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);

    const send = (
        purpose: string,
        granted: boolean,
        subject: string | null,
        after?: () => void,
    ) =>
        router.put(
            update(purpose).url,
            { granted, subject },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onSuccess: after,
            },
        );

    const toggle = (purpose: SwitchPurpose, on: boolean) =>
        send(purpose, on, null);

    const withdraw = (record: ConsentRecordRow) =>
        send(record.purpose, false, record.subject, () =>
            // A switch can be turned back on; consents for one thing are asked again where they happen.
            record.subject === null
                ? undoToast({
                      message: t('consents.withdrawnToast', {
                          purpose: t(
                              `consents.purposes.${record.purpose}.title`,
                          ),
                      }),
                      onUndo: () => send(record.purpose, true, null),
                  })
                : undefined,
        );

    return (
        <>
            <Head title={t('consents.title')} />
            <h1 className="sr-only">{t('consents.title')}</h1>

            <div className="space-y-10">
                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title={t('consents.switchesTitle')}
                        description={t('consents.switchesDescription')}
                    />
                    <PrivacySwitches
                        switches={switches}
                        onChange={toggle}
                        disabled={busy}
                    />
                </section>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title={t('consents.history.title')}
                        description={t('consents.history.description')}
                    />
                    <ConsentHistory
                        records={records}
                        onWithdraw={withdraw}
                        disabled={busy}
                    />
                </section>

                <section className="space-y-3 rounded-reos-lg border border-border bg-card p-5">
                    <h2 className="flex items-center gap-2 font-semibold">
                        <FileText aria-hidden className="size-5" />
                        {t('consents.documents.title')}
                    </h2>
                    <ul className="space-y-2 text-body">
                        <li>
                            <a
                                href={terms().url}
                                target="_blank"
                                rel="noreferrer"
                                className="underline"
                            >
                                {t('consents.purposes.terms.title')}
                            </a>{' '}
                            <span className="text-muted-foreground">
                                {t('consents.history.version', {
                                    version: versions.terms,
                                })}
                            </span>
                        </li>
                        <li>
                            <a
                                href={privacy().url}
                                target="_blank"
                                rel="noreferrer"
                                className="underline"
                            >
                                {t('consents.purposes.privacy_notice.title')}
                            </a>{' '}
                            <span className="text-muted-foreground">
                                {t('consents.history.version', {
                                    version: versions.privacy_notice,
                                })}
                            </span>
                        </li>
                    </ul>
                    <p className="text-body text-muted-foreground">
                        {t('consents.documents.yourData')}{' '}
                        <Link href={profile()} className="underline">
                            {t('consents.documents.profileLink')}
                        </Link>
                    </p>
                </section>
            </div>
        </>
    );
}
