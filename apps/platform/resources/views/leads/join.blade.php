@extends('layouts.public', [
    'title' => __('leads.invites.join.title', ['name' => $name]),
    'description' => __('leads.invites.join.purpose'),
    'canonical' => url()->current(),
    'noindex' => true,
])

@section('content')
<div class="mx-auto max-w-lg space-y-6 px-4 py-10">
    <header class="space-y-2">
        <h1 class="text-2xl font-semibold">{{ __('leads.invites.join.title', ['name' => $name]) }}</h1>
        <p class="text-lg text-muted-foreground">{{ __('leads.invites.join.purpose') }}</p>
    </header>

    <div class="space-y-4 rounded-reos-lg border border-border bg-card p-5">
        @if ($signedIn)
            <p class="text-base">{{ __('leads.invites.join.signedIn') }}</p>
            <a href="{{ url('/dashboard') }}" class="inline-flex h-12 items-center justify-center rounded-reos-md bg-primary px-6 text-base font-semibold text-primary-foreground">{{ __('leads.invites.join.open') }}</a>
        @else
            <a href="{{ route('register') }}" class="inline-flex h-12 w-full items-center justify-center rounded-reos-md bg-primary px-6 text-base font-semibold text-primary-foreground">{{ __('leads.invites.join.create') }}</a>
            <p class="text-base text-muted-foreground">{{ __('leads.invites.join.app') }}</p>
            <p class="text-center font-mono text-2xl tracking-widest" aria-label="{{ __('leads.invites.join.codeLabel') }}">{{ $code }}</p>
        @endif
    </div>
</div>
@endsection
