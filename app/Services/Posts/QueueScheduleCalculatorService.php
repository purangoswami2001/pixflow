<?php

namespace App\Services\Posts;

use App\Enums\PostStatus;
use App\Enums\ScheduleMode;
use App\Models\PostTarget;
use App\Models\QueueSlot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class QueueScheduleCalculatorService
{
    /**
     * Map of post_target_id => assignment details (auto + manual).
     */
    public function targetScheduleMap(): array
    {
        $map = [];

        $manualTargets = PostTarget::with('post')
            ->manualScheduled()
            ->where('status', PostStatus::SCHEDULED)
            ->whereHas('post', fn ($q) => $q->where('status', PostStatus::APPROVED))
            ->whereNotNull('scheduled_at')
            ->get();

        foreach ($manualTargets as $target) {
            $map[$target->id] = $this->formatAssignment($target, $target->scheduled_at, true);
        }

        $destinationIds = PostTarget::query()
            ->autoQueued()
            ->where('status', PostStatus::SCHEDULED)
            ->whereHas('post', fn ($q) => $q->where('status', PostStatus::APPROVED))
            ->distinct()
            ->pluck('social_destination_id');

        foreach ($destinationIds as $destinationId) {
            foreach ($this->autoAssignmentsForDestination((int) $destinationId) as $assignment) {
                $map[$assignment['target_id']] = $assignment;
            }
        }

        return $map;
    }

    /**
     * Build a forward-looking calendar for one account (up to 7 days from range start).
     */
    public function buildAccountWeekCalendar(\App\Models\SocialAccount $account, Carbon $rangeStart): array
    {
        $rangeStart = $rangeStart->copy()->startOfDay();
        $rangeEnd = $rangeStart->copy()->addDays(6)->endOfDay();
        $now = now();
        $destination = $account->primaryDestination();

        $slots = $destination
            ? QueueSlot::where('social_destination_id', $destination->id)
                ->orderBy('day_of_week')
                ->orderBy('time')
                ->get()
            : collect();

        $scheduleMap = $destination
            ? $this->scheduleMapForDestination($destination->id)
            : [];

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $rangeStart->copy()->addDays($i);
            if ($date->copy()->endOfDay()->lt($now)) {
                continue;
            }

            $days[] = $this->buildAccountDayColumn($slots, $date, $scheduleMap, $now);
        }

        return [
            'range_start' => $rangeStart,
            'range_end' => $rangeEnd,
            'week_start' => $rangeStart,
            'week_end' => $rangeEnd,
            'days' => $days,
            'slot_count' => $slots->count(),
        ];
    }

    protected function scheduleMapForDestination(int $destinationId): array
    {
        return array_filter(
            $this->targetScheduleMap(),
            fn ($assignment) => (int) ($assignment['social_destination_id'] ?? 0) === $destinationId
        );
    }

    protected function buildAccountDayColumn(Collection $slots, Carbon $date, array $scheduleMap, ?Carbon $now = null): array
    {
        $now = $now ?? now();
        $dayOfWeek = $date->dayOfWeek;
        $dateStart = $date->copy()->startOfDay();
        $dateEnd = $date->copy()->endOfDay();

        $recurringSlots = $slots
            ->filter(fn ($s) => (int) $s->day_of_week === (int) $dayOfWeek)
            ->sortBy('time')
            ->values();

        $shownTargetIds = [];
        $times = [];

        foreach ($recurringSlots as $slot) {
            $atUtc = $this->slotInstantOnDate($slot, $date);

            if ($atUtc->lt($now)) {
                continue;
            }

            $posts = array_values(array_filter(
                $this->collectPostsForInstant($scheduleMap, $slot, $atUtc, $shownTargetIds),
                fn ($assignment) => $assignment['scheduled_at']->gte($now)
            ));

            $times[] = [
                'slot_id' => $slot->id,
                'time' => Carbon::parse($slot->time)->format('g:i A'),
                'timezone' => $slot->timezone ?: 'UTC',
                'capacity' => max(1, (int) $slot->posts_per_slot),
                'posts' => $posts,
            ];
        }

        $manualPosts = [];
        foreach ($scheduleMap as $assignment) {
            if (! ($assignment['is_manual'] ?? false)) {
                continue;
            }

            $at = $assignment['scheduled_at'];
            if ($at->lt($now) || ! $at->between($dateStart, $dateEnd)) {
                continue;
            }

            if (in_array($assignment['target_id'], $shownTargetIds, true)) {
                continue;
            }

            $manualPosts[] = $assignment;
        }

        return [
            'date' => $date,
            'date_label' => $date->format('M j'),
            'day_name' => $date->format('l'),
            'is_today' => $date->isToday(),
            'times' => $times,
            'manual_posts' => $manualPosts,
            'has_content' => ! empty($times) || ! empty($manualPosts),
        ];
    }

    /**
     * Build a single-week calendar grouped by day, then account.
     */
    public function buildWeekCalendar(Collection $slots, Carbon $weekStart): array
    {
        $weekStart = $weekStart->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::MONDAY);
        $scheduleMap = $this->targetScheduleMap();

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = $this->buildDayColumn($slots, $weekStart->copy()->addDays($i), $scheduleMap);
        }

        return [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'days' => $days,
        ];
    }

    protected function buildDayColumn(Collection $slots, Carbon $date, array $scheduleMap): array
    {
        $dayOfWeek = $date->dayOfWeek;
        $dateStart = $date->copy()->startOfDay();
        $dateEnd = $date->copy()->endOfDay();

        $recurringSlots = $slots
            ->filter(fn ($s) => (int) $s->day_of_week === (int) $dayOfWeek)
            ->sortBy('time')
            ->values();

        $accountGroups = [];
        $shownTargetIds = [];

        foreach ($recurringSlots as $slot) {
            $dest = $slot->socialDestination;
            $account = $dest?->socialAccount;
            $groupKey = ($dest?->provider->value ?? 'unknown') . ':' . ($account?->id ?? $slot->id);

            $atUtc = $this->slotInstantOnDate($slot, $date);
            $posts = $this->collectPostsForInstant($scheduleMap, $slot, $atUtc, $shownTargetIds);

            if (! isset($accountGroups[$groupKey])) {
                $accountGroups[$groupKey] = [
                    'provider' => $dest?->provider->value ?? 'unknown',
                    'account_name' => $account?->name ?? '—',
                    'destination_name' => $dest?->name ?? '—',
                    'times' => [],
                ];
            }

            $accountGroups[$groupKey]['times'][] = [
                'slot_id' => $slot->id,
                'destination_id' => $dest?->id,
                'time' => Carbon::parse($slot->time)->format('g:i A'),
                'timezone' => $slot->timezone ?: 'UTC',
                'capacity' => max(1, (int) $slot->posts_per_slot),
                'posts' => $posts,
            ];
        }

        $customPinned = [];
        foreach ($scheduleMap as $assignment) {
            if (! ($assignment['is_manual'] ?? false)) {
                continue;
            }

            $at = $assignment['scheduled_at'];
            if (! $at->between($dateStart, $dateEnd)) {
                continue;
            }

            if (in_array($assignment['target_id'], $shownTargetIds, true)) {
                continue;
            }

            $slot = $assignment['slot_id'] ? QueueSlot::find($assignment['slot_id']) : null;
            if ($slot && (int) $slot->day_of_week === (int) $dayOfWeek) {
                continue;
            }

            $customPinned[] = $assignment;
        }

        return [
            'date' => $date,
            'date_label' => $date->format('M j'),
            'day_name' => $date->format('l'),
            'is_today' => $date->isToday(),
            'account_groups' => array_values($accountGroups),
            'custom_pinned' => $customPinned,
            'has_content' => ! empty($accountGroups) || ! empty($customPinned),
        ];
    }

    protected function slotInstantOnDate(QueueSlot $slot, Carbon $date): Carbon
    {
        $timezone = $slot->timezone ?: 'UTC';
        $timeParts = explode(':', (string) $slot->time);
        $hour = (int) ($timeParts[0] ?? 0);
        $minute = (int) ($timeParts[1] ?? 0);

        return $date->copy()
            ->timezone($timezone)
            ->setTime($hour, $minute, 0)
            ->utc();
    }

    protected function collectPostsForInstant(array $scheduleMap, QueueSlot $slot, Carbon $atUtc, array &$shownTargetIds): array
    {
        $posts = [];
        $atStart = $atUtc->copy()->startOfMinute();
        $atEnd = $atUtc->copy()->endOfMinute();

        foreach ($scheduleMap as $assignment) {
            if (! $assignment['scheduled_at']->between($atStart, $atEnd)) {
                continue;
            }

            if ((int) ($assignment['slot_id'] ?? 0) !== (int) $slot->id) {
                continue;
            }

            if (in_array($assignment['target_id'], $shownTargetIds, true)) {
                continue;
            }

            $posts[] = $assignment;
            $shownTargetIds[] = $assignment['target_id'];
        }

        return $posts;
    }

    /**
     * Build slot-centric preview for admin schedule page.
     */
    public function buildSlotScheduleBoard(Collection $slots): array
    {
        $board = [];

        foreach ($slots as $slot) {
            $board[$slot->id] = $this->slotOccurrencePreview($slot);
        }

        return $board;
    }

    public function slotOccurrencePreview(QueueSlot $slot, int $occurrenceCount = 3): array
    {
        $capacity = max(1, (int) $slot->posts_per_slot);
        $occurrences = [];

        $futureOccs = $this->slotOccurrences($slot, now(), $occurrenceCount);

        foreach ($futureOccs as $occ) {
            $manualPosts = PostTarget::with('post')
                ->manualScheduled()
                ->where('queue_slot_id', $slot->id)
                ->where('status', PostStatus::SCHEDULED)
                ->whereHas('post', fn ($q) => $q->where('status', PostStatus::APPROVED))
                ->whereBetween('scheduled_at', [
                    $occ['at']->copy()->startOfMinute(),
                    $occ['at']->copy()->endOfMinute(),
                ])
                ->get()
                ->map(fn ($t) => $this->formatAssignment($t, $t->scheduled_at, true));

            $manualCount = $manualPosts->count();
            $autoSlots = max(0, $capacity - $manualCount);

            $occurrences[] = [
                'at' => $occ['at'],
                'at_local' => $occ['at_local'],
                'timezone' => $occ['timezone'],
                'capacity' => $capacity,
                'manual_posts' => $manualPosts->values()->all(),
                'auto_posts' => array_slice(
                    $this->peekAutoForSlotOccurrence($slot, $occ, $autoSlots),
                    0,
                    $autoSlots
                ),
            ];
        }

        return [
            'slot_id' => $slot->id,
            'capacity' => $capacity,
            'auto_queue_count' => $this->autoQueueCount($slot->social_destination_id),
            'occurrences' => $occurrences,
        ];
    }

    public function autoAssignmentsForDestination(int $destinationId): array
    {
        $slots = QueueSlot::where('social_destination_id', $destinationId)->get();
        if ($slots->isEmpty()) {
            return [];
        }

        $queue = PostTarget::with('post')
            ->autoQueued()
            ->where('social_destination_id', $destinationId)
            ->where('status', PostStatus::SCHEDULED)
            ->whereHas('post', fn ($q) => $q->where('status', PostStatus::APPROVED))
            ->orderBy('created_at')
            ->get();

        if ($queue->isEmpty()) {
            return [];
        }

        $occurrences = $this->upcomingOccurrencesWithCapacity($slots, now(), $queue->count());
        $assignments = [];

        foreach ($queue->values() as $index => $target) {
            if (!isset($occurrences[$index])) {
                break;
            }

            $occ = $occurrences[$index];
            $assignments[] = array_merge(
                $this->formatAssignment($target, $occ['at'], false),
                [
                    'slot_id' => $occ['slot_id'],
                    'position' => $index + 1,
                    'slot_position' => $occ['slot_position'] ?? 1,
                ]
            );
        }

        return $assignments;
    }

    public function refreshDestinationSchedule(int|array $destinationIds): void
    {
        $destinationIds = is_array($destinationIds) ? $destinationIds : [$destinationIds];

        foreach ($destinationIds as $destinationId) {
            PostTarget::query()
                ->autoQueued()
                ->where('social_destination_id', $destinationId)
                ->where('status', PostStatus::SCHEDULED)
                ->whereHas('post', fn ($q) => $q->where('status', PostStatus::APPROVED))
                ->update(['scheduled_at' => null, 'queue_slot_id' => null]);

            foreach ($this->autoAssignmentsForDestination((int) $destinationId) as $assignment) {
                PostTarget::where('id', $assignment['target_id'])->update([
                    'scheduled_at' => $assignment['scheduled_at'],
                    'timezone' => $assignment['timezone'],
                    'queue_slot_id' => $assignment['slot_id'],
                ]);
            }
        }
    }

    public function refreshAllQueuedSchedules(): void
    {
        $destinationIds = PostTarget::query()
            ->autoQueued()
            ->where('status', PostStatus::SCHEDULED)
            ->whereHas('post', fn ($q) => $q->where('status', PostStatus::APPROVED))
            ->distinct()
            ->pluck('social_destination_id');

        $this->refreshDestinationSchedule($destinationIds->all());
    }

    public function assignManualPost(QueueSlot $slot, PostTarget $target, Carbon $scheduleDate): void
    {
        $timezone = $slot->timezone ?: 'UTC';
        $timeParts = explode(':', (string) $slot->time);
        $hour = (int) ($timeParts[0] ?? 0);
        $minute = (int) ($timeParts[1] ?? 0);

        $scheduledLocal = $scheduleDate->copy()
            ->timezone($timezone)
            ->setTime($hour, $minute, 0);

        $manualOnDate = PostTarget::manualScheduled()
            ->where('queue_slot_id', $slot->id)
            ->where('status', PostStatus::SCHEDULED)
            ->whereBetween('scheduled_at', [
                $scheduledLocal->copy()->utc()->startOfMinute(),
                $scheduledLocal->copy()->utc()->endOfMinute(),
            ])
            ->count();

        if ($manualOnDate >= $slot->posts_per_slot) {
            throw new \RuntimeException('This slot is full for the selected date.');
        }

        $target->update([
            'schedule_mode' => ScheduleMode::MANUAL,
            'queue_slot_id' => $slot->id,
            'scheduled_at' => $scheduledLocal->copy()->utc(),
            'timezone' => $timezone,
        ]);

        $this->refreshDestinationSchedule($slot->social_destination_id);
    }

    public function releaseManualPost(PostTarget $target): void
    {
        $destinationId = $target->social_destination_id;

        $target->update([
            'schedule_mode' => ScheduleMode::AUTO,
            'queue_slot_id' => null,
            'scheduled_at' => null,
        ]);

        $this->refreshDestinationSchedule($destinationId);
    }

    public function scheduleManualPostAt(PostTarget $target, Carbon $scheduleDate, string $time, string $timezone): void
    {
        $timeParts = explode(':', $time);
        $hour = (int) ($timeParts[0] ?? 0);
        $minute = (int) ($timeParts[1] ?? 0);

        $scheduledLocal = $scheduleDate->copy()
            ->timezone($timezone)
            ->setTime($hour, $minute, 0);

        if ($scheduledLocal->copy()->utc()->isPast()) {
            throw new \RuntimeException('Scheduled time must be in the future.');
        }

        $destinationId = $target->social_destination_id;

        $target->update([
            'schedule_mode' => ScheduleMode::MANUAL,
            'queue_slot_id' => null,
            'scheduled_at' => $scheduledLocal->copy()->utc(),
            'timezone' => $timezone,
        ]);

        $this->refreshDestinationSchedule($destinationId);
    }

    public function queuedPostsForAccount(int $accountId): Collection
    {
        $account = \App\Models\SocialAccount::find($accountId);
        $destination = $account?->primaryDestination();

        if (! $destination) {
            return collect();
        }

        return $this->queuedPostsForDestination($destination->id);
    }

    public function queuedPostsForDestination(int $destinationId): Collection
    {
        return PostTarget::with(['post.user', 'mediaAsset'])
            ->where('social_destination_id', $destinationId)
            ->where('status', PostStatus::SCHEDULED)
            ->whereHas('post', fn ($q) => $q->where('status', PostStatus::APPROVED))
            ->autoQueued()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'post_id' => $t->post_id,
                'title' => $t->post->title,
                'author' => $t->post->user->name ?? 'Unknown',
                'created_at' => $t->created_at->format('M d, Y'),
            ]);
    }

    protected function autoQueueCount(int $destinationId): int
    {
        return PostTarget::autoQueued()
            ->where('social_destination_id', $destinationId)
            ->where('status', PostStatus::SCHEDULED)
            ->whereHas('post', fn ($q) => $q->where('status', PostStatus::APPROVED))
            ->count();
    }

    protected function peekAutoForSlotOccurrence(QueueSlot $slot, array $occ, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $allAuto = $this->autoAssignmentsForDestination($slot->social_destination_id);

        return array_values(array_filter(
            $allAuto,
            fn ($a) => $a['slot_id'] === $slot->id
                && $a['scheduled_at']->equalTo($occ['at'])
        ));
    }

    protected function formatAssignment(PostTarget $target, Carbon $scheduledAt, bool $isManual): array
    {
        $timezone = $target->timezone ?: 'UTC';

        return [
            'target_id' => $target->id,
            'post_id' => $target->post_id,
            'post_title' => $target->post->title,
            'scheduled_at' => $scheduledAt,
            'scheduled_at_local' => $scheduledAt->copy()->timezone($timezone),
            'timezone' => $timezone,
            'is_manual' => $isManual,
            'slot_id' => $target->queue_slot_id,
            'social_destination_id' => $target->social_destination_id,
        ];
    }

    protected function slotOccurrences(QueueSlot $slot, Carbon $from, int $count): array
    {
        return $this->upcomingOccurrences(collect([$slot]), $from, $count);
    }

    protected function upcomingOccurrencesWithCapacity(Collection $slots, Carbon $from, int $postCount): array
    {
        $raw = $this->upcomingOccurrences($slots, $from, $postCount * 3);
        $expanded = [];

        foreach ($raw as $occ) {
            $slot = $slots->firstWhere('id', $occ['slot_id']);
            $capacity = max(1, (int) ($slot->posts_per_slot ?? 1));

            for ($i = 1; $i <= $capacity; $i++) {
                $expanded[] = array_merge($occ, ['slot_position' => $i]);
            }
        }

        return array_slice($expanded, 0, $postCount);
    }

    protected function upcomingOccurrences(Collection $slots, Carbon $from, int $count): array
    {
        $occurrences = [];
        $horizon = $from->copy()->addWeeks(26);

        foreach ($slots as $slot) {
            $timezone = $slot->timezone ?: 'UTC';
            $fromLocal = $from->copy()->timezone($timezone);
            $timeParts = explode(':', (string) $slot->time);
            $hour = (int) ($timeParts[0] ?? 0);
            $minute = (int) ($timeParts[1] ?? 0);

            $cursor = $fromLocal->copy()->startOfDay();
            while ($cursor->lte($horizon) && count($occurrences) < $count * 30) {
                if ((int) $cursor->dayOfWeek === (int) $slot->day_of_week) {
                    $atLocal = $cursor->copy()->setTime($hour, $minute, 0);
                    if ($atLocal->gt($fromLocal)) {
                        $occurrences[] = [
                            'slot_id' => $slot->id,
                            'at' => $atLocal->copy()->utc(),
                            'at_local' => $atLocal,
                            'timezone' => $timezone,
                        ];
                    }
                }
                $cursor->addDay();
            }
        }

        usort($occurrences, fn ($a, $b) => $a['at']->timestamp <=> $b['at']->timestamp);

        return array_slice($occurrences, 0, max($count, 1) * 10);
    }
}
