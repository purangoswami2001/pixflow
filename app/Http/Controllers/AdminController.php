<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PublishLog;
use App\Models\SocialAccount;
use App\Services\Posts\QueueScheduleCalculatorService;
use App\Services\Media\MediaConfigService;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    // Middleware check moved to web.php route definition

    public function users()
    {
        $users = User::with(['roles', 'socialAccounts', 'posts'])->get();

        return view('admin.users', [
            'users' => $users,
        ]);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'role' => 'required|in:admin,creator',
        ];

        if ($request->filled('password')) {
            $rules['password'] = 'string|min:8';
        }

        $request->validate($rules);

        if ($id == auth()->id() && $request->role !== 'admin') {
            return redirect()->back()->with('error', 'You cannot remove your own admin role.');
        }

        $user->name = $request->name;
        $user->email = $request->email;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        Role::findOrCreate($request->role);
        $user->syncRoles([$request->role]);

        return redirect()->back()->with('status', "User {$user->name} updated successfully.");
    }

    public function deleteUser($id)
    {
        if ($id == auth()->id()) {
            return redirect()->back()->with('error', 'You cannot delete your own account.');
        }

        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->back()->with('status', 'User account deleted successfully.');
    }

    public function logs(Request $request)
    {
        $filter = $request->query('status', 'all');
        $query = PublishLog::with(['postTarget.post', 'postTarget.socialAccount.user'])
            ->orderBy('created_at', 'desc');

        if ($filter !== 'all') {
            $query->where('status', $filter);
        }

        $logs = $query->get();

        $selectedLog = null;
        if ($request->query('selected_log')) {
            $selectedLog = PublishLog::find($request->query('selected_log'));
        }

        return view('admin.logs', [
            'logs' => $logs,
            'statusFilter' => $filter,
            'selectedLog' => $selectedLog,
        ]);
    }

    public function clearLogs()
    {
        PublishLog::truncate();
        return redirect()->route('admin.logs')->with('status', 'Publish logs cleared successfully.');
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,creator',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
        ]);

        // Dynamically assign Spatie role (ensuring roles exist)
        Role::findOrCreate($request->role);
        $user->assignRole($request->role);

        return redirect()->back()->with('status', "User {$user->name} created successfully with role {$request->role}.");
    }

    public function schedule(Request $request, MediaConfigService $mediaConfig)
    {
        $adminIds = User::role('admin')->pluck('id');
        $channelFilter = $request->query('channel', 'all');

        $accounts = SocialAccount::query()
            ->where('status', 'active')
            ->whereIn('user_id', $adminIds)
            ->orderBy('provider')
            ->orderBy('name')
            ->get();

        $allAccounts = $accounts;
        $accounts = $accounts
            ->when($channelFilter !== 'all', fn ($c) => $c->filter(
                fn ($a) => $a->provider->value === $channelFilter
            ))
            ->values();

        $stats = [
            'total' => $allAccounts->count(),
            'facebook' => $allAccounts->where(fn ($a) => $a->provider->value === 'facebook')->count(),
            'instagram' => $allAccounts->where(fn ($a) => $a->provider->value === 'instagram')->count(),
            'pinterest' => $allAccounts->where(fn ($a) => $a->provider->value === 'pinterest')->count(),
        ];

        return view('admin.schedule', [
            'accounts' => $accounts,
            'channels' => $mediaConfig->channels(),
            'channelFilter' => $channelFilter,
            'stats' => $stats,
        ]);
    }

    public function scheduleAccount(Request $request, $accountId, QueueScheduleCalculatorService $calculator, MediaConfigService $mediaConfig)
    {
        $adminIds = User::role('admin')->pluck('id');
        $account = SocialAccount::query()
            ->whereIn('user_id', $adminIds)
            ->where('status', 'active')
            ->findOrFail($accountId);

        $today = now()->startOfDay();
        $weekParam = $request->query('week');
        $rangeStart = $weekParam
            ? \Carbon\Carbon::parse($weekParam)->startOfDay()
            : $today->copy();

        if ($rangeStart->lt($today)) {
            $rangeStart = $today->copy();
        }

        $destination = $account->primaryDestination();
        if ($destination) {
            $calculator->refreshDestinationSchedule($destination->id);
        }

        $weekCalendar = $calculator->buildAccountWeekCalendar($account, $rangeStart);
        $canGoPrevious = $rangeStart->gt($today);
        $prevWeek = $canGoPrevious ? $rangeStart->copy()->subDays(7)->format('Y-m-d') : null;
        $nextWeek = $rangeStart->copy()->addDays(7)->format('Y-m-d');

        $scheduledPosts = collect($weekCalendar['days'] ?? [])->sum(function ($day) {
            $slotPosts = collect($day['times'] ?? [])->sum(fn ($t) => count($t['posts'] ?? []));
            $manual = count($day['manual_posts'] ?? []);

            return $slotPosts + $manual;
        });

        $daysMap = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday'];
        $timezones = $this->scheduleTimezones();
        $queueSlots = collect();

        if ($destination) {
            $queueSlots = \App\Models\QueueSlot::where('social_destination_id', $destination->id)
                ->get()
                ->sortBy(function ($slot) {
                    $day = (int) $slot->day_of_week === 0 ? 7 : (int) $slot->day_of_week;

                    return sprintf('%02d-%s', $day, $slot->time);
                })
                ->values()
                ->map(function ($slot) use ($daysMap, $timezones) {
                    $dow = (int) $slot->day_of_week;
                    $time = \Carbon\Carbon::parse($slot->time);
                    $dayName = $daysMap[$dow] ?? 'Unknown';

                    return [
                        'id' => $slot->id,
                        'day_of_week' => $dow,
                        'day_name' => $dayName,
                        'day_short' => substr($dayName, 0, 3),
                        'time' => $time->format('g:i A'),
                        'time_value' => $time->format('H:i'),
                        'timezone' => $slot->timezone,
                        'timezone_label' => $timezones[$slot->timezone] ?? $slot->timezone,
                        'posts_per_slot' => (int) $slot->posts_per_slot,
                    ];
                });
        }

        return view('admin.schedule-account', [
            'account' => $account,
            'weekCalendar' => $weekCalendar,
            'rangeStart' => $rangeStart,
            'canGoPrevious' => $canGoPrevious,
            'prevWeek' => $prevWeek,
            'nextWeek' => $nextWeek,
            'timezones' => $timezones,
            'daysMap' => $daysMap,
            'queueSlots' => $queueSlots,
            'channelFilter' => $request->query('channel', 'all'),
            'scheduledPosts' => $scheduledPosts,
        ]);
    }

    public function queuedPostsForSchedule(Request $request, QueueScheduleCalculatorService $calculator)
    {
        $request->validate(['account_id' => 'required|integer|exists:social_accounts,id']);

        return response()->json([
            'posts' => $calculator->queuedPostsForAccount((int) $request->account_id),
        ]);
    }

    public function assignManualSchedule(Request $request, QueueScheduleCalculatorService $calculator)
    {
        $request->validate([
            'queue_slot_id' => 'required|exists:queue_slots,id',
            'post_target_id' => 'required|exists:post_targets,id',
            'schedule_date' => 'required|date|after_or_equal:today',
        ]);

        $slot = \App\Models\QueueSlot::findOrFail($request->queue_slot_id);
        $target = \App\Models\PostTarget::with('post')->findOrFail($request->post_target_id);

        abort_unless($target->social_destination_id === $slot->social_destination_id, 422);
        abort_unless($target->status === \App\Enums\PostStatus::SCHEDULED, 422);
        abort_unless($target->post->status === \App\Enums\PostStatus::APPROVED, 422);
        abort_unless(
            ! $target->schedule_mode || $target->schedule_mode === \App\Enums\ScheduleMode::AUTO,
            422
        );

        try {
            $calculator->assignManualPost(
                $slot,
                $target,
                \Carbon\Carbon::parse($request->schedule_date, $slot->timezone ?: 'UTC')
            );
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('status', 'Post pinned to the selected date. It is no longer in the auto queue.');
    }

    public function releaseManualSchedule($targetId, QueueScheduleCalculatorService $calculator)
    {
        $target = \App\Models\PostTarget::findOrFail($targetId);
        abort_unless($target->schedule_mode === \App\Enums\ScheduleMode::MANUAL, 422);

        $calculator->releaseManualPost($target);

        return redirect()->back()->with('status', 'Post returned to the auto queue.');
    }

    public function scheduleManualPost(Request $request, QueueScheduleCalculatorService $calculator)
    {
        $request->validate([
            'social_account_id' => 'required|exists:social_accounts,id',
            'post_target_id' => 'required|exists:post_targets,id',
            'schedule_date' => 'required|date|after_or_equal:today|before_or_equal:' . now()->addDays(30)->format('Y-m-d'),
            'time' => 'required',
            'timezone' => 'required|string|max:64',
        ]);

        $account = SocialAccount::findOrFail($request->social_account_id);
        $destination = $account->primaryDestination();
        abort_unless($destination, 422, 'Account is not ready for scheduling.');

        $target = \App\Models\PostTarget::with('post')->findOrFail($request->post_target_id);
        abort_unless($target->social_destination_id === $destination->id, 422);
        abort_unless($target->status === \App\Enums\PostStatus::SCHEDULED, 422);
        abort_unless($target->post->status === \App\Enums\PostStatus::APPROVED, 422);
        abort_unless(
            ! $target->schedule_mode || $target->schedule_mode === \App\Enums\ScheduleMode::AUTO,
            422
        );

        try {
            $calculator->scheduleManualPostAt(
                $target,
                \Carbon\Carbon::parse($request->schedule_date, $request->timezone),
                $request->time,
                $request->timezone
            );
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('status', 'Post scheduled for the selected date and time.');
    }

    public function saveScheduleSlot(Request $request, QueueScheduleCalculatorService $calculator)
    {
        $request->validate([
            'social_account_id' => 'required|exists:social_accounts,id',
            'day_of_week' => 'required|integer|between:0,6',
            'time' => 'required',
            'timezone' => 'required|string|max:64',
            'posts_per_slot' => 'required|integer|min:1|max:20',
        ]);

        $account = SocialAccount::findOrFail($request->social_account_id);
        $destination = $account->primaryDestination();

        if (! $destination) {
            return redirect()->back()->with('error', 'Account is not ready for scheduling. Try syncing the profile first.');
        }

        $exists = \App\Models\QueueSlot::where('social_destination_id', $destination->id)
            ->where('day_of_week', $request->day_of_week)
            ->where('time', \Carbon\Carbon::parse($request->time)->format('H:i:00'))
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->with('error', 'A slot already exists for this day and time.')
                ->with('open_slots_modal', true);
        }

        try {
            $slot = \App\Models\QueueSlot::create([
                'social_destination_id' => $destination->id,
                'day_of_week' => $request->day_of_week,
                'time' => \Carbon\Carbon::parse($request->time)->format('H:i:00'),
                'timezone' => $request->timezone,
                'posts_per_slot' => $request->posts_per_slot,
            ]);

            $calculator->refreshDestinationSchedule($slot->social_destination_id);

            return redirect()->back()
                ->with('status', 'Weekly queue slot added successfully.')
                ->with('open_slots_modal', true);
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Could not add slot. It may already exist for this day and time.')
                ->with('open_slots_modal', true);
        }
    }

    public function updateScheduleSlot(Request $request, $id, QueueScheduleCalculatorService $calculator)
    {
        $slot = $this->findAdminQueueSlot($id);

        $request->validate([
            'day_of_week' => 'required|integer|between:0,6',
            'time' => 'required',
            'timezone' => 'required|string|max:64',
            'posts_per_slot' => 'required|integer|min:1|max:20',
        ]);

        $timeFormatted = \Carbon\Carbon::parse($request->time)->format('H:i:00');

        $exists = \App\Models\QueueSlot::where('social_destination_id', $slot->social_destination_id)
            ->where('day_of_week', $request->day_of_week)
            ->where('time', $timeFormatted)
            ->where('id', '!=', $slot->id)
            ->exists();

        if ($exists) {
            return redirect()->back()
                ->with('error', 'A slot already exists for this day and time.')
                ->with('open_slots_modal', true);
        }

        $slot->update([
            'day_of_week' => $request->day_of_week,
            'time' => $timeFormatted,
            'timezone' => $request->timezone,
            'posts_per_slot' => $request->posts_per_slot,
        ]);

        $calculator->refreshDestinationSchedule($slot->social_destination_id);

        return redirect()->back()
            ->with('status', 'Weekly queue slot updated successfully.')
            ->with('open_slots_modal', true);
    }

    public function deleteScheduleSlot($id, QueueScheduleCalculatorService $calculator)
    {
        $slot = $this->findAdminQueueSlot($id);
        $destinationId = $slot->social_destination_id;
        $slot->delete();

        $calculator->refreshDestinationSchedule($destinationId);

        return redirect()->back()
            ->with('status', 'Weekly queue slot deleted successfully.')
            ->with('open_slots_modal', true);
    }

    protected function findAdminQueueSlot(int $id): \App\Models\QueueSlot
    {
        $adminIds = User::role('admin')->pluck('id');
        $slot = \App\Models\QueueSlot::with('socialDestination.socialAccount')->findOrFail($id);
        $account = $slot->socialDestination?->socialAccount;

        abort_unless($account && $adminIds->contains($account->user_id), 403);

        return $slot;
    }

    protected function scheduleTimezones(): array
    {
        return \App\Support\ScheduleTimezones::options();
    }
}
