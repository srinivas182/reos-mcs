<?php

declare(strict_types=1);

use App\Authorization\Role;
use App\Models\Organisation;
use App\Models\State;
use App\Models\User;
use App\Services\Licences\Licences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/*
 * The digital card and the server-rendered public page (B14.1, ADR 0064):
 * contact details only as the agent chooses, a vCard, a QR code, FAQs and
 * schema.org data for search and answer engines, and the TREC notices.
 */

beforeEach(function () {
    Notification::fake();
    $this->org = Organisation::factory()->create(['name' => 'Lone Star Realty']);
    $this->maria = User::factory()->withRole(Role::Agent, $this->org)->create(['name' => 'Maria Garcia', 'email' => 'maria.private@example.com', 'phone' => '+18325550111']);
    app(Licences::class)->add($this->maria, State::query()->findOrFail('TX'), '0712345', null, null);
});

function publishCard(object $test, array $card = []): array
{
    return $test->actingAs($test->maria)->putJson('/api/v1/listings/page', ['published' => true, 'bio' => 'I help Katy families buy and sell with a clear plan.', ...$card])->assertOk()->json('data');
}

describe('the digital card (B14.1)', function () {
    it('shows only the contact details the agent adds, with a card, a contact file and a QR code', function () {
        $settings = publishCard($this, ['phone' => '(832) 555-0199', 'email' => 'maria@lonestar.example', 'areas' => ['Katy', 'Cinco Ranch', 'Katy']]);
        expect($settings)->toMatchArray(['phone' => '(832) 555-0199', 'email' => 'maria@lonestar.example', 'areas' => ['Katy', 'Cinco Ranch'],
            'card_url' => url('/agent/maria-garcia/card'), 'vcard_url' => url('/agent/maria-garcia/contact.vcf'), 'qr_url' => url('/agent/maria-garcia/qr.svg')]);
        auth()->forgetGuards();

        $this->get('/agent/maria-garcia/card')->assertOk()
            ->assertSee('Maria Garcia')->assertSee('Lone Star Realty')->assertSee('TX license #0712345')
            ->assertSee('href="tel:8325550199"', false)->assertSee('href="sms:8325550199"', false)->assertSee('mailto:maria@lonestar.example', false)
            ->assertSee('<svg', false)
            // The account's own email and phone never show.
            ->assertDontSee('maria.private@example.com')->assertDontSee('5550111');

        $vcard = $this->get('/agent/maria-garcia/contact.vcf')->assertOk()->assertHeader('Content-Type', 'text/vcard; charset=utf-8')->getContent();
        expect($vcard)->toContain("BEGIN:VCARD\r\nVERSION:3.0\r\nN:Garcia;Maria;;;\r\nFN:Maria Garcia\r\n")
            ->toContain("ORG:Lone Star Realty\r\n")->toContain("TEL;TYPE=CELL,VOICE:(832) 555-0199\r\n")
            ->toContain("NOTE:TX license #0712345\r\n")->toContain('URL:'.url('/agent/maria-garcia'));

        expect($this->get('/agent/maria-garcia/qr.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->getContent())->toStartWith('<?xml')->toContain('<svg');
    });

    it('clears contact details, keeps them valid, and shows nothing for an unpublished page', function () {
        publishCard($this, ['phone' => '832 555 0199']);
        $this->putJson('/api/v1/listings/page', ['published' => true, 'bio' => 'I help Katy families buy and sell with a clear plan.', 'phone' => null, 'areas' => []])->assertOk()->assertJsonPath('data.phone', null)->assertJsonPath('data.areas', []);
        $this->putJson('/api/v1/listings/page', ['published' => true, 'phone' => 'call me maybe'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->putJson('/api/v1/listings/page', ['published' => true, 'email' => 'not-an-email'])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->putJson('/api/v1/listings/page', ['published' => false])->assertOk();
        auth()->forgetGuards();
        $this->get('/agent/maria-garcia/card')->assertNotFound();
        $this->get('/agent/maria-garcia/contact.vcf')->assertNotFound();
        $this->get('/agent/maria-garcia/qr.svg')->assertNotFound();
    });
});

describe('the public page for search and answer engines (B14.1)', function () {
    it('is plain server HTML with a title, description, canonical, schema.org data, FAQs and the TREC notices', function () {
        publishCard($this, ['phone' => '(832) 555-0199', 'areas' => ['Katy', 'Fulshear']]);
        auth()->forgetGuards();

        $html = (string) $this->get('/agent/maria-garcia')->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('<title>Maria Garcia, real estate agent</title>', false)
            ->assertSee('<meta name="description" content="Maria Garcia is a real estate agent with Lone Star Realty serving Katy, Fulshear.', false)
            ->assertSee('<link rel="canonical" href="'.url('/agent/maria-garcia').'">', false)
            ->assertDontSee('noindex')
            ->assertSee('Who is Maria Garcia?')->assertSee('Maria Garcia is a licensed real estate sales agent (TX license #0712345) with Lone Star Realty.')
            ->assertSee('Maria Garcia works with buyers and sellers in Katy, Fulshear.')
            ->assertSee('https://www.trec.texas.gov/forms/information-about-brokerage-services')
            ->assertSee('https://www.trec.texas.gov/forms/consumer-protection-notice')
            ->getContent();

        // No app script: the page doesn't need JavaScript.
        expect($html)->not->toContain('resources/js/app.tsx')->not->toContain('data-page=');

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $blocks);
        $agent = json_decode($blocks[1][0], true);
        $faq = json_decode($blocks[1][1], true);
        expect($agent)->toMatchArray(['@type' => 'RealEstateAgent', 'name' => 'Maria Garcia', 'telephone' => '(832) 555-0199', 'url' => url('/agent/maria-garcia')])
            ->and($agent['parentOrganization'])->toBe(['@type' => 'RealEstateAgent', 'name' => 'Lone Star Realty'])
            ->and($agent['areaServed'][1])->toBe(['@type' => 'Place', 'name' => 'Fulshear'])
            ->and($agent['hasCredential'][0]['recognizedBy']['name'])->toBe('Texas Real Estate Commission')
            ->and($faq['@type'])->toBe('FAQPage')
            ->and($faq['mainEntity'][0]['name'])->toBe('Who is Maria Garcia?');
    });

    it('is in Spanish for an agent who works in Spanish', function () {
        $this->maria->forceFill(['locale' => 'es'])->save();
        publishCard($this);
        auth()->forgetGuards();

        $this->get('/agent/maria-garcia')->assertOk()->assertSee('<html lang="es">', false)->assertSee('¿Quién es Maria Garcia?')->assertSee('Preguntas frecuentes');
    });

    it('lists published pages in the sitemap, and robots.txt points to it', function () {
        publishCard($this);
        $hidden = User::factory()->withRole(Role::Agent, $this->org)->create(['name' => 'James Miller']);
        $this->actingAs($hidden)->getJson('/api/v1/listings/page')->assertOk();
        auth()->forgetGuards();

        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8')
            ->assertSee('<loc>'.url('/agent/maria-garcia').'</loc>', false)->assertDontSee('james-miller');
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: '.url('/sitemap.xml'));
    });
});
