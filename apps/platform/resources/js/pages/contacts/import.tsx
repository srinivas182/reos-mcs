import { Head, Link, router, useForm } from '@inertiajs/react';
import { FileUp, History } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { nativeSelect } from '@/lib/tenants';
import { timeAgo } from '@/lib/time-ago';
import { index as contacts } from '@/routes/contacts';
import {
    preview as previewRoute,
    run,
    undo,
    upload,
} from '@/routes/contacts/import';

type Outcome = 'new' | 'merged' | 'skipped';

type Field =
    | 'full_name'
    | 'first_name'
    | 'last_name'
    | 'email'
    | 'phone'
    | 'stage'
    | 'tags'
    | 'birthday';

type Props = {
    fields: Field[];
    max_rows: number;
    current: {
        id: string;
        file_name: string;
        rows: number;
        headers: string[];
        mapping: Record<string, string>;
    } | null;
    preview: {
        new: number;
        merged: number;
        skipped: number;
        samples: {
            row: number;
            name: string;
            email: string | null;
            phone: string | null;
            outcome: Outcome;
            joins: string | null;
        }[];
    } | null;
    imports: {
        id: string;
        file_name: string;
        name: string;
        status: 'imported' | 'undone';
        counts: { new?: number; merged?: number; skipped?: number } | null;
        imported_at: string | null;
        undone_at: string | null;
    }[];
};

const th = 'px-3 py-2 text-left font-medium';
const td = 'px-3 py-3';

/**
 * CSV import, the last resort (screen 13, B8.3b): upload, match the
 * columns, check who is new and who joins a contact already there, import,
 * and undo any past import.
 */
