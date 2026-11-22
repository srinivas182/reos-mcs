<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Content\Scanning\FileScanner;
use App\Content\Scanning\ScanResult;
use App\Enums\OrganisationType;
use App\Events\Messaging\AnnouncementPosted;
use App\Features\Flags;
use App\Messaging\Conversations;
use App\Models\Announcement;
use App\Models\AuditEvent;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\MessageReport;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\MessagesReported;
use App\Services\Privacy\DataExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $flags = app(Flags::class);
    $flag = $flags->flag('messages');
    $flag->forceFill(['stage' => 'everyone'])->save();
    $flags->changed($flag);

    config(['reos.content.disk' => 'local']);
    Storage::fake('local');

    $this->lone = Organisation::factory()->create(['name' => 'Lone Star Realty', 'states' => ['TX']]);
    $this->team = Organisation::factory()->ofType(OrganisationType::Team)->create(['name' => 'Okoye Team', 'parent_id' => $this->lone->id]);
    $this->linda = User::factory()->withRole(Role::Broker, $this->lone)->create(['name' => 'Linda Park']);
    $this->james = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'James Miller', 'current_state' => 'TX']);
    $this->ana = User::factory()->withRole(Role::Agent, $this->team)->create(['name' => 'Ana Okoye', 'current_state' => 'TX']);
    $this->dana = User::factory()->withRole(Role::Mentor, $this->lone)->create(['name' => 'Dana Brooks']);
});

