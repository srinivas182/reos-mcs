<?php

declare(strict_types=1);

namespace App\Studio;

use App\Mls\MyListings;
use App\Models\Deal;
use App\Models\StudioGeneration;
use App\Models\User;
use App\Services\Ai\AiCredits;
use Illuminate\Support\Facades\URL;

/**
 * The content studio's rows (B12.1, ADR 0050): what an agent can start
 * from, what they made, and links to the designs.
 *
 * @phpstan-type GenerationRow array{id: string, source: array{type: string, id: string|null, label: string|null}, listing: string|null, outputs: list<string>, captions: list<array{tone: string, text: string}>, designs: list<array{layout: string, path: string, url: string}>, carousel: list<array{path: string, url: string}>, ad: array{headlines: list<string>, primary: list<string>, description: string}|null, credits: int, engine: string, created_at: string|null}
 */
final readonly class Studio
{
    public const LINK_HOURS = 48;

    public function __construct(private MyListings $listings, private AiCredits $credits, private StudioGenerator $generator) {}

    /**
     * @return array{cost: int, credits_left: int, listings: list<array{id: string, address: string, status: string, photo: string|null}>, sales: list<array{id: string, property: string}>, generations: list<GenerationRow>}
     */
    public function home(User $agent): array
    {
        $listings = [];

        foreach ($this->listings->overview($agent)['listings'] as $l) {
            if ($l['can_post']) {
                $listings[] = ['id' => $l['uuid'], 'address' => $l['address'], 'status' => $l['status'], 'photo' => $l['photo']['url'] ?? null];
            }
        }

        $generations = [];

        foreach (StudioGeneration::query()->where('user_id', $agent->id)->latest('id')->limit(10)->get() as $g) {
            $generations[] = self::row($g);
        }

        return [
            'cost' => $this->generator->cost(),
            'credits_left' => $this->credits->left($agent),
            'listings' => $listings,
            'sales' => array_values(Deal::query()->where('user_id', $agent->id)->where('stage', 'closed')->latest('closed_at')->limit(20)->get()
                ->map(static fn (Deal $d): array => ['id' => $d->uuid, 'property' => $d->property])->all()),
            'generations' => $generations,
        ];
    }

    public function own(User $agent, string $uuid): StudioGeneration
    {
        return StudioGeneration::query()->where('user_id', $agent->id)->where('uuid', $uuid)->with('user')->firstOrFail();
    }

    /** @return GenerationRow */
    public static function row(StudioGeneration $g): array
    {
        $link = static fn (string $path): string => URL::temporarySignedRoute('social.media', now()->addHours(self::LINK_HOURS), ['path' => $path]);

        return [
            'id' => $g->uuid,
            'source' => ['type' => $g->source['type'], 'id' => $g->source['id'] ?? null, 'label' => $g->source['label'] ?? null],
            'listing' => $g->listing,
            'outputs' => $g->outputs,
            'captions' => $g->captions,
            'designs' => array_map(static fn (array $d): array => [...$d, 'url' => $link($d['path'])], $g->designs),
            'carousel' => array_map(static fn (string $p): array => ['path' => $p, 'url' => $link($p)], $g->carousel),
            'ad' => $g->ad,
            'credits' => $g->credits,
            'engine' => $g->engine,
            'created_at' => $g->created_at?->toIso8601String(),
        ];
    }
}
