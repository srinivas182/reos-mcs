<?php

declare(strict_types=1);

use App\Content\Scanning\FileScanner;
use App\Content\Scanning\ScanResult;
use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use App\Integrations\CredentialEnvironment;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Jobs\ScanCertificate;
use App\Licences\Certificates\CertificateReaders;
use App\Models\CeCompletion;
use App\Models\CertificateRead;
use App\Models\IntegrationSetting;
use App\Models\Licence;
use App\Models\User;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\Textract\Exception\TextractException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-11-24 10:00:00');
    Storage::fake(config()->string('reos.content.disk'));
    Queue::fake();
    $this->james = User::factory()->create(['name' => 'James Miller']);
    Licence::query()->create(['user_id' => $this->james->id, 'state_code' => 'TX', 'type' => LicenceType::SalesAgent, 'number' => '0798765', 'status' => LicenceStatus::Active, 'holder_name' => 'James Miller', 'verified_by' => 'simulator', 'verified_at' => now()]);
});

/** Textract with test keys; its answers come from `$mock`. */
function textractAnswers(MockHandler $mock): void
{
    app(CredentialVault::class)->put('aws-textract', CredentialEnvironment::Sandbox, ['access_key_id' => 'AKIATESTTESTTEST', 'secret_access_key' => 'test-secret', 'region' => 'us-east-2'], null);
    IntegrationSetting::query()->create(['integration_id' => 'aws-textract', 'mode' => IntegrationMode::Sandbox]);
    app()->instance(CertificateReaders::HANDLER, $mock);
}

/** @param  array<string, array{0: string, 1: float}>  $answers  alias => [text, confidence] */
function textractSaid(array $answers): Result
{
    $blocks = [['BlockType' => 'PAGE', 'Id' => 'p1']];

    foreach ($answers as $alias => [$text, $confidence]) {
        $blocks[] = ['BlockType' => 'QUERY', 'Id' => "q-{$alias}", 'Query' => ['Text' => '…', 'Alias' => $alias], 'Relationships' => [['Type' => 'ANSWER', 'Ids' => ["a-{$alias}"]]]];
        $blocks[] = ['BlockType' => 'QUERY_RESULT', 'Id' => "a-{$alias}", 'Text' => $text, 'Confidence' => $confidence];
    }

    return new Result(['Blocks' => $blocks]);
}

function certificateFile(string $name = 'legal-update.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
}

