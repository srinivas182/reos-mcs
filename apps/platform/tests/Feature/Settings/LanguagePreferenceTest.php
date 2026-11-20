<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defaults to English', function () {
    expect(User::factory()->create()->fresh()?->locale)->toBe(Locale::English);
});

it('saves the language to the account, sets a cookie and renders in it', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('appearance.update'), ['locale' => 'es'])
        ->assertRedirect()
        ->assertPlainCookie('locale', 'es');

    expect($user->refresh()->locale)->toBe(Locale::Spanish);

    $this->actingAs($user)->get(route('appearance.edit'))
        ->assertSee('<html lang="es"', false)
        ->assertInertia(fn ($page) => $page->where('preferences.locale', 'es'));
});

it('rejects languages REOS does not offer', function () {
    $this->actingAs(User::factory()->create())
        ->patch(route('appearance.update'), ['locale' => 'fr'])
        ->assertSessionHasErrors('locale');
});

it('uses the browser language for signed-out visitors', function () {
    $this->withHeader('Accept-Language', 'es-MX,es;q=0.9,en;q=0.8')
        ->get(route('login'))
        ->assertSee('<html lang="es"', false);

    $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
        ->get(route('login'))
        ->assertSee('<html lang="en"', false);
});

it('prefers the device cookie over the browser language, and the account over both', function () {
    $this->withUnencryptedCookies(['locale' => 'en'])
        ->withHeader('Accept-Language', 'es')
        ->get(route('login'))
        ->assertSee('<html lang="en"', false);

    $user = User::factory()->create(['locale' => 'es']);
    $this->actingAs($user)
        ->withUnencryptedCookies(['locale' => 'en'])
        ->get(route('appearance.edit'))
        ->assertSee('<html lang="es"', false);
});

it('gives Laravel the shared translations, with placeholders', function () {
    app()->setLocale('es');

    expect(__('nav.settings'))->toBe('Configuración')
        ->and(__('tour.stepOf', ['step' => 2, 'total' => 5]))->toBe('Paso 2 de 5');

    app()->setLocale('en');
    expect(__('nav.settings'))->toBe('Settings');
});

it('reads language tags', function (?string $tag, ?Locale $expected) {
    expect(Locale::fromTag($tag))->toBe($expected);
})->with([
    ['es-MX', Locale::Spanish],
    ['EN_us', Locale::English],
    ['es', Locale::Spanish],
    ['fr', null],
    ['', null],
    [null, null],
]);
