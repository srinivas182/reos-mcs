import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DealCardView } from './deal-card';
import type { DealBoard, DealCard, DealStage } from './types';

const COLUMNS = ['active', 'under_contract', 'closed'] as const;

/**
 * The Deals board (screen 14): columns by stage. REOS deals drag between
 * columns (decision 3); deals from a tool stay where the tool puts them.
 * Keyboard users move a REOS deal from its panel.
 */
export function DealBoardView({
    board,
    onOpen,
    onMove,
}: {
    board: DealBoard;
    onOpen: (deal: DealCard) => void;
    onMove: (deal: DealCard, stage: DealStage) => void;
}) {
    const { t } = useTranslation();
    const [dragging, setDragging] = useState<DealCard | null>(null);
    const [over, setOver] = useState<DealStage | null>(null);

    return (
        <div className="grid gap-4 lg:grid-cols-3">
            {COLUMNS.map((stage) => (
                <section
                    key={stage}
                    aria-labelledby={`stage-${stage}`}
                    onDragOver={(e) => {
                        if (dragging && dragging.stage !== stage) {
                            e.preventDefault();
                            setOver(stage);
                        }
                    }}
                    onDragLeave={() => setOver(null)}
                    onDrop={(e) => {
                        e.preventDefault();
                        if (dragging && dragging.stage !== stage) {
                            onMove(dragging, stage);
                        }
                        setDragging(null);
                        setOver(null);
                    }}
                    className={`space-y-3 rounded-reos-lg border p-3 ${over === stage ? 'border-primary bg-accent' : 'border-border bg-muted/40'}`}
                >
                    <h2
                        id={`stage-${stage}`}
                        className="flex items-center justify-between px-1 text-lg font-semibold"
                    >
                        {t(`deals.stages.${stage}`)}
                        <span className="text-sm font-normal text-muted-foreground">
                            {board[stage].length}
                        </span>
                    </h2>
                    {board[stage].length === 0 ? (
                        <p className="px-1 text-body text-muted-foreground">
                            {t('deals.emptyColumn')}
                        </p>
                    ) : (
                        <ul className="space-y-3">
                            {board[stage].map((deal) => (
                                <li
                                    key={deal.uuid}
                                    draggable={!deal.read_only}
                                    onDragStart={() => setDragging(deal)}
                                    onDragEnd={() => setDragging(null)}
                                >
                                    <DealCardView
                                        deal={deal}
                                        onOpen={() => onOpen(deal)}
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            ))}
        </div>
    );
}
