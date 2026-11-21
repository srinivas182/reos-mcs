import { Form, Head } from '@inertiajs/react';
import { Check, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { state as chooseState } from '@/routes/get-started';

type StateRow = { code: string; name: string; live: boolean; waiting: number };
type Props = { states: StateRow[]; chosen: string | null };

/**
 * "Where do you want to be licensed?" (candidate journey screen 2, on the
 * web). Live states go on to the free quiz; the others to their waitlist.
 */
export default function ChooseState({ states, chosen }: Props) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [selected, setSelected] = useState(
        chosen ?? states.find((s) => s.live)?.code ?? '',
    );
    const shown = useMemo(() => {
        const search = query.trim().toLowerCase();

        return search === ''
            ? states
            : states.filter(
                  (s) =>
                      s.name.toLowerCase().includes(search) ||
                      s.code.toLowerCase() === search,
              );
    }, [query, states]);
    const current = states.find((s) => s.code === selected);

    return (
        <>
            <Head title={t('onboarding.state.titleCandidate')} />
            <div className="mx-auto max-w-2xl space-y-6 px-4 py-10 md:py-16">
                <header className="space-y-3">
                    <p className="text-sm font-semibold tracking-wide text-primary uppercase">
                        {t('onboarding.state.step', { step: 1, total: 4 })}
                    </p>
                    <h1 className="text-4xl font-bold tracking-tight text-foreground">
                        {t('onboarding.state.titleCandidate')}
                    </h1>
                    <p className="text-lg text-muted-foreground">
                        {t('onboarding.state.subtitle')}
                    </p>
                </header>

                <div className="relative">
                    <Search
                        aria-hidden
                        className="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        type="search"
                        aria-label={t('onboarding.state.search')}
                        placeholder={t('onboarding.state.search')}
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        className="pl-12"
                    />
                </div>

                <Form {...chooseState.form()} className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="state"
                                value={selected}
                            />
                            <div
                                role="radiogroup"
                                aria-label={t('site.getStarted.stateHelp')}
                                className="max-h-[28rem] space-y-3 overflow-y-auto p-1"
                            >
                                {shown.map((row) => {
                                    const isSelected = row.code === selected;

                                    return (
                                        <button
                                            key={row.code}
                                            type="button"
                                            role="radio"
                                            aria-checked={isSelected}
                                            onClick={() =>
                                                setSelected(row.code)
                                            }
                                            className={cn(
                                                'flex min-h-16 w-full items-center gap-4 rounded-reos-md border bg-card px-4 py-3 text-left transition-colors',
                                                isSelected
                                                    ? 'border-primary ring-1 ring-primary'
                                                    : 'border-border hover:border-primary',
                                            )}
                                        >
                                            <span
                                                aria-hidden
                                                className="flex size-11 shrink-0 items-center justify-center rounded-reos-sm bg-foreground font-bold text-background"
                                            >
                                                {row.code}
                                            </span>
                                            <span className="flex-1">
                                                <span className="block font-semibold text-foreground">
                                                    {row.name}
                                                </span>
                                                {row.live ? (
                                                    <span className="block text-sm text-muted-foreground">
                                                        {t(
                                                            'onboarding.state.liveDetail',
                                                        )}
                                                    </span>
                                                ) : null}
                                            </span>
                                            <span
                                                className={cn(
                                                    'rounded-reos-pill px-3 py-1 text-sm font-semibold',
                                                    row.live
                                                        ? 'bg-reos-tone-success-bg text-reos-tone-success-fg'
                                                        : 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
                                                )}
                                            >
                                                {row.live
                                                    ? t('onboarding.state.live')
                                                    : t(
                                                          'onboarding.state.comingSoon',
                                                      )}
                                            </span>
                                            {isSelected ? (
                                                <Check
                                                    aria-hidden
                                                    className="size-5 text-primary"
                                                />
                                            ) : null}
                                        </button>
                                    );
                                })}
                                {shown.length === 0 ? (
                                    <p className="text-muted-foreground">
                                        {t('onboarding.state.noResults', {
                                            search: query,
                                        })}
                                    </p>
                                ) : null}
                            </div>
                            <InputError message={errors.state} />
                            <p className="text-sm text-muted-foreground">
                                {t('onboarding.state.note')}
                            </p>
                            <Button
                                type="submit"
                                className="w-full"
                                disabled={!current || processing}
                            >
                                {t('onboarding.state.continue', {
                                    state: current?.name ?? '',
                                })}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
