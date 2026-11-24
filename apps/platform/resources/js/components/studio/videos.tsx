import { ArrowRight, Clapperboard } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { nativeSelect } from '@/lib/tenants';
import type { Maker, Video } from './types';

/**
 * Listing videos on the web studio (B12.2; web screen 17 "start from …
 * video"): make a 30-second vertical video from a listing, with the cost
 * first; watch my videos as they finish; use one in a post. Editing (trim,
 * captions, music, brand, hook) is on the phone (screen 168).
 */
export function Videos({
    data,
    busy = false,
    onMake,
    onUse,
}: {
    data: Pick<Maker, 'listings' | 'video_cost' | 'credits_left' | 'videos'>;
    busy?: boolean;
    onMake: (listing: string) => void;
    onUse: (video: Video) => void;
}) {
    const { t } = useTranslation();
    const [listing, setListing] = useState(data.listings[0]?.id ?? '');

    return (
        <section
            aria-labelledby="videos-title"
            className="space-y-5 rounded-reos-lg border border-border bg-card p-5"
        >
            <div className="space-y-1">
                <h2 id="videos-title" className="text-lg font-semibold">
                    {t('studio.video.videos')}
                </h2>
                <p className="text-base text-muted-foreground">
                    {t('studio.video.hint')}
                </p>
            </div>
            {data.listings.length === 0 ? (
                <p className="text-base text-muted-foreground">
                    {t('studio.video.noListings')}
                </p>
            ) : (
                <div className="flex flex-wrap items-end gap-3">
                    <div className="min-w-64 flex-1 space-y-2">
                        <Label htmlFor="video-listing">
                            {t('studio.video.chooseListing')}
                        </Label>
                        <select
                            id="video-listing"
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
                    <Button
                        disabled={busy || listing === ''}
                        onClick={() => onMake(listing)}
                    >
                        <Clapperboard aria-hidden />
                        {t('studio.video.listing')}
                    </Button>
                </div>
            )}
            <p className="text-sm text-muted-foreground">
                {t('studio.video.cost', {
                    credits: data.video_cost,
                    left: data.credits_left,
                })}
            </p>
            {data.videos.length === 0 ? (
                <EmptyState
                    icon={Clapperboard}
                    title={t('studio.video.none')}
                    description={t('studio.video.noneHint')}
                />
            ) : (
                <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {data.videos.map((v) => (
                        <li
                            key={v.id}
                            className="space-y-3 rounded-reos-lg border border-border p-4"
                        >
                            <div className="mx-auto aspect-[9/16] w-full max-w-56 overflow-hidden rounded-reos-md bg-muted">
                                {v.status === 'ready' && v.video ? (
                                    <video
                                        className="h-full w-full object-cover"
                                        src={v.video.url}
                                        poster={v.poster?.url}
                                        controls
                                        playsInline
                                        aria-label={t('studio.video.play')}
                                    />
                                ) : v.poster ? (
                                    <img
                                        className="h-full w-full object-cover"
                                        src={v.poster.url}
                                        alt={t('studio.video.play')}
                                    />
                                ) : null}
                            </div>
                            <p className="text-base font-semibold">
                                {v.source.label ?? v.title}
                            </p>
                            <p
                                className="text-sm text-muted-foreground"
                                aria-live="polite"
                            >
                                {v.status === 'rendering'
                                    ? t('studio.video.rendering')
                                    : v.status === 'failed'
                                      ? (v.error ?? t('studio.video.failed'))
                                      : t('studio.video.ready', {
                                            seconds: v.seconds ?? 0,
                                        })}
                            </p>
                            {v.status === 'ready' && v.video ? (
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    disabled={busy}
                                    onClick={() => onUse(v)}
                                >
                                    {t('studio.video.use')}
                                    <ArrowRight aria-hidden />
                                </Button>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