export default function ContactImportPage({
    fields,
    max_rows: maxRows,
    current,
    preview,
    imports,
}: Props) {
    const { t, i18n } = useTranslation();
    const file = useForm<{ file: File | null }>({ file: null });
    const [mapping, setMapping] = useState<Record<string, string>>(
        current?.mapping ?? {},
    );
    const [errors, setErrors] = useState<Record<string, string>>({});

    return (
        <>
            <Head title={t('crm.import.title')} />
            <div className="space-y-8 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('crm.import.title')}
                    description={t('crm.import.description')}
                    back={
                        <Link
                            href={contacts()}
                            className="text-body text-primary underline underline-offset-4"
                        >
                            {t('crm.hub.title')}
                        </Link>
                    }
                />

                {current === null ? (
                    <form
                        className="max-w-xl space-y-3 rounded-reos-lg border border-border bg-card p-5"
                        onSubmit={(e) => {
                            e.preventDefault();
                            file.post(upload().url, { forceFormData: true });
                        }}
                    >
                        <Label htmlFor="csv-file">
                            {t('crm.import.upload.label')}
                        </Label>
                        <Input
                            id="csv-file"
                            type="file"
                            accept=".csv,text/csv"
                            onChange={(e) =>
                                file.setData(
                                    'file',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <p className="text-sm text-muted-foreground">
                            {t('crm.import.upload.hint', { max: maxRows })}
                        </p>
                        <InputError message={file.errors.file} />
                        <Button
                            type="submit"
                            disabled={
                                file.processing || file.data.file === null
                            }
                        >
                            <FileUp aria-hidden />
                            {t('crm.import.upload.submit')}
                        </Button>
                    </form>
                ) : (
                    <section aria-labelledby="map-title" className="space-y-4">
                        <h2 id="map-title" className="text-lg font-semibold">
                            {t('crm.import.map.title')}
                        </h2>
                        <p className="text-body text-muted-foreground">
                            {t('crm.import.map.intro', {
                                file: current.file_name,
                                rows: current.rows,
                            })}
                        </p>
                        <div className="overflow-x-auto rounded-reos-lg border border-border bg-card">
                            <table className="w-full text-body">
                                <thead className="border-b border-border text-sm text-muted-foreground">
                                    <tr>
                                        <th className={th}>
                                            {t('crm.import.map.column')}
                                        </th>
                                        <th className={th}>
                                            {t('crm.import.map.field')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {current.headers.map((header, i) => (
                                        <tr key={`${header}-${i}`}>
                                            <th
                                                scope="row"
                                                className={`${td} text-left font-medium`}
                                            >
                                                <label htmlFor={`map-${i}`}>
                                                    {header}
                                                </label>
                                            </th>
                                            <td className={td}>
                                                <select
                                                    id={`map-${i}`}
                                                    className={nativeSelect}
                                                    value={
                                                        mapping[header] ??
                                                        'ignore'
                                                    }
                                                    onChange={(e) =>
                                                        setMapping({
                                                            ...mapping,
                                                            [header]:
                                                                e.target.value,
                                                        })
                                                    }
                                                >
                                                    <option value="ignore">
                                                        {t(
                                                            'crm.import.fields.ignore',
                                                        )}
                                                    </option>
                                                    {fields.map((field) => (
                                                        <option
                                                            key={field}
                                                            value={field}
                                                        >
                                                            {t(
                                                                `crm.import.fields.${field}`,
                                                            )}
                                                        </option>
                                                    ))}
                                                </select>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <InputError message={errors.mapping} />
                        <div className="flex flex-wrap gap-3">
                            <Button
                                variant={preview ? 'outline' : 'default'}
                                onClick={() =>
                                    router.put(
                                        previewRoute(current.id).url,
                                        { mapping },
                                        {
                                            preserveScroll: true,
                                            onError: setErrors,
                                        },
                                    )
                                }
                            >
                                {t('crm.import.map.preview')}
                            </Button>
                            <Button
                                variant="ghost"
                                onClick={() =>
                                    router.delete(undo(current.id).url)
                                }
                            >
                                {t('common.cancel')}
                            </Button>
                        </div>

                        {preview ? (
                            <section
                                aria-labelledby="preview-title"
                                aria-live="polite"
                                className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
                            >
                                <h3
                                    id="preview-title"
                                    className="font-semibold"
                                >
                                    {t('crm.import.preview.title')}
                                </h3>
                                <dl className="grid gap-3 sm:grid-cols-3">
                                    {(
                                        ['new', 'merged', 'skipped'] as const
                                    ).map((key) => (
                                        <div key={key}>
                                            <dt className="text-sm text-muted-foreground">
                                                {t(`crm.import.preview.${key}`)}
                                            </dt>
                                            <dd className="text-xl font-semibold tabular-nums">
                                                {preview[key]}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-body">
                                        <thead className="border-b border-border text-sm text-muted-foreground">
                                            <tr>
                                                <th className={th}>
                                                    {t(
                                                        'crm.import.preview.row',
                                                    )}
                                                </th>
                                                <th className={th}>
                                                    {t('crm.hub.columns.name')}
                                                </th>
                                                <th className={th}>
                                                    {t('crm.hub.email')}
                                                </th>
                                                <th className={th}>
                                                    {t('crm.hub.phone')}
                                                </th>
                                                <th className={th}>
                                                    {t(
                                                        'crm.import.preview.outcome',
                                                    )}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            {preview.samples.map((sample) => (
                                                <tr key={sample.row}>
                                                    <td
                                                        className={`${td} tabular-nums`}
                                                    >
                                                        {sample.row}
                                                    </td>
                                                    <td className={td}>
                                                        {sample.name}
                                                    </td>
                                                    <td
                                                        className={`${td} break-all`}
                                                    >
                                                        {sample.email}
                                                    </td>
                                                    <td
                                                        className={`${td} tabular-nums`}
                                                    >
                                                        {sample.phone}
                                                    </td>
                                                    <td className={td}>
                                                        {sample.joins
                                                            ? t(
                                                                  'crm.import.preview.joins',
                                                                  {
                                                                      name: sample.joins,
                                                                  },
                                                              )
                                                            : t(
                                                                  `crm.import.preview.outcomes.${sample.outcome}`,
                                                              )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                <Button
                                    onClick={() =>
                                        router.post(run(current.id).url)
                                    }
                                >
                                    {t('crm.import.preview.import', {
                                        count: preview.new + preview.merged,
                                    })}
                                </Button>
                            </section>
                        ) : null}
                    </section>
                )}

                <section aria-labelledby="past-title" className="space-y-3">
                    <h2 id="past-title" className="text-lg font-semibold">
                        {t('crm.import.past.title')}
                    </h2>
                    {imports.length === 0 ? (
                        <EmptyState
                            icon={History}
                            title={t('crm.import.past.empty')}
                            description={t('crm.import.past.emptyBody')}
                        />
                    ) : (
                        <ul className="divide-y divide-border rounded-reos-lg border border-border bg-card">
                            {imports.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex flex-wrap items-center justify-between gap-3 p-4"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {item.name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {item.file_name}
                                            {' · '}
                                            {item.status === 'undone'
                                                ? t('crm.import.past.undone')
                                                : t(
                                                      'crm.import.past.imported',
                                                      {
                                                          new:
                                                              item.counts
                                                                  ?.new ?? 0,
                                                          merged:
                                                              item.counts
                                                                  ?.merged ?? 0,
                                                          skipped:
                                                              item.counts
                                                                  ?.skipped ??
                                                              0,
                                                      },
                                                  )}
                                            {item.imported_at
                                                ? ` · ${timeAgo(item.imported_at, i18n.language)}`
                                                : ''}
                                        </p>
                                    </div>
                                    {item.status === 'imported' ? (
                                        <Button
                                            variant="outline"
                                            aria-label={t(
                                                'crm.import.past.undoLabel',
                                                { name: item.name },
                                            )}
                                            onClick={() =>
                                                router.delete(
                                                    undo(item.id).url,
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {t('crm.import.past.undo')}
                                        </Button>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}