describe('attachments', function () {
    it('sends images and PDFs that open only once scanned clean', function () {
        $conversation = app(Conversations::class)->start($this->james, [$this->linda->id], 'Offer attached');

        $this->actingAs($this->james, 'sanctum')->post("/api/v1/messages/{$conversation->uuid}", [
            'files' => [UploadedFile::fake()->create('elm-st-offer.pdf', 300, 'application/pdf'), UploadedFile::fake()->image('survey.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('body', null)
            ->assertJsonCount(2, 'attachments')
            // Scanned at once here (sync queue, no ClamAV outside production).
            ->assertJsonPath('attachments.0.status', 'clean')
            ->assertJsonPath('attachments.0.name', 'elm-st-offer.pdf')
            ->assertJsonPath('attachments.1.image', true);

        $file = MessageAttachment::query()->firstOrFail();
        Storage::disk('local')->assertExists($file->path);
        $url = $this->actingAs($this->linda, 'sanctum')->getJson("/api/v1/messages/{$conversation->uuid}")->json('messages.1.attachments.0.url');
        expect($url)->toContain('signature=');
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        // Without the signature: nothing.
        $this->get("/messages/attachments/{$file->uuid}")->assertForbidden();
        $this->actingAs($this->linda)->get('/messages')->assertInertia(fn (Assert $page) => $page->where('inbox.0.last_message.files', 2));
    });

    it('removes an infected file and never serves it', function () {
        app()->instance(FileScanner::class, new class implements FileScanner
        {
            public function scan($stream): ScanResult
            {
                return ScanResult::Infected;
            }
        });
        $conversation = app(Conversations::class)->start($this->james, [$this->linda->id], 'Look');

        $this->actingAs($this->james, 'sanctum')->post("/api/v1/messages/{$conversation->uuid}", ['files' => [UploadedFile::fake()->create('bad.pdf', 10, 'application/pdf')]], ['Accept' => 'application/json'])
            ->assertCreated();

        $file = MessageAttachment::query()->firstOrFail();
        expect($file->scan_status)->toBe('infected');
        Storage::disk('local')->assertMissing($file->path);
    });

    it('refuses other kinds of file and more than 5', function () {
        $conversation = app(Conversations::class)->start($this->james, [$this->linda->id], 'Hi');

        $this->actingAs($this->james, 'sanctum')->post("/api/v1/messages/{$conversation->uuid}", ['files' => [UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload')]], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('files.0');
        $this->actingAs($this->james, 'sanctum')->post("/api/v1/messages/{$conversation->uuid}", ['files' => array_map(fn () => UploadedFile::fake()->image('a.png'), range(1, 6))], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('files');
    });

    it('deletes the files when the message is deleted for everyone', function () {
        $conversation = app(Conversations::class)->start($this->james, [$this->linda->id], 'Hi');
        $this->actingAs($this->james, 'sanctum')->post("/api/v1/messages/{$conversation->uuid}", ['files' => [UploadedFile::fake()->image('a.png')]], ['Accept' => 'application/json']);
        $file = MessageAttachment::query()->firstOrFail();

        $this->actingAs($this->james, 'sanctum')->deleteJson('/api/v1/messages/items/'.$file->message->uuid)->assertOk()->assertJsonPath('attachments', []);
        Storage::disk('local')->assertMissing($file->path);
        expect(MessageAttachment::query()->count())->toBe(0);
    });
});

describe('report and block', function () {
    it('blocks both ways for one-to-one messages and hides read state', function () {
        $conversation = app(Conversations::class)->start($this->james, [$this->linda->id], 'Hi');
        app(Conversations::class)->read($this->linda, $conversation);

        $this->actingAs($this->linda)->post('/messages/blocks', ['user' => $this->james->id])->assertRedirect();

        $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/messages/{$conversation->uuid}", ['body' => 'Still there?'])
            ->assertUnprocessable()->assertJsonPath('errors.body.0', "You can't message each other: one of you blocked the other.");
        $this->actingAs($this->james, 'sanctum')->getJson("/api/v1/messages/{$conversation->uuid}")
            ->assertJsonPath('reads', [])->assertJsonPath('blocked', 'by_them');
        $this->actingAs($this->linda, 'sanctum')->getJson('/api/v1/messages')->assertJsonPath('conversations.0.blocked', 'by_me');
        $this->actingAs($this->james, 'sanctum')->getJson('/api/v1/messages/contacts')->assertJsonMissing(['name' => 'Linda Park']);

        $this->actingAs($this->linda, 'sanctum')->deleteJson("/api/v1/messages/blocks/{$this->james->id}")->assertOk()->assertJsonPath('blocked', false);
        $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/messages/{$conversation->uuid}", ['body' => 'Back again'])->assertCreated();
    });

    it('sends a report with a copy of the messages to the tenant admins and REOS', function () {
        Notification::fake();
        $ops = User::factory()->superAdmin()->create(['name' => 'Ops Srini']);
        $conversation = app(Conversations::class)->start($this->dana, [$this->james->id], 'Buy my course for $500');
        app(Conversations::class)->post($this->dana, $conversation, 'Last chance');

        $this->actingAs($this->james)->post("/messages/{$conversation->uuid}/report", ['reason' => 'spam', 'details' => 'Selling to me', 'block' => true])->assertRedirect();

        $report = MessageReport::query()->firstOrFail();
        expect($report->reported_user_id)->toBe($this->dana->id)
            ->and($report->organisation_id)->toBe($this->lone->id)
            ->and(array_column($report->messages, 'body'))->toBe(['Buy my course for $500', 'Last chance'])
            ->and(AuditEvent::query()->where('action', 'message.reported')->exists())->toBeTrue();

        // A later delete can't hide what was reported.
        Message::query()->update(['body' => null, 'deleted_at' => now()]);
        expect($report->fresh()?->messages[0]['body'])->toBe('Buy my course for $500');

        Notification::assertSentTo([$this->linda, $ops], MessagesReported::class);
        Notification::assertNotSentTo($this->james, MessagesReported::class);
        $this->actingAs($this->dana, 'sanctum')->postJson("/api/v1/messages/{$conversation->uuid}", ['body' => 'Hello?'])->assertUnprocessable();

        // REOS reviews it on Compliance and audit and marks it handled.
        $confirmed = ['auth.password_confirmed_at' => now()->getTimestamp()];
        $this->actingAs($ops)->withSession($confirmed)->get('/admin/compliance')->assertInertia(fn (Assert $page) => $page
            ->where('messageReports.0.reason', 'spam')
            ->where('messageReports.0.reported', 'Dana Brooks')
            ->has('messageReports.0.messages', 2));
        $this->actingAs($ops)->withSession($confirmed)->patch("/admin/compliance/message-reports/{$report->uuid}")->assertRedirect();
        expect($report->fresh()?->status)->toBe('resolved');
    });

    it('lets only participants report, and asks who in a group', function () {
        $group = app(Conversations::class)->start($this->linda, [$this->james->id, $this->dana->id], 'Huddle', 'Huddle');

        $this->actingAs($this->ana, 'sanctum')->postJson("/api/v1/messages/{$group->uuid}/report", ['reason' => 'spam'])->assertNotFound();
        $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/messages/{$group->uuid}/report", ['reason' => 'spam', 'user' => $this->ana->id])
            ->assertUnprocessable()->assertJsonValidationErrors('user');
        $this->actingAs($this->james, 'sanctum')->postJson("/api/v1/messages/{$group->uuid}/report", ['reason' => 'harassment', 'user' => $this->dana->id])->assertOk();
    });
});

describe('announcements', function () {
    it('goes to the chosen group, shows on Messages and Today, and counts who read it', function () {
        Event::fake([AnnouncementPosted::class]);

        $this->actingAs($this->linda)->get('/announcements')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('announcements/index')
            ->where('options.teams.0.name', 'Okoye Team')
            ->where('options.states', ['TX']));

        $this->actingAs($this->linda)->post('/announcements', ['title' => 'Office closed Friday', 'body' => 'Showings still run.', 'roles' => ['agent'], 'teams' => [], 'states' => []])
            ->assertRedirect()->assertSessionHasNoErrors();

        $announcement = Announcement::query()->firstOrFail();
        expect($announcement->recipients_count)->toBe(2);
        Event::assertDispatched(AnnouncementPosted::class, fn (AnnouncementPosted $e) => $e->recipients === [$this->james->id, $this->ana->id] || $e->recipients === [$this->ana->id, $this->james->id]);

        // The team agent (another tenant) sees it too.
        $this->actingAs($this->ana, 'sanctum')->getJson('/api/v1/announcements')->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('announcements.0.title', 'Office closed Friday')
            ->assertJsonPath('announcements.0.from', 'Lone Star Realty');
        $this->actingAs($this->james)->get('/messages')->assertInertia(fn (Assert $page) => $page->where('announcements.0.title', 'Office closed Friday'));
        $this->actingAs($this->dana, 'sanctum')->getJson('/api/v1/announcements')->assertJsonPath('announcements', []);

        $this->actingAs($this->ana, 'sanctum')->postJson("/api/v1/announcements/{$announcement->uuid}/read")->assertOk();
        $this->actingAs($this->linda)->get('/announcements')->assertInertia(fn (Assert $page) => $page
            ->where('sent.0.recipients', 2)
            ->where('sent.0.read', 1));
        expect(AuditEvent::query()->where('action', 'announcement.sent')->exists())->toBeTrue();
    });

    it('narrows to a team or state, and only admins send', function () {
        $this->actingAs($this->linda)->post('/announcements', ['title' => 'Team only', 'body' => 'Hi team', 'roles' => [], 'teams' => [$this->team->id], 'states' => []])->assertSessionHasNoErrors();
        expect(Announcement::query()->latest('id')->value('recipients_count'))->toBe(1);

        $this->actingAs($this->linda)->post('/announcements', ['title' => 'Florida', 'body' => 'Hi', 'roles' => [], 'teams' => [], 'states' => ['FL']])->assertSessionHasErrors('audience');

        $this->actingAs($this->james)->get('/announcements')->assertForbidden();
        $this->actingAs($this->james)->post('/announcements', ['title' => 'x', 'body' => 'y', 'roles' => [], 'teams' => [], 'states' => []])->assertForbidden();
        $this->actingAs($this->james, 'sanctum')->postJson('/api/v1/announcements/'.Announcement::query()->value('uuid').'/read')->assertNotFound();
    });
});

describe('your data', function () {
    it('done when: a user downloads their data including their messages', function () {
        $conversation = app(Conversations::class)->start($this->james, [$this->linda->id], 'Can you look at the Elm St offer?');
        app(Conversations::class)->post($this->linda, $conversation, 'On it.');

        $export = app(DataExport::class)->for($this->james);

        expect(array_column($export['messages']['sent'], 'body'))->toBe(['Can you look at the Elm St offer?'])
            ->and($export['messages']['conversations'])->toHaveCount(1);
    });
});
