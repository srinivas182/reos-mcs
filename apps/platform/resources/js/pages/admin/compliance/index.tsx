import { Form, Head, Link, router } from '@inertiajs/react';
import { ChevronDown, Download, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import type { MessageReportRow } from '@/components/message-reports';
import { MessageReports } from '@/components/message-reports';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { auditLabel, describeChange } from '@/lib/audit';
import { cn } from '@/lib/utils';
import { exportMethod, index } from '@/routes/compliance';
import { update as updatePrivacy } from '@/routes/compliance/privacy';

type PrivacyRow = {
    id: number;
    kind: 'access' | 'correct' | 'delete';
    person: string | null;
    details: string | null;
    status: 'open' | 'in_progress' | 'hold' | 'done' | 'declined' | 'withdrawn';
    due_at: string;
    overdue: boolean;
    extended: boolean;
    created_at: string | null;
    response: string | null;
};

type AuditRow = {
    id: number;
    at: string;
    actor: string | null;
    action: string;
    tenant: string | null;
    before: unknown;
    after: unknown;
    ip: string | null;
};

type Filters = {
    q: string | null;
    group: string | null;
    tenant: number | null;
    from: string | null;
    to: string | null;
};

type Props = {
    privacy: PrivacyRow[];
    messageReports?: MessageReportRow[];
    limitDays: number;
    dataMapReviewed: string;
    audit: { rows: AuditRow[]; next: string | null; previous: string | null };
    filters: Filters;
    options: { groups: AuditGroup[]; tenants: { id: number; name: string }[] };
};

type Action = 'start' | 'done' | 'decline' | 'extend';
type DialogAction = Exclude<Action, 'start'>;
type AuditGroup =
    | 'sign_in'
    | 'tenants'
    | 'people'
    | 'brand'
    | 'integrations'
    | 'states'
    | 'content'
    | 'billing'
    | 'privacy'
    | 'releases';

const RULES = [
    'respa',
    'advertising',
    'fairHousing',
    'noInAppPurchases',
    'twoStep',
    'supportConsent',
] as const;

const card = 'rounded-reos-lg border border-border bg-card p-5';
const th = 'px-3 py-2 text-left';
const td = 'px-3 py-3 align-top';

const statusTone: Record<PrivacyRow['status'], string> = {
    open: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    in_progress: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    hold: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    done: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    declined: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
    withdrawn: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
};

/**
 * Compliance and audit (REOS super admin, web journey screen 71; A4.3b):
 * privacy requests under the Texas Data Privacy and Security Act with their
 * 45-day clock, the platform's rules, and the platform audit log with
 * filters and CSV export.
 */
export default function Compliance({
    privacy,
    messageReports = [],
    limitDays,
    dataMapReviewed,
    audit,
    filters,
    options,
}: Props) {
    const { t, i18n } = useTranslation();
    const [acting, setActing] = useState<{
        row: PrivacyRow;
        action: DialogAction;
    } | null>(null);
    const date = (iso: string, withTime = false) =>
        new Intl.DateTimeFormat(
            i18n.language,
            withTime
                ? {
                      month: 'short',
                      day: 'numeric',
                      hour: 'numeric',
                      minute: '2-digit',
                  }
                : { month: 'short', day: 'numeric' },
        ).format(new Date(iso));
    const query = Object.fromEntries(
        Object.entries(filters).filter(
            ([, value]) => value !== null && value !== '',
        ),
    ) as Record<string, string>;
    const waiting = (row: PrivacyRow) =>
        ['open', 'in_progress', 'hold'].includes(row.status);

    return (
        <>
            <Head title={t('compliance.title')} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('compliance.title')}
                    description={t('compliance.purpose')}
                    actions={
                        <Button asChild>
                            <a href={exportMethod({ query }).url}>
                                <Download aria-hidden className="size-5" />
                                {t('compliance.export')}
                            </a>
                        </Button>
                    }
                />

                <div className="grid gap-4 xl:grid-cols-2">
                    <section
                        className={cn(card, 'space-y-4')}
                        aria-labelledby="privacy-title"
                    >
                        <h2 id="privacy-title" className="font-semibold">
                            {t('compliance.privacy.title')}
                        </h2>
                        {privacy.length === 0 ? (
                            <p className="text-muted-foreground">
                                {t('compliance.privacy.none')}
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[32rem] text-sm">
                                    <thead className="border-b border-border text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        <tr>
                                            {(
                                                [
                                                    'request',
                                                    'user',
                                                    'due',
                                                    'status',
                                                ] as const
                                            ).map((column) => (
                                                <th
                                                    key={column}
                                                    scope="col"
                                                    className={th}
                                                >
                                                    {t(
                                                        `compliance.privacy.columns.${column}`,
                                                    )}
                                                </th>
                                            ))}
                                            <th scope="col" className={th}>
                                                <span className="sr-only">
                                                    {t(
                                                        'compliance.privacy.columns.actions',
                                                    )}
                                                </span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {privacy.map((row) => (
                                            <tr key={row.id}>
                                                <th
                                                    scope="row"
                                                    className={cn(
                                                        td,
                                                        'text-left font-medium',
                                                    )}
                                                >
                                                    {t(
                                                        `compliance.privacy.kinds.${row.kind}`,
                                                    )}
                                                    {row.details ? (
                                                        <span
                                                            className="line-clamp-2 block font-normal text-muted-foreground"
                                                            title={row.details}
                                                        >
                                                            {row.details}
                                                        </span>
                                                    ) : null}
                                                </th>
                                                <td className={td}>
                                                    {row.person ??
                                                        t(
                                                            'compliance.privacy.deletedAccount',
                                                        )}
                                                </td>
                                                <td
                                                    className={cn(
                                                        td,
                                                        row.overdue &&
                                                            'font-semibold text-reos-tone-danger-fg',
                                                    )}
                                                >
                                                    {date(row.due_at)}
                                                    {row.overdue ? (
                                                        <span className="block">
                                                            {t(
                                                                'compliance.privacy.overdue',
                                                            )}
                                                        </span>
                                                    ) : null}
                                                    {row.extended ? (
                                                        <span className="block text-muted-foreground">
                                                            {t(
                                                                'compliance.privacy.extended',
                                                            )}
                                                        </span>
                                                    ) : null}
                                                </td>
                                                <td className={td}>
                                                    <span
                                                        className={cn(
                                                            'inline-flex rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold whitespace-nowrap',
                                                            statusTone[
                                                                row.status
                                                            ],
                                                        )}
                                                    >
                                                        {t(
                                                            `compliance.privacy.statuses.${row.status}`,
                                                        )}
                                                    </span>
                                                </td>
                                                <td className={td}>
                                                    {waiting(row) &&
                                                    row.status !== 'hold' ? (
                                                        <PrivacyActions
                                                            row={row}
                                                            onStart={() =>
                                                                router.patch(
                                                                    updatePrivacy(
                                                                        row.id,
                                                                    ).url,
                                                                    {
                                                                        action: 'start',
                                                                    },
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                            onChoose={(
                                                                action,
                                                            ) =>
                                                                setActing({
                                                                    row,
                                                                    action,
                                                                })
                                                            }
                                                        />
                                                    ) : null}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        <dl className="divide-y divide-border border-t border-border text-sm">
                            <div className="flex justify-between gap-4 py-2">
                                <dt className="text-muted-foreground">
                                    {t('compliance.privacy.limit')}
                                </dt>
                                <dd className="font-semibold">
                                    {t('compliance.privacy.days', {
                                        count: limitDays,
                                    })}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-4 py-2">
                                <dt className="text-muted-foreground">
                                    {t('compliance.privacy.dataMap')}
                                </dt>
                                <dd className="font-semibold">
                                    {t('compliance.privacy.reviewed', {
                                        date: date(
                                            `${dataMapReviewed}T12:00:00`,
                                        ),
                                    })}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section
                        className={cn(card, 'space-y-4')}
                        aria-labelledby="rules-title"
                    >
                        <h2 id="rules-title" className="font-semibold">
                            {t('compliance.rules.title')}
                        </h2>
                        <ul className="space-y-3">
                            {RULES.map((rule) => (
                                <li key={rule} className="flex gap-2">
                                    <ShieldCheck
                                        aria-hidden
                                        className="mt-0.5 size-5 shrink-0 text-reos-tone-success-fg"
                                    />
                                    {t(`compliance.rules.${rule}`)}
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>

                <section
                    id="message-reports"
                    className={cn(card, 'space-y-2')}
                    aria-labelledby="reports-title"
                >
                    <h2 id="reports-title" className="font-semibold">
                        {t('messages.report.admin.title')}
                    </h2>
                    <MessageReports reports={messageReports} />
                </section>

                <section
                    className={cn(card, 'space-y-4')}
                    aria-labelledby="audit-title"
                >
                    <h2 id="audit-title" className="font-semibold">
                        {t('compliance.audit.title')}
                    </h2>

                    <Form
                        action={index().url}
                        method="get"
                        className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_1fr_1fr_auto] lg:items-end"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="audit-q">
                                {t('compliance.audit.search')}
                            </Label>
                            <Input
                                id="audit-q"
                                name="q"
                                type="search"
                                defaultValue={filters.q ?? ''}
                                placeholder={t(
                                    'compliance.audit.searchPlaceholder',
                                )}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="audit-group">
                                {t('compliance.audit.area')}
                            </Label>
                            <select
                                id="audit-group"
                                name="group"
                                defaultValue={filters.group ?? ''}
                                className="h-12 rounded-reos-md border border-input bg-card px-3 text-base text-foreground"
                            >
                                <option value="">
                                    {t('compliance.audit.all')}
                                </option>
                                {options.groups.map((group) => (
                                    <option key={group} value={group}>
                                        {t(`compliance.audit.groups.${group}`)}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="audit-tenant">
                                {t('compliance.audit.tenant')}
                            </Label>
                            <select
                                id="audit-tenant"
                                name="tenant"
                                defaultValue={filters.tenant ?? ''}
                                className="h-12 rounded-reos-md border border-input bg-card px-3 text-base text-foreground"
                            >
                                <option value="">
                                    {t('compliance.audit.all')}
                                </option>
                                {options.tenants.map((tenant) => (
                                    <option key={tenant.id} value={tenant.id}>
                                        {tenant.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="audit-from">
                                {t('compliance.audit.from')}
                            </Label>
                            <Input
                                id="audit-from"
                                name="from"
                                type="date"
                                defaultValue={filters.from ?? ''}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="audit-to">
                                {t('compliance.audit.to')}
                            </Label>
                            <Input
                                id="audit-to"
                                name="to"
                                type="date"
                                defaultValue={filters.to ?? ''}
                            />
                        </div>
                        <Button type="submit" variant="outline">
                            {t('compliance.audit.apply')}
                        </Button>
                    </Form>

                    {audit.rows.length === 0 ? (
                        <EmptyState
                            icon={ShieldCheck}
                            title={t('compliance.audit.emptyTitle')}
                            description={t('compliance.audit.emptyBody')}
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[48rem] text-sm">
                                <thead className="border-b border-border text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    <tr>
                                        {(
                                            [
                                                'time',
                                                'who',
                                                'action',
                                                'change',
                                                'tenant',
                                            ] as const
                                        ).map((column) => (
                                            <th
                                                key={column}
                                                scope="col"
                                                className={th}
                                            >
                                                {t(
                                                    `compliance.audit.columns.${column}`,
                                                )}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {audit.rows.map((row) => (
                                        <tr key={row.id}>
                                            <td
                                                className={cn(
                                                    td,
                                                    'whitespace-nowrap',
                                                )}
                                            >
                                                {date(row.at, true)}
                                            </td>
                                            <td className={td}>
                                                {row.actor ??
                                                    t(
                                                        'compliance.audit.system',
                                                    )}
                                            </td>
                                            <th
                                                scope="row"
                                                className={cn(
                                                    td,
                                                    'text-left font-medium',
                                                )}
                                            >
                                                {auditLabel(
                                                    t,
                                                    row.action,
                                                    row.before,
                                                    row.after,
                                                )}
                                            </th>
                                            <td
                                                className={cn(
                                                    td,
                                                    'max-w-xs break-words text-muted-foreground',
                                                )}
                                            >
                                                {describeChange(
                                                    row.before,
                                                    row.after,
                                                )}
                                            </td>
                                            <td className={td}>
                                                {row.tenant ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <nav
                        aria-label={t('compliance.audit.pages')}
                        className="flex justify-between gap-2"
                    >
                        {audit.previous ? (
                            <Button asChild variant="outline">
                                <Link href={audit.previous} preserveScroll>
                                    {t('compliance.audit.newer')}
                                </Link>
                            </Button>
                        ) : (
                            <span />
                        )}
                        {audit.next ? (
                            <Button asChild variant="outline">
                                <Link href={audit.next} preserveScroll>
                                    {t('compliance.audit.older')}
                                </Link>
                            </Button>
                        ) : null}
                    </nav>
                </section>
            </div>

            <Dialog
                open={acting !== null}
                onOpenChange={(open) => (open ? null : setActing(null))}
            >
                <DialogContent>
                    {acting ? (
                        <Form
                            {...updatePrivacy.form(acting.row.id)}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setActing(null)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <DialogHeader>
                                        <DialogTitle>
                                            {t(
                                                `compliance.privacy.dialog.${acting.action}.title`,
                                            )}
                                        </DialogTitle>
                                        <DialogDescription>
                                            {t(
                                                `compliance.privacy.dialog.${acting.action}.body`,
                                                {
                                                    kind: t(
                                                        `compliance.privacy.kinds.${acting.row.kind}`,
                                                    ),
                                                    person:
                                                        acting.row.person ?? '',
                                                    days: limitDays,
                                                },
                                            )}
                                        </DialogDescription>
                                    </DialogHeader>
                                    <input
                                        type="hidden"
                                        name="action"
                                        value={acting.action}
                                    />
                                    <div className="grid gap-2">
                                        <Label htmlFor="privacy-note">
                                            {t(
                                                acting.action === 'done'
                                                    ? 'compliance.privacy.noteOptional'
                                                    : 'compliance.privacy.note',
                                            )}
                                        </Label>
                                        <Textarea
                                            id="privacy-note"
                                            name="note"
                                            rows={3}
                                            required={acting.action !== 'done'}
                                            aria-invalid={Boolean(errors.note)}
                                        />
                                        <InputError
                                            message={
                                                errors.note ?? errors.request
                                            }
                                        />
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setActing(null)}
                                        >
                                            {t('compliance.privacy.cancel')}
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {t(
                                                `compliance.privacy.actions.${acting.action}`,
                                            )}
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    ) : null}
                </DialogContent>
            </Dialog>
        </>
    );
}

/** One "Manage" menu per request: start, done, decline, extend (once). */
function PrivacyActions({
    row,
    onStart,
    onChoose,
}: {
    row: PrivacyRow;
    onStart: () => void;
    onChoose: (action: DialogAction) => void;
}) {
    const { t } = useTranslation();
    const words = {
        kind: t(`compliance.privacy.kinds.${row.kind}`),
        person: row.person ?? '',
    };
    const choices: DialogAction[] = row.extended
        ? ['done', 'decline']
        : ['done', 'decline', 'extend'];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    aria-label={t('compliance.privacy.manageFor', words)}
                >
                    {t('compliance.privacy.manage')}
                    <ChevronDown aria-hidden className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {row.status === 'open' ? (
                    <DropdownMenuItem onSelect={onStart}>
                        {t('compliance.privacy.actions.start')}
                    </DropdownMenuItem>
                ) : null}
                {choices.map((action) => (
                    <DropdownMenuItem
                        key={action}
                        onSelect={() => onChoose(action)}
                    >
                        {t(`compliance.privacy.actions.${action}`)}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
