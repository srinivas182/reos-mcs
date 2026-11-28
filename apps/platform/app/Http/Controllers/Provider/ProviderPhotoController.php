<?php

declare(strict_types=1);

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\ProviderProfile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A provider's photo or logo (B15.3), through a link signed for a day. */
final class ProviderPhotoController extends Controller
{
    public function __invoke(int $profile, string $kind): StreamedResponse
    {
        $row = ProviderProfile::query()->findOrFail($profile);
        $path = $kind === 'logo' ? $row->logo_path : $row->photo_path;
        abort_if($path === null, 404);

        return Storage::disk(config()->string('reos.content.disk'))->response($path, null, ['Cache-Control' => 'private, max-age=3600']);
    }
}
