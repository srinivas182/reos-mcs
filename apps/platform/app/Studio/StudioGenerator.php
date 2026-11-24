<?php

declare(strict_types=1);

namespace App\Studio;

use App\Mls\ListingPosts;
use App\Models\Deal;
use App\Models\MlsListing;
use App\Models\StudioGeneration;
use App\Models\User;
use App\Services\Ai\AiCredits;
use App\Services\Ai\AiSettings;
use App\Services\Ai\Bedrock;
use App\Social\SocialPosts;
use App\Tenancy\Brand;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The content studio's maker (B12.1, ADR 0050; agent screens 165, 166 and
 * 107, web screen 17).
 *
 * From a listing, a sale, a market update or the agent's own words (typed
 * or spoken), it makes what the agent asked for:
 *
 * - **post:** three caption versions (professional, warm, short) with
 *   hashtags, and three designs (PNG, 1080 × 1080) in the brand colour;
 * - **carousel:** up to five slides from the listing's photos, then the
 *   agent's card;
 * - **ad:** three headlines, three primary texts and a description.
 *
 * Claude writes the words when it's on; otherwise REOS's templates do.
 * Every version is checked (fair housing, claims, the brokerage's banned
 * phrases) before it's shown. One making costs `content_post` credits
 * (5 by default), shown first; changing a caption's tone is free.
 *
 * @phpstan-type Source array{type: 'listing'|'sale'|'market'|'text', id: string|null, text: string|null}
 * @phpstan-type Facts array{type: string, headline: string, address: string|null, city: string|null, price: string|null, beds: string|null, baths: string|null, text: string|null, photos: list<string>, listing: string|null}
 */
