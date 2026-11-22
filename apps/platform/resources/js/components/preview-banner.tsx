import { usePage } from '@inertiajs/react';
import { FlaskConical } from 'lucide-react';
import { useTranslation } from 'react-i18next';

/**
 * Shown on every page of the private preview (P7.0): it's for testing,
 * nothing in it is real, and the one code every verification uses.
 */
export function PreviewBanner() {
    const { preview } = usePage().props;

    return preview ? <PreviewNotice code={preview.code} /> : null;
}

export function PreviewNotice({ code }: { code: string }) {
    const { t } = useTranslation();

    return (
        <section
            aria-label={t('preview.label')}
            className="flex items-center gap-3 bg-reos-tone-info-bg px-4 py-2 text-reos-tone-info-fg md:px-8"
        >
            <FlaskConical aria-hidden className="size-5 shrink-0" />
            <p className="min-w-0 flex-1">
                <span className="font-semibold">{t('preview.title')}</span>{' '}
                {t('preview.text', { code })}
            </p>
        </section>
    );
}
