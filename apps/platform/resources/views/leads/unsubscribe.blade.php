@extends('layouts.public', [
    'title' => __('leads.farm.unsubscribe.title', ['agent' => $agent]),
    'description' => __('leads.farm.unsubscribe.purpose', ['agent' => $agent]),
    'canonical' => url()->current(),
    'noindex' => true,
])

@section('content')
<div class="mx-auto max-w-lg space-y-6 px-4 py-10">
    <h1 class="text-2xl font-semibold">{{ __('leads.farm.unsubscribe.title', ['agent' => $agent]) }}</h1>
    @if ($done)
        <p role="status" class="rounded-reos-lg bg-reos-tone-success-bg p-5 text-lg text-reos-tone-success-fg">{{ __('leads.farm.unsubscribe.done', ['agent' => $agent]) }}</p>
    @else
        <p class="text-lg text-muted-foreground">{{ __('leads.farm.unsubscribe.purpose', ['agent' => $agent]) }}</p>
        <form method="POST" action="{{ $action }}">
            @csrf
            <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-reos-md bg-primary px-5 text-base font-semibold text-primary-foreground">{{ __('leads.farm.unsubscribe.confirm') }}</button>
        </form>
    @endif
</div>
@endsection
