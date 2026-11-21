import { Link } from '@inertiajs/react';
import { MapPin, Menu } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { BrandMark } from '@/components/brand-mark';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { audience, dashboard, home, login } from '@/routes';

export const AUDIENCES = [
    'candidates',
    'agents',
    'brokerages',
    'schools',
    'associations',
    'service-providers',
] as const;

export type Audience = (typeof AUDIENCES)[number];

/**
 * The public site's top bar (web journey screen 1): REOS, the navigation by
 * audience, the state chip, Sign in and Get started. Signed-in visitors get
 * "Go to dashboard" instead. On small screens the audiences move into a sheet.
 */
export function PublicHeader({
    name,
    liveState,
    signedIn,
}: {
    /** The product name next to the mark ("REOS"). */
    name: string;
    /** The live state shown in the state chip; none hides the chip. */
    liveState?: string | null;
    signedIn: boolean;
}) {
    const { t } = useTranslation();

    const links = AUDIENCES.map((key) => (
        <Link
            key={key}
            href={audience(key)}
            className="rounded-reos-sm px-2 py-2 text-base whitespace-nowrap text-muted-foreground hover:text-foreground"
        >
            {t(`site.nav.${key}`)}
        </Link>
    ));

    return (
        <header className="border-b border-border bg-background">
            <div className="mx-auto flex min-h-18 max-w-7xl items-center gap-6 px-4 py-3 md:px-8">
                <Link
                    href={home()}
                    className="flex items-center gap-3 text-lg font-bold text-foreground"
                >
                    <BrandMark className="size-10" />
                    <span>{name}</span>
                </Link>

                <nav
                    aria-label={t('site.nav.label')}
                    className="hidden flex-1 items-center gap-1 min-[1360px]:flex"
                >
                    {links}
                </nav>

                <div className="ml-auto flex items-center gap-3">
                    {liveState ? (
                        <span
                            className="hidden items-center gap-1.5 rounded-reos-pill bg-muted px-3 py-1 text-sm font-medium text-foreground sm:inline-flex"
                            title={t('site.nav.stateChip', {
                                state: liveState,
                            })}
                        >
                            <MapPin aria-hidden className="size-4" />
                            {liveState}
                        </span>
                    ) : null}
                    {signedIn ? (
                        <Button asChild>
                            <Link href={dashboard()}>
                                {t('site.nav.dashboard')}
                            </Link>
                        </Button>
                    ) : (
                        <>
                            <Button
                                asChild
                                variant="ghost"
                                className="hidden sm:inline-flex"
                            >
                                <Link href={login()}>
                                    {t('site.nav.signIn')}
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={`${home().url}#roles`}>
                                    {t('site.nav.getStarted')}
                                </Link>
                            </Button>
                        </>
                    )}
                    <Sheet>
                        <SheetTrigger asChild>
                            <Button
                                variant="outline"
                                size="icon"
                                className="min-[1360px]:hidden"
                                aria-label={t('site.nav.menu')}
                            >
                                <Menu aria-hidden />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="right" className="p-6">
                            <SheetTitle>{t('site.nav.menu')}</SheetTitle>
                            <nav
                                aria-label={t('site.nav.label')}
                                className="mt-4 flex flex-col gap-1"
                            >
                                {links}
                                {signedIn ? null : (
                                    <Link
                                        href={login()}
                                        className="px-2 py-2 text-base font-semibold text-foreground"
                                    >
                                        {t('site.nav.signIn')}
                                    </Link>
                                )}
                            </nav>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
        </header>
    );
}
