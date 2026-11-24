<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use App\Models\Connection;
use App\Models\Licence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('posts a short video to YouTube Shorts and TikTok, needs a video, and reads views back (B11.3)', function () {
    $this->travelTo('2026-11-24 15:00:00');
    Storage::fake('content');
    $james = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller']);
    Licence::query()->create(['user_id' => $james->id, 'state_code' => 'TX', 'type' => LicenceType::SalesAgent, 'number' => '0798765', 'status' => LicenceStatus::Active, 'sponsor_name' => 'Linda Park Realty']);
    connectOnWeb($this, $james, tool: 'youtube')->assertRedirect();
    connectOnWeb($this, $james, tool: 'tiktok')->assertRedirect();
    $targets = [
        ['connection' => Connection::query()->where('integration_id', 'youtube')->sole()->id, 'channel' => 'youtube', 'id' => 'sim-yt-channel'],
        ['connection' => Connection::query()->where('integration_id', 'tiktok')->sole()->id, 'channel' => 'tiktok', 'id' => 'sim-tt-1'],
    ];
    $this->actingAs($james);

    $photo = $this->postJson('/api/v1/social/media', ['file' => UploadedFile::fake()->image('kitchen.jpg')])->json('data.path');
    $checks = $this->postJson('/api/v1/social/posts/check', ['body' => 'Tour 1420 Pecan St', 'media' => [$photo], 'targets' => $targets, 'when' => 'draft'])->json('data');
    expect(collect($checks['checks'])->where('code', 'video')->pluck('ok')->all())->toBe([false, false])->and($checks['ok'])->toBeFalse();

    $video = $this->postJson('/api/v1/social/media', ['file' => UploadedFile::fake()->create('tour.mp4', 2048, 'video/mp4')])->assertCreated()->assertJsonPath('data.kind', 'video')->json('data.path');
    $id = $this->postJson('/api/v1/social/posts', ['body' => "Tour 1420 Pecan St\nThree bedrooms, big backyard.", 'media' => [$video], 'targets' => $targets, 'when' => 'now'])
        ->assertCreated()->assertJsonPath('data.status', 'published')->json('data.id');

    $this->travel(2)->hours();
    $this->artisan('reos:social:insights')->expectsOutput('Read insights for 2 posts.');
    $post = $this->getJson("/api/v1/social/posts/{$id}")->json('data');
    expect(array_keys($post['targets'][0]['insights']))->toBe(['views', 'likes', 'comments'])
        ->and(array_keys($post['targets'][1]['insights']))->toBe(['views', 'likes', 'comments', 'shares'])
        ->and($post['media'][0]['kind'])->toBe('video');

    // Social tokens are encrypted at rest (S11 definition of done).
    $youtube = Connection::query()->where('integration_id', 'youtube')->sole();
    $raw = DB::table('connections')->where('id', $youtube->id)->value('access_token');
    expect($raw)->toBeString()->not->toBe($youtube->access_token)->not->toContain((string) $youtube->access_token);
});
