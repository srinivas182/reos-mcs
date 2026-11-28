@extends('layouts.public', [
    'title' => __('cards.title', ['name' => $page['name']]),
    'description' => __('cards.metaDescription', ['name' => $page['name'], 'brokerage' => $page['brokerage'] ? __('cards.withBrokerage', ['brokerage' => $page['brokerage']]) : '', 'areas' => $page['areas'] !== [] ? __('cards.serving', ['areas' => implode(', ', $page['areas'])]) : '']),
    'canonical' => route('agent-pages.card', $page['handle']),
    'noindex' => $page['sample'],
])

@section('content')
<div class="mx-auto max-w-md px-4 py-10">
    <article class="space-y-5 rounded-reos-lg border border-border bg-card p-6 text-center">
        <div aria-hidden="true" class="mx-auto flex size-20 items-center justify-center rounded-reos-pill bg-reos-tone-info-bg text-2xl font-semibold text-reos-tone-info-fg">{{ mb_strtoupper(collect(preg_split('/\s+/u', trim($page['name'])))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('')) }}</div>
        <div class="space-y-1">
            <h1 class="text-2xl font-semibold">{{ $page['name'] }}</h1>
            <p class="text-base">{{ __('cards.vcardTitle') }}</p>
            @if ($page['brokerage'])
                <p class="text-lg font-medium">{{ $page['brokerage'] }}</p>
            @endif
            @foreach ($page['licences'] as $licence)
                <p class="text-base text-muted-foreground">{{ __('listings.public.license', ['state' => $licence['state'], 'number' => $licence['number']]) }}</p>
            @endforeach
        </div>
        <div class="grid gap-3">
            @if ($page['phone'])
                <a href="tel:{{ preg_replace('/[^+0-9]/', '', $page['phone']) }}" class="inline-flex min-h-12 items-center justify-center rounded-reos-md bg-primary px-5 text-base font-semibold text-primary-foreground">{{ __('cards.call') }}</a>
                <a href="sms:{{ preg_replace('/[^+0-9]/', '', $page['phone']) }}" class="inline-flex min-h-12 items-center justify-center rounded-reos-md border border-border px-5 text-base font-semibold">{{ __('cards.text') }}</a>
            @endif
            @if ($page['email'])
                <a href="mailto:{{ $page['email'] }}" class="inline-flex min-h-12 items-center justify-center rounded-reos-md border border-border px-5 text-base font-semibold">{{ __('cards.email') }}</a>
            @endif
            <a href="{{ route('agent-pages.vcard', $page['handle']) }}" class="inline-flex min-h-12 items-center justify-center rounded-reos-md border border-border px-5 text-base font-semibold">{{ __('cards.save') }}</a>
            <a href="{{ $page['url'] }}" class="inline-flex min-h-12 items-center justify-center rounded-reos-md border border-border px-5 text-base font-semibold">{{ __('cards.viewPage') }}</a>
        </div>
        <figure class="space-y-2">
            <div role="img" aria-label="{{ __('cards.qrAlt', ['name' => $page['name']]) }}" class="mx-auto w-60 rounded-reos-md bg-card p-2">{!! $qr !!}</div>
            <figcaption class="text-base text-muted-foreground">{{ __('cards.scan') }}</figcaption>
        </figure>
        <p class="flex flex-wrap justify-center gap-x-4 gap-y-1 border-t border-border pt-4 text-sm">
            <a class="underline" href="{{ \App\Compliance\TexasAdRules::IABS }}" rel="noopener">{{ __('cards.iabsShort') }}</a>
            <a class="underline" href="{{ \App\Compliance\TexasAdRules::CPN }}" rel="noopener">{{ __('cards.cpnShort') }}</a>
        </p>
    </article>
</div>
@endsection
