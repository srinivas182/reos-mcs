import { router, usePage } from '@inertiajs/react';
import { Sparkles, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { dismiss } from '@/routes/whats-new';

export type WhatsNewItem = {
    id: number;
    version: string;
    released_on: string;
    note: string;
};

/** The release note for this role (A6.2), shown once until dismissed. */
export function WhatsNewCard({
    item,
    onDismiss,
}: {
    item: WhatsNewItem;
    onDismiss: () => void;
}) {
    const { t } = useTranslation();
    const title = t('whatsNew.title', { version: item.version });

    return (
        <section
            aria-label={t('whatsNew.label')}
            className="flex items-start gap-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <span
                aria-hidden
                className="flex size-12 shrink-0 items-center justify-center rounded-reos-md bg-reos-tone-info-bg text-reos-tone-info-fg"
            >
                <Sparkles className="size-6" />
            </span>
            <div className="min-w-0 flex-1 space-y-1">
                <h2 className="font-semibold">{title}</h2>
                <p className="text-body">{item.note}</p>
            </div>
            <Button
                variant="ghost"
                size="icon"
                onClick={onDismiss}
                aria-label={t('whatsNew.dismissLabel', {
                    version: item.version,
                })}
            >
                <X aria-hidden />
            </Button>
        </section>
    );
}

/** "What's new" at the top of each page until the person dismisses it. */
export function WhatsNew() {
    const { whatsNew } = usePage().props;

    if (whatsNew === null) {
        return null;
    }

    return (
        <div className="mx-4 mt-4 md:mx-6">
            <WhatsNewCard
                item={whatsNew}
                onDismiss={() =>
                    router.post(
                        dismiss(whatsNew.id).url,
                        {},
                        { preserveScroll: true, preserveState: true },
                    )
                }
            />
        </div>
    );
}
