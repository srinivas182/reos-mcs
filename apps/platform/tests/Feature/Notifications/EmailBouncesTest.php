<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Mail\Ses\SesMailer;
use App\Models\IntegrationSetting;
use App\Models\State;
use App\Models\User;
use App\Notifications\StateWentLive;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Mail\Transport\SesV2Transport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

const CERT_URL = 'https://sns.us-east-2.amazonaws.com/SimpleNotificationService-test.pem';
const TOPIC = 'arn:aws:sns:us-east-2:123456789012:reos-ses-events';

beforeEach(function () {
    $this->james = User::factory()->withRole(Role::Agent)->create(['name' => 'James Miller', 'email' => 'james.miller@lonestar.example']);
    $this->state = State::query()->firstOrCreate(['code' => 'LA'], ['name' => 'Louisiana', 'status' => 'live']);
    $this->travelTo(CarbonImmutable::parse('2026-11-24 12:00', 'America/Chicago'));

    // A signing key and certificate standing in for SNS's.
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $cert = openssl_csr_sign(openssl_csr_new(['commonName' => 'sns.amazonaws.com'], $key), null, $key, 1);
    openssl_x509_export($cert, $pem);
    $this->snsKey = $key;
    Http::fake([
        CERT_URL => Http::response($pem),
        'sns.us-east-2.amazonaws.com/?Action=ConfirmSubscription*' => Http::response('<ConfirmSubscriptionResponse/>'),
    ]);
});

/** An SNS message signed the way SNS signs it (version 2, SHA-256). */
function sns(object $test, string $type, array $fields): string
{
    $message = ['Type' => $type, 'MessageId' => 'm-'.uniqid(), 'TopicArn' => TOPIC, 'Timestamp' => '2026-11-24T18:00:00.000Z', 'SignatureVersion' => '2', 'SigningCertURL' => CERT_URL, ...$fields];
    $order = $type === 'Notification' ? ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'] : ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
    $signed = '';

    foreach ($order as $field) {
        if (isset($message[$field])) {
            $signed .= $field."\n".$message[$field]."\n";
        }
    }

    openssl_sign($signed, $signature, $test->snsKey, OPENSSL_ALGO_SHA256);

    return json_encode([...$message, 'Signature' => base64_encode($signature)], JSON_THROW_ON_ERROR);
}

function sesEvent(array $event): array
{
    return ['Message' => json_encode($event, JSON_THROW_ON_ERROR)];
}

function postSns(object $test, string $body): TestResponse
{
    return $test->call('POST', '/webhooks/ses', [], [], [], ['CONTENT_TYPE' => 'text/plain; charset=UTF-8'], $body);
}

describe('bounces and complaints', function () {
    it('done when: a hard bounce stops email to that address until the person resumes it', function () {
        $bounce = sesEvent(['notificationType' => 'Bounce', 'bounce' => ['bounceType' => 'Permanent', 'bouncedRecipients' => [
            ['emailAddress' => 'James.Miller@lonestar.example', 'diagnosticCode' => 'smtp; 550 5.1.1 user unknown'],
        ]]]);
        postSns($this, sns($this, 'Notification', $bounce))->assertOk();

        expect(DB::table('email_suppressions')->sole())->toMatchArray(['email' => 'james.miller@lonestar.example', 'reason' => 'bounce'])
            ->and(DB::table('audit_events')->where('action', 'email.suppressed')->value('subject_id'))->toBe((string) $this->james->id);

        // The centre no longer picks email; the inbox still gets it.
        $this->james->notify(new StateWentLive($this->state));
        expect(DB::table('notification_deliveries')->where('channel', 'email')->count())->toBe(0)
            ->and($this->james->notifications()->count())->toBe(1);

        // Any other mail to the address is dropped before it goes.
        Mail::raw('Hello', fn ($m) => $m->to('james.miller@lonestar.example'));
        expect(Mail::mailer()->getSymfonyTransport()->messages())->toHaveCount(0);

        $this->actingAs($this->james)->get('/settings/notifications')
            ->assertInertia(fn ($page) => $page->where('email.stopped.reason', 'bounce')->where('email.address', 'james.miller@lonestar.example'));

        $this->actingAs($this->james)->post('/settings/notifications/email/resume')->assertRedirect();
        $this->james->fresh()?->notify(new StateWentLive($this->state));
        expect(DB::table('notification_deliveries')->where('channel', 'email')->pluck('status')->all())->toBe(['sent']);
    });

    it('stops email after a complaint, and ignores transient bounces', function () {
        postSns($this, sns($this, 'Notification', sesEvent(['notificationType' => 'Bounce', 'bounce' => ['bounceType' => 'Transient', 'bouncedRecipients' => [['emailAddress' => 'james.miller@lonestar.example']]]])))->assertOk();
        expect(DB::table('email_suppressions')->count())->toBe(0);

        postSns($this, sns($this, 'Notification', sesEvent(['eventType' => 'Complaint', 'complaint' => ['complaintFeedbackType' => 'abuse', 'complainedRecipients' => [['emailAddress' => 'james.miller@lonestar.example']]]])))->assertOk();

        $this->actingAs($this->james, 'sanctum')->getJson('/api/v1/me/notification-settings')->assertJsonPath('email.stopped.reason', 'complaint');
        $this->actingAs($this->james, 'sanctum')->postJson('/api/v1/me/email/resume')->assertOk()->assertJsonPath('stopped', null);
    });

    it('drops only the suppressed people from a message to several', function () {
        DB::table('email_suppressions')->insert(['email' => 'gone@lonestar.example', 'reason' => 'bounce', 'created_at' => now()]);

        Mail::raw('Team update', fn ($m) => $m->to(['gone@lonestar.example', 'linda.park@lonestar.example'])->cc('gone@lonestar.example'));

        $sent = Mail::mailer()->getSymfonyTransport()->messages();
        expect($sent)->toHaveCount(1)
            ->and(array_map(fn ($a) => $a->getAddress(), $sent[0]->getOriginalMessage()->getTo()))->toBe(['linda.park@lonestar.example'])
            ->and($sent[0]->getOriginalMessage()->getCc())->toBe([]);
    });
});

