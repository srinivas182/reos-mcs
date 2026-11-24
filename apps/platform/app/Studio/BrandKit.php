<?php

declare(strict_types=1);

namespace App\Studio;

use App\Models\AgentProfile;
use App\Models\Licence;
use App\Models\User;
use App\Social\SocialPosts;
use App\Tenancy\Brand;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Profile photo and brand kit (B12.3, ADR 0052; agent screen 162).
 *
 * The agent's photo goes on their celebration designs. It's cropped
 * square (a little above the middle, where faces are), sized to 800 ×
 * 800, and its lighting evened out; the quality check says when it's too
 * small, too dark or too bright. The brand kit (colour, logo) is the
 * brokerage's, set on the web; the brokerage's name is required and goes
 * on every design at the size TREC requires.
 *
 * @phpstan-type Check array{width: int, height: int, brightness: int, issues: list<string>}
 * @phpstan-type Kit array{photo: array{path: string, url: string}|null, check: Check|null, name: string, brokerage: string|null, licence: string|null, colour: string, logo: string|null}
 */
final readonly class BrandKit
{
    public const SIZE = 800;

    public const MIN_SIZE = 400;

    public function __construct(private AdvertisingPolicy $policy) {}

    /** @return Kit */
    public function show(User $agent): array
    {
        $profile = AgentProfile::query()->where('user_id', $agent->id)->first();
        $brokerage = $this->policy->brokerage($agent);
        $brand = $brokerage === null ? null : Brand::of($brokerage);
        $path = $profile?->photo_path;
        $licence = Licence::query()->where('user_id', $agent->id)->orderByDesc('licensed_on')->value('number');

        return [
            'photo' => $path === null ? null : ['path' => $path, 'url' => URL::temporarySignedRoute('social.media', now()->addHours(SocialPosts::MEDIA_HOURS), ['path' => $path])],
            'check' => $profile?->photo_check,
            'name' => (string) $agent->name,
            'brokerage' => $brokerage?->name,
            'licence' => is_string($licence) ? $licence : null,
            'colour' => $brand?->primary->hex ?? StudioGenerator::COLOUR,
            'logo' => $brand === null ? null : (is_string($logo = $brand->toArray()['logo_url'] ?? null) ? $logo : null),
        ];
    }

    /**
     * A new profile photo: checked, squared and evened out.
     *
     * @return Kit
     */
    public function upload(User $agent, UploadedFile $file): array
    {
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages(['photo' => __('studio.brand.errors.photo')]);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $brightness = self::brightness($image);
        $issues = [];

        if (min($width, $height) < self::MIN_SIZE) {
            $issues[] = 'small';
        }

        if ($brightness < 70) {
            $issues[] = 'dark';
        } elseif ($brightness > 200) {
            $issues[] = 'bright';
        }

        // Square, a little above the middle (where faces are).
        $side = min($width, $height);
        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled($square, $image, 0, 0, (int) (($width - $side) / 2), (int) max(0, ($height - $side) * 0.3), self::SIZE, self::SIZE, $side, $side);

        // Even out the lighting: halfway to a middle grey.
        $shift = (int) round((128 - $brightness) * 0.5);

        if (abs($shift) > 4) {
            imagefilter($square, IMG_FILTER_BRIGHTNESS, max(-60, min(60, $shift)));
        }

        $profile = AgentProfile::query()->firstOrCreate(['user_id' => $agent->id]);
        $old = $profile->photo_path;
        $path = "social/{$agent->id}/studio/profile-".Str::lower(Str::random(12)).'.jpg';
        ob_start();
        imagejpeg($square, null, 88);
        Storage::disk(SocialPosts::MEDIA_DISK)->put($path, (string) ob_get_clean());
        $profile->forceFill(['photo_path' => $path, 'photo_check' => ['width' => $width, 'height' => $height, 'brightness' => $brightness, 'issues' => $issues]])->save();

        if ($old !== null) {
            Storage::disk(SocialPosts::MEDIA_DISK)->delete($old);
        }

        return $this->show($agent);
    }

    /** @return Kit */
    public function remove(User $agent): array
    {
        $profile = AgentProfile::query()->where('user_id', $agent->id)->first();

        if ($profile?->photo_path !== null) {
            Storage::disk(SocialPosts::MEDIA_DISK)->delete($profile->photo_path);
            $profile->forceFill(['photo_path' => null, 'photo_check' => null])->save();
        }

        return $this->show($agent);
    }

    public function photo(User $agent): ?string
    {
        $path = AgentProfile::query()->where('user_id', $agent->id)->value('photo_path');

        return is_string($path) ? $path : null;
    }

    /** Average brightness (0–255) from a grid of samples. */
    private static function brightness(GdImage $image): int
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $sum = 0;
        $n = 0;

        for ($y = 0; $y < 20; $y++) {
            for ($x = 0; $x < 20; $x++) {
                $rgb = (int) imagecolorat($image, (int) (($x + 0.5) * $w / 20), (int) (($y + 0.5) * $h / 20));
                $sum += (int) round(0.299 * (($rgb >> 16) & 0xFF) + 0.587 * (($rgb >> 8) & 0xFF) + 0.114 * ($rgb & 0xFF));
                $n++;
            }
        }

        return intdiv($sum, max(1, $n));
    }
}
