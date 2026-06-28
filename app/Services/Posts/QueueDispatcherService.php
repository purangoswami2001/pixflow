<?php

namespace App\Services\Posts;

use App\Enums\PostStatus;
use App\Enums\ScheduleMode;
use App\Models\PostTarget;
use App\Models\QueueSlot;
use App\Jobs\PublishPostTargetJob;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class QueueDispatcherService
{
    public function dispatchDueSlots(): void
    {
        $slots = QueueSlot::all();

        if ($slots->isEmpty()) {
            return;
        }

        foreach ($slots as $slot) {
            $this->dispatchForSlot($slot);
        }
    }

    public function dispatchManualDuePosts(): void
    {
        $now = now();

        $targets = PostTarget::manualScheduled()
            ->where('status', PostStatus::SCHEDULED)
            ->whereHas('post', fn ($q) => $q->where('status', PostStatus::APPROVED))
            ->where('scheduled_at', '<=', $now)
            ->get();

        foreach ($targets as $target) {
            $this->dispatchTarget($target);
        }
    }

    protected function dispatchForSlot(QueueSlot $slot): void
    {
        $timezone = $slot->timezone ?: 'UTC';
        $now = now()->timezone($timezone);
        $slotTime = Carbon::parse($slot->time)->format('H:i:00');

        if ((int) $now->dayOfWeek !== (int) $slot->day_of_week) {
            return;
        }

        if ($now->format('H:i:00') !== $slotTime) {
            return;
        }

        $capacity = max(1, (int) $slot->posts_per_slot);
        $dispatched = 0;

        $manualTargets = PostTarget::manualScheduled()
            ->where('queue_slot_id', $slot->id)
            ->where('social_destination_id', $slot->social_destination_id)
            ->where('status', PostStatus::SCHEDULED)
            ->whereHas('post', fn ($q) => $q->where('status', PostStatus::APPROVED))
            ->whereBetween('scheduled_at', [
                now()->startOfMinute(),
                now()->endOfMinute(),
            ])
            ->orderBy('created_at')
            ->get();

        foreach ($manualTargets as $target) {
            if ($dispatched >= $capacity) {
                break;
            }
            $this->dispatchTarget($target);
            $dispatched++;
        }

        $remaining = $capacity - $dispatched;
        if ($remaining <= 0) {
            return;
        }

        $autoTargets = PostTarget::autoQueued()
            ->where('social_destination_id', $slot->social_destination_id)
            ->where('status', PostStatus::SCHEDULED)
            ->whereHas('post', fn ($q) => $q->where('status', PostStatus::APPROVED))
            ->orderBy('created_at')
            ->limit($remaining)
            ->get();

        foreach ($autoTargets as $target) {
            $this->dispatchTarget($target);
        }
    }

    protected function dispatchTarget(PostTarget $target): void
    {
        Log::info("QueueDispatcher: Dispatching Post Target ID {$target->id}");

        $target->update(['status' => PostStatus::PUBLISHING]);
        $target->post->update(['status' => PostStatus::PUBLISHING]);

        PublishPostTargetJob::dispatch($target->id);
    }
}
