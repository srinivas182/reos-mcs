<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\VideoSession;
use App\Video\LiveKit;
use App\Video\SessionRecordings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `POST /webhooks/livekit` (B6.3c): who is in each room (participant joined
 * or left) and recordings LiveKit finished writing. Signed by LiveKit with
 * the API secret.
 */
final class LiveKitWebhookController extends Controller
{
    public function __invoke(Request $request, LiveKit $livekit, SessionRecordings $recordings): JsonResponse
    {
        $body = $request->getContent();

        if (! $livekit->verifyWebhook($body, $request->header('Authorization'))) {
            return response()->json(['received' => false], 403);
        }

        $event = json_decode($body, true);
        $event = is_array($event) ? $event : [];
        $name = $event['event'] ?? null;

        if ($name === 'participant_joined' || $name === 'participant_left') {
            $this->presence($event, $name === 'participant_joined');
        }

        if ($name === 'egress_ended' && is_array($event['egressInfo'] ?? null)) {
            $info = $event['egressInfo'];
            $files = $info['fileResults'] ?? (isset($info['file']) ? [$info['file']] : []);
            $file = is_array($files) && is_array($files[0] ?? null) ? $files[0] : null;
            $id = $info['egressId'] ?? null;

            if (is_string($id)) {
                /** @var array{filename?: string, size?: int|string, duration?: int|string}|null $file */
                $recordings->finished($id, ($info['status'] ?? null) === 'EGRESS_COMPLETE', $file);
            }
        }

        return response()->json(['received' => true]);
    }

    /** @param  array<mixed>  $event */
    private function presence(array $event, bool $joined): void
    {
        $room = is_array($event['room'] ?? null) ? ($event['room']['name'] ?? null) : null;
        $identity = is_array($event['participant'] ?? null) ? ($event['participant']['identity'] ?? null) : null;

        if (! is_string($room) || ! is_string($identity) || ! str_starts_with($room, 'reos-') || preg_match('/^user-(\d+)$/', $identity, $m) !== 1) {
            return;
        }

        $session = VideoSession::query()->where('uuid', substr($room, 5))->first();
        $session?->participants()->where('user_id', (int) $m[1])->update(['in_room' => $joined]);
    }
}
