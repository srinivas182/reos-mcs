import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, History, ShieldAlert, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import type {
    ConsentRecordRow,
    ConsentSwitch,
    SwitchPurpose,
} from '@/components/consents';
import { ConsentHistory, PrivacySwitches } from '@/components/consents';
import { EmptyState } from '@/components/empty-state';
import { GrantRoleForm } from '@/components/grant-role-form';
import type {
    OrganisationOption,
    RoleOption,
} from '@/components/grant-role-form';
import Heading from '@/components/heading';
import { PersonLicences } from '@/components/licence-reviews';
import { MembershipList } from '@/components/membership-list';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { roleName } from '@/lib/roles';
import { undoToast } from '@/lib/undo-toast';
import { index } from '@/routes/people';
import { update as updateConsent } from '@/routes/people/consents';
import {
    destroy as removeRole,
    store as grantRole,
} from '@/routes/people/roles';
import type { Membership, RoleName } from '@/types/auth';
import type { Licence } from '@/types/licences';
import type { Person, RoleChange } from '@/types/people';

type Props = {
    person: Person;
    roleOptions: RoleOption[];
    organisations: OrganisationOption[];
    activity: RoleChange[];
    licences?: Licence[];
    consents?: { switches: ConsentSwitch[]; records: ConsentRecordRow[] };
};

/**
 * One person (super admin): their roles, giving a role, removing one with
 * Undo, and the recent role changes. Every change is audited on the server.
 */
