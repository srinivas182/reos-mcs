import { Link, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { BrandMark } from '@/components/brand-mark';
import { BrandStyle } from '@/components/brand-style';
import { BrandTile } from '@/components/brand-tile';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * Sign-in and sign-up (journey screen 2, "Sign in (branded)"): a brand
 * panel on large screens and the form on the right. On a tenant's address
 * (`<slug>.reos.com`, or its own domain from A4.2b) the panel shows that
 * brokerage, school or association, "powered by REOS", in its colour.
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { t } = useTranslation();
    const page = usePage();
    const { name, brand } = page.props;
    // People arriving to join (sign-up, an invite, a code) aren't "back".
    const newcomer = ['auth/register', 'auth/invite', 'auth/join'].includes(
        page.component,
    );
    const host = typeof window === 'undefined' ? '' : window.location.host;

    const mark = brand ? (
        <>
            <BrandTile
                name={brand.name}
                logoUrl={brand.logo_url}
                colour={brand.primary}
            />
            <span>{t('signIn.brand.poweredBy', { name: brand.name })}</span>
        </>
    ) : (
        <>
            <BrandMark />
            <span>{name}</span>
        </>
    );

    return (
        <div className="grid min-h-dvh bg-background lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
            <BrandStyle palette={brand?.palette ?? null} />
            <aside className="relative hidden flex-col justify-between bg-reos-hero-bg p-12 text-reos-hero-text lg:flex">
                <Link
                    href={home()}
                    className="flex items-center gap-3 text-lg font-semibold"
                >
                    {mark}
                </Link>
                <div className="max-w-xl space-y-4">
                    <p className="text-5xl leading-[1.08] font-bold tracking-tight text-balance">
                        {newcomer
                            ? t('signIn.brand.welcomeNew')
                            : brand
                              ? t(`signIn.brand.welcome.${brand.type}`)
                              : t('signIn.brand.welcomeBack')}
                    </p>
                    {host ? (
                        <p className="text-lg text-reos-hero-muted">{host}</p>
                    ) : null}
                </div>
            </aside>

            <main className="flex items-center justify-center px-4 py-10 sm:px-8">
                <div className="w-full max-w-md space-y-8">
                    <Link
                        href={home()}
                        className="flex items-center gap-3 text-lg font-semibold lg:hidden"
                    >
                        {mark}
                    </Link>
                    <header className="space-y-2">
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            {title}
                        </h1>
                        {description ? (
                            <p className="text-muted-foreground">
                                {description}
                            </p>
                        ) : null}
                    </header>
                    {children}
                </div>
            </main>
        </div>
    );
}
