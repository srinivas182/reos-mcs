<?php

declare(strict_types=1);

namespace App\Licences\Certificates;

use App\Jobs\ScanCertificate;
use App\Models\CeCompletion;
use App\Models\CertificateRead;
use App\Models\Licence;
use App\Models\User;
use App\Services\Content\LessonFiles;
use App\Services\Licences\Licences;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Certificates for CE and courses taken elsewhere (B10.3b, ADR 0043):
 *
 * 1. **Read:** the upload (an image or a one-page PDF) is kept privately and
 *    virus-scanned; the reader fills in name, course, hours, date and
 *    provider, each with its confidence. Nothing is saved as a completion yet.
 * 2. **Review and save:** the person checks and corrects the fields; the
 *    completion is recorded for one of their licence states with its
 *    certificate. A name that doesn't match the account is pointed out.
 *
 * Reads not saved within a day are deleted with their file.
 *
 * @phpstan-type ReadResult array{upload: string, reader: string|null, name: string|null, course: string|null, hours: float|null, completed_on: string|null, provider: string|null, confidence: array<string, float>, name_matches: bool|null, states: list<string>}
 */
final readonly class Certificates
{
    public function __construct(private CertificateReaders $readers) {}

    /** @return ReadResult */
    public function read(User $user, UploadedFile $file): array
    {
        $states = $this->states($user);

        if ($states === []) {
            throw ValidationException::withMessages(['file' => __('certificates.errors.noLicence')]);
        }

        $bytes = (string) $file->get();
        $mime = (string) $file->getMimeType();
        $extension = match ($mime) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            default => 'jpg',
        };
        $uuid = (string) Str::uuid7();
        $path = "certificates/{$user->id}/{$uuid}.{$extension}";
        LessonFiles::disk()->put($path, $bytes);

        $reader = $this->readers->for($file->getClientOriginalName());
        $fields = null;

        try {
            $fields = $reader?->read($bytes, $mime, $user->name);
        } catch (CertificateUnreadable) {
            $reader = null;
        }

        $fields ??= new CertificateFields(null, null, null, null, null);
        CertificateRead::query()->create(['uuid' => $uuid, 'user_id' => $user->id, 'path' => $path, 'mime' => $mime, 'reader' => $reader?->id(), 'fields' => $fields->toArray()]);
        ScanCertificate::dispatch($path);

        return [
            'upload' => $uuid,
            'reader' => $reader?->id(),
            ...$fields->toArray(),
            'name_matches' => $fields->name === null ? null : Licences::sameName($fields->name, $user->name),
            'states' => $states,
        ];
    }

    /**
     * @param  array{state: string, title: string, hours: float, completed_on: string, provider: string|null}  $values
     */
    public function save(User $user, string $upload, array $values): CeCompletion
    {
        $read = CertificateRead::query()->where('uuid', $upload)->where('user_id', $user->id)->firstOrFail();

        if ($read->scan_status === 'infected') {
            throw ValidationException::withMessages(['upload' => __('certificates.errors.infected')]);
        }

        if (! in_array(strtoupper($values['state']), $this->states($user), true)) {
            throw ValidationException::withMessages(['state' => __('certificates.errors.state')]);
        }

        $completion = CeCompletion::query()->create([
            'user_id' => $user->id,
            'state_code' => strtoupper($values['state']),
            'title' => $values['title'],
            'course_code' => null,
            'hours' => $values['hours'],
            'provider' => $values['provider'],
            'source' => 'other_provider',
            'completed_on' => $values['completed_on'],
        ]);
        $completion->forceFill(['certificate_path' => $read->path, 'read_by' => $read->reader])->save();
        $read->delete();

        return $completion;
    }

    /** Reads left unsaved for a day go, with their files. */
    public function prune(): int
    {
        $stale = CertificateRead::query()->where('created_at', '<', now()->subDay())->get();

        foreach ($stale as $read) {
            LessonFiles::disk()->delete($read->path);
            $read->delete();
        }

        return $stale->count();
    }

    /** @return list<string> the states of the person's licences */
    private function states(User $user): array
    {
        $states = [];

        foreach (Licence::query()->where('user_id', $user->id)->orderBy('state_code')->get() as $licence) {
            $states[$licence->state_code] = $licence->state_code;
        }

        return array_values($states);
    }
}
