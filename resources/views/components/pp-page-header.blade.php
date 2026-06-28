@props(['title', 'description' => null, 'breadcrumb' => null])

<div class="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
    <div>
        @if($breadcrumb)
            <nav class="flex gap-2 text-xs font-semibold text-[#737686] tracking-wide uppercase mb-2">
                {!! $breadcrumb !!}
            </nav>
        @endif
        <h2 class="font-display text-2xl font-semibold text-[#131b2e] tracking-tight">{{ $title }}</h2>
        @if($description)
            <p class="text-[#434655] text-sm mt-1">{{ $description }}</p>
        @endif
    </div>
    @if(isset($actions))
        <div class="flex gap-3 flex-wrap">{{ $actions }}</div>
    @endif
</div>
