import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { putJson } from '@/lib/content';
import type { Duty, StaffPerson } from './types';

const DUTIES: Duty[] = [
    'approve_posts',
    'answer_escalations',
    'manage_policies',
    'sponsor_agents',
];

/**
 * People and permissions (A15.2): the designated broker, office admins and
 * team leaders, each with the duties the designated broker turned on.
 * Everyone else sees them read-only.
 */
export function StaffPermissions({
    people: initial,
    designated,
    route,
}: {
    people: StaffPerson[];
    designated: boolean;
    route: (userId: number) => string;
}) {
    const { t } = useTranslation();
    const ids = useId();
    const [people, setPeople] = useState(initial);
    const [error, setError] = useState<string | null>(null);

    const toggle = (person: StaffPerson, duty: Duty, on: boolean) => {
        const duties = on
            ? [...person.duties, duty]
            : person.duties.filter((d) => d !== duty);
        setError(null);
        void putJson<{ data: { people: StaffPerson[] } }>(
            route(person.user_id),
            {
                duties,
            },
        )
            .then((r) => {
                setPeople(r.data.people);
                toast.success(t('brokerage.staff.saved'));
            })
            .catch((e: unknown) =>
                setError(e instanceof Error ? e.message : String(e)),
            );
    };

    return (
        <section
            aria-labelledby={`${ids}-title`}
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <header className="space-y-1">
                <h2 id={`${ids}-title`} className="font-semibold">
                    {t('brokerage.staff.title')}
                </h2>
                <p className="text-base text-muted-foreground">
                    {t('brokerage.staff.purpose')}
                </p>
            </header>
            {people.length <= 1 ? (
                <p className="text-base text-muted-foreground">
                    {t('brokerage.staff.none')}
                </p>
            ) : null}
            <ul className="space-y-3">
                {people.map((person) => (
                    <li
                        key={person.user_id}
                        className="space-y-2 rounded-reos-md border border-border p-3"
                    >
                        <p className="font-medium">
                            {person.name} ·{' '}
                            {t(`brokerage.staff.titles.${person.title}`)}
                            {person.team
                                ? ` · ${t('brokerage.staff.team', { team: person.team })}`
                                : ''}
                        </p>
                        {person.editable ? (
                            <fieldset className="flex flex-wrap gap-2">
                                <legend className="sr-only">
                                    {person.name}
                                </legend>
                                {DUTIES.map((duty) => (
                                    <label
                                        key={duty}
                                        className="flex min-h-12 items-center gap-3 rounded-reos-md border border-input px-3"
                                    >
                                        <input
                                            type="checkbox"
                                            className="size-5 accent-primary"
                                            checked={person.duties.includes(
                                                duty,
                                            )}
                                            disabled={!designated}
                                            onChange={(e) =>
                                                toggle(
                                                    person,
                                                    duty,
                                                    e.target.checked,
                                                )
                                            }
                                        />
                                        {t(`brokerage.staff.duties.${duty}`)}
                                    </label>
                                ))}
                            </fieldset>
                        ) : (
                            <p className="text-base text-muted-foreground">
                                {t('brokerage.staff.all')}
                            </p>
                        )}
                    </li>
                ))}
            </ul>
            <InputError message={error ?? undefined} />
        </section>
    );
}
