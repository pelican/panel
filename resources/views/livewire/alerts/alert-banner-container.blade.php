<div id="alert-banner-container" @class([
    'flex flex-col gap-4',
    'mt-3' => count($alertBanners) > 0,
])>
    @foreach ($alertBanners as $alertBanner)
        {{ $alertBanner }}
    @endforeach
</div>
