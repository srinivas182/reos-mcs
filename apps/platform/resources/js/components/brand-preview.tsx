import { useTranslation } from 'react-i18next';
import { BrandTile } from '@/components/brand-tile';
import type { TenantType } from '@/types';

/**
 * What members will see (A4.2a): the sign-in page's brand panel and an
 * email, in the colour being edited, before anything is saved.
 */
export function BrandPreview({
    name,
    type,
    logoUrl,
    colour,
    address,
    sender,
}: {
    name: string;
    type: TenantType;
    logoUrl: string | null;
    colour: string;
    address: string | null;
    sender: string;
}) {
    const { t } = useTranslation();

    return (
        <section
            className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
            aria-labelledby="preview-title"
        >
            <h2 id="preview-title" className="font-semibold">
                {t('brand.page.preview')}
            </h2>
            <div className="grid gap-3 sm:grid-cols-2">
                <figure className="space-y-2">
                    <figcaption className="text-sm text-muted-foreground">
                        {t('brand.page.previewSignIn')}
                    </figcaption>
                    <div className="flex min-h-48 flex-col justify-between rounded-reos-md bg-reos-hero-bg p-4 text-reos-hero-text">
                        <div className="flex items-center gap-2 text-sm font-semibold">
                            <BrandTile
                                name={name}
                                logoUrl={logoUrl}
                                colour={colour}
                                className="size-8 text-xs"
                            />
                            {t('brand.page.poweredBy', { name })}
                        </div>
                        <div className="space-y-3">
                            <p className="text-base leading-snug font-bold">
                                {t(`signIn.brand.welcome.${type}`)}
                            </p>
                            {address ? (
                                <p className="text-sm text-reos-hero-muted">
                                    {address}
                                </p>
                            ) : null}
                            <span
                                className="inline-flex min-h-10 items-center rounded-reos-md px-4 text-sm font-semibold"
                                style={{
                                    backgroundColor: colour,
                                    color: '#FFFFFF',
                                }}
                            >
                                {t('brand.page.previewButton')}
                            </span>
                        </div>
                    </div>
                </figure>
                <figure className="space-y-2">
                    <figcaption className="text-sm text-muted-foreground">
                        {t('brand.page.previewEmail')}
                    </figcaption>
                    <div className="min-h-48 overflow-hidden rounded-reos-md border border-border bg-background">
                        <div
                            className="flex items-center gap-2 px-4 py-3 text-sm font-semibold text-white"
                            style={{ backgroundColor: colour }}
                        >
                            <BrandTile
                                name={name}
                                logoUrl={logoUrl}
                                colour={colour}
                                className="size-7 text-xs"
                            />
                            {name}
                        </div>
                        <div className="space-y-2 p-4 text-sm">
                            <p className="text-muted-foreground">
                                {t('brand.page.from', { name: sender })}
                            </p>
                            <p>{t('brand.page.emailGreeting')}</p>
                            <p>{t('brand.page.emailBody')}</p>
                        </div>
                    </div>
                </figure>
            </div>
        </section>
    );
}
