<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\AuditEvent;
use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\Deal;
use App\Models\DealDate;
use App\Models\Organisation;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Tuesday 24 November 2026, 10:00 in Texas.
    $this->travelTo('2026-11-24 16:00:00');
    Notification::fake();
    $this->lone = Organisation::factory()->ofType(OrganisationType::Brokerage)->create(['name' => 'Lone Star Realty', 'states' => ['TX']]);
    User::factory()->withRole(Role::Broker, $this->lone)->withTwoFactor()->create(['name' => 'Linda Park']);
    $this->james = User::factory()->withRole(Role::Agent, $this->lone)->create(['name' => 'James Miller']);
    $this->actingAs($this->james)->putJson('/api/v1/agent/mode', ['goal' => 'launch', 'reason' => 'setup'])->assertOk();
    $this->maria = Contact::query()->create(['uuid' => (string) Str::uuid7(), 'owner_type' => 'user', 'owner_id' => $this->james->id, 'first_name' => 'Maria', 'last_name' => 'Lopez', 'emails' => [['value' => 'maria@example.com', 'source' => 'follow-up-boss']], 'phones' => [['value' => '+15125550142', 'source' => 'follow-up-boss']]]);
});

function askAssistant(object $test, string $question, ?string $chat = null): array
{
    return $test->postJson('/api/v1/assistant/messages', array_filter(['question' => $question, 'chat' => $chat]))->assertCreated()->json('data');
}

