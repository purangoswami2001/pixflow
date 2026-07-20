<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Models\Post;
use App\Models\PostTarget;
use App\Models\SocialAccount;
use App\Models\SocialDestination;
use App\Models\MediaAsset;
use App\Models\ContentCategory;
use App\Models\RecurringSchedule;
use App\Support\AutomationImageStyles;
use App\Support\PerPage;
use App\Enums\PostStatus;
use App\Enums\PostType;
use App\Enums\ProviderType;
use App\Jobs\PublishPostTargetJob;
use App\Services\Posts\PostSchedulerService;
use App\Exceptions\AiGenerationException;
use App\Services\Ai\AiConfig;
use App\Services\Posts\CaptionSuggestionService;
use App\Services\Posts\PlatformPostFieldsService;
use App\Services\Posts\PostMediaArchiveService;
use App\Services\Posts\QueueScheduleCalculatorService;
use App\Services\Media\MediaConfigService;
use App\Services\Social\SocialAccountManager;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PostController extends Controller
{
    use ResolvesOrganization;

    protected PostSchedulerService $scheduler;

    public function __construct(
        PostSchedulerService $scheduler,
        protected MediaConfigService $mediaConfig,
        protected QueueScheduleCalculatorService $queueSchedule,
        protected SocialAccountManager $accountManager,
        protected PlatformPostFieldsService $platformFields,
        protected PostMediaArchiveService $postMediaArchive,
        protected \App\Services\Posts\PostReviewService $postReviews,
    ) {
        $this->scheduler = $scheduler;
    }

    public function index(Request $request)
    {
        $statusFilter = $request->query('status', $request->query('tab', 'all'));
        $channelFilter = $request->query('channel', 'all');
        $search = trim((string) $request->query('search', ''));
        $isAdmin = auth()->user()->isAdmin();

        $query = Post::with(['user', 'targets.socialAccount', 'targets.socialDestination', 'recurringSchedule'])
            ->forCurrentOrganization();

        if (! $isAdmin) {
            $query->where('user_id', auth()->id());
        }

        if ($statusFilter === 'queued') {
            $query->where('status', PostStatus::APPROVED);
        } elseif ($statusFilter !== 'all' && $statusFilter !== '') {
            $query->where('status', PostStatus::from($statusFilter));
        }

        if (in_array($channelFilter, ['facebook', 'instagram', 'pinterest'], true)) {
            $query->whereHas('targets', fn ($q) => $q->where('provider', $channelFilter));
        }

        if ($request->query('source') === 'automation') {
            $query->where('metadata->source', 'automation');
        }

        if ($search !== '') {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('caption', 'like', $term)
                    ->orWhere('hashtags', 'like', $term)
                    ->orWhere('tags', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term))
                    ->orWhereHas('targets.socialAccount', function ($a) use ($term) {
                        $a->where('name', 'like', $term)
                            ->orWhere('username', 'like', $term);
                    });
            });
        }

        $query->orderBy('created_at', 'desc');

        $perPage = PerPage::resolve($request, 8, [8, 10, 20, 40, 50]);

        $posts = $query->paginate($perPage)->withQueryString();
        $scheduleMap = $this->queueSchedule->targetScheduleMap();

        $countsQuery = Post::query()->forCurrentOrganization();
        if (! $isAdmin) {
            $countsQuery->where('user_id', auth()->id());
        }

        $viewPost = null;
        if ($request->filled('view')) {
            $viewQuery = Post::with(['targets.socialAccount', 'targets.socialDestination', 'targets.mediaAsset', 'user'])
                ->forCurrentOrganization();
            if (! $isAdmin) {
                $viewQuery->where('user_id', auth()->id());
            }
            $viewPost = $viewQuery->find((int) $request->query('view'));
        }

        return view('posts.index', [
            'posts' => $posts,
            'perPage' => $perPage,
            'statusFilter' => $statusFilter,
            'channelFilter' => $channelFilter,
            'search' => $search,
            'isAdmin' => $isAdmin,
            'channels' => $this->mediaConfig->channels(),
            'pendingCount' => (clone $countsQuery)->where('status', PostStatus::PENDING_APPROVAL)->count(),
            'scheduleMap' => $scheduleMap,
            'viewPost' => $viewPost,
        ]);
    }

    public function create()
    {
        return view('posts.create', $this->postFormViewData());
    }

    public function edit($id)
    {
        $post = $this->findEditablePost($id);
        $post->load(['targets.socialAccount', 'targets.socialDestination']);

        return view('posts.create', array_merge($this->postFormViewData(), [
            'post' => $post,
            'editing' => true,
            'editPayload' => $this->buildEditPayload($post),
        ]));
    }

    protected function postFormViewData(): array
    {
        $accounts = $this->organizationAccounts();

        $accountsJson = $accounts->map(function ($account) {
            $destinations = $account->destinations
                ->where('status', 'active')
                ->sortBy('name')
                ->values()
                ->map(fn ($destination) => [
                    'id' => $destination->id,
                    'name' => $destination->name,
                    'label' => $account->provider === ProviderType::PINTEREST
                        ? $account->name . ' → ' . $destination->name
                        : $destination->name,
                ]);

            return [
                'id' => $account->id,
                'provider' => $account->provider->value,
                'name' => $account->name,
                'username' => $account->displayHandle(),
                'ready' => $destinations->isNotEmpty(),
                'destinations' => $destinations,
            ];
        })->values();

        $categories = ContentCategory::query()
            ->forCurrentOrganization()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'niche_prompt', 'audience']);

        $imageStyles = collect(AutomationImageStyles::options())->map(fn ($opt, $key) => [
            'key' => $key,
            'label' => $opt['label'],
            'description' => $opt['description'],
        ])->values()->all();

        return [
            'accounts' => $accounts,
            'accountsJson' => $accountsJson,
            'channels' => $this->mediaConfig->channels(),
            'presets' => config('social-platforms.presets'),
            'aiConfigured' => AiConfig::isConfigured(),
            'aiModeEnabled' => (bool) $this->currentOrganization()->ai_enabled,
            'aiProvider' => AiConfig::providerLabel(),
            'platformFields' => config('post-platform-fields'),
            'contentCategories' => $categories,
            'imageStyles' => $imageStyles,
            'editing' => false,
            'post' => null,
            'editPayload' => null,
        ];
    }

    protected function organizationAccounts()
    {
        return SocialAccount::with('destinations')
            ->forCurrentOrganization()
            ->where('status', 'active')
            ->get();
    }

    protected function scopedPosts()
    {
        $query = Post::query()->forCurrentOrganization();

        if (! auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        return $query;
    }

    protected function scopedMedia()
    {
        $query = MediaAsset::query()->forCurrentOrganization();

        if (! auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        return $query;
    }

    protected function findEditablePost($id): Post
    {
        $post = $this->scopedPosts()->findOrFail($id);

        abort_unless(
            $post->canBeEditedBy(auth()->user()),
            403,
            'This post cannot be edited.'
        );

        return $post;
    }

    protected function findDeletablePost($id): Post
    {
        $post = $this->scopedPosts()->findOrFail($id);

        abort_unless(
            $post->canBeDeletedBy(auth()->user()),
            403,
            'This post cannot be deleted.'
        );

        return $post;
    }

    protected function buildEditPayload(Post $post): array
    {
        $target = $post->targets->first();
        $wizard = $post->metadata['wizard'] ?? null;
        $wizardState = is_array($wizard['state'] ?? null) ? $wizard['state'] : [];
        $wizardMedia = null;

        if (! $target && ! empty($wizardState['selected_media_id'])) {
            $wizardMedia = $this->scopedMedia()->find($wizardState['selected_media_id']);
        }

        return [
            'channel' => $target?->provider->value ?? ($wizardState['channel'] ?? null),
            'media_type' => $target?->archivedMediaType() ?? ($wizardState['media_type'] ?? null),
            'preset_key' => $target?->media_snapshot['preset_key'] ?? ($wizardState['preset_key'] ?? null),
            'media_id' => $wizardMedia?->id,
            'media_url' => $target?->resolvedMediaUrl() ?? $wizardMedia?->url,
            'media_name' => $target?->archivedOriginalName() ?? $wizardMedia?->original_name,
            'has_archived_media' => (bool) $target?->hasDisplayableMedia(),
            'account_id' => $target?->social_account_id ?? ($wizardState['account_id'] ?? null),
            'destination_id' => $target?->social_destination_id ?? ($wizardState['destination_id'] ?? null),
            'title' => $post->title,
            'caption' => $post->caption,
            'hashtags' => implode(', ', $post->hashtags ?? []),
            'platform_fields' => $target?->platform_payload ?? ($wizardState['platform_fields'] ?? []),
            'creation_source' => $post->metadata['source'] ?? 'manual',
            'wizard' => $wizard,
        ];
    }

    protected function validateAccount(int $accountId): SocialAccount
    {
        return SocialAccount::query()
            ->forCurrentOrganization()
            ->where('status', 'active')
            ->findOrFail($accountId);
    }

    protected function destinationForAccount(SocialAccount $account): SocialDestination
    {
        $destination = $account->primaryDestination();

        if (!$destination) {
            $this->accountManager->syncDestinations($account->fresh());
            $account = $account->fresh();
            $destination = $account->primaryDestination();
        }

        abort_unless($destination, 422, $this->destinationNotReadyMessage($account));

        return $destination;
    }

    protected function destinationNotReadyMessage(SocialAccount $account): string
    {
        if ($account->last_error) {
            return $account->last_error;
        }

        return match ($account->provider) {
            ProviderType::PINTEREST => 'Select a Pinterest board first (Accounts → Select board).',
            ProviderType::INSTAGRAM => 'No Instagram business account is linked. Connect a Facebook Page with Instagram, then sync.',
            ProviderType::FACEBOOK => 'No Facebook Page is available. Add a Page in Meta, then sync from Accounts.',
            default => 'Selected account is not ready for publishing.',
        };
    }

    protected function validateDestination(int $destinationId): SocialDestination
    {
        $destination = SocialDestination::query()
            ->forCurrentOrganization()
            ->findOrFail($destinationId);

        abort_unless(
            SocialAccount::query()
                ->forCurrentOrganization()
                ->where('id', $destination->social_account_id)
                ->exists(),
            403,
            'Invalid destination selected.'
        );

        return $destination;
    }

    protected function buildTargetData(Request $request, SocialAccount $account): array
    {
        if ($request->filled('selectedDestination')) {
            $destination = $this->validateDestination((int) $request->selectedDestination);
            abort_unless(
                $destination->social_account_id === $account->id,
                422,
                'Selected board does not belong to this account.'
            );
        } else {
            $destination = $this->destinationForAccount($account);
        }

        $platform = $request->channel;
        $postType = $this->mediaConfig->defaultPostType(
            $platform,
            $request->media_type,
            $request->preset_key
        );

        $payload = $this->platformFields->buildStoredPayload(
            $request,
            $platform,
            $this->resolvePostTitle($request)
        );

        return [
            'social_account_id' => $destination->social_account_id,
            'social_destination_id' => $destination->id,
            'provider' => $platform,
            'post_type' => $postType,
            'platform_payload' => $payload,
            'scheduled_at' => null,
            'timezone' => 'UTC',
        ];
    }

    protected function syncTargetMediaArchive(PostTarget $target, ?int $mediaAssetId): void
    {
        if (!$mediaAssetId) {
            return;
        }

        $this->postMediaArchive->replaceFromLibrary(
            $target,
            $this->scopedMedia()->findOrFail($mediaAssetId)
        );
    }

    public function filterMedia(Request $request)
    {
        $request->validate([
            'channel' => 'required|string|in:facebook,instagram,pinterest',
            'media_type' => 'required|string|in:image,video',
            'preset_key' => 'nullable|string',
        ]);

        $query = $this->scopedMedia()->orderBy('created_at', 'desc');

        $query->where('channels', 'like', '%"' . $request->channel . '"%')
            ->where('media_type', $request->media_type);

        if ($request->filled('preset_key')) {
            $query->where('preset_key', $request->preset_key);
        }

        $assets = $query->get()->map(fn ($asset) => [
            'id' => $asset->id,
            'url' => $asset->url,
            'original_name' => $asset->original_name,
            'media_type' => $asset->media_type,
            'width' => $asset->width,
            'height' => $asset->height,
            'preset_key' => $asset->preset_key,
            'preset_label' => $asset->preset_label,
            'is_video' => str_starts_with($asset->mime_type, 'video/'),
        ]);

        return response()->json(['assets' => $assets]);
    }

    public function store(Request $request)
    {
        $request->validate(array_merge([
            'title' => $request->channel === 'pinterest' ? 'required|string|max:100' : 'nullable|string|max:100',
            'caption' => 'required|string',
            'channel' => 'required|string|in:facebook,instagram,pinterest',
            'media_type' => 'required|string|in:image,video',
            'preset_key' => 'nullable|string',
            'selectedAccount' => 'required|integer',
            'selectedDestination' => 'nullable|integer',
            'selectedMediaId' => 'required|integer',
            'creation_source' => 'nullable|string|in:manual,semi-ai',
        ], $this->platformFields->validationRules($request->channel, $request)));

        $user = auth()->user();
        $isDraft = $request->has('is_draft');
        $account = $this->validateAccount((int) $request->selectedAccount);
        $tags = array_filter(array_map('trim', explode(',', $request->hashtagsInput ?? '')));
        $creationSource = $request->input('creation_source', 'manual') === 'semi-ai' ? 'semi-ai' : 'manual';

        $defaultStatus = $user->isAdmin() ? PostStatus::APPROVED : PostStatus::PENDING_APPROVAL;
        if ($isDraft) {
            $defaultStatus = PostStatus::DRAFT;
        }

        $postData = [
            'title' => $this->resolvePostTitle($request),
            'caption' => $request->caption,
            'is_draft' => $isDraft,
            'status' => $defaultStatus->value,
            'hashtags' => array_values($tags),
            'metadata' => [
                'source' => $creationSource,
            ],
            'schedule_type' => 'once',
            'targets' => [array_merge($this->buildTargetData($request, $account), [
                'media_asset_id' => (int) $request->selectedMediaId,
            ])],
        ];

        $post = $this->scheduler->createPost($user, $postData);

        if ($defaultStatus === PostStatus::APPROVED) {
            $this->queueSchedule->refreshDestinationSchedule(
                $post->targets()->pluck('social_destination_id')->all()
            );
        }

        if ($defaultStatus === PostStatus::PENDING_APPROVAL && ! $user->isAdmin()) {
            try {
                $title = $post->title ?: ('Post #'.$post->id);
                app(\App\Services\Notifications\InAppNotificationService::class)->notifyAdmins(
                    'post_pending_approval',
                    'Post awaiting approval',
                    "{$user->name} submitted \"{$title}\" for approval.",
                    'warning',
                    route('posts.index', ['status' => 'pending_approval']),
                    ['post_id' => $post->id, 'author_id' => $user->id],
                    $post->organization_id,
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $message = $isDraft
            ? 'Draft saved successfully.'
            : ($user->isAdmin()
                ? 'Post approved and added to the publishing queue.'
                : 'Post submitted for admin approval.');

        return redirect()->route('posts.index')->with('status', $message);
    }

    public function update(Request $request, $id)
    {
        $post = $this->findEditablePost($id);
        $post->load('targets');

        $request->validate(array_merge([
            'title' => $request->channel === 'pinterest' ? 'required|string|max:100' : 'nullable|string|max:100',
            'caption' => 'required|string',
            'channel' => 'required|string|in:facebook,instagram,pinterest',
            'media_type' => 'required|string|in:image,video',
            'preset_key' => 'nullable|string',
            'selectedAccount' => 'required|integer',
            'selectedDestination' => 'nullable|integer',
            'selectedMediaId' => 'nullable|integer',
        ], $this->platformFields->validationRules($request->channel, $request)));

        $existingTarget = $post->targets->first();
        if (!$request->filled('selectedMediaId') && !($existingTarget?->hasDisplayableMedia())) {
            abort(422, 'Please select a media file.');
        }

        $isDraft = $request->has('is_draft');
        $account = $this->validateAccount((int) $request->selectedAccount);
        $tags = array_filter(array_map('trim', explode(',', $request->hashtagsInput ?? '')));
        $targetData = $this->buildTargetData($request, $account);
        $user = auth()->user();
        $previousStatus = $post->status;
        $previousTargetStatus = $post->targets->first()?->status;

        if ($isDraft) {
            $newStatus = PostStatus::DRAFT;
            $targetStatus = PostStatus::DRAFT;
        } elseif ($user->isAdmin() && $previousStatus === PostStatus::FAILED) {
            $newStatus = $previousStatus;
            $targetStatus = $previousTargetStatus ?? $previousStatus;
        } elseif ($user->isAdmin()) {
            $newStatus = PostStatus::APPROVED;
            $targetStatus = PostStatus::SCHEDULED;
        } else {
            $newStatus = PostStatus::PENDING_APPROVAL;
            $targetStatus = PostStatus::PENDING_APPROVAL;
        }

        $post->update([
            'title' => $this->resolvePostTitle($request),
            'caption' => $request->caption,
            'hashtags' => array_values($tags),
            'status' => $newStatus,
            'admin_note' => $newStatus === PostStatus::PENDING_APPROVAL ? null : $post->admin_note,
        ]);

        $target = $post->targets->first();
        if ($target) {
            if ($request->filled('selectedMediaId')) {
                $this->syncTargetMediaArchive($target, (int) $request->selectedMediaId);
            }

            $target->update(array_merge($targetData, ['status' => $targetStatus]));
        } else {
            $target = PostTarget::create(array_merge($targetData, [
                'post_id' => $post->id,
                'organization_id' => $post->organization_id,
                'status' => $targetStatus,
            ]));

            if ($request->filled('selectedMediaId')) {
                $this->postMediaArchive->copyFromLibrary(
                    $target,
                    $this->scopedMedia()->findOrFail((int) $request->selectedMediaId)
                );
            }
        }

        if ($newStatus === PostStatus::APPROVED) {
            $this->queueSchedule->refreshDestinationSchedule(
                $post->targets()->pluck('social_destination_id')->all()
            );
        }

        if ($newStatus === PostStatus::PENDING_APPROVAL && ! $user->isAdmin()) {
            try {
                $title = $post->title ?: ('Post #'.$post->id);
                app(\App\Services\Notifications\InAppNotificationService::class)->notifyAdmins(
                    'post_pending_approval',
                    'Post awaiting approval',
                    "{$user->name} submitted \"{$title}\" for approval.",
                    'warning',
                    route('posts.index', ['status' => 'pending_approval']),
                    ['post_id' => $post->id, 'author_id' => $user->id],
                    $post->organization_id,
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $message = $isDraft
            ? 'Draft updated successfully.'
            : (auth()->user()->isAdmin()
                ? 'Post updated and added to the publishing queue.'
                : 'Post updated and resubmitted for approval.');

        return redirect()->route('posts.index')->with('status', $message);
    }

    public function suggestCaption(Request $request)
    {
        if (! $this->currentOrganization()->ai_enabled) {
            return response()->json([
                'message' => 'AI Mode is off for your firm. An owner or admin can enable it in Firm Settings.',
            ], 403);
        }

        $request->validate([
            'brief' => 'required|string|min:5|max:2000',
            'platform' => 'nullable|string|in:facebook,instagram,pinterest',
            'category' => 'nullable|string|max:50',
        ]);

        $service = new CaptionSuggestionService();

        try {
            $result = $service->suggest(
                $request->brief,
                $request->platform ?? 'facebook',
                $request->category ?? 'general'
            );
        } catch (AiGenerationException $e) {
            return response()->json(['message' => $e->getMessage()], $e->httpStatus());
        }

        return response()->json($result);
    }

    public function review(Request $request, $id)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $post = Post::query()->forCurrentOrganization()->findOrFail($id);
        abort_unless(
            $post->status === PostStatus::PENDING_APPROVAL,
            422,
            'Only posts pending approval can be reviewed.'
        );

        $request->validate([
            'action' => 'required|in:approve,reject,revision',
            'admin_note' => 'nullable|string',
        ]);

        $result = $this->postReviews->apply(
            $post,
            $request->action,
            $request->admin_note,
            'admin:'.auth()->id(),
            ['source' => 'manual_admin']
        );

        return redirect()->back()->with('status', $result['message']);
    }

    public function retryTarget($id)
    {
        $target = PostTarget::query()
            ->forCurrentOrganization()
            ->whereHas('post', function ($q) {
                $q->forCurrentOrganization();
                if (! auth()->user()->isAdmin()) {
                    $q->where('user_id', auth()->id());
                }
            })->findOrFail($id);

        abort_unless($target->status === PostStatus::FAILED, 422, 'Only failed posts can be retried.');

        $destinationId = $target->social_destination_id;

        $target->update([
            'status' => PostStatus::SCHEDULED,
            'scheduled_at' => now(),
            'attempts_count' => 0,
            'error_message' => null,
        ]);

        $target->post->update(['status' => PostStatus::SCHEDULED]);

        PublishPostTargetJob::dispatchSync($target->id, true);

        $target->refresh();
        $target->post->refresh();

        if ($destinationId) {
            $this->queueSchedule->refreshDestinationSchedule($destinationId);
        }

        if ($target->status === PostStatus::PUBLISHED) {
            return redirect()->back()->with('status', 'Post published successfully.');
        }

        return redirect()->back()->with(
            'error',
            $target->error_message ?: 'Retry failed. Please check the account connection and try again.'
        );
    }

    public function publishNow($id)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $post = Post::with('targets')->forCurrentOrganization()->findOrFail($id);
        abort_unless($post->status === PostStatus::APPROVED, 422, 'Only queued posts can be published now.');

        $target = $post->targets()->where('status', PostStatus::SCHEDULED)->first();
        abort_unless($target, 422, 'No schedulable target found for this post.');

        $destinationId = $target->social_destination_id;

        PublishPostTargetJob::dispatchSync($target->id, true);

        $target->refresh();
        $post->refresh();

        $this->queueSchedule->refreshDestinationSchedule($destinationId);

        if ($target->status === PostStatus::PUBLISHED) {
            return redirect()->back()->with('status', 'Post published successfully.');
        }

        return redirect()->back()->with(
            'error',
            $target->error_message ?: 'Publishing failed. The post was removed from the queue.'
        );
    }

    public function cancelTarget($id)
    {
        $target = PostTarget::query()
            ->forCurrentOrganization()
            ->whereHas('post', function ($q) {
                $q->forCurrentOrganization();
                if (! auth()->user()->isAdmin()) {
                    $q->where('user_id', auth()->id());
                }
            })->findOrFail($id);

        $target->update(['status' => PostStatus::CANCELLED]);

        return redirect()->back()
            ->with('status', 'Target publication cancelled.');
    }

    public function delete($id)
    {
        $post = $this->findDeletablePost($id);

        $destinationIds = $post->targets()->pluck('social_destination_id')->filter()->all();
        $post->targets()->update(['status' => PostStatus::CANCELLED]);
        $post->delete();

        if ($destinationIds !== []) {
            $this->queueSchedule->refreshDestinationSchedule($destinationIds);
        }

        return redirect()->back()
            ->with('status', 'Post deleted successfully.');
    }

    public function calendar(Request $request)
    {
        $month = $request->query('month', now()->format('m'));
        $year = $request->query('year', now()->format('Y'));

        $currentMonthDate = Carbon::create($year, $month, 1);
        $monthName = $currentMonthDate->format('F Y');
        
        $startOfCalendar = $currentMonthDate->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $endOfCalendar = $currentMonthDate->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $targetsQuery = PostTarget::with(['post', 'socialDestination'])
            ->forCurrentOrganization()
            ->whereHas('post', function ($q) {
                $q->forCurrentOrganization();
                if (! auth()->user()->isAdmin()) {
                    $q->where('user_id', auth()->id());
                }
            });

        // Include scheduled posts AND publish-now posts (scheduled_at is often null).
        $targets = $targetsQuery
            ->where(function ($q) use ($startOfCalendar, $endOfCalendar) {
                $q->whereBetween('scheduled_at', [$startOfCalendar, $endOfCalendar])
                    ->orWhere(function ($q2) use ($startOfCalendar, $endOfCalendar) {
                        $q2->whereNull('scheduled_at')
                            ->whereBetween('published_at', [$startOfCalendar, $endOfCalendar]);
                    });
            })
            ->get()
            ->filter(fn ($target) => $target->calendarAt() !== null)
            ->groupBy(fn ($target) => $target->calendarAt()->toDateString());

        $daysGrid = [];
        $currentDay = $startOfCalendar->copy();

        while ($currentDay->lte($endOfCalendar)) {
            $dateString = $currentDay->toDateString();
            $daysGrid[] = [
                'date' => $currentDay->copy(),
                'isCurrentMonth' => $currentDay->month === (int) $month,
                'isToday' => $currentDay->isToday(),
                'targets' => $targets->get($dateString) ?? collect([]),
            ];
            $currentDay->addDay();
        }

        $selectedTarget = null;
        if ($request->query('selected_target')) {
            $stQuery = PostTarget::with(['post', 'socialDestination', 'mediaAsset'])
                ->forCurrentOrganization()
                ->whereHas('post', function ($q) {
                    $q->forCurrentOrganization();
                    if (! auth()->user()->isAdmin()) {
                        $q->where('user_id', auth()->id());
                    }
                });
            $selectedTarget = $stQuery->find($request->query('selected_target'));
        }

        return view('posts.calendar', [
            'monthName' => $monthName,
            'daysGrid' => $daysGrid,
            'month' => $month,
            'year' => $year,
            'selectedTarget' => $selectedTarget,
        ]);
    }

    protected function resolvePostTitle(Request $request): string
    {
        $title = trim((string) $request->input('title', ''));

        if ($request->channel === 'pinterest') {
            return $title;
        }

        if ($title !== '') {
            return mb_strlen($title) > 100 ? mb_substr($title, 0, 97) . '...' : $title;
        }

        $caption = trim((string) $request->input('caption', ''));
        $words = preg_split('/\s+/', $caption, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $derived = implode(' ', array_slice($words, 0, 8));

        if ($derived !== '') {
            return mb_strlen($derived) > 100 ? mb_substr($derived, 0, 97) . '...' : $derived;
        }

        return ucfirst((string) $request->channel) . ' post';
    }
}
