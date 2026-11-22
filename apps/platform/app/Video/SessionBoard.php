<?php

declare(strict_types=1);

namespace App\Video;

use App\Models\User;
use App\Models\VideoSession;
use App\Services\Content\LessonFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * The session's shared whiteboard (B6.3b, ADR 0024): Excalidraw on the web,
 * drawn together live through the room's data channel and saved with the
 * session (the drawing, and a picture of it for the phone and the notes).
 */
final readonly class SessionBoard
{
    /** The longest drawing kept (characters of JSON). */
    public const MAX_JSON = 2_000_000;

    public const LINK_MINUTES = 30;

    public function __construct(private VideoSessions $sessions) {}

    /** @return array{elements: list<mixed>, updated_at: string|null} */
    public function load(VideoSession $session, User $user): array
    {
        $this->sessions->ensureParticipant($session, $user);
        $elements = $session->whiteboard === null ? [] : json_decode($session->whiteboard, true);

        return ['elements' => is_array($elements) ? array_values($elements) : [], 'updated_at' => $session->whiteboard_updated_at?->toIso8601String()];
    }

    /** @param  list<mixed>  $elements */
    public function save(VideoSession $session, User $user, array $elements, ?UploadedFile $image): void
    {
        $this->sessions->ensureParticipant($session, $user);

        if ($session->status === VideoSession::CANCELLED) {
            throw ValidationException::withMessages(['elements' => __('sessions.errors.cancelled')]);
        }

        $json = json_encode($elements, JSON_THROW_ON_ERROR);

        if (strlen($json) > self::MAX_JSON) {
            throw ValidationException::withMessages(['elements' => __('sessions.whiteboard.tooBig')]);
        }

        $path = $session->whiteboard_image;

        if ($image !== null) {
            $path = 'sessions/'.$session->uuid.'/whiteboard.png';
            LessonFiles::disk()->putFileAs(dirname($path), $image, basename($path));
        }

        $session->forceFill(['whiteboard' => $json, 'whiteboard_image' => $path, 'whiteboard_updated_at' => now()])->save();
    }

    /** A 30-minute link to the picture of the board, for the people in the session. */
    public static function imageUrl(VideoSession $session): ?string
    {
        if ($session->whiteboard_image === null) {
            return null;
        }

        $disk = LessonFiles::disk();
        $version = $session->whiteboard_updated_at?->getTimestamp() ?? 0;

        if ($disk->getAdapter() instanceof LocalFilesystemAdapter) {
            return URL::temporarySignedRoute('sessions.whiteboard.image', now()->addMinutes(self::LINK_MINUTES), ['session' => $session->uuid, 'v' => $version]);
        }

        return $disk->temporaryUrl($session->whiteboard_image, now()->addMinutes(self::LINK_MINUTES), ['ResponseContentType' => 'image/png']);
    }
}
