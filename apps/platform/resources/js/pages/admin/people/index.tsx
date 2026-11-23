import { Form, Head, Link } from '@inertiajs/react';
import { ChevronRight, SearchX, ShieldCheck, Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { LicenceReviewQueue } from '@/components/licence-reviews';
import {
    ProviderLicenceReviewQueue,
    type ProviderLicenceReview,
} from '@/components/provider-licences';
import type { LicenceReviewItem } from '@/components/licence-reviews';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { membershipLabel } from '@/lib/roles';
import { index, show } from '@/routes/people';
import type { Person } from '@/types/people';

type Props = {
    search: string;
    people: {
        data: Person[];
        current_page: number;
        last_page: number;
        total: number;
    };
    licenceReviews?: LicenceReviewItem[];
    providerLicenceReviews?: ProviderLicenceReview[];
};

/** Settings › People (super admin): find a person and open their roles. */
export default function PeopleIndex({
    search,
    people,
    licenceReviews = [],
    providerLicenceReviews = [],
}: Props) {
    const { t } = useTranslation();
    const page = (number: number) =>
        index({ query: { ...(search ? { search } : {}), page: number } });

    return (
        <>
            <Head title={t('people.title')} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('people.title')}
                    description={t('people.description')}
                />

                <LicenceReviewQueue items={licenceReviews} />
                <ProviderLicenceReviewQueue items={providerLicenceReviews} />

                <Form
                    {...index.form()}
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-3 sm:flex-row sm:items-end"
                >
                    <div className="grid flex-1 gap-2">
                        <Label htmlFor="search">
                            {t('people.searchLabel')}
                        </Label>
                        <Input
                            id="search"
                            name="search"
                            type="search"
                            defaultValue={search}
                            placeholder={t('people.searchPlaceholder')}
                            autoComplete="off"
                        />
                    </div>
                    <Button type="submit">{t('people.search')}</Button>
                    {search ? (
                        <Button variant="ghost" asChild>
                            <Link href={index()}>{t('people.clear')}</Link>
                        </Button>
                    ) : null}
                </Form>

                {people.data.length === 0 ? (
                    search ? (
                        <EmptyState
                            icon={SearchX}
                            title={t('people.noResultsTitle', { search })}
                            description={t('people.noResultsDescription')}
                        />
                    ) : (
                        <EmptyState
                            icon={Users}
                            title={t('people.emptyTitle')}
                            description={t('people.emptyDescription')}
                        />
                    )
                ) : (
                    <>
                        <p
                            className="text-body text-muted-foreground"
                            aria-live="polite"
                        >
                            {t('people.count', { count: people.total })}
                        </p>
                        <ul className="divide-y divide-border rounded-reos-md border border-border bg-card">
                            {people.data.map((person) => {
                                const name = person.name ?? t('people.unnamed');

                                return (
                                    <li key={person.id}>
                                        <Link
                                            href={show(person.id)}
                                            className="flex min-h-12 items-center gap-4 p-4 hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                            aria-label={t('people.open', {
                                                name,
                                            })}
                                        >
                                            <div className="min-w-0 flex-1 space-y-1">
                                                <p className="font-semibold text-foreground">
                                                    {name}
                                                </p>
                                                <p className="truncate text-body text-muted-foreground">
                                                    {[
                                                        person.email,
                                                        person.phone,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </p>
                                                <p className="text-body text-foreground">
                                                    {person.roles.length > 0
                                                        ? person.roles
                                                              .map((m) =>
                                                                  membershipLabel(
                                                                      t,
                                                                      m,
                                                                  ),
                                                              )
                                                              .join(', ')
                                                        : t('people.noRoles')}
                                                </p>
                                            </div>
                                            {person.two_step ? (
                                                <span className="hidden items-center gap-1 text-sm text-muted-foreground sm:flex">
                                                    <ShieldCheck
                                                        aria-hidden
                                                        className="size-4"
                                                    />
                                                    {t('people.twoStepOn')}
                                                </span>
                                            ) : null}
                                            <ChevronRight
                                                aria-hidden
                                                className="size-5 text-muted-foreground"
                                            />
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>

                        {people.last_page > 1 ? (
                            <nav
                                className="flex items-center justify-between gap-4"
                                aria-label={t('people.page', {
                                    page: people.current_page,
                                    pages: people.last_page,
                                })}
                            >
                                {people.current_page > 1 ? (
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={page(people.current_page - 1)}
                                        >
                                            {t('people.previous')}
                                        </Link>
                                    </Button>
                                ) : (
                                    <span />
                                )}
                                <span className="text-body text-muted-foreground">
                                    {t('people.page', {
                                        page: people.current_page,
                                        pages: people.last_page,
                                    })}
                                </span>
                                {people.current_page < people.last_page ? (
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={page(people.current_page + 1)}
                                        >
                                            {t('people.next')}
                                        </Link>
                                    </Button>
                                ) : (
                                    <span />
                                )}
                            </nav>
                        ) : null}
                    </>
                )}
            </div>
        </>
    );
}
