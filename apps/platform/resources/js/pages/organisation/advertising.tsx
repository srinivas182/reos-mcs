import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import {
    type AdvertisingPage,
    AdvertisingPolicyView,
} from '@/components/studio/advertising-policy';
import {
    approve,
    returnMethod as sendBack,
    update,
} from '@/routes/organisation/advertising';
import { settings as organisationSettings } from '@/routes/organisation';
import {
    approve as approvePage,
    returnMethod as returnPage,
} from '@/routes/organisation/advertising/pages';

/**
 * Advertising policy (B12.1; broker web screen 35 with broker journey
 * screen 6): posts waiting for approval, the locked Texas rules, the
 * brokerage's rules and banned phrases, and the brand kit. Changes apply
 * at once; scheduled posts are checked again when they go out.
 */
export default function AdvertisingPolicyPage(props: AdvertisingPage) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);
    const done = { preserveScroll: true, onFinish: () => setBusy(false) };

    return (
        <>
            <Head title={t('studio.policy.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('studio.policy.title')}
                    description={t('studio.policy.purpose', {
                        brokerage: props.brokerage,
                    })}
                />
                <AdvertisingPolicyView
                    data={props}
                    busy={busy}
                    settingsHref={organisationSettings().url}
                    onSave={(rules) => {
                        setBusy(true);
                        router.put(update().url, rules, done);
                    }}
                    onApprove={(id) => {
                        setBusy(true);
                        router.post(approve(id).url, {}, done);
                    }}
                    onSendBack={(id, note) => {
                        setBusy(true);
                        router.post(sendBack(id).url, { note }, done);
                    }}
                    onPage={(id, yes, note) => {
                        setBusy(true);
                        router.post(
                            yes ? approvePage(id).url : returnPage(id).url,
                            yes ? {} : { note },
                            done,
                        );
                    }}
                />
            </div>
        </>
    );
}
