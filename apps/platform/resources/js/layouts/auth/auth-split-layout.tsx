import { Link, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * Sign-in and sign-up (journey screen w002): a brand panel on large screens
 * and the form on the right. The panel shows REOS today; from A4.2 it shows
 * the brokerage, school or association whose domain the person is on.
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { t } = useTranslation();
    const { name } = usePage<{ name: string }>().props;
    const host = typeof window === 'undefined' ? '' : window.location.host;

    return (
        <div className="grid min-h-dvh bg-background lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
            <aside className="relative hidden flex-col justify-between bg-reos-hero-bg p-12 text-reos-hero-text lg:flex">
                <Link
                    href={home()}
                    className="flex items-center gap-3 text-lg font-semibold"
                >
                    <BrandMark />
                    <span>{name}</span>
                </Link>
                <div className="max-w-xl space-y-4">
                    <p className="text-5xl leading-[1.08] font-bold tracking-tight text-balance">
                        {t('signIn.brand.welcomeBack')}
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
                        <BrandMark />
                        <span>{name}</span>
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

function BrandMark() {
    return (
        <span
            aria-hidden
            className="flex size-11 items-center justify-center rounded-reos-md bg-primary text-base font-bold text-primary-foreground"
        >
            RE
        </span>
    );
}
