{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($pages as $page)
    <url>
        <loc>{{ route('agent-pages.show', $page->handle) }}</loc>
@if ($page->updated_at)
        <lastmod>{{ $page->updated_at->toDateString() }}</lastmod>
@endif
    </url>
@endforeach
</urlset>
