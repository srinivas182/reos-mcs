<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Studio;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Studio\BrandKit;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/** Profile photo and brand kit (B12.3, ADR 0052; agent screen 162). */
final class BrandKitController extends Controller
{
    /**
     * My photo and brand.
     *
     * My profile photo (with its quality check), name, brokerage, license number and the brokerage's brand color and logo.
     *
     * @response array{data: array{photo: array{path: string, url: string}|null, check: array{width: int, height: int, brightness: int, issues: list<'small'|'dark'|'bright'>}|null, name: string, brokerage: string|null, licence: string|null, colour: string, logo: string|null}}
     */
    public function show(#[CurrentUser] User $user, BrandKit $kit): JsonResponse
    {
        return response()->json(['data' => $kit->show($user)]);
    }

    /**
     * A new profile photo.
     *
     * Squared, sized and its lighting evened out; the check says when it's too small, too dark or too bright.
     *
     * @response array{data: array{photo: array{path: string, url: string}|null, check: array{width: int, height: int, brightness: int, issues: list<'small'|'dark'|'bright'>}|null, name: string, brokerage: string|null, licence: string|null, colour: string, logo: string|null}}
     */
    public function photo(Request $request, #[CurrentUser] User $user, BrandKit $kit): JsonResponse
    {
        $request->validate(['photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360']]);
        $file = $request->file('photo');
        abort_unless($file instanceof UploadedFile, 422);

        return response()->json(['data' => $kit->upload($user, $file)]);
    }

    /**
     * Remove my profile photo.
     *
     * @response array{data: array{photo: array{path: string, url: string}|null, check: array{width: int, height: int, brightness: int, issues: list<'small'|'dark'|'bright'>}|null, name: string, brokerage: string|null, licence: string|null, colour: string, logo: string|null}}
     */
    public function removePhoto(#[CurrentUser] User $user, BrandKit $kit): JsonResponse
    {
        return response()->json(['data' => $kit->remove($user)]);
    }
}
