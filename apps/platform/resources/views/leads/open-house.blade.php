@extends('layouts.public', [
    'title' => __('leads.openHouse.title', ['address' => $house['address']]),
    'description' => __('leads.openHouse.purpose', ['agent' => $house['agent']]),
    'canonical' => url()->current(),
    'noindex' => true,
])

@section('content')
<div class="mx-auto max-w-lg space-y-6 px-4 py-8">
    <header class="space-y-1">
        <p class="text-base text-muted-foreground">{{ __('leads.openHouse.welcome') }}</p>
        <h1 class="text-2xl font-semibold">{{ $house['address'] }}@if ($house['city']), {{ $house['city'] }}@endif</h1>
        <p class="text-base">{{ __('leads.openHouse.hostedBy', ['agent' => $house['agent']]) }}@if ($house['brokerage']) · {{ $house['brokerage'] }}@endif</p>
    </header>

    @if ($done)
        <p role="status" class="rounded-reos-lg bg-reos-tone-success-bg p-5 text-lg text-reos-tone-success-fg">{{ __('leads.openHouse.thanks') }}</p>
    @else
        <form method="POST" action="{{ route('open-house.sign-in', $code) }}" class="space-y-4 rounded-reos-lg border border-border bg-card p-5">
            @csrf
            @if ($errors->any())
                <p role="alert" class="rounded-reos-md bg-reos-tone-danger-bg p-3 text-base text-reos-tone-danger-fg">{{ $errors->first() }}</p>
            @endif
            <div class="hidden" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
            <div class="space-y-2">
                <label for="name" class="text-base font-medium">{{ __('leads.form.name') }}</label>
                <input id="name" name="name" required minlength="2" maxlength="120" autocomplete="name" value="{{ old('name') }}" class="h-12 w-full rounded-reos-md border border-input bg-background px-3 text-base">
            </div>
            <div class="space-y-2">
                <label for="email" class="text-base font-medium">{{ __('leads.form.email') }}</label>
                <input id="email" name="email" type="email" maxlength="160" autocomplete="email" value="{{ old('email') }}" class="h-12 w-full rounded-reos-md border border-input bg-background px-3 text-base">
            </div>
            <div class="space-y-2">
                <label for="phone" class="text-base font-medium">{{ __('leads.form.phone') }}</label>
                <input id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" value="{{ old('phone') }}" aria-describedby="contact-hint" class="h-12 w-full rounded-reos-md border border-input bg-background px-3 text-base">
                <p id="contact-hint" class="text-sm text-muted-foreground">{{ __('leads.form.oneOf') }}</p>
            </div>
            <div class="space-y-2">
                <label for="timeline" class="text-base font-medium">{{ __('leads.form.timeline') }}</label>
                <select id="timeline" name="timeline" class="h-12 w-full rounded-reos-md border border-input bg-background px-3 text-base">
                    <option value="">{{ __('leads.form.choose') }}</option>
                    @foreach ($timelines as $timeline)
                        <option value="{{ $timeline }}" @selected(old('timeline') === $timeline)>{{ __('leads.timelines.'.$timeline) }}</option>
                    @endforeach
                </select>
            </div>
            <label class="flex min-h-12 items-start gap-3 text-base">
                <input type="hidden" name="has_agent" value="0">
                <input type="checkbox" name="has_agent" value="1" class="mt-1 size-5" @checked(old('has_agent'))>
                <span>{{ __('leads.form.hasAgent') }}</span>
            </label>
            <label class="flex min-h-12 items-start gap-3 text-base">
                <input type="hidden" name="pre_approved" value="0">
                <input type="checkbox" name="pre_approved" value="1" class="mt-1 size-5" @checked(old('pre_approved'))>
                <span>{{ __('leads.form.preApproved') }}</span>
            </label>
            <label class="flex min-h-12 items-start gap-3 text-base">
                <input type="checkbox" name="consent" value="1" required class="mt-1 size-5">
                <span>{{ __('leads.form.consent', ['agent' => $house['agent']]) }}</span>
            </label>
            <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-reos-md bg-primary px-5 text-base font-semibold text-primary-foreground">{{ __('leads.openHouse.signIn') }}</button>
        </form>
    @endif

    <footer class="flex flex-wrap gap-x-4 gap-y-1 border-t border-border pt-4 text-sm">
        <a class="underline" href="{{ \App\Compliance\TexasAdRules::IABS }}" rel="noopener">{{ __('cards.iabsShort') }}</a>
        <a class="underline" href="{{ \App\Compliance\TexasAdRules::CPN }}" rel="noopener">{{ __('cards.cpnShort') }}</a>
    </footer>
</div>
@endsection
