<?php

declare(strict_types=1);

namespace App\Mls;

use App\Deals\DealAccess;
use App\Models\ListingPost;
use App\Models\MlsListing;
use App\Models\MlsMemberLink;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Listing posts (B10.2; done when a listing post uses MLS photos within the
 * licence terms). Only the agent's own listings (where the MLS says so),
 * only listings the public may see, never more photos than the MLS allows,
 * and the MLS's attribution added to every post. One post per listing,
 * changed until B11 publishes it.
 *
 * @phpstan-type PostDraft array{listing: string, address: string, photos: list<array{url: string, caption: string|null}>, max_photos: int, caption: string, chosen: list<string>, attribution: string, saved_at: string|null, sample: bool}
 */
final readonly class ListingPosts
{
    public function __construct(private ListingService $listings, private DealAccess $access) {}

    /** @return PostDraft */
    public function draft(User $user, string $uuid): array
    {
        [$listing, $view] = $this->postable($user, $uuid);
        $post = ListingPost::query()->where('user_id', $user->id)->where('integration', $listing->integration)->where('listing_key', $listing->listing_key)->first();
        $allowed = array_column($view['photos'], 'url');

        return [
            'listing' => $listing->uuid,
            'address' => $view['address'],
            'photos' => $view['photos'],
            'max_photos' => count($view['photos']),
            'caption' => $post->caption ?? $this->suggestion($view),
            // Photos the MLS has since withdrawn drop out.
            'chosen' => $post === null ? array_slice($allowed, 0, 5) : array_values(array_intersect($post->photos, $allowed)),
            'attribution' => $view['attribution'],
            'saved_at' => $post?->updated_at?->toIso8601String(),
            'sample' => $view['sample'],
        ];
    }

    /**
     * @param  list<string>  $photos  chosen photo URLs, in order
     * @return PostDraft
     */
    public function save(User $user, string $uuid, string $caption, array $photos): array
    {
        [$listing, $view] = $this->postable($user, $uuid);
        $allowed = array_column($view['photos'], 'url');
        $photos = array_values(array_unique($photos));

        if (array_diff($photos, $allowed) !== []) {
            throw ValidationException::withMessages(['photos' => __('listings.post.errors.photo')]);
        }

        if (count($photos) > count($allowed)) {
            throw ValidationException::withMessages(['photos' => __('listings.post.errors.tooMany', ['max' => count($allowed)])]);
        }

        $post = ListingPost::query()->firstOrNew(
            ['user_id' => $user->id, 'integration' => $listing->integration, 'listing_key' => $listing->listing_key],
            ['uuid' => (string) Str::uuid7()],
        );
        $post->fill(['caption' => $caption, 'photos' => $photos, 'attribution' => $view['attribution']])->save();

        return $this->draft($user, $uuid);
    }

    /**
     * The agent's own listing, as a post may show it.
     *
     * @return array{0: MlsListing, 1: array{address: string, photos: list<array{url: string, caption: string|null}>, attribution: string, sample: bool, price: int|null, beds: int|null, baths: float|null, area: int|null, city: string|null, status: string}}
     */
    private function postable(User $user, string $uuid): array
    {
        $this->access->allow($user);
        $listing = MlsListing::query()->where('uuid', $uuid)->firstOrFail();
        $own = MlsMemberLink::query()->where('user_id', $user->id)->where('status', MlsMemberLink::CONFIRMED)->where('integration', $listing->integration)
            ->whereIn('member_key', array_filter([$listing->list_agent_key, $listing->co_list_agent_key]))->exists();
        $view = $own ? $this->listings->view($listing, ListingPurpose::Post, true) : null;

        if ($view === null) {
            throw ValidationException::withMessages(['listing' => __($own ? 'listings.post.errors.notAllowed' : 'listings.post.errors.notYours')]);
        }

        return [$listing, $view];
    }

    /**
     * A first caption: status, price, address and the main facts.
     *
     * @param  array{address: string, price: int|null, beds: int|null, baths: float|null, area: int|null, city: string|null, status: string}  $view
     */
    private function suggestion(array $view): string
    {
        $facts = array_filter([
            $view['beds'] === null ? null : self::line('listings.post.beds', ['count' => $view['beds']]),
            $view['baths'] === null ? null : self::line('listings.post.baths', ['count' => rtrim(rtrim(number_format($view['baths'], 1), '0'), '.')]),
            $view['area'] === null ? null : self::line('listings.post.area', ['area' => number_format($view['area'])]),
        ]);

        return self::line('listings.post.suggestion.'.($view['status'] === 'coming_soon' ? 'comingSoon' : ($view['status'] === 'active' ? 'active' : 'contract')), [
            'address' => $view['address'],
            'city' => $view['city'] ?? '',
            'price' => $view['price'] === null ? '' : '$'.number_format($view['price']),
            'facts' => implode(' · ', $facts),
        ]);
    }

    /** @param  array<string, string|int>  $replace */
    private static function line(string $key, array $replace): string
    {
        $line = __($key, $replace);

        return is_string($line) ? $line : $key;
    }
}
