<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePostRequest;
use App\Models\Post;
use App\Services\Api\ApiPostCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(
        protected ApiPostCreator $creator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $user = $request->user();
        $query = Post::query()
            ->with(['targets.socialAccount', 'targets.socialDestination'])
            ->whereHas('targets', fn ($q) => $q->where('provider', 'pinterest'))
            ->latest();

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $posts = $query->paginate((int) $request->input('per_page', 20));

        return response()->json([
            'data' => collect($posts->items())->map(fn (Post $post) => $this->postPayload($post))->values(),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    public function show(Request $request, int $post): JsonResponse
    {
        $model = $this->findAccessiblePost($request, $post);

        return response()->json([
            'data' => $this->postPayload($model),
        ]);
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->creator->create(
            $request->user(),
            $request->validated(),
            $request->uploadedMediaFile()
        );

        return response()->json([
            'message' => $this->successMessage($post, $request->user()->isAdmin()),
            'data' => $this->postPayload($post),
        ], 201);
    }

    protected function findAccessiblePost(Request $request, int $postId): Post
    {
        $query = Post::query()->with(['targets.socialAccount', 'targets.socialDestination']);
        if (! $request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        return $query->findOrFail($postId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function postPayload(Post $post): array
    {
        $targets = $post->targets->map(function ($target) {
            $scheduledLocal = null;
            if ($target->scheduled_at) {
                $tz = $target->timezone ?: 'UTC';
                $scheduledLocal = $target->scheduled_at->copy()->timezone($tz)->toDateTimeString();
            }

            return [
                'id' => $target->id,
                'provider' => $target->provider?->value ?? $target->provider,
                'status' => $target->status?->value ?? $target->status,
                'schedule_mode' => $target->schedule_mode?->value ?? $target->schedule_mode,
                'scheduled_at_utc' => $target->scheduled_at?->utc()->toIso8601String(),
                'scheduled_at_local' => $scheduledLocal,
                'timezone' => $target->timezone,
                'account' => $target->socialAccount ? [
                    'id' => $target->socialAccount->id,
                    'name' => $target->socialAccount->name,
                ] : null,
                'destination' => $target->socialDestination ? [
                    'id' => $target->socialDestination->id,
                    'name' => $target->socialDestination->name,
                ] : null,
                'platform_payload' => $target->platform_payload,
                'media_url' => $target->resolvedMediaUrl(),
            ];
        })->values()->all();

        return [
            'id' => $post->id,
            'title' => $post->title,
            'caption' => $post->caption,
            'hashtags' => $post->hashtags ?? [],
            'status' => $post->status?->value ?? $post->status,
            'metadata' => $post->metadata,
            'created_at' => $post->created_at?->toIso8601String(),
            'targets' => $targets,
        ];
    }

    protected function successMessage(Post $post, bool $isAdmin): string
    {
        $status = $post->status?->value ?? (string) $post->status;

        return match ($status) {
            'draft' => 'Draft saved successfully.',
            'pending_approval' => 'Post submitted for admin approval.',
            'approved' => $isAdmin
                ? 'Post created and scheduled successfully.'
                : 'Post created successfully.',
            default => 'Post created successfully.',
        };
    }
}