describe('certificate OCR (B10.3b)', function () {
    it('reads a certificate with Textract into fields for review, keeping only confident answers', function () {
        $mock = new MockHandler;
        $mock->append(function (CommandInterface $command) {
            expect($command->getName())->toBe('AnalyzeDocument')
                ->and($command['FeatureTypes'])->toBe(['QUERIES'])
                ->and(array_column($command['QueriesConfig']['Queries'], 'Alias'))->toBe(['name', 'course', 'hours', 'completed_on', 'provider']);

            return textractSaid([
                'name' => ['JAMES R. MILLER', 99.1], 'course' => ['Legal Update II: Ethics and Agency', 97.5], 'hours' => ['4.0 Clock Hours', 96.2],
                'completed_on' => ['November 20, 2026', 94.0], 'provider' => ['Smudge', 31.0],
            ]);
        });
        textractAnswers($mock);

        $data = $this->actingAs($this->james)->post('/api/v1/me/certificates/read', ['file' => certificateFile()], ['Accept' => 'application/json'])->assertOk()->json('data');

        expect($data)->toMatchArray([
            'reader' => 'textract', 'name' => 'JAMES R. MILLER', 'course' => 'Legal Update II: Ethics and Agency', 'hours' => 4, 'completed_on' => '2026-11-20',
            'provider' => null, 'name_matches' => true, 'states' => ['TX'],
        ])->and($data['confidence'])->toMatchArray(['hours' => 96.2])
            ->and(CertificateRead::query()->sole())->reader->toBe('textract')->scan_status->toBe('pending');
        Storage::disk(config()->string('reos.content.disk'))->assertExists(CertificateRead::query()->sole()->path);
        Queue::assertPushed(ScanCertificate::class);
    });

    it('saves the checked details as a completion with its certificate', function () {
        $data = $this->actingAs($this->james)->post('/api/v1/me/certificates/read', ['file' => certificateFile()], ['Accept' => 'application/json'])->assertOk()->json('data');
        expect($data)->toMatchArray(['reader' => 'simulator', 'name' => 'James Miller', 'hours' => 4]);

        $this->postJson('/api/v1/me/certificates', ['upload' => $data['upload'], 'state' => 'TX', 'title' => 'Legal Update II', 'hours' => 4, 'completed_on' => '2026-11-21', 'provider' => 'Texas Realty Institute'])
            ->assertCreated()->assertJsonPath('data.read_by', 'simulator')->assertJsonPath('data.hours', 4);

        $completion = CeCompletion::query()->sole();
        expect($completion)->source->toBe('other_provider')->state_code->toBe('TX')->read_by->toBe('simulator')->certificate_path->toStartWith("certificates/{$this->james->id}/")
            ->and(CertificateRead::query()->count())->toBe(0);
        // The read can't be saved twice.
        $this->postJson('/api/v1/me/certificates', ['upload' => $data['upload'], 'state' => 'TX', 'title' => 'x', 'hours' => 1, 'completed_on' => '2026-11-21'])->assertNotFound();
    });

    it('lets the person type the details when the reader fails, and points out another name', function () {
        $mock = new MockHandler;
        $mock->append(fn (CommandInterface $c) => new TextractException('Bad document', $c, ['code' => 'UnsupportedDocumentException']));
        textractAnswers($mock);

        $this->actingAs($this->james)->post('/api/v1/me/certificates/read', ['file' => certificateFile()], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.reader', null)->assertJsonPath('data.course', null);

        $mock->append(fn () => textractSaid(['name' => ['Pat Jordan', 98.0]]));
        $this->post('/api/v1/me/certificates/read', ['file' => certificateFile()], ['Accept' => 'application/json'])->assertJsonPath('data.name_matches', false);
    });

    it('needs a licence, a certificate file, a licence state, and a clean scan', function () {
        $other = User::factory()->create();
        $this->actingAs($other)->post('/api/v1/me/certificates/read', ['file' => certificateFile()], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->actingAs($this->james)->post('/api/v1/me/certificates/read', ['file' => UploadedFile::fake()->create('notes.docx', 10)], ['Accept' => 'application/json'])->assertUnprocessable();
        $upload = $this->post('/api/v1/me/certificates/read', ['file' => certificateFile()], ['Accept' => 'application/json'])->json('data.upload');
        $this->postJson('/api/v1/me/certificates', ['upload' => $upload, 'state' => 'LA', 'title' => 'x', 'hours' => 2, 'completed_on' => '2026-11-21'])->assertUnprocessable()->assertJsonValidationErrors('state');
        $this->postJson('/api/v1/me/certificates', ['upload' => $upload, 'state' => 'TX', 'title' => 'x', 'hours' => 2, 'completed_on' => '2026-12-21'])->assertUnprocessable()->assertJsonValidationErrors('completed_on');

        // The scanner finds a virus: the file goes, and the read can't be saved.
        app()->instance(FileScanner::class, new class implements FileScanner
        {
            public function scan($stream): ScanResult
            {
                return ScanResult::Infected;
            }
        });
        $read = CertificateRead::query()->where('uuid', $upload)->sole();
        (new ScanCertificate($read->path))->handle(app(FileScanner::class));
        Storage::disk(config()->string('reos.content.disk'))->assertMissing($read->path);
        $this->postJson('/api/v1/me/certificates', ['upload' => $upload, 'state' => 'TX', 'title' => 'x', 'hours' => 2, 'completed_on' => '2026-11-21'])->assertUnprocessable()->assertJsonValidationErrors('upload');
    });

    it('deletes reads not saved within a day', function () {
        $this->actingAs($this->james)->post('/api/v1/me/certificates/read', ['file' => certificateFile()], ['Accept' => 'application/json'])->assertOk();
        $path = CertificateRead::query()->sole()->path;

        $this->travel(25)->hours();
        $this->artisan('reos:certificates:prune')->assertSuccessful();

        expect(CertificateRead::query()->count())->toBe(0);
        Storage::disk(config()->string('reos.content.disk'))->assertMissing($path);
    });
});
