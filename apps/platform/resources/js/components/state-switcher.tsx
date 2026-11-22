import { router } from '@inertiajs/react';
import { ChevronDown, Map } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { update as updateCurrentState } from '@/routes/current-state';

export type StateRegion = {
    code: string;
    name: string;
    options?: { code: string; name: string }[];
};

type Props = {
    region: StateRegion;
    /** Switches the state (the page reloads with that state's content). */
    onSwitch?: (code: string) => void;
};

const chip =
    'inline-flex items-center gap-1.5 rounded-reos-pill bg-muted px-3 py-1 text-sm font-medium text-foreground';

/**
 * The state chip in the top bar (web journey, Navigation; A5.1): names the
 * state you're working in and, when you hold licences or study in more than
 * one live state, switches it. Content, plans and the CE card follow it.
 */
export function StateSwitcher({ region, onSwitch }: Props) {
    const { t } = useTranslation();
    const options = region.options ?? [];
    const switchTo =
        onSwitch ??
        ((code: string) =>
            router.put(
                updateCurrentState().url,
                { state: code },
                { preserveScroll: true },
            ));

    if (options.length < 2) {
        return (
            <span
                className={`hidden sm:inline-flex ${chip}`}
                aria-label={t('nav.state', { state: region.name })}
            >
                <Map aria-hidden className="size-4" />
                {region.name}
            </span>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="hidden min-h-12 items-center rounded-reos-md focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none sm:flex"
                    aria-label={`${t('nav.state', { state: region.name })}. ${t('nav.switchState')}`}
                >
                    <span className={chip}>
                        <Map aria-hidden className="size-4" />
                        {region.name}
                        <ChevronDown aria-hidden className="size-4" />
                    </span>
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-60">
                <DropdownMenuLabel>{t('nav.switchState')}</DropdownMenuLabel>
                <DropdownMenuRadioGroup
                    value={region.code}
                    onValueChange={(code) => {
                        if (code !== region.code) {
                            switchTo(code);
                        }
                    }}
                >
                    {options.map((option) => (
                        <DropdownMenuRadioItem
                            key={option.code}
                            value={option.code}
                            className="min-h-12"
                        >
                            {option.name}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
