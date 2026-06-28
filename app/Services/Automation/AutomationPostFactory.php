<?php

namespace App\Services\Automation;

use App\Enums\PostStatus;
use App\Models\AutomationRunItem;
use App\Models\ContentTopic;
use App\Enums\ProviderType;
use App\Models\SocialAccount;
use App\Services\Media\MediaConfigService;
use App\Services\Posts\PostSchedulerService;

class AutomationPostFactory
{
    public function __construct(
        protected PostSchedulerService $scheduler,
        protected MediaConfigService $mediaConfig
    ) {
    }

    public function create(
        AutomationRunItem $item,
        array $copy,
        int $mediaAssetId,
        int $socialAccountId
    ): \App\Models\Post {
        $account = SocialAccount::with('destinations')->findOrFail($socialAccountId);
        $destination = $account->primaryDestination();

        if (! $destination) {
            throw new \RuntimeException("Account {$account->id} has no destination.");
        }

        $platform = $item->platform;
        $presetKey = config("automation.default_presets.{$platform}")
            ?? config('automation.default_presets.pinterest');

        $postType = $this->mediaConfig->defaultPostType($platform, 'image', $presetKey);

        $platformPayload = [];
        if ($item->topic_type === 'promotional' && ! empty($copy['link_url'])) {
            $platformPayload['link_url'] = $copy['link_url'];
        }

        $caption = $copy['caption'];
        if (! empty($copy['hashtags'])) {
            $caption .= "\n\n" . implode(' ', $copy['hashtags']);
        }

        $user = AutomationMediaService::aiAgentUser();

        $post = $this->scheduler->createPost($user, [
            'title' => $copy['title'],
            'caption' => $caption,
            'is_draft' => false,
            'status' => PostStatus::PENDING_APPROVAL->value,
            'hashtags' => $copy['hashtags'] ?? [],
            'tags' => ['ai-automation', $platform, $item->topic_type],
            'metadata' => [
                'source' => 'automation',
                'run_id' => $item->automation_run_id,
                'run_item_id' => $item->id,
                'topic_id' => $item->content_topic_id,
                'platform' => $platform,
                'topic_type' => $item->topic_type,
                'design_prompt' => $item->design_prompt,
                'image_provider' => $item->image_provider,
                'text_provider' => $item->text_provider,
            ],
            'schedule_type' => 'once',
            'targets' => [[
                'social_account_id' => $account->id,
                'social_destination_id' => $destination->id,
                'provider' => $platform,
                'post_type' => $postType,
                'media_asset_id' => $mediaAssetId,
                'platform_payload' => $platformPayload,
                'timezone' => $item->run->campaign->default_timezone ?? 'UTC',
            ]],
        ]);

        if ($item->content_topic_id) {
            $topic = ContentTopic::find($item->content_topic_id);
            $topic?->increment('times_used');
            $topic?->update(['last_used_at' => now()]);
        }

        return $post;
    }

    public function resolveAccountId(AutomationRunItem $item): int
    {
        $campaign = $item->run->campaign;
        $accounts = $campaign->platform_accounts ?? [];
        $platform = $item->platform;

        if (isset($accounts[$platform])) {
            $id = is_array($accounts[$platform]) ? ($accounts[$platform][0] ?? null) : $accounts[$platform];
            if ($id) {
                return (int) $id;
            }
        }

        $adminIds = \App\Models\User::role('admin')->pluck('id');
        $account = SocialAccount::query()
            ->whereIn('user_id', $adminIds)
            ->where('status', 'active')
            ->where('provider', ProviderType::from($platform))
            ->first();

        if (! $account) {
            throw new \RuntimeException("No active {$platform} account for automation.");
        }

        return $account->id;
    }
}
