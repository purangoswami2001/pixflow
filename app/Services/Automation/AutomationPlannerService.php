<?php

namespace App\Services\Automation;

use App\Models\AutomationCampaign;
use App\Models\AutomationRun;
use App\Models\AutomationRunItem;
use App\Models\ContentTopic;
use Illuminate\Support\Collection;

class AutomationPlannerService
{
    public function buildDailyPlan(AutomationCampaign $campaign): array
    {
        $platforms = $campaign->platforms ?? [];
        $counts = $campaign->platform_counts ?? [];
        $total = (int) $campaign->posts_per_day;
        $promoRatio = min(100, max(0, (int) $campaign->promotional_ratio));
        $promoCount = (int) round($total * $promoRatio / 100);
        $eduCount = max(0, $total - $promoCount);

        $slots = [];
        foreach ($platforms as $platform) {
            $count = (int) ($counts[$platform] ?? 0);
            if ($count <= 0) {
                $count = max(1, (int) floor($total / max(1, count($platforms))));
            }

            for ($i = 0; $i < $count; $i++) {
                $slots[] = ['platform' => $platform, 'topic_type' => 'educational'];
            }
        }

        if (empty($slots)) {
            for ($i = 0; $i < $total; $i++) {
                $slots[] = [
                    'platform' => $platforms[array_rand($platforms)] ?? 'pinterest',
                    'topic_type' => 'educational',
                ];
            }
        }

        $slots = array_slice($slots, 0, $total);

        if ($promoCount > 0 && count($slots) > 0) {
            $indexes = range(0, count($slots) - 1);
            shuffle($indexes);
            $promoIndexes = array_slice($indexes, 0, min($promoCount, count($slots)));
            foreach ($promoIndexes as $idx) {
                $slots[$idx]['topic_type'] = 'promotional';
            }
        }

        shuffle($slots);
        $slots = $this->spreadPromotional($slots);

        return $slots;
    }

    protected function spreadPromotional(array $slots): array
    {
        $promo = array_values(array_filter($slots, fn ($s) => $s['topic_type'] === 'promotional'));
        $other = array_values(array_filter($slots, fn ($s) => $s['topic_type'] !== 'promotional'));

        $result = [];
        while ($promo || $other) {
            if ($other) {
                $result[] = array_shift($other);
            }
            if ($promo && (empty($result) || end($result)['topic_type'] !== 'promotional')) {
                $result[] = array_shift($promo);
            } elseif ($promo && ! $other) {
                $result[] = array_shift($promo);
            }
        }

        return $result;
    }

    public function pickTopic(AutomationCampaign $campaign, string $topicType): ?ContentTopic
    {
        $cooldownDays = (int) config('automation.topic_cooldown_days', 14);

        return ContentTopic::query()
            ->where('content_category_id', $campaign->content_category_id)
            ->where('is_active', true)
            ->where('topic_type', $topicType)
            ->where(function ($q) use ($cooldownDays) {
                $q->whereNull('last_used_at')
                    ->orWhere('last_used_at', '<', now()->subDays($cooldownDays));
            })
            ->orderBy('times_used')
            ->orderBy('last_used_at')
            ->first()
            ?? ContentTopic::query()
                ->where('content_category_id', $campaign->content_category_id)
                ->where('is_active', true)
                ->where('topic_type', $topicType)
                ->orderBy('times_used')
                ->first();
    }

    public function createRun(AutomationCampaign $campaign, array $plan): AutomationRun
    {
        $run = AutomationRun::create([
            'organization_id' => $campaign->organization_id,
            'automation_campaign_id' => $campaign->id,
            'status' => 'running',
            'planned_count' => count($plan),
            'created_count' => 0,
            'failed_count' => 0,
            'started_at' => now(),
            'log' => [],
        ]);

        foreach ($plan as $slot) {
            $topic = $this->pickTopic($campaign, $slot['topic_type']);
            if (! $topic) {
                $topic = $this->pickTopic($campaign, 'educational');
            }

            AutomationRunItem::create([
                'organization_id' => $campaign->organization_id,
                'automation_run_id' => $run->id,
                'content_topic_id' => $topic?->id,
                'platform' => $slot['platform'],
                'topic_type' => $slot['topic_type'],
                'status' => 'pending',
            ]);
        }

        return $run->load('items');
    }
}
