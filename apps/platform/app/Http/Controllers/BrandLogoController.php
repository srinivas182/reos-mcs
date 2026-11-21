<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Organisation;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * A tenant's logo (A4.2a). Public, because it shows on its sign-in page;
 * the address carries a version, so browsers can keep it for a year.
 */
final class BrandLogoController extends Controller
{
    public function __invoke(string $tenant): Response
    {
        $organisation = Organisation::query()->where('slug', $tenant)->first();
        $path = $organisation?->brand['logo_path'] ?? null;
        abort_unless(is_string($path) && Storage::exists($path), 404);

        return response((string) Storage::get($path), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
