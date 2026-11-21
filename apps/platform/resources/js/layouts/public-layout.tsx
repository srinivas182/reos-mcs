import { localeNames, supportedLocales } from '@reos/i18n';
import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { PublicHeader } from '@/components/public-header';
import { useAppearance } from '@/hooks/use-appearance';
import { liveStateWording } from '@/lib/live-states';
import { cn } from '@/lib/utils';

/**
 * The public site and the web "get started" path: top bar, page, and a footer
 * with English / Español. The signed-in web app has its own shell (sidebar);
 * this layout is only for pages before it.
 */
export default function PublicLayout({ children }: { children: ReactNode }) {
    const { t, i18n } = useTranslation();
    const { name, auth, liveStates } = usePage().props;
    const { locale, updateLocale } = useAppearance();

    return (
        <div className="flex min-h-dvh flex-col bg-background text-foreground">
            <PublicHeader
                name={name}
                signedIn={Boolean(auth.user)}
                liveStates={liveStateWording(liveStates, i18n.language)}
            />
            <main className="flex-1">{children}</main>
            <footer className="border-t border-border">
                <div className="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-8 text-muted-foreground md:flex-row md:items-center md:justify-between md:px-8">
                    <div>
                        <p className="font-semibold text-foreground">
                            {t('site.footer.copyright', {
                                year: new Date().getFullYear(),
                            })}
                        </p>
                        <p>{t('site.footer.tagline')}</p>
                    </div>
                    <div
                        role="group"
                        aria-label={t('site.footer.language')}
                        className="flex gap-2"
                    >
                        {supportedLocales.map((value) => (
                            <button
                                key={value}
                                type="button"
                                lang={value}
                                aria-pressed={locale === value}
                                onClick={() => updateLocale(value)}
                                className={cn(
                                    'min-h-12 rounded-reos-pill border border-border px-4 text-base font-medium',
                                    locale === value
                                        ? 'border-foreground bg-foreground text-background'
                                        : 'bg-card text-foreground hover:bg-muted',
                                )}
                            >
                                {localeNames[value]}
                            </button>
                        ))}
                    </div>
                </div>
            </footer>
        </div>
    );
}
