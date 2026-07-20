<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Models\Post;
use App\Services\Posts\SemiAiAssistService;
use App\Support\MediaUpload;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SemiAiAssistController extends Controller
{
    use ResolvesOrganization;

    public function __construct(
        protected SemiAiAssistService $assist,
    ) {}

    protected function scopedPosts()
    {
        $query = Post::query()->forCurrentOrganization();

        if (! auth()->user()->isAdmin()) {
            $query->where('user_id', auth()->id());
        }

        return $query;
    }

    public function saveDraftProgress(Request $request)
    {
        $data = $request->validate([
            'draft_id' => 'nullable|integer',
            'resume_step' => 'required|integer|min:7|max:11',
            'state' => 'required|array',
            'state.channel' => 'required|string|in:facebook,instagram,pinterest',
            'state.media_type' => 'required|string|in:image,video',
            'state.preset_key' => 'nullable|string|max:80',
            'state.category_id' => 'nullable|integer',
            'state.custom_niche' => 'nullable|string|max:500',
            'state.custom_audience' => 'nullable|string|max:500',
            'state.topic' => 'required|string|min:3|max:200',
            'state.topic_type' => 'required|string|in:educational,promotional,listicle,tip',
            'state.link_url' => 'nullable|url|max:500',
            'state.image_style' => 'nullable|string|max:40',
            'state.idea_prompt' => 'nullable|string|max:50000',
            'state.idea_json' => 'nullable|string|max:50000',
            'state.copy_prompt' => 'nullable|string|max:50000',
            'state.copy_json' => 'nullable|string|max:50000',
            'state.image_prompt' => 'nullable|string|max:50000',
            'state.image_json' => 'nullable|string|max:50000',
            'state.design_prompt' => 'nullable|string|max:50000',
            'state.selected_media_id' => 'nullable|integer',
            'state.title' => 'nullable|string|max:100',
            'state.caption' => 'nullable|string|max:10000',
            'state.hashtags' => 'nullable|string|max:2000',
            'state.account_id' => 'nullable|integer',
            'state.destination_id' => 'nullable|integer',
            'state.platform_fields' => 'nullable|array',
        ]);

        $post = null;
        if (! empty($data['draft_id'])) {
            $post = $this->scopedPosts()->findOrFail($data['draft_id']);

            abort_unless(
                $post->status === PostStatus::DRAFT
                    && ($post->metadata['source'] ?? null) === 'semi-ai',
                422,
                'Only your semi-AI draft can be updated from this wizard.'
            );
        }

        $state = $data['state'];
        $metadata = array_merge($post?->metadata ?? [], [
            'source' => 'semi-ai',
            'ai_generated' => true,
            'generation_mode' => 'semi-automatic',
            'wizard' => [
                'resume_step' => (int) $data['resume_step'],
                'saved_at' => now()->toIso8601String(),
                'state' => $state,
            ],
        ]);

        $postData = [
            'title' => trim((string) ($state['title'] ?? ''))
                ?: mb_substr(trim((string) $state['topic']), 0, 100),
            'caption' => ($state['caption'] ?? '') !== '' ? $state['caption'] : null,
            'hashtags' => $this->parseHashtags((string) ($state['hashtags'] ?? '')),
            'status' => PostStatus::DRAFT,
            'metadata' => $metadata,
        ];

        if ($post) {
            $post->update($postData);
        } else {
            $post = Post::create(array_merge($postData, [
                'user_id' => auth()->id(),
                'organization_id' => $this->currentOrganizationId(),
            ]));
        }

        return response()->json([
            'draft_id' => $post->id,
            'edit_url' => route('posts.edit', $post->id),
            'update_url' => route('posts.update', $post->id),
            'saved_at' => now()->toIso8601String(),
        ]);
    }

    public function ideaPrompt(Request $request)
    {
        $data = $this->validateContext($request);

        try {
            $category = $this->assist->resolveCategory($data['category_id'] ?? null);
            $result = $this->assist->buildIdeaPrompt(
                $data['platform'],
                $data['topic'],
                $data['topic_type'],
                $category,
                $data['custom_niche'] ?? null,
                $data['custom_audience'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function validateIdea(Request $request)
    {
        $data = $this->validateContext($request, [
            'idea_json' => 'required|string|min:2|max:20000',
        ]);

        try {
            $idea = $this->assist->validateIdeaJson($data['idea_json']);
            $category = $this->assist->resolveCategory($data['category_id'] ?? null);
            $copyPrompt = $this->assist->buildCopyPrompt(
                $data['platform'],
                $data['topic'],
                $data['topic_type'],
                $idea,
                $category,
                $data['custom_niche'] ?? null,
                $data['custom_audience'] ?? null,
                $data['link_url'] ?? null,
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'idea' => $idea,
            'copy_prompt' => $copyPrompt,
        ]);
    }

    public function validateCopy(Request $request)
    {
        $data = $this->validateContext($request, [
            'idea_json' => 'required|string|min:2|max:20000',
            'copy_json' => 'required|string|min:2|max:20000',
            'preset_key' => 'nullable|string|max:80',
            'image_style' => 'nullable|string|max:40',
        ]);

        try {
            $idea = $this->assist->validateIdeaJson($data['idea_json']);
            $copy = $this->assist->validateCopyJson($data['copy_json']);
            $category = $this->assist->resolveCategory($data['category_id'] ?? null);
            $imagePrompt = $this->assist->buildImagePrompt(
                $data['platform'],
                $data['topic'],
                $data['topic_type'],
                $idea,
                $copy,
                $data['preset_key'] ?? null,
                $data['image_style'] ?? null,
                $category,
                $data['custom_niche'] ?? null,
                $data['custom_audience'] ?? null,
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'idea' => $idea,
            'copy' => $copy,
            'image_prompt' => $imagePrompt,
        ]);
    }

    public function validateImage(Request $request)
    {
        $request->validate([
            'image_json' => 'required|string|min:2|max:20000',
        ]);

        $image = $this->assist->validateImageJson($request->input('image_json'));

        return response()->json($image);
    }

    public function uploadImage(Request $request)
    {
        if (MediaUpload::postTooLarge()) {
            throw ValidationException::withMessages([
                'file' => [MediaUpload::postTooLargeMessage()],
            ]);
        }

        try {
            $request->validate([
                'file' => MediaUpload::imageFileRules(),
                'channel' => 'required|string|in:facebook,instagram,pinterest',
                'media_type' => 'required|string|in:image,video',
                'preset_key' => 'nullable|string|max:80',
                'topic_type' => 'nullable|string|in:educational,promotional,listicle,tip',
                'image_style' => 'nullable|string|max:40',
            ], MediaUpload::validationMessages('file'));
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(
                MediaUpload::friendlyValidationErrors($e->errors(), $request->file('file'))
            );
        }

        MediaUpload::assertValid($request->file('file'), 'file');

        $user = auth()->user();
        $extraTags = array_filter([
            $request->input('topic_type'),
            $request->input('image_style'),
        ]);

        try {
            $asset = $this->assist->uploadAttributedImage(
                $request->file('file'),
                $request->input('channel'),
                $request->input('media_type'),
                $request->input('preset_key'),
                $user->name ?: ('user-'.$user->id),
                $user->id,
                $extraTags,
                $this->currentOrganizationId(),
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        return response()->json([
            'asset' => [
                'id' => $asset->id,
                'url' => $asset->url,
                'original_name' => $asset->original_name,
                'media_type' => $asset->media_type,
                'width' => $asset->width,
                'height' => $asset->height,
                'preset_key' => $asset->preset_key,
                'preset_label' => $asset->preset_label,
                'alt_text' => $asset->alt_text,
                'is_video' => str_starts_with((string) $asset->mime_type, 'video/'),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function validateContext(Request $request, array $extra = []): array
    {
        return $request->validate(array_merge([
            'platform' => 'required|string|in:facebook,instagram,pinterest',
            'topic' => 'required|string|min:3|max:200',
            'topic_type' => 'required|string|in:educational,promotional,listicle,tip',
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('content_categories', 'id')->where(fn ($q) => $q
                    ->where('is_active', true)
                    ->where('organization_id', $this->currentOrganizationId())),
            ],
            'custom_niche' => 'nullable|string|max:500',
            'custom_audience' => 'nullable|string|max:500',
            'link_url' => 'nullable|url|max:500',
        ], $extra));
    }

    /**
     * @return array<int, string>
     */
    protected function parseHashtags(string $value): array
    {
        return array_values(array_filter(array_map(
            fn (string $tag) => ltrim(trim($tag), '#'),
            preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: []
        )));
    }
}
