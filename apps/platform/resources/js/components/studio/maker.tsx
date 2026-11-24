import { Sparkles } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { nativeSelect } from '@/lib/tenants';
import type {
    MakeRequest,
    Maker as MakerData,
    Output,
    SourceType,
} from './types';

const SOURCES: SourceType[] = ['listing', 'sale', 'market', 'text'];
const OUTPUTS: Output[] = ['post', 'carousel', 'ad'];

/**
 * Make content (agent screen 165; web screen 17): start from a listing, a
 * sale, a market update or your own words, choose what to make, and see
 * the cost in credits before anything is spent.
 */
export function Maker({
    data,
    onMake,
    busy = false,
}: {
    data: MakerData;
    onMake: (request: MakeRequest) => void;
    busy?: boolean;
}) {
    const { t } = useTranslation();
    const [type, setType] = useState<SourceType>(
        data.listings.length > 0 ? 'listing' : 'text',
    );
    const [listing, setListing] = useState(data.listings[0]?.id ?? '');
    const [sale, setSale] = useState(data.sales[0]?.id ?? '');
    const [text, setText] = useState('');
    const [city, setCity] = useState('');
    const [outputs, setOutputs] = useState<Output[]>(['post']);
    const ready =
        outputs.length > 0 &&
        ((type === 'listing' && listing !== '') ||
            (type === 'sale' && sale !== '') ||
            ((type === 'market' || type === 'text') &&
                text.trim().length >= 3));

    return (
        <form
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
            aria-labelledby="maker-title"
            onSubmit={(e) => {
                e.preventDefault();
                if (!ready) return;
                onMake({
                    source:
                        type === 'listing'
                            ? { type, id: listing }
                            : type === 'sale'
                              ? { type, id: sale }
                              : {
                                    type,
                                    text: text.trim(),
                                    city: city.trim() || null,
                                },
                    outputs:
                        type === 'listing'
                            ? outputs
                            : outputs.filter((o) => o !== 'carousel'),
                });
            }}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="space-y-1">
                    <h2 id="maker-title" className="text-lg font-semibold">
                        {t('studio.maker.title')}
                    </h2>
                    <p className="text-base text-muted-foreground">
                        {t('studio.maker.purpose')}
                    </p>
                </div>
                <p className="text-base text-muted-foreground">
                    {t('studio.maker.cost', {
                        credits: data.cost,
                        left: data.credits_left,
                    })}
                </p>
            </div>
            <fieldset className="space-y-2">
                <legend className="text-base font-medium">
                    {t('studio.maker.from')}
                </legend>
                <div className="flex flex-wrap gap-2">
                    {SOURCES.map((s) => (
                        <Button
                            key={s}
                            type="button"
                            variant={type === s ? 'default' : 'outline'}
                            aria-pressed={type === s}
                            onClick={() => setType(s)}
                        >
                            {t(`studio.maker.sources.${s}`)}
                        </Button>
                    ))}
                </div>
            </fieldset>
            {type === 'listing' ? (
                data.listings.length === 0 ? (
                    <p className="text-base text-muted-foreground">
                        {t('studio.maker.noListings')}
                    </p>
                ) : (
                    <div className="space-y-2">
                        <Label htmlFor="maker-listing">
                            {t('studio.maker.chooseListing')}
                        </Label>
                        <select
                            id="maker-listing"
                            className={nativeSelect}
                            value={listing}
                            onChange={(e) => setListing(e.target.value)}
                        >
                            {data.listings.map((l) => (
                                <option key={l.id} value={l.id}>
                                    {l.address}
                                </option>
                            ))}
                        </select>
                    </div>
                )
            ) : null}
            {type === 'sale' ? (
                data.sales.length === 0 ? (
                    <p className="text-base text-muted-foreground">
                        {t('studio.maker.noSales')}
                    </p>
                ) : (
                    <div className="space-y-2">
                        <Label htmlFor="maker-sale">
                            {t('studio.maker.chooseSale')}
                        </Label>
                        <select
                            id="maker-sale"
                            className={nativeSelect}
                            value={sale}
                            onChange={(e) => setSale(e.target.value)}
                        >
                            {data.sales.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.property}
                                </option>
                            ))}
                        </select>
                    </div>
                )
            ) : null}
            {type === 'market' || type === 'text' ? (
                <div className="grid gap-3 md:grid-cols-[3fr_1fr]">
                    <div className="space-y-2">
                        <Label htmlFor="maker-text">
                            {type === 'market'
                                ? t('studio.maker.marketText')
                                : t('studio.maker.text')}
                        </Label>
                        <Textarea
                            id="maker-text"
                            rows={3}
                            maxLength={1000}
                            value={text}
                            onChange={(e) => setText(e.target.value)}
                            placeholder={
                                type === 'market'
                                    ? t('studio.maker.marketPlaceholder')
                                    : t('studio.maker.textPlaceholder')
                            }
                        />
                    </div>
                    {type === 'market' ? (
                        <div className="space-y-2">
                            <Label htmlFor="maker-city">
                                {t('studio.maker.city')}
                            </Label>
                            <Input
                                id="maker-city"
                                value={city}
                                maxLength={80}
                                onChange={(e) => setCity(e.target.value)}
                            />
                        </div>
                    ) : null}
                </div>
            ) : null}
            <fieldset className="space-y-2">
                <legend className="text-base font-medium">
                    {t('studio.maker.outputs')}
                </legend>
                <div className="flex flex-wrap gap-4">
                    {OUTPUTS.filter(
                        (o) => o !== 'carousel' || type === 'listing',
                    ).map((o) => (
                        <label
                            key={o}
                            className="flex min-h-12 items-center gap-2 text-base"
                        >
                            <input
                                type="checkbox"
                                className="size-5"
                                checked={outputs.includes(o)}
                                onChange={(e) =>
                                    setOutputs((list) =>
                                        e.target.checked
                                            ? [...list, o]
                                            : list.filter((x) => x !== o),
                                    )
                                }
                            />
                            {t(`studio.maker.outputsNames.${o}`)}
                        </label>
                    ))}
                </div>
            </fieldset>
            <Button type="submit" disabled={busy || !ready}>
                <Sparkles aria-hidden />
                {busy ? t('studio.maker.making') : t('studio.maker.make')}
            </Button>
        </form>
    );
}
