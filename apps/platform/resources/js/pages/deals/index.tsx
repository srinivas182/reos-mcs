import { Head, router } from '@inertiajs/react';
import { Handshake, Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DealBoardView } from '@/components/deals/deal-board';
import {
    bodyOf,
    DealFormDialog,
    type DealValues,
} from '@/components/deals/deal-form';
import { DealPanel } from '@/components/deals/deal-panel';
import type {
    DealBoard,
    DealCard,
    DealFull,
    DealStage,
} from '@/components/deals/types';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { deleteJson, postJson, putJson } from '@/lib/content';
import { undoToast } from '@/lib/undo-toast';
import {
    destroy,
    index,
    stage as stageRoute,
    store,
    update,
} from '@/routes/deals';

/**
 * Deals board (agent web screen 14, B9.1): every deal and deadline. Columns
 * by stage; cards with property, side, price and the next deadline. Deals
 * from a transaction tool are read-only (changes are made in the tool,
 * decision 3); deals tracked in REOS drag between stages, and each key date
 * is a to-do on that day.
 */
export default function DealsPage({
    board,
    selected,
}: {
    board: DealBoard;
    selected: DealFull | null;
}) {
    const { t } = useTranslation();
    const [form, setForm] = useState<{ open: boolean; deal: DealFull | null }>({
        open: false,
        deal: null,
    });
    const [error, setError] = useState<string | null>(null);
    const empty =
        board.active.length +
            board.under_contract.length +
            board.closed.length ===
        0;
    const reload = (deal?: string | null) =>
        router.visit(index({ query: deal ? { deal } : {} }).url, {
            preserveScroll: true,
            preserveState: true,
            only: ['board', 'selected'],
        });
    const open = (deal: DealCard) => reload(deal.uuid);
    const move = async (deal: DealCard, to: DealStage) => {
        try {
            await putJson(stageRoute(deal.uuid).url, { stage: to });
            setError(null);
            reload(selected?.uuid ?? null);
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };
    const save = async (values: DealValues) => {
        try {
            const saved = form.deal
                ? await putJson<{ data: DealFull }>(
                      update(form.deal.uuid).url,
                      bodyOf(values),
                  )
                : await postJson<{ data: DealFull }>(
                      store().url,
                      bodyOf(values),
                  );
            setError(null);
            setForm({ open: false, deal: null });
            reload(saved.data.uuid);
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };
    const remove = async (deal: DealFull) => {
        await deleteJson(destroy(deal.uuid).url);
        reload(null);
        undoToast({
            message: t('deals.deleted'),
            onUndo: async () => {
                const back = await postJson<{ data: DealFull }>(
                    store().url,
                    bodyOf({
                        property: deal.property,
                        city: deal.city ?? '',
                        side: deal.side,
                        stage: deal.stage,
                        price: deal.price === null ? '' : String(deal.price),
                        commission: deal.commission ?? '',
                        parties: deal.parties.map((p) => ({
                            name: p.name,
                            role: p.role,
                            email: p.email ?? '',
                            phone: p.phone ?? '',
                        })),
                        dates: deal.dates.map((d) => ({
                            kind: d.kind,
                            label: '',
                            due_on: d.due_on,
                            done: d.done,
                        })),
                    }),
                );
                reload(back.data.uuid);
            },
        });
    };

    return (
        <>
            <Head title={t('deals.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('deals.title')}
                    description={t('deals.description')}
                    actions={
                        <Button
                            onClick={() => setForm({ open: true, deal: null })}
                        >
                            <Plus aria-hidden />
                            {t('deals.add')}
                        </Button>
                    }
                />
                {error && !form.open ? (
                    <p role="alert" className="text-body text-destructive">
                        {error}
                    </p>
                ) : null}
                {empty ? (
                    <EmptyState
                        icon={Handshake}
                        title={t('deals.emptyTitle')}
                        description={t('deals.empty')}
                    />
                ) : (
                    <DealBoardView
                        board={board}
                        onOpen={open}
                        onMove={(deal, to) => void move(deal, to)}
                    />
                )}
            </div>
            <Sheet
                open={selected !== null}
                onOpenChange={(o) => (o ? undefined : reload(null))}
            >
                <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                    {selected ? (
                        <>
                            <SheetHeader>
                                <SheetTitle>{selected.property}</SheetTitle>
                                <SheetDescription>
                                    {t('deals.description')}
                                </SheetDescription>
                            </SheetHeader>
                            <div className="px-4 pb-6">
                                <DealPanel
                                    deal={selected}
                                    onMove={(to) => void move(selected, to)}
                                    onEdit={() =>
                                        setForm({ open: true, deal: selected })
                                    }
                                    onDelete={() => void remove(selected)}
                                    onChanged={() => reload(selected.uuid)}
                                />
                            </div>
                        </>
                    ) : null}
                </SheetContent>
            </Sheet>
            {form.open ? (
                <DealFormDialog
                    open
                    initial={form.deal}
                    error={error}
                    onOpenChange={(o) =>
                        setForm(o ? form : { open: false, deal: null })
                    }
                    onSave={(values) => void save(values)}
                />
            ) : null}
        </>
    );
}
