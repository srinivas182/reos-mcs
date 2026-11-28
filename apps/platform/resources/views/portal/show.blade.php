@extends('layouts.public', [
    'title' => __('portal.title', ['property' => $view['deal']['property']]),
    'description' => __('portal.purpose', ['agent' => $view['agent']['name']]),
    'canonical' => url()->current(),
    'noindex' => true,
])

@section('content')
@php($agent = $view['agent'])
@php($deal = $view['deal'])
<div class="mx-auto max-w-2xl space-y-6 px-4 py-8">
    <header class="space-y-2">
        <p class="text-base text-muted-foreground">{{ __('portal.hello', ['name' => $view['client']]) }}</p>
        <h1 class="text-2xl font-semibold">{{ $deal['property'] }}@if ($deal['city']), {{ $deal['city'] }}@endif</h1>
        <p class="inline-flex rounded-reos-pill bg-reos-tone-info-bg px-3 py-1 text-base font-semibold text-reos-tone-info-fg">{{ __('deals.stages.'.$deal['stage']) }}</p>
        @if ($deal['price'] !== null)
            <p class="text-lg">${{ number_format($deal['price']) }}</p>
        @endif
    </header>

    @if ($reviewed)
        <p role="status" class="rounded-reos-md bg-reos-tone-success-bg p-4 text-base text-reos-tone-success-fg">{{ __('portal.thanks') }}</p>
    @endif

    @if ($errors->any())
        <p role="alert" class="rounded-reos-md bg-reos-tone-danger-bg p-4 text-base text-reos-tone-danger-fg">{{ $errors->first() }}</p>
    @endif

    @if ($view['signatures'] !== [])
        <section aria-labelledby="to-sign" class="space-y-3 rounded-reos-lg border border-border bg-card p-5">
            <h2 id="to-sign" class="text-lg font-semibold">{{ __('portal.toSign') }}</h2>
            <ul class="space-y-3">
                @foreach ($view['signatures'] as $signature)
                    <li class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-base font-medium">{{ $signature['subject'] }}</p>
                            <p class="text-base text-muted-foreground">{{ __('portal.signStatus.'.(in_array($signature['mine'], ['signed', 'completed'], true) ? 'signed' : ($signature['can_sign'] ? 'yourTurn' : ($signature['in_portal'] ? 'waiting' : 'byEmail')))) }}</p>
                        </div>
                        @if ($signature['can_sign'])
                            <form method="POST" action="{{ route('portal.sign', ['token' => $token, 'signature' => $signature['id']]) }}">
                                @csrf
                                <button type="submit" class="inline-flex min-h-12 items-center rounded-reos-md bg-primary px-5 text-base font-semibold text-primary-foreground">{{ __('portal.sign') }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section aria-labelledby="key-dates" class="space-y-3 rounded-reos-lg border border-border bg-card p-5">
        <h2 id="key-dates" class="text-lg font-semibold">{{ __('portal.dates') }}</h2>
        @if ($view['dates'] === [])
            <p class="text-base text-muted-foreground">{{ __('portal.noDates') }}</p>
        @else
            <ol class="space-y-2">
                @foreach ($view['dates'] as $date)
                    <li class="flex flex-wrap justify-between gap-2 text-base">
                        <span @class(['line-through text-muted-foreground' => $date['done']])>{{ $date['label'] ?? __('deals.kinds.'.$date['kind']) }}</span>
                        <time datetime="{{ $date['due_on'] }}" class="font-medium">{{ \Carbon\CarbonImmutable::parse($date['due_on'])->locale($view['language'])->isoFormat('ddd, MMM D') }}</time>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    @if ($view['documents'] !== [])
        <section aria-labelledby="documents" class="space-y-3 rounded-reos-lg border border-border bg-card p-5">
            <h2 id="documents" class="text-lg font-semibold">{{ __('portal.documents') }}</h2>
            <ul class="space-y-2">
                @foreach ($view['documents'] as $document)
                    <li class="flex flex-wrap justify-between gap-2 text-base">
                        <span>{{ $document['name'] }}</span>
                        <span class="text-muted-foreground">{{ __('deals.fileStatus.'.$document['status']) }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($view['review']['asked'])
        <section aria-labelledby="review" class="space-y-3 rounded-reos-lg border border-reos-action bg-card p-5">
            <h2 id="review" class="text-lg font-semibold">{{ __('portal.reviewTitle', ['agent' => $agent['name']]) }}</h2>
            <p class="text-base text-muted-foreground">{{ __('portal.reviewHint') }}</p>
            <form method="POST" action="{{ route('portal.review', $token) }}" class="space-y-4">
                @csrf
                <fieldset>
                    <legend class="mb-2 text-base font-medium">{{ __('portal.rating') }}</legend>
                    <div class="flex flex-wrap gap-2">
                        @for ($star = 5; $star >= 1; $star--)
                            <label class="inline-flex min-h-12 cursor-pointer items-center gap-2 rounded-reos-md border border-border px-4 text-base has-[:checked]:border-reos-action has-[:checked]:bg-reos-tone-info-bg">
                                <input type="radio" name="rating" value="{{ $star }}" class="size-5" @checked(($view['review']['mine']['rating'] ?? 5) === $star) required>
                                {{ __('portal.stars', ['count' => $star]) }}
                            </label>
                        @endfor
                    </div>
                </fieldset>
                <div class="space-y-2">
                    <label for="comment" class="text-base font-medium">{{ __('portal.comment') }}</label>
                    <textarea id="comment" name="comment" rows="4" maxlength="1500" class="w-full rounded-reos-md border border-input bg-background p-3 text-base">{{ $view['review']['mine']['comment'] ?? '' }}</textarea>
                </div>
                <label class="flex min-h-12 items-start gap-3 text-base">
                    <input type="hidden" name="publish" value="0">
                    <input type="checkbox" name="publish" value="1" class="mt-1 size-5" @checked($view['review']['mine']['publish'] ?? false)>
                    <span>{{ __('portal.publish', ['agent' => $agent['name']]) }}</span>
                </label>
                <button type="submit" class="inline-flex min-h-12 items-center rounded-reos-md bg-primary px-5 text-base font-semibold text-primary-foreground">{{ $view['review']['mine'] ? __('portal.updateReview') : __('portal.sendReview') }}</button>
            </form>
        </section>
    @endif

    <section aria-labelledby="your-agent" class="space-y-3 rounded-reos-lg border border-border bg-card p-5">
        <h2 id="your-agent" class="text-lg font-semibold">{{ __('portal.yourAgent') }}</h2>
        <p class="text-base"><span class="font-semibold">{{ $agent['name'] }}</span>@if ($agent['brokerage']) · {{ $agent['brokerage'] }}@endif</p>
        <div class="flex flex-wrap gap-3">
            @if ($agent['phone'])
                <a href="tel:{{ preg_replace('/[^+0-9]/', '', $agent['phone']) }}" class="inline-flex min-h-12 items-center rounded-reos-md border border-border px-5 text-base font-semibold">{{ __('cards.call') }}</a>
            @endif
            @if ($agent['email'])
                <a href="mailto:{{ $agent['email'] }}" class="inline-flex min-h-12 items-center rounded-reos-md border border-border px-5 text-base font-semibold">{{ __('cards.email') }}</a>
            @endif
            @if ($agent['page_url'])
                <a href="{{ $agent['page_url'] }}" class="inline-flex min-h-12 items-center rounded-reos-md border border-border px-5 text-base font-semibold">{{ __('portal.listings') }}</a>
            @endif
        </div>
    </section>

    <footer class="space-y-2 border-t border-border pt-4 text-sm text-muted-foreground">
        <p>{{ __('portal.private') }}</p>
        <p class="flex flex-wrap gap-x-4 gap-y-1">
            <a class="underline" href="{{ \App\Compliance\TexasAdRules::IABS }}" rel="noopener">{{ __('cards.iabsShort') }}</a>
            <a class="underline" href="{{ \App\Compliance\TexasAdRules::CPN }}" rel="noopener">{{ __('cards.cpnShort') }}</a>
        </p>
    </footer>
</div>
@endsection
