<?php

declare(strict_types=1);

namespace App\Video;

use App\Models\User;
use App\Models\VideoSession;
use App\Models\VideoSessionParticipant;
use App\Models\VideoSessionShare;

/**
 * A session as the web and the app show it (B6.3).
 *
 * @phpstan-type SessionData array{id: string, kind: 'one_on_one'|'live_class', title: string, agenda: string|null, starts_at: string, ends_at: string, join_opens_at: string, status: 'scheduled'|'live'|'ended'|'cancelled', host: array{id: int, name: string}, people: list<array{id: int, name: string, role: 'host'|'guest', joined: bool}>, me: 'host'|'guest', can_join: bool, notes: string|null, notes_updated_at: string|null, notes_updated_by: string|null, whiteboard: array{image_url: string|null, updated_at: string|null}, shares: list<ShareData>}
 *
 * @phpstan-import-type ShareData from SessionShares
 */
final class SessionPresenter
{
    /** @return SessionData */
    public static function present(VideoSession $session, User $viewer): array
    {
        $me = VideoSessionParticipant::GUEST;
        $people = [];

        foreach ($session->participants as $participant) {
            if ($participant->user_id === $viewer->id) {
                $me = $participant->role === VideoSessionParticipant::HOST ? VideoSessionParticipant::HOST : VideoSessionParticipant::GUEST;
            }

            $people[] = [
                'id' => $participant->user_id,
                'name' => $participant->user->name ?? $participant->user->email ?? '',
                'role' => $participant->role === VideoSessionParticipant::HOST ? VideoSessionParticipant::HOST : VideoSessionParticipant::GUEST,
                'joined' => $participant->first_joined_at !== null,
            ];
        }

        $editor = $session->notes_updated_by === null ? null : $session->participants->firstWhere('user_id', $session->notes_updated_by)?->user;

        /** @var SessionData */
        return [
            'id' => $session->uuid,
            'kind' => $session->kind,
            'title' => $session->title,
            'agenda' => $session->agenda,
            'starts_at' => $session->starts_at->toIso8601String(),
            'ends_at' => $session->ends_at->toIso8601String(),
            'join_opens_at' => $session->joinOpensAt()->toIso8601String(),
            'status' => $session->status,
            'host' => ['id' => $session->host_id, 'name' => $session->host->name ?? ''],
            'people' => $people,
            'me' => $me,
            'can_join' => $session->open(),
            'notes' => $session->notes,
            'notes_updated_at' => $session->notes_updated_at?->toIso8601String(),
            'notes_updated_by' => $editor?->name,
            // The shared whiteboard (B6.3b): a picture for the phone and the notes.
            'whiteboard' => ['image_url' => SessionBoard::imageUrl($session), 'updated_at' => $session->whiteboard_updated_at?->toIso8601String()],
            // Lessons and practice questions the host shared (B6.3b), oldest first.
            'shares' => array_values($session->shares()->with('user')->orderBy('id')->get()->map(static fn (VideoSessionShare $share): array => SessionShares::present($share, app()->getLocale()))->all()),
        ];
    }
}