describe('SNS', function () {
    it('confirms the subscription by visiting the SNS link only', function () {
        postSns($this, sns($this, 'SubscriptionConfirmation', ['Message' => 'You have chosen to subscribe', 'Token' => 'tok', 'SubscribeURL' => 'https://sns.us-east-2.amazonaws.com/?Action=ConfirmSubscription&Token=tok']))->assertOk();
        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://sns.us-east-2.amazonaws.com/?Action=ConfirmSubscription'));

        postSns($this, sns($this, 'SubscriptionConfirmation', ['Message' => 'x', 'Token' => 'tok', 'SubscribeURL' => 'https://evil.example/confirm']))->assertForbidden();
    });

    it('rejects forged, tampered or foreign-topic messages', function () {
        $body = sns($this, 'Notification', sesEvent(['notificationType' => 'Complaint', 'complaint' => ['complainedRecipients' => [['emailAddress' => 'james.miller@lonestar.example']]]]));

        // Tampered after signing.
        postSns($this, str_replace('james.miller', 'linda.park', $body))->assertForbidden();
        // A certificate from anywhere but SNS.
        postSns($this, str_replace(json_encode(CERT_URL), json_encode('https://evil.example/cert.pem'), $body))->assertForbidden();
        expect(DB::table('email_suppressions')->count())->toBe(0);

        // With the topic pinned in Settings › Integrations, other topics are refused.
        IntegrationSetting::query()->create(['integration_id' => 'amazon-ses', 'mode' => IntegrationMode::Sandbox]);
        app(CredentialVault::class)->put('amazon-ses', CredentialEnvironment::Sandbox, ['access_key_id' => 'AKIATEST', 'secret_access_key' => 'secret', 'region' => 'us-east-2', 'from_email' => 'hello@reos.com', 'sns_topic_arn' => 'arn:aws:sns:us-east-2:123456789012:other'], null);
        postSns($this, $body)->assertForbidden();
    });
});

describe('the reos mailer', function () {
    it('uses SES with the keys from Settings › Integrations, and the log until then', function () {
        expect(app(SesMailer::class)->transport())->not->toBeInstanceOf(SesV2Transport::class);

        IntegrationSetting::query()->create(['integration_id' => 'amazon-ses', 'mode' => IntegrationMode::Sandbox]);
        app(CredentialVault::class)->put('amazon-ses', CredentialEnvironment::Sandbox, ['access_key_id' => 'AKIATEST', 'secret_access_key' => 'secret', 'region' => 'us-west-2', 'from_email' => 'hello@reos.com'], null);

        $transport = app(SesMailer::class)->transport();
        expect($transport)->toBeInstanceOf(SesV2Transport::class)
            ->and((string) $transport)->toBe('ses-v2')
            ->and($transport->ses()->getRegion())->toBe('us-west-2')
            ->and(app(SesMailer::class)->fromAddress())->toBe('hello@reos.com');
    });
});