describe('the AI assistant (A12.3)', function () {
    it('asks first, then answers "What should I do today?" from the CRM, deals and calendar', function () {
        $overdue = Todo::query()->create(['user_id' => $this->james->id, 'title' => 'Call Maria back', 'due_date' => '2026-11-22', 'source' => 'crm', 'related_label' => 'Maria Lopez']);
        DB::table('contact_tasks')->insert(['contact_id' => $this->maria->id, 'todo_id' => $overdue->id, 'source' => 'follow-up-boss', 'external_id' => 't1', 'created_at' => now(), 'updated_at' => now()]);
        $deal = Deal::query()->create(['uuid' => (string) Str::uuid7(), 'user_id' => $this->james->id, 'source' => 'dotloop', 'property' => '1420 Pecan St', 'side' => 'buy', 'stage' => 'under_contract', 'parties' => [], 'documents' => []]);
        $option = Todo::query()->create(['user_id' => $this->james->id, 'title' => 'Option period ends', 'due_date' => '2026-11-26', 'source' => 'deal']);
        DealDate::query()->create(['deal_id' => $deal->id, 'kind' => 'option_period', 'due_on' => '2026-11-26', 'todo_id' => $option->id]);
        CalendarEvent::query()->create(['user_id' => $this->james->id, 'source' => 'reos', 'kind' => 'showing', 'title' => 'Showing with the Nguyens', 'starts_at' => '2026-11-24 21:30:00', 'ends_at' => '2026-11-24 22:30:00', 'all_day' => false, 'status' => 'confirmed']);

        $home = $this->getJson('/api/v1/assistant')->assertOk()->json('data');
        expect($home)->toMatchArray(['permission' => false, 'chats' => []])->and($home['starters'])->toHaveCount(6);

        $first = askAssistant($this, 'What should I do today?');
        expect($first['messages'][1])->toMatchArray(['role' => 'answer', 'kind' => 'permission', 'items' => []]);

        $this->putJson('/api/v1/assistant/permission', ['allowed' => true])->assertOk()->assertJsonPath('data.permission', true);
        expect(AuditEvent::query()->where('action', 'assistant.context')->sole()->properties)->toBe(['from' => false, 'to' => true]);

        // The done-when: real CRM data.
        $chat = askAssistant($this, 'What should I do today?', $first['id']);
        $answer = $chat['messages'][3];
        expect($answer['kind'])->toBe('today')
            ->and($answer['body'])->toContain('Here\'s your day')
            ->and(array_slice(array_map(static fn (array $i): string => $i['title'].' · '.$i['source'], $answer['items']), 0, 3))->toBe([
                'Call Maria back · Follow Up Boss', 'Option period ends · dotloop', 'Showing with the Nguyens · REOS',
            ])
            ->and($answer['items'][1]['link'])->toBe('/deals?deal='.$deal->uuid);
        $this->getJson('/api/v1/assistant')->assertJsonPath('data.chats.0.id', $first['id']);
    });

    it('preps the next appointment from the contact\'s CRM notes and their deal', function () {
        $this->putJson('/api/v1/assistant/permission', ['allowed' => true]);
        ContactNote::query()->create(['contact_id' => $this->maria->id, 'body' => 'Wants a pool; pre-approved to $450k.', 'source' => 'follow-up-boss']);
        Deal::query()->create(['uuid' => (string) Str::uuid7(), 'user_id' => $this->james->id, 'source' => 'reos', 'property' => '88 Oak Ridge Dr', 'side' => 'buy', 'stage' => 'active', 'parties' => [['name' => 'Maria Lopez', 'role' => 'buyer', 'email' => null, 'phone' => null]], 'documents' => []]);
        CalendarEvent::query()->create(['user_id' => $this->james->id, 'source' => 'reos', 'kind' => 'appointment', 'title' => 'Buyer consult', 'starts_at' => '2026-11-24 20:00:00', 'ends_at' => '2026-11-24 21:00:00', 'all_day' => false, 'status' => 'confirmed', 'related_type' => 'contact', 'related_id' => $this->maria->uuid]);

        $answer = askAssistant($this, 'Prep my next appointment')['messages'][1];
        expect($answer['kind'])->toBe('prep')
            ->and($answer['body'])->toBe('Your next appointment is with Maria Lopez, Tuesday 2:00 PM. Here\'s what to know.')
            ->and(array_column($answer['items'], 'title'))->toBe(['Buyer consult', 'Latest note', 'Their deal'])
            ->and($answer['items'][1]['detail'])->toBe('Wants a pool; pre-approved to $450k.');
    });

    it('drafts a text or email to a contact, to open in Messages or Mail, never sent', function () {
        $this->putJson('/api/v1/assistant/permission', ['allowed' => true]);

        $text = askAssistant($this, 'Text Maria to check in')['messages'][1];
        expect($text['kind'])->toBe('draft')
            ->and($text['draft'])->toMatchArray(['channel' => 'text', 'to' => '+15125550142', 'name' => 'Maria Lopez'])
            ->and($text['draft']['href'])->toStartWith('sms:+15125550142?&body=Hi%20Maria');

        $email = askAssistant($this, 'Write an email to Maria about the open house')['messages'][1];
        expect($email['draft'])->toMatchArray(['channel' => 'email', 'to' => 'maria@example.com', 'subject' => 'Checking in, from James Miller'])
            ->and($email['draft']['href'])->toStartWith('mailto:maria%40example.com?subject=');

        expect(askAssistant($this, 'Email to Zed about the listing')['messages'][1]['body'])->toContain("couldn't find that person");
    });

    it('hands policy questions to the virtual broker, and answers deals', function () {
        $this->putJson('/api/v1/assistant/permission', ['allowed' => true]);
        $policy = askAssistant($this, 'How fast must earnest money be deposited under TREC rules?')['messages'][1];
        expect($policy['kind'])->toBe('policy')
            ->and($policy['broker_question'])->not->toBeNull()
            ->and($policy['items'][0]['title'])->toBe('22 TAC §535.146');

        $deal = Deal::query()->create(['uuid' => (string) Str::uuid7(), 'user_id' => $this->james->id, 'source' => 'reos', 'property' => '1420 Pecan St', 'side' => 'buy', 'stage' => 'under_contract', 'parties' => [], 'documents' => []]);
        DealDate::query()->create(['deal_id' => $deal->id, 'kind' => 'closing', 'due_on' => '2027-01-08']);
        $answer = askAssistant($this, 'When is the Pecan deal closing?')['messages'][1];
        expect($answer)->toMatchArray(['kind' => 'deal', 'body' => '1420 Pecan St is under contract. Here are its next dates.'])
            ->and($answer['items'][0])->toMatchArray(['title' => 'Closing', 'at' => '2027-01-08']);

        expect(askAssistant($this, 'Tell me a joke')['messages'][1]['kind'])->toBe('none');
    });

    it('keeps chats to their owner', function () {
        $chat = askAssistant($this, 'What should I do today?');
        $this->actingAs(User::factory()->withRole(Role::Agent, $this->lone)->create())->getJson("/api/v1/assistant/chats/{$chat['id']}")->assertNotFound();
        $this->postJson('/api/v1/assistant/messages', ['question' => 'Hello', 'chat' => $chat['id']])->assertNotFound();
    });
});
