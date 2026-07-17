@props(['title', 'description' => null, 'breadcrumb' => null])

<div class="pp-page-header pp-reveal is-visible">
    <div class="pp-page-header-copy min-w-0 flex-1">
        @if($breadcrumb)
            <nav class="pp-page-breadcrumb">
                {!! $breadcrumb !!}
            </nav>
        @endif
        <h2 class="pp-page-title">{{ $title }}</h2>
        @if($description)
            <p class="pp-page-desc">{{ $description }}</p>
        @endif
    </div>
    @if(isset($actions))
        <div class="pp-page-actions">{{ $actions }}</div>
    @endif
</div>
