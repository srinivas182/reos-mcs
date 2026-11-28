@extends('layouts.public', ['title' => __('portal.simulator.title'), 'description' => __('portal.simulator.body'), 'canonical' => url()->current(), 'noindex' => true])

@section('content')
<div class="mx-auto max-w-md space-y-4 px-4 py-10">
    <h1 class="text-2xl font-semibold">{{ __('portal.simulator.title') }}</h1>
    <p class="text-base text-muted-foreground">{{ __('portal.simulator.body') }}</p>
    <form method="POST" action="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('portal.simulator.finish', now()->addMinutes(5), $query) }}">
        @csrf
        <button type="submit" class="inline-flex min-h-12 items-center rounded-reos-md bg-primary px-5 text-base font-semibold text-primary-foreground">{{ __('portal.simulator.finish') }}</button>
    </form>
</div>
@endsection