export default function PersonShow({
    person,
    roleOptions,
    organisations,
    activity,
    licences = [],
    consents = { switches: [], records: [] },
}: Props) {
    const { t, i18n: language } = useTranslation();
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [processing, setProcessing] = useState(false);
    // A new key clears the grant form after a role is given.
    const [grantKey, setGrantKey] = useState(0);
    // Errors belong next to the form that caused them; removals use a toast.
    const [lastAction, setLastAction] = useState<'grant' | 'remove' | null>(
        null,
    );
    const name = person.name ?? t('people.unnamed');
    // Why REOS changes a switch for them (their request), kept with the record (A6.3).
    const [consentNote, setConsentNote] = useState('');
    const changeConsent = (purpose: SwitchPurpose, on: boolean) =>
        router.put(
            updateConsent({ person: person.id, purpose }).url,
            { granted: on, note: consentNote },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setConsentNote(''),
                onError: (e) =>
                    toast.error(
                        e.note ?? e.purpose ?? t('consents.errors.generic'),
                    ),
            },
        );

    const organisationName = (id?: number): string =>
        organisations.find((o) => o.id === id)?.name ??
        t('people.activity.unknownOrganisation');

    const grant = (role: RoleName, organisationId: number) =>
        router.post(
            grantRole(person.id).url,
            { role, organisation_id: organisationId },
            {
                preserveScroll: true,
                onStart: () => {
                    setLastAction('grant');
                    setProcessing(true);
                },
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setGrantKey((key) => key + 1);
                    toast.success(
                        t('people.grant.granted', {
                            role: roleName(t, role),
                            organisation: organisationName(organisationId),
                        }),
                    );
                },
            },
        );

    const remove = (membership: Membership) =>
        router.delete(removeRole(person.id).url, {
            data: {
                role: membership.role,
                organisation_id: membership.organisation.id,
            },
            preserveScroll: true,
            onStart: () => {
                setLastAction('remove');
                setProcessing(true);
            },
            onFinish: () => setProcessing(false),
            onError: (failed) => {
                const message = Object.values(failed)[0];

                if (message) {
                    toast.error(message);
                }
            },
            onSuccess: () =>
                undoToast({
                    message: t('people.remove.removed', {
                        role: roleName(t, membership.role),
                        organisation: membership.organisation.name,
                    }),
                    onUndo: () =>
                        router.post(
                            grantRole(person.id).url,
                            {
                                role: membership.role,
                                organisation_id: membership.organisation.id,
                            },
                            { preserveScroll: true },
                        ),
                }),
        });

    const when = (value: string | null): string =>
        value === null
            ? ''
            : new Intl.DateTimeFormat(language.language, {
                  dateStyle: 'medium',
              }).format(new Date(value));

    return (
        <>
            <Head title={name} />

            <div className="space-y-8 px-4 py-6 md:px-8">
                <PageHeader
                    title={name}
                    description={[person.email, person.phone]
                        .filter(Boolean)
                        .join(' · ')}
                    back={
                        <Button variant="ghost" asChild className="-ml-3">
                            <Link href={index()}>
                                <ArrowLeft aria-hidden />
                                {t('people.back')}
                            </Link>
                        </Button>
                    }
                />
                <p className="flex flex-wrap items-center gap-x-4 gap-y-1 text-body text-muted-foreground">
                    {person.joined_at ? (
                        <span>
                            {t('people.joined', {
                                date: when(person.joined_at),
                            })}
                        </span>
                    ) : null}
                    <span className="flex items-center gap-1">
                        {person.two_step ? (
                            <ShieldCheck aria-hidden className="size-4" />
                        ) : (
                            <ShieldAlert aria-hidden className="size-4" />
                        )}
                        {person.two_step
                            ? t('people.twoStepOn')
                            : t('people.twoStepOff')}
                    </span>
                </p>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title={t('people.roles.title')}
                        description={t('people.roles.description')}
                    />
                    <MembershipList
                        memberships={person.roles}
                        onRemove={remove}
                        disabled={processing}
                    />
                </section>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title={t('licences.heading')}
                        description={t('licences.reviews.description')}
                    />
                    <PersonLicences licences={licences} />
                </section>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title={t('consents.admin.title')}
                        description={t('consents.admin.description')}
                    />
                    <div className="grid gap-2">
                        <Label htmlFor="consent-note">
                            {t('consents.admin.note')}
                        </Label>
                        <Input
                            id="consent-note"
                            value={consentNote}
                            maxLength={300}
                            placeholder={t('consents.admin.notePlaceholder')}
                            onChange={(e) => setConsentNote(e.target.value)}
                        />
                    </div>
                    <PrivacySwitches
                        switches={consents.switches}
                        onChange={changeConsent}
                        disabled={processing || consentNote.trim().length < 5}
                        person={name}
                    />
                    <ConsentHistory records={consents.records} />
                </section>

                <section className="space-y-4">
                    <Heading variant="small" title={t('people.grant.title')} />
                    <GrantRoleForm
                        key={grantKey}
                        roleOptions={roleOptions}
                        organisations={organisations}
                        onSubmit={grant}
                        processing={processing}
                        error={
                            lastAction === 'grant'
                                ? (errors.role ?? errors.organisation_id)
                                : undefined
                        }
                    />
                </section>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title={t('people.activity.title')}
                    />
                    {activity.length === 0 ? (
                        <EmptyState
                            icon={History}
                            title={t('people.activity.empty')}
                            description={t('people.description')}
                        />
                    ) : (
                        <ul className="divide-y divide-border rounded-reos-md border border-border bg-card">
                            {activity.map((change) => (
                                <li
                                    key={change.id}
                                    className="flex flex-wrap gap-x-4 gap-y-1 p-4"
                                >
                                    <span className="flex-1 text-foreground">
                                        {t(
                                            change.action === 'role.removed'
                                                ? 'people.activity.removed'
                                                : 'people.activity.granted',
                                            {
                                                actor:
                                                    change.actor ??
                                                    t(
                                                        'people.activity.someone',
                                                    ),
                                                role: change.properties?.role
                                                    ? roleName(
                                                          t,
                                                          change.properties
                                                              .role as RoleName,
                                                      )
                                                    : '',
                                                organisation: organisationName(
                                                    change.properties
                                                        ?.organisation_id,
                                                ),
                                            },
                                        )}
                                    </span>
                                    <time
                                        dateTime={change.created_at}
                                        className="text-body text-muted-foreground"
                                    >
                                        {when(change.created_at)}
                                    </time>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}
