<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Certificates;

use App\Http\Controllers\Controller;
use App\Http\Requests\Certificates\ReadCertificateRequest;
use App\Http\Requests\Certificates\SaveCertificateRequest;
use App\Licences\Certificates\Certificates;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Certificates for CE and courses taken elsewhere (B10.3b, ADR 0043): read
 * by OCR, checked by me, saved as a completion with the certificate.
 */
final class CertificateController extends Controller
{
    /**
     * Read a certificate.
     *
     * An image or a one-page PDF (`file`, up to 10 MB). Returns what was read (name, course, hours, date, provider, each with its confidence) for me to check; nothing is saved as a completion yet. `reader` is null when no reader is connected: type the details.
     *
     * @response array{data: array{upload: string, reader: string|null, name: string|null, course: string|null, hours: float|null, completed_on: string|null, provider: string|null, confidence: array<string, float>, name_matches: bool|null, states: list<string>}}
     */
    public function read(ReadCertificateRequest $request, #[CurrentUser] User $user, Certificates $certificates): JsonResponse
    {
        return response()->json(['data' => $certificates->read($user, $request->certificate())]);
    }

    /**
     * Save a checked certificate.
     *
     * Records the completion for one of my licence states, with its certificate.
     *
     * @response 201 array{data: array{id: int, state: string, title: string, hours: float, completed_on: string, provider: string|null, read_by: string|null}}
     */
    public function store(SaveCertificateRequest $request, #[CurrentUser] User $user, Certificates $certificates): JsonResponse
    {
        $completion = $certificates->save($user, $request->string('upload')->toString(), $request->values());

        return response()->json(['data' => [
            'id' => $completion->id,
            'state' => $completion->state_code,
            'title' => $completion->title,
            'hours' => (float) $completion->hours,
            'completed_on' => $completion->completed_on->toDateString(),
            'provider' => $completion->provider,
            'read_by' => $completion->read_by,
        ]], 201);
    }
}
