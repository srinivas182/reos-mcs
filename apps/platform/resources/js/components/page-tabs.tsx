import type { InertiaLinkProps } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';

export type PageTab = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

/** The journey's underlined tabs under a page title (Settings, a tool's detail…). */
export function PageTabs({ tabs, label }: { tabs: PageTab[]; label: string }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <nav aria-label={label} className="border-b border-border">
            <ul className="-mb-px flex gap-2 overflow-x-auto">
                {tabs.map((tab) => {
                    const active = isCurrentOrParentUrl(tab.href);

                    return (
                        <li key={toUrl(tab.href)}>
                            <Link
                                href={tab.href}
                                prefetch
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'flex min-h-12 items-center border-b-2 px-3 text-base whitespace-nowrap focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                    active
                                        ? 'border-primary font-semibold text-primary'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {tab.title}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
