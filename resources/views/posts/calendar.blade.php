<x-app-layout>
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">Calendar Planner</h1>
            <p class="text-muted mb-0 small">Schedule visualization board mapping social updates monthly.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('posts.calendar', ['month' => $month == 1 ? 12 : $month - 1, 'year' => $month == 1 ? $year - 1 : $year]) }}" class="btn btn-outline-secondary btn-sm fw-bold">
                &larr; Prev Month
            </a>
            <span class="fw-bold px-2 text-dark">{{ $monthName }}</span>
            <a href="{{ route('posts.calendar', ['month' => $month == 12 ? 1 : $month + 1, 'year' => $month == 12 ? $year + 1 : $year]) }}" class="btn btn-outline-secondary btn-sm fw-bold">
                Next Month &rarr;
            </a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Calendar Grid -->
        <div class="col-12 col-lg-9">
            <div class="card border-0 shadow-sm overflow-hidden">
                <!-- Day Headers -->
                <div class="row g-0 text-center bg-light border-bottom text-muted fw-bold small py-2.5">
                    <div class="col">Sun</div>
                    <div class="col">Mon</div>
                    <div class="col">Tue</div>
                    <div class="col">Wed</div>
                    <div class="col">Thu</div>
                    <div class="col">Fri</div>
                    <div class="col">Sat</div>
                </div>

                <!-- Calendar Cells -->
                <div class="row g-0">
                    @foreach($daysGrid as $day)
                        <div class="col-1-7 border-end border-bottom p-2 d-flex flex-column justify-content-between min-vh-10 {{ $day['isCurrentMonth'] ? 'bg-white' : 'bg-light text-muted' }}" style="min-height: 110px; width: 14.285%;">
                            <!-- Date Num -->
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge rounded-circle {{ $day['isToday'] ? 'bg-primary text-white' : ($day['isCurrentMonth'] ? 'text-dark fw-bold' : 'text-muted') }}" style="font-size: 11px;">
                                    {{ $day['date']->format('d') }}
                                </span>
                            </div>

                            <!-- Indicators -->
                            <div class="mt-2 space-y-1 overflow-y-auto" style="max-height: 60px;">
                                @foreach($day['targets'] as $target)
                                    <a href="{{ route('posts.calendar', ['month' => $month, 'year' => $year, 'selected_target' => $target->id]) }}" class="d-block text-decoration-none px-2 py-0.5 rounded mb-1 text-truncate border font-bold" style="font-size: 9px; {{ $target->provider->value === 'facebook' ? 'background-color:#e7f1ff; color:#0d6efd; border-color:#cff4fc;' : ($target->provider->value === 'instagram' ? 'background-color:#f8d7da; color:#b02a37; border-color:#f5c2c7;' : 'background-color:#f8d7da; color:#dc3545; border-color:#f5c2c7;') }}">
                                        [{{ ucfirst($target->provider->value) }}] {{ $target->post->title }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Sidebar selected target details -->
        <div class="col-12 col-lg-3">
            <div class="card border-0 shadow-sm p-4 h-100 min-vh-30">
                @if($selectedTarget)
                    <div class="d-flex justify-content-between border-bottom pb-2 mb-3">
                        <h5 class="fw-bold text-dark mb-0">Post Details</h5>
                        <a href="{{ route('posts.calendar', ['month' => $month, 'year' => $year]) }}" class="btn-close" aria-label="Close"></a>
                    </div>

                    <div class="d-flex flex-column gap-3">
                        <div>
                            <span class="text-uppercase text-muted fw-bold d-block mb-1" style="font-size: 9px;">Title</span>
                            <h6 class="fw-bold text-dark mb-0">{{ $selectedTarget->post->title }}</h6>
                        </div>

                        @if($selectedTarget->hasDisplayableMedia())
                            <div class="border rounded overflow-hidden" style="max-height: 140px;">
                                @if($selectedTarget->isVideoMedia())
                                    <video src="{{ $selectedTarget->resolvedMediaUrl() }}" class="w-100 object-fit-cover" muted playsinline></video>
                                @else
                                    <img src="{{ $selectedTarget->resolvedMediaUrl() }}" class="w-100 object-fit-cover cursor-zoom-in" alt=""
                                         data-pp-lightbox="{{ $selectedTarget->resolvedMediaUrl() }}"
                                         title="View full image">
                                @endif
                            </div>
                        @endif

                        <div>
                            <span class="text-uppercase text-muted fw-bold d-block mb-1" style="font-size: 9px;">Caption</span>
                            <div class="p-2.5 bg-light rounded border text-muted small" style="white-space: pre-wrap; max-height: 100px; overflow-y:auto;">{{ $selectedTarget->post->caption }}</div>
                        </div>

                        <div class="row g-2 text-xs">
                            <div class="col-6">
                                <span class="text-uppercase text-muted fw-bold d-block" style="font-size: 8px;">Network</span>
                                <span class="fw-bold text-primary">{{ ucfirst($selectedTarget->provider->value) }}</span>
                            </div>
                            <div class="col-6">
                                <span class="text-uppercase text-muted fw-bold d-block" style="font-size: 8px;">Time</span>
                                <span class="fw-bold text-dark">{{ $selectedTarget->scheduled_at->timezone($selectedTarget->timezone)->format('H:i') }}</span>
                            </div>
                        </div>

                        <div>
                            <span class="text-uppercase text-muted fw-bold d-block mb-1" style="font-size: 9px;">Status</span>
                            @if($selectedTarget->status->value === 'published')
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Published</span>
                            @elseif($selectedTarget->status->value === 'failed')
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Failed</span>
                            @else
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Scheduled</span>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-info-circle fs-3 mb-2"></i>
                        <p class="small mb-0">Select any scheduled post on the calendar cells to view target options.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
