<?php

declare(strict_types=1);

namespace App\Features;

/**
 * Every feature flag the code checks (A6.2, ADR 0022). A flag exists because
 * code reads it, so the list lives here; its rollout lives in
 * `feature_flags` and is changed on Features and releases (screen 70). The
 * row is created at its starting stage the first time it is read.
 *
 * The six tenant features of screen 60 are flags too: their rollout decides
 * who can have them, and a tenant's own switch (or its type's default)
 * decides whether it does.
 */
final class FlagCatalogue
{
    /** @var array<string, array{name: string, description: string, owner: string, stage: RolloutStage, tenant: bool}> */
    public const FLAGS = [
        'virtual_broker' => ['name' => 'Virtual broker', 'description' => 'Answers agents\' questions with the brokerage\'s policies.', 'owner' => 'Team A', 'stage' => RolloutStage::Everyone, 'tenant' => true],
        'contract_review' => ['name' => 'Contract review', 'description' => 'Checks contracts against the state forms before the broker sees them.', 'owner' => 'Team A', 'stage' => RolloutStage::Everyone, 'tenant' => true],
        'provider_marketplace' => ['name' => 'Provider marketplace', 'description' => 'Agents find and invite verified providers.', 'owner' => 'Team B', 'stage' => RolloutStage::Everyone, 'tenant' => true],
        // Decision 5 (Sprint 15): providers go live only where the RESPA counsel review is signed off; per tenant or state.
        'provider_network' => ['name' => 'Provider network', 'description' => 'Provider profiles go live once every step is done (B15.2).', 'owner' => 'Team B', 'stage' => RolloutStage::Off, 'tenant' => false],
        'recruiting_agents' => ['name' => 'Recruiting agents', 'description' => 'Brokerages see candidates who chose to be found.', 'owner' => 'Team A', 'stage' => RolloutStage::Off, 'tenant' => true],
        'ai_video_lessons' => ['name' => 'AI video lessons', 'description' => 'Lessons as narrated videos (paid add-on).', 'owner' => 'Team B', 'stage' => RolloutStage::Everyone, 'tenant' => true],
        'spanish_ui' => ['name' => 'Spanish', 'description' => 'The web app and the phone in Spanish.', 'owner' => 'Team A', 'stage' => RolloutStage::Everyone, 'tenant' => true],
        'messages' => ['name' => 'Messages', 'description' => 'Conversations and announcements, live on the web and the phone (B6.1).', 'owner' => 'Team B', 'stage' => RolloutStage::Internal, 'tenant' => false],
        'notification_centre' => ['name' => 'Notification center', 'description' => 'Push, email, SMS and the inbox with preferences and quiet hours (B6.2).', 'owner' => 'Team B', 'stage' => RolloutStage::Internal, 'tenant' => false],
        'video_rooms' => ['name' => 'Video rooms', 'description' => 'Sessions and live classes inside REOS with a whiteboard (B6.3).', 'owner' => 'Team B', 'stage' => RolloutStage::Internal, 'tenant' => false],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::FLAGS);
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::FLAGS);
    }
}
