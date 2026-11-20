<?php

declare(strict_types=1);

use App\Enums\Appearance;
use App\Enums\TextSize;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defaults new people to the system theme and standard text', function () {
    $user = User::factory()->create()->fresh();

    expect($user->appearance)->toBe(Appearance::System)
        ->and($user->text_size)->toBe(TextSize::Standard);
});

it('saves the theme and text size to the account and sets first-paint cookies', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('appearance.edit'))
        ->patch(route('appearance.update'), ['appearance' => 'contrast', 'text_size' => 'largest'])
        ->assertRedirect(route('appearance.edit'))
        ->assertPlainCookie('appearance', 'contrast')
        ->assertPlainCookie('text_size', 'largest');

    $user->refresh();
    expect($user->appearance)->toBe(Appearance::Contrast)
        ->and($user->text_size)->toBe(TextSize::Largest);
});

it('saves one preference without touching the other', function () {
    $user = User::factory()->create(['appearance' => 'dark', 'text_size' => 'large']);

    $this->actingAs($user)->patch(route('appearance.update'), ['text_size' => 'standard'])->assertRedirect();

    $user->refresh();
    expect($user->appearance)->toBe(Appearance::Dark)
        ->and($user->text_size)->toBe(TextSize::Standard);
});

it('rejects values that are not real themes or sizes', function (array $payload, string $field) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('appearance.update'), $payload)
        ->assertSessionHasErrors($field);
})->with([
    'unknown theme' => [['appearance' => 'neon'], 'appearance'],
    'unknown size' => [['text_size' => 'huge'], 'text_size'],
    'empty theme' => [['appearance' => ''], 'appearance'],
]);

it('requires sign-in to save preferences', function () {
    $this->patch(route('appearance.update'), ['appearance' => 'dark'])->assertRedirect(route('login'));
});

it('renders the saved theme and text size on the first paint', function () {
    $user = User::factory()->create(['appearance' => 'contrast', 'text_size' => 'large']);

    $this->actingAs($user)->get(route('appearance.edit'))
        ->assertOk()
        ->assertSee('data-theme="contrast"', false)
        ->assertSee('data-text-size="large"', false)
        ->assertInertia(fn ($page) => $page
            ->where('preferences.appearance', 'contrast')
            ->where('preferences.textSize', 'large'));
});

it('lets the account choice win over an old device cookie', function () {
    $user = User::factory()->create(['appearance' => 'dark', 'text_size' => 'standard']);

    $this->actingAs($user)
        ->withUnencryptedCookies(['appearance' => 'light', 'text_size' => 'largest'])
        ->get(route('appearance.edit'))
        ->assertSee('class="dark"', false)
        ->assertSee('data-text-size="standard"', false);
});

it('uses the device cookies for signed-out pages and ignores bad values', function () {
    $this->withUnencryptedCookies(['appearance' => 'dark', 'text_size' => 'largest'])
        ->get(route('login'))
        ->assertSee('data-theme="dark"', false)
        ->assertSee('data-text-size="largest"', false);

    $this->withUnencryptedCookies(['appearance' => '<script>', 'text_size' => 'huge'])
        ->get(route('login'))
        ->assertDontSee('data-theme="', false)
        ->assertSee('data-text-size="standard"', false);
});