final readonly class StudioGenerator
{
    public const OUTPUTS = ['post', 'carousel', 'ad'];

    public const TONES = ['professional', 'warm', 'short'];

    /** REOS's navy, when the brokerage hasn't set a colour. */
    public const COLOUR = '#1f3a5f';

    public function __construct(
        private ListingPosts $listings,
        private AiCredits $credits,
        private AiSettings $settings,
        private Bedrock $bedrock,
        private AdvertisingPolicy $policy,
    ) {}

    public function cost(): int
    {
        return max(0, $this->settings->cost('content_post'));
    }

    /**
     * @param  Source  $source
     * @param  list<string>  $outputs
     */
    public function make(User $agent, array $source, array $outputs): StudioGeneration
    {
        $cost = $this->cost();

        if (! $this->credits->canSpend($agent, $cost)) {
            throw ValidationException::withMessages(['outputs' => self::line('studio.errors.credits', ['credits' => (string) $cost])]);
        }

        $facts = $this->facts($agent, $source);
        $language = $agent->locale->value;
        $attribution = $this->attribution($agent);
        $words = null;
        $engine = 'simulator';
        $bedrock = $this->bedrock->client(40);

        if ($bedrock !== null) {
            try {
                $words = (new ClaudeStudioWriter($bedrock['client'], $bedrock['model']))->write($facts, $language);
                $engine = 'ai';
            } catch (Throwable $e) {
                Log::warning('studio.write_failed', ['error' => $e->getMessage()]);
            }
        }

        $words ??= ['captions' => array_map(fn (string $tone): array => ['tone' => $tone, 'text' => $this->template($facts, $tone, $language)], self::TONES), 'ad' => $this->adTemplate($facts, $language)];
        $uuid = (string) Str::uuid7();
        $brokerage = $this->policy->brokerage($agent);
        $colour = ($brokerage === null ? null : Brand::of($brokerage)->primary?->hex) ?? self::COLOUR;
        $designs = [];
        $carousel = [];

        if (in_array('post', $outputs, true)) {
            foreach (StudioRenderer::LAYOUTS as $i => $layout) {
                $path = "social/{$agent->id}/studio/{$uuid}-{$layout}.png";
                Storage::disk(SocialPosts::MEDIA_DISK)->put($path, StudioRenderer::design($layout, $facts['photos'][$i % max(1, count($facts['photos']))] ?? null, $colour, $this->text($facts, $attribution, $language)));
                $designs[] = ['layout' => $layout, 'path' => $path];
            }
        }

        if (in_array('carousel', $outputs, true)) {
            foreach (array_slice($facts['photos'], 0, 5) as $i => $photo) {
                $path = "social/{$agent->id}/studio/{$uuid}-slide-".($i + 1).'.png';
                Storage::disk(SocialPosts::MEDIA_DISK)->put($path, StudioRenderer::design('band', $photo, $colour, ['headline' => $i === 0 ? $facts['headline'] : '', 'lines' => $i === 0 ? array_values(array_filter([$facts['address'], $facts['price']])) : [], 'attribution' => $attribution]));
                $carousel[] = $path;
            }
            $path = "social/{$agent->id}/studio/{$uuid}-slide-card.png";
            Storage::disk(SocialPosts::MEDIA_DISK)->put($path, StudioRenderer::design('split', null, $colour, ['headline' => self::line('studio.card.headline', [], $language), 'lines' => [self::line('studio.card.line', [], $language)], 'attribution' => $attribution]));
            $carousel[] = $path;
        }

        $generation = StudioGeneration::query()->create([
            'uuid' => $uuid, 'user_id' => $agent->id, 'source' => [...$source, 'label' => $facts['address'] ?? $facts['city'] ?? Str::limit((string) $facts['text'], 60)],
            'listing' => $facts['listing'], 'outputs' => array_values(array_intersect(self::OUTPUTS, $outputs)),
            'captions' => array_map(fn (array $c): array => ['tone' => $c['tone'], 'text' => $this->clean($agent, $c['text'])], $words['captions']),
            'designs' => $designs, 'carousel' => $carousel, 'ad' => in_array('ad', $outputs, true) ? $this->cleanAd($agent, $words['ad']) : null,
            'language' => $language, 'engine' => $engine, 'credits' => $cost,
        ]);
        $this->credits->charge($agent, 'content_post', $engine, $cost);

        return $generation;
    }

    /** A caption again, shorter, more professional or in Spanish (free; screen 166). */
    public function retone(StudioGeneration $generation, int $index, string $tone): StudioGeneration
    {
        $captions = $generation->captions;
        $current = $captions[$index] ?? throw ValidationException::withMessages(['index' => self::line('studio.errors.caption')]);
        $facts = $this->facts($generation->user, $generation->source);
        $language = $tone === 'spanish' ? 'es' : $generation->language;
        $text = match ($tone) {
            'shorter' => $this->shorter($current['text']),
            'professional' => $this->template($facts, 'professional', $language),
            default => $this->template($facts, $current['tone'], 'es'),
        };
        $bedrock = $this->bedrock->client(20);

        if ($bedrock !== null) {
            try {
                $text = (new ClaudeStudioWriter($bedrock['client'], $bedrock['model']))->retone($current['text'], $tone, $language);
            } catch (Throwable $e) {
                Log::warning('studio.retone_failed', ['error' => $e->getMessage()]);
            }
        }

        $captions[$index] = ['tone' => $current['tone'], 'text' => $this->clean($generation->user, $text)];
        $generation->forceFill(['captions' => $captions])->save();

        return $generation;
    }

    /**
     * What a source says, for words and designs.
     *
     * @param  array<string, mixed>  $source
     * @return Facts
     */
    public function facts(User $agent, array $source): array
    {
        $type = is_string($source['type'] ?? null) ? $source['type'] : 'text';
        $id = is_string($source['id'] ?? null) ? $source['id'] : null;
        $text = is_string($source['text'] ?? null) ? trim($source['text']) : null;
        $money = static fn (?int $n): ?string => $n === null ? null : '$'.number_format($n);

        if ($type === 'listing' && $id !== null) {
            $draft = $this->listings->draft($agent, $id);
            $listing = MlsListing::query()->where('uuid', $id)->firstOrFail();

            return [
                'type' => 'listing', 'headline' => self::line($listing->status === 'coming_soon' ? 'studio.headlines.coming_soon' : 'studio.headlines.listing', [], $agent->locale->value),
                'address' => $draft['address'], 'city' => $listing->city, 'price' => $money($listing->list_price), 'beds' => $listing->bedrooms === null ? null : (string) $listing->bedrooms,
                'baths' => $listing->bathrooms, 'text' => $text, 'photos' => array_slice(array_column($draft['photos'], 'url'), 0, $draft['max_photos'] > 0 ? $draft['max_photos'] : 5), 'listing' => $id,
            ];
        }

        if ($type === 'sale' && $id !== null) {
            $deal = Deal::query()->where('user_id', $agent->id)->where('uuid', $id)->where('stage', 'closed')->first()
                ?? throw ValidationException::withMessages(['source' => self::line('studio.errors.sale')]);

            // A sale names the property, never the clients.
            return ['type' => 'sale', 'headline' => self::line('studio.headlines.sale', [], $agent->locale->value), 'address' => $deal->property, 'city' => $deal->city, 'price' => null, 'beds' => null, 'baths' => null, 'text' => $text, 'photos' => [], 'listing' => null];
        }

        if ($text === null || mb_strlen($text) < 3) {
            throw ValidationException::withMessages(['source' => self::line('studio.errors.text')]);
        }

        return [
            'type' => $type === 'market' ? 'market' : 'text', 'headline' => self::line($type === 'market' ? 'studio.headlines.market' : 'studio.headlines.text', [], $agent->locale->value),
            'address' => null, 'city' => is_string($source['city'] ?? null) ? $source['city'] : null, 'price' => null, 'beds' => null, 'baths' => null, 'text' => mb_substr($text, 0, 1000), 'photos' => [], 'listing' => null,
        ];
    }

    /** @param  Facts  $facts */
    private function template(array $facts, string $tone, string $language): string
    {
        $replace = ['address' => $facts['address'] ?? '', 'city' => $facts['city'] ?? '', 'price' => $facts['price'] ?? '', 'beds' => $facts['beds'] ?? '', 'baths' => $facts['baths'] ?? '', 'text' => $facts['text'] ?? '', 'tag' => Str::studly((string) ($facts['city'] ?? 'Texas'))];
        $line = self::line("studio.captions.{$facts['type']}.{$tone}", $replace, $language);

        return trim((string) preg_replace('/\s{2,}/u', ' ', $line));
    }

    /**
     * @param  Facts  $facts
     * @return array{headlines: list<string>, primary: list<string>, description: string}
     */
    private function adTemplate(array $facts, string $language): array
    {
        $replace = ['address' => $facts['address'] ?? ($facts['city'] ?? ''), 'price' => $facts['price'] ?? '', 'city' => $facts['city'] ?? ''];

        return [
            'headlines' => array_map(static fn (int $n): string => self::line("studio.ad.headline{$n}.{$facts['type']}", $replace, $language), [1, 2, 3]),
            'primary' => array_map(fn (string $tone): string => $this->template($facts, $tone, $language), self::TONES),
            'description' => self::line('studio.ad.description', $replace, $language),
        ];
    }

    /**
     * @param  Facts  $facts
     * @return array{headline: string, lines: list<string>, attribution: string}
     */
    private function text(array $facts, string $attribution, string $language): array
    {
        $details = array_values(array_filter([
            $facts['beds'] !== null ? self::line('studio.beds', ['beds' => $facts['beds'], 'baths' => $facts['baths'] ?? '—'], $language) : null,
            $facts['price'],
        ]));

        return [
            'headline' => $facts['headline'],
            'lines' => array_values(array_filter([$facts['address'] ?? $facts['city'] ?? Str::limit((string) $facts['text'], 48), $details === [] ? null : implode(' · ', $details)])),
            'attribution' => $attribution,
        ];
    }

    private function attribution(User $agent): string
    {
        $brokerage = $this->policy->brokerage($agent);

        return trim((string) $agent->name).($brokerage !== null ? ' · '.$brokerage->name : '');
    }

    private function shorter(string $text): string
    {
        preg_match_all('/#[\p{L}\p{N}_]+/u', $text, $tags);
        $first = preg_split('/(?<=[.!?])\s+/u', trim((string) preg_replace('/#[\p{L}\p{N}_]+/u', '', $text))) ?: [$text];

        return trim($first[0].' '.implode(' ', array_slice($tags[0], 0, 3)));
    }

    /** Flagged wording swapped before anything is shown. */
    private function clean(User $agent, string $text): string
    {
        return $this->policy->fix($agent, $text);
    }

    /**
     * @param  array{headlines: list<string>, primary: list<string>, description: string}  $ad
     * @return array{headlines: list<string>, primary: list<string>, description: string}
     */
    private function cleanAd(User $agent, array $ad): array
    {
        return [
            'headlines' => array_map(fn (string $s): string => $this->clean($agent, $s), $ad['headlines']),
            'primary' => array_map(fn (string $s): string => $this->clean($agent, $s), $ad['primary']),
            'description' => $this->clean($agent, $ad['description']),
        ];
    }

    /** @param  array<string, string>  $replace */
    private static function line(string $key, array $replace = [], ?string $language = null): string
    {
        $line = __($key, $replace, $language);

        return is_string($line) ? $line : $key;
    }
}
