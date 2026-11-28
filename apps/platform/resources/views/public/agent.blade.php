@extends('layouts.public', [
    'title' => __('listings.public.title', ['name' => $page['name']]),
    'description' => __('cards.metaDescription', ['name' => $page['name'], 'brokerage' => $page['brokerage'] ? __('cards.withBrokerage', ['brokerage' => $page['brokerage']]) : '', 'areas' => $page['areas'] !== [] ? __('cards.serving', ['areas' => implode(', ', $page['areas'])]) : '']),
    'canonical' => $page['url'],
    'noindex' => $page['sample'],
    'schema' => $schema,
])

@section('content')
<div class="mx-auto max-w-5xl space-y-10 px-4 py-10">
    <header class="space-y-3">
        <h1 class="text-3xl font-semibold">{{ $page['name'] }}</h1>
        @if ($page['brokerage'])
            <p class="text-lg">{{ $page['brokerage'] }}</p>
        @endif
        @if ($page['licences'] !== [])
            <ul class="flex flex-wrap gap-x-4 gap-y-1 text-base text-muted-foreground">
                @foreach ($page['licences'] as $licence)
                    <li>{{ __('listings.public.license', ['state' => $licence['state'], 'number' => $licence['number']]) }}</li>
                @endforeach
            </ul>
        @endif
        @if ($page['peer_rating'])
            <p class="text-base">{{ __('feedback.publicLine', ['average' => number_format($page['peer_rating']['average'], 1), 'count' => $page['peer_rating']['count']]) }}</p>
        @endif
        @if ($page['bio'])
            <p class="max-w-3xl text-lg">{{ $page['bio'] }}</p>
        @endif
        <div class="flex flex-wrap gap-3 pt-2">
            @if ($page['phone'])
                <a href="tel:{{ preg_replace('/[^+0-9]/', '', $page['phone']) }}" class="inline-flex min-h-12 items-center rounded-reos-md bg-primary px-5 text-base font-semibold text-primary-foreground">{{ __('cards.call') }}</a>
            @endif
            @if ($page['email'])
                <a href="mailto:{{ $page['email'] }}" class="inline-flex min-h-12 items-center rounded-reos-md border border-border bg-card px-5 text-base font-semibold">{{ __('cards.email') }}</a>
            @endif
            <a href="{{ route('agent-pages.vcard', $page['handle']) }}" class="inline-flex min-h-12 items-center rounded-reos-md border border-border bg-card px-5 text-base font-semibold">{{ __('cards.save') }}</a>
            <a href="{{ route('agent-pages.card', $page['handle']) }}" class="inline-flex min-h-12 items-center rounded-reos-md border border-border bg-card px-5 text-base font-semibold">{{ __('cards.open') }}</a>
        </div>
    </header>

    @if ($page['sample'])
        <p class="text-base text-muted-foreground">
            <span class="mr-3 rounded-reos-pill bg-reos-tone-info-bg px-3 py-1 font-semibold text-reos-tone-info-fg">{{ __('listings.sample') }}</span>
            {{ __('listings.public.sampleNote') }}
        </p>
    @endif

    <section aria-labelledby="agent-listings" class="space-y-4">
        <h2 id="agent-listings" class="text-xl font-semibold">{{ __('listings.public.listingsTitle') }}</h2>
        @if ($page['listings'] === [])
            <p class="rounded-reos-lg border border-border bg-card p-5 text-base text-muted-foreground">{{ __('listings.public.noListings') }}</p>
        @else
            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($page['listings'] as $listing)
                    <li class="overflow-hidden rounded-reos-lg border border-border bg-card">
                        @if ($listing['photo'])
                            <img src="{{ $listing['photo']['url'] }}" alt="{{ $listing['photo']['caption'] ?? __('cards.photoOf', ['address' => $listing['address']]) }}" width="640" height="427" loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async" class="aspect-[3/2] w-full object-cover">
                        @endif
                        <div class="space-y-1 p-4">
                            <p class="text-sm font-semibold uppercase text-muted-foreground">{{ __('listings.status.'.$listing['status']) }}</p>
                            @if ($listing['price'] !== null)
                                <p class="text-xl font-semibold">${{ number_format($listing['price']) }}</p>
                            @endif
                            <h3 class="text-base font-medium">{{ $listing['address'] }}@if ($listing['city']), {{ $listing['city'] }}@endif</h3>
                            <p class="text-base text-muted-foreground">
                                @if ($listing['beds'] !== null){{ __('cards.beds', ['count' => $listing['beds']]) }} · @endif
                                @if ($listing['baths'] !== null){{ __('cards.baths', ['count' => rtrim(rtrim(number_format($listing['baths'], 1), '0'), '.')]) }} · @endif
                                @if ($listing['area'] !== null){{ __('cards.area', ['area' => number_format($listing['area'])]) }}@endif
                            </p>
                            <p class="text-sm text-muted-foreground">{{ $listing['attribution'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($page['client_reviews'])
        <section aria-labelledby="agent-reviews" class="space-y-4">
            <h2 id="agent-reviews" class="text-xl font-semibold">{{ __('cards.reviewsTitle') }}</h2>
            <p class="text-base">{{ __('cards.reviewLine', ['average' => number_format($page['client_reviews']['average'], 1), 'count' => $page['client_reviews']['count']]) }}</p>
            <ul class="grid gap-4 sm:grid-cols-2">
                @foreach ($page['client_reviews']['latest'] as $review)
                    <li class="rounded-reos-lg border border-border bg-card p-5">
                        <figure class="space-y-2">
                            @if ($review['comment'])
                                <blockquote class="text-base">“{{ $review['comment'] }}”</blockquote>
                            @endif
                            <figcaption class="text-sm text-muted-foreground">{{ __('cards.reviewBy', ['name' => $review['name'], 'rating' => $review['rating']]) }}</figcaption>
                        </figure>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section aria-labelledby="agent-faqs" class="space-y-4">
        <h2 id="agent-faqs" class="text-xl font-semibold">{{ __('cards.faqTitle') }}</h2>
        <dl class="space-y-4">
            @foreach ($faqs as $faq)
                <div class="rounded-reos-lg border border-border bg-card p-5">
                    <dt class="text-lg font-semibold">{{ $faq['question'] }}</dt>
                    <dd class="pt-1 text-base break-words">{{ $faq['answer'] }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    <footer class="flex flex-wrap gap-x-6 gap-y-2 border-t border-border pt-6 text-base">
        <a class="underline" href="{{ \App\Compliance\TexasAdRules::IABS }}" rel="noopener">{{ __('listings.public.iabs') }}</a>
        <a class="underline" href="{{ \App\Compliance\TexasAdRules::CPN }}" rel="noopener">{{ __('listings.public.cpn') }}</a>
    </footer>
</div>
@endsection
