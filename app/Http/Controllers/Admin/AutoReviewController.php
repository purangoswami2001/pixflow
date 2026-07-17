<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Jobs\AutoReviewPostJob;
use App\Models\Post;
use App\Services\Automation\AutoReviewAgentService;
use App\Services\Automation\AutoReviewLogger;
use App\Support\PerPage;
use Illuminate\Http\Request;

class AutoReviewController extends Controller
{
    public function index(Request $request, AutoReviewAgentService $agent)
    {
        $config = config('automation.auto_review', []);
        $enabled = $agent->enabled();
        $perPage = PerPage::resolve($request);

        $pending = Post::query()
            ->with(['targets', 'user'])
            ->where('status', PostStatus::PENDING_APPROVAL)
            ->where('metadata->source', 'automation')
            ->whereNotNull('metadata->run_id')
            ->whereNotNull('metadata->run_item_id')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'pending_page')
            ->withQueryString();

        $reviewed = Post::query()
            ->with(['targets', 'user'])
            ->where('metadata->source', 'automation')
            ->whereNotNull('metadata->run_id')
            ->whereNotNull('metadata->auto_review->decision')
            ->where('metadata->auto_review->reviewed_by', 'auto-review-agent')
            ->orderByDesc('updated_at')
            ->paginate($perPage, ['*'], 'reviewed_page')
            ->withQueryString();

        $stats = [
            'pending' => Post::query()
                ->where('status', PostStatus::PENDING_APPROVAL)
                ->where('metadata->source', 'automation')
                ->whereNotNull('metadata->run_id')
                ->count(),
            'approved_by_agent' => Post::query()
                ->where('metadata->auto_review->reviewed_by', 'auto-review-agent')
                ->where('metadata->auto_review->decision', 'approve')
                ->where('metadata->source', 'automation')
                ->where('updated_at', '>=', now()->subDays(30))
                ->count(),
            'revision_by_agent' => Post::query()
                ->where('metadata->auto_review->reviewed_by', 'auto-review-agent')
                ->where('metadata->auto_review->decision', 'revision')
                ->where('metadata->source', 'automation')
                ->where('updated_at', '>=', now()->subDays(30))
                ->count(),
            'rejected_by_agent' => Post::query()
                ->where('metadata->auto_review->reviewed_by', 'auto-review-agent')
                ->where('metadata->auto_review->decision', 'reject')
                ->where('metadata->source', 'automation')
                ->where('updated_at', '>=', now()->subDays(30))
                ->count(),
            'unreviewed_pending' => Post::query()
                ->where('status', PostStatus::PENDING_APPROVAL)
                ->where('metadata->source', 'automation')
                ->whereNotNull('metadata->run_id')
                ->whereNotNull('metadata->run_item_id')
                ->where(function ($q) {
                    $q->whereNull('metadata->auto_review->decision')
                        ->orWhere('metadata->auto_review->decision', '');
                })
                ->count(),
        ];

        return view('admin.automation.auto-review', compact(
            'enabled',
            'config',
            'pending',
            'reviewed',
            'perPage',
            'stats'
        ));
    }

    public function show(int $id)
    {
        $post = Post::with(['targets.mediaAsset', 'targets.socialAccount', 'user'])->findOrFail($id);
        $report = $post->metadata['auto_review'] ?? null;

        if (! is_array($report) || empty($report['decision'])) {
            return redirect()
                ->route('admin.automation.auto-review')
                ->withErrors(['auto_review' => "Post #{$post->id} has no agent review report yet."]);
        }

        return view('admin.automation.auto-review-show', [
            'post' => $post,
            'report' => $report,
            'enabled' => app(AutoReviewAgentService::class)->enabled(),
        ]);
    }

    public function scan(Request $request, AutoReviewAgentService $agent)
    {
        if (! $agent->enabled()) {
            return back()->withErrors(['auto_review' => 'Auto-review is disabled. Set AUTO_REVIEW_ENABLED=true in .env, then run php artisan config:clear.']);
        }

        $data = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'mode' => 'nullable|in:queue,sync',
        ]);

        $limit = (int) ($data['limit'] ?? config('automation.auto_review.scan_limit', 20));
        $sync = ($data['mode'] ?? 'queue') === 'sync';

        $posts = Post::query()
            ->where('status', PostStatus::PENDING_APPROVAL)
            ->where('metadata->source', 'automation')
            ->whereNotNull('metadata->run_id')
            ->whereNotNull('metadata->run_item_id')
            ->where(function ($q) {
                $q->whereNull('metadata->auto_review->decision')
                    ->orWhere('metadata->auto_review->decision', '');
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($posts->isEmpty()) {
            return back()->with('status', 'No unreviewed automation posts are pending.');
        }

        $processed = 0;
        $summary = ['approve' => 0, 'revision' => 0, 'reject' => 0];

        foreach ($posts as $post) {
            if ($sync) {
                $result = $agent->reviewPost($post->load(['targets.mediaAsset']));
                if (! empty($result['decision'])) {
                    $summary[$result['decision']] = ($summary[$result['decision']] ?? 0) + 1;
                    $processed++;
                }
            } else {
                AutoReviewPostJob::dispatch($post->id);
                $processed++;
            }
        }

        AutoReviewLogger::info('Admin triggered pending scan', [
            'mode' => $sync ? 'sync' : 'queue',
            'count' => $processed,
            'admin_id' => auth()->id(),
        ]);

        if ($sync) {
            return back()->with('status', "Reviewed {$processed} post(s): "
                ."{$summary['approve']} approved, {$summary['revision']} revision, {$summary['reject']} rejected.");
        }

        return back()->with('status', "Queued auto-review for {$processed} post(s). Keep a queue worker running.");
    }

    public function reviewOne(Request $request, int $id, AutoReviewAgentService $agent)
    {
        if (! $agent->enabled()) {
            return back()->withErrors(['auto_review' => 'Auto-review is disabled. Set AUTO_REVIEW_ENABLED=true in .env.']);
        }

        $post = Post::with(['targets.mediaAsset'])->findOrFail($id);

        if (! $agent->isEligibleForAutoReview($post)) {
            return back()->withErrors(['auto_review' => $agent->ineligibilityReason($post)]);
        }

        $data = $request->validate([
            'mode' => 'nullable|in:queue,sync',
            'force' => 'nullable|boolean',
        ]);

        if ($request->boolean('force')) {
            $meta = $post->metadata ?? [];
            unset($meta['auto_review']);
            $post->update([
                'metadata' => $meta,
                'status' => PostStatus::PENDING_APPROVAL,
            ]);
            $post->targets()->update(['status' => PostStatus::PENDING_APPROVAL]);
            $post->refresh()->load(['targets.mediaAsset']);
        }

        if (($data['mode'] ?? 'sync') === 'queue') {
            AutoReviewPostJob::dispatch($post->id);
            AutoReviewLogger::info('Admin queued single review', [
                'post_id' => $post->id,
                'admin_id' => auth()->id(),
            ]);

            return back()->with('status', "Queued AutoReview for post #{$post->id}.");
        }

        try {
            $result = $agent->reviewPost($post);
        } catch (\Throwable $e) {
            AutoReviewLogger::error('Admin single review failed', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['auto_review' => $e->getMessage()]);
        }

        if (! empty($result['skipped'])) {
            return back()->with('status', "Skipped #{$post->id}: ".($result['reason'] ?? 'n/a'));
        }

        $score = $result['report']['overall_score'] ?? '?';

        return back()->with('status', "Post #{$post->id} → {$result['decision']} (score {$score}).");
    }
}
