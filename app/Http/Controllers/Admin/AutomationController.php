<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateAutomatedPostJob;
use App\Jobs\RunAutomationCampaignJob;
use App\Models\AutomationCampaign;
use App\Models\AutomationRun;
use App\Models\AutomationRunItem;
use App\Models\ContentCategory;
use App\Models\ContentTopic;
use App\Models\PromptTemplate;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Automation\AutomationLogReader;
use App\Services\Automation\AutomationMediaService;
use App\Services\Automation\ContentLibraryGeneratorService;
use App\Support\AutomationCampaignPresets;
use App\Support\AutomationImageStyles;
use App\Support\AutomationContentBundle;
use App\Support\AutomationPromptBundle;
use App\Support\AutomationPromptSchema;
use App\Support\AutomationRunItemPresenter;
use App\Support\PerPage;
use App\Support\ScheduleTimezones;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AutomationController extends Controller
{
    public function index()
    {
        $campaigns = AutomationCampaign::with('category')->orderByDesc('updated_at')->get();
        $recentRuns = AutomationRun::with('campaign')
            ->withCount([
                'items as live_failed_count' => fn ($q) => $q->where('status', 'failed'),
                'items as live_created_count' => fn ($q) => $q->where('status', 'post_created'),
            ])
            ->orderByDesc('id')
            ->limit(8)
            ->get();
        $recentRuns->each(function (AutomationRun $run) {
            $run->failed_count = (int) $run->live_failed_count;
            $run->created_count = (int) $run->live_created_count;
            if ($run->failed_count === 0 && $run->status === 'partial' && (int) $run->live_created_count >= $run->planned_count) {
                $run->status = 'completed';
            }
        });
        $stats = [
            'categories' => ContentCategory::where('is_active', true)->count(),
            'topics' => ContentTopic::where('is_active', true)->count(),
            'campaigns_active' => $campaigns->where('is_active', true)->count(),
            'campaigns_total' => $campaigns->count(),
            'runs_today' => AutomationRun::whereDate('created_at', today())->count(),
            'failed_recent' => AutomationRunItem::where('status', 'failed')->where('updated_at', '>=', now()->subDays(7))->count(),
        ];

        return view('admin.automation.index', compact('campaigns', 'recentRuns', 'stats'));
    }

    public function categories(Request $request)
    {
        $perPage = PerPage::resolve($request, 10);
        $categories = ContentCategory::withCount('topics')
            ->orderBy('sort_order')
            ->paginate($perPage)
            ->withQueryString();
        $mode = $request->query('mode', 'manual') === 'ai' ? 'ai' : 'manual';
        $brief = old('category_brief', '');
        $contentMetaPrompt = AutomationContentBundle::metaPrompt($brief);
        $contentBundleJson = old('content_bundle', session('generated_bundle', AutomationContentBundle::exportJson()));

        return view('admin.automation.categories', compact(
            'categories',
            'perPage',
            'mode',
            'contentMetaPrompt',
            'contentBundleJson'
        ));
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'niche_prompt' => 'nullable|string',
            'safety_rules' => 'nullable|string',
            'audience' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        ContentCategory::create([
            ...$data,
            'slug' => Str::slug($data['name']) . '-' . Str::random(4),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('status', 'Category created.');
    }

    public function generateCategoryBundle(Request $request, ContentLibraryGeneratorService $generator)
    {
        $data = $request->validate([
            'category_brief' => 'required|string|min:10|max:2000',
        ]);

        try {
            $bundle = $generator->generate($data['category_brief']);

            return redirect()
                ->route('admin.automation.categories', ['mode' => 'ai'])
                ->withInput(['category_brief' => $data['category_brief']])
                ->with('generated_bundle', json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.automation.categories', ['mode' => 'ai'])
                ->withInput()
                ->withErrors(['category_brief' => $e->getMessage()]);
        }
    }

    public function importCategoryBundle(Request $request)
    {
        $request->validate([
            'content_bundle' => 'required|string',
        ]);

        try {
            $result = AutomationContentBundle::import($request->input('content_bundle'));

            return redirect()
                ->route('admin.automation.topics', $result['category'])
                ->with('status', "Category \"{$result['category']->name}\" created with {$result['topics_created']} topics.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->route('admin.automation.categories', ['mode' => 'ai'])
                ->withInput()
                ->withErrors($e->errors());
        }
    }

    public function updateCategory(Request $request, ContentCategory $category)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'niche_prompt' => 'nullable|string',
            'safety_rules' => 'nullable|string',
            'audience' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $category->update([
            ...$data,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('status', 'Category updated.');
    }

    public function topics(Request $request, ContentCategory $category)
    {
        $perPage = PerPage::resolve($request);
        $topics = $category->topics()
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.automation.topics', compact('category', 'topics', 'perPage'));
    }

    public function storeTopic(Request $request, ContentCategory $category)
    {
        $data = $request->validate([
            'title' => 'required|string|max:500',
            'topic_type' => 'required|in:educational,promotional,listicle,tip',
            'link_url' => 'nullable|url|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        ContentTopic::create([
            'content_category_id' => $category->id,
            'title' => $data['title'],
            'topic_type' => $data['topic_type'],
            'is_active' => $request->boolean('is_active', true),
            'metadata' => array_filter(['link_url' => $data['link_url'] ?? null]),
        ]);

        return back()->with('status', 'Topic added.');
    }

    public function bulkTopics(Request $request, ContentCategory $category)
    {
        $request->validate([
            'titles' => 'required|string',
            'topic_type' => 'nullable|in:educational,promotional,listicle,tip',
        ]);
        $lines = preg_split('/\r\n|\r|\n/', $request->titles) ?: [];
        $topicType = $request->input('topic_type', 'educational');
        $count = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            ContentTopic::create([
                'content_category_id' => $category->id,
                'title' => $line,
                'topic_type' => $topicType,
                'is_active' => true,
            ]);
            $count++;
        }

        return back()->with('status', "{$count} topics imported.");
    }

    public function deleteTopic(ContentTopic $topic)
    {
        $topic->delete();

        return back()->with('status', 'Topic removed.');
    }

    public function updateTopic(Request $request, ContentTopic $topic)
    {
        $data = $request->validate([
            'title' => 'required|string|max:500',
            'topic_type' => 'required|in:educational,promotional,listicle,tip',
            'link_url' => 'nullable|url|max:500',
            'is_active' => 'nullable|boolean',
        ]);

        $metadata = $topic->metadata ?? [];
        if (! empty($data['link_url'])) {
            $metadata['link_url'] = $data['link_url'];
        } else {
            unset($metadata['link_url']);
        }

        $topic->update([
            'title' => $data['title'],
            'topic_type' => $data['topic_type'],
            'is_active' => $request->boolean('is_active'),
            'metadata' => $metadata ?: null,
        ]);

        return back()->with('status', 'Topic updated.');
    }

    public function prompts()
    {
        $templates = PromptTemplate::orderBy('platform')->orderBy('step')->get();
        $schemas = AutomationPromptSchema::steps();
        $placeholders = AutomationPromptSchema::placeholders();
        $metaPrompt = AutomationPromptSchema::metaPrompt();
        $promptBundleJson = AutomationPromptBundle::exportJson();

        return view('admin.automation.prompts', compact('templates', 'schemas', 'placeholders', 'metaPrompt', 'promptBundleJson'));
    }

    public function bulkUpdatePrompts(Request $request)
    {
        $request->validate([
            'prompt_bundle' => 'required|string',
        ]);

        $result = AutomationPromptBundle::import($request->input('prompt_bundle'));

        if ($result['updated'] === 0 && $result['errors'] !== []) {
            return back()
                ->withInput()
                ->with('error', 'No templates updated. '.implode(' ', array_slice($result['errors'], 0, 3)));
        }

        $message = "Updated {$result['updated']} template(s).";
        if ($result['errors'] !== []) {
            $message .= ' Warnings: '.implode(' | ', array_slice($result['errors'], 0, 5));
        }

        return back()->with('status', $message);
    }

    public function updatePrompt(Request $request, PromptTemplate $template)
    {
        $data = $request->validate([
            'template' => 'required|string',
            'is_active' => 'nullable|boolean',
        ]);

        $template->update([
            'template' => $data['template'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('status', 'Prompt template saved.');
    }

    public function campaigns(Request $request)
    {
        $perPage = PerPage::resolve($request);
        $campaigns = AutomationCampaign::with('category')
            ->withCount('runs')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin.automation.campaigns', compact('campaigns', 'perPage'));
    }

    public function createCampaign()
    {
        return view('admin.automation.campaign-form', array_merge(
            $this->campaignFormData(),
            ['campaign' => null]
        ));
    }

    public function editCampaign(AutomationCampaign $campaign)
    {
        return view('admin.automation.campaign-form', array_merge(
            $this->campaignFormData($campaign),
            ['campaign' => $campaign]
        ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function campaignFormData(?AutomationCampaign $campaign = null): array
    {
        return [
            'categories' => ContentCategory::where('is_active', true)->orderBy('name')->get(),
            'accounts' => SocialAccount::whereIn('user_id', User::role('admin')->pluck('id'))->where('status', 'active')->get(),
            'timezones' => ScheduleTimezones::options(),
            'channelImagePresets' => AutomationCampaignPresets::optionsByPlatform(),
            'channelImageStyles' => AutomationImageStyles::options(),
        ];
    }

    public function storeCampaign(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'content_category_id' => 'required|exists:content_categories,id',
            'posts_per_day' => 'required|integer|min:1|max:20',
            'promotional_ratio' => 'required|integer|min:0|max:100',
            'default_timezone' => 'required|string|in:'.implode(',', array_keys(ScheduleTimezones::options())),
            'generation_time' => 'required|date_format:H:i',
            'platforms' => 'required|array|min:1',
            'platforms.*' => 'in:pinterest,facebook,instagram',
            'platform_accounts' => 'nullable|array',
            'platform_counts' => 'nullable|array',
            'platform_image_presets' => 'nullable|array',
            'platform_image_styles' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $imagePresets = AutomationCampaignPresets::normalizeInput(
            $request->input('platform_image_presets', []),
            $data['platforms']
        );
        $imageStyles = AutomationImageStyles::normalizeInput(
            $request->input('platform_image_styles', []),
            $data['platforms']
        );

        AutomationCampaign::create([
            'name' => $data['name'],
            'content_category_id' => $data['content_category_id'],
            'posts_per_day' => $data['posts_per_day'],
            'promotional_ratio' => $data['promotional_ratio'],
            'default_timezone' => $data['default_timezone'],
            'generation_time' => $data['generation_time'] . ':00',
            'platforms' => $data['platforms'],
            'platform_accounts' => $request->input('platform_accounts', []),
            'platform_counts' => $request->input('platform_counts', []),
            'platform_image_presets' => $imagePresets,
            'platform_image_styles' => $imageStyles,
            'is_active' => $request->boolean('is_active', true),
            'created_by' => auth()->id(),
            'next_run_at' => now(),
        ]);

        AutomationMediaService::aiAgentUser();

        return redirect()->route('admin.automation.campaigns')->with('status', 'Automation campaign created.');
    }

    public function updateCampaign(Request $request, AutomationCampaign $campaign)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'content_category_id' => 'required|exists:content_categories,id',
            'posts_per_day' => 'required|integer|min:1|max:20',
            'promotional_ratio' => 'required|integer|min:0|max:100',
            'default_timezone' => 'required|string|in:'.implode(',', array_keys(ScheduleTimezones::options())),
            'generation_time' => 'required|date_format:H:i',
            'platforms' => 'required|array|min:1',
            'platforms.*' => 'in:pinterest,facebook,instagram',
            'platform_accounts' => 'nullable|array',
            'platform_counts' => 'nullable|array',
            'platform_image_presets' => 'nullable|array',
            'platform_image_styles' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $imagePresets = AutomationCampaignPresets::normalizeInput(
            $request->input('platform_image_presets', []),
            $data['platforms']
        );
        $imageStyles = AutomationImageStyles::normalizeInput(
            $request->input('platform_image_styles', []),
            $data['platforms']
        );

        $campaign->update([
            'name' => $data['name'],
            'content_category_id' => $data['content_category_id'],
            'posts_per_day' => $data['posts_per_day'],
            'promotional_ratio' => $data['promotional_ratio'],
            'default_timezone' => $data['default_timezone'],
            'generation_time' => $data['generation_time'] . ':00',
            'platforms' => $data['platforms'],
            'platform_accounts' => $request->input('platform_accounts', []),
            'platform_counts' => $request->input('platform_counts', []),
            'platform_image_presets' => $imagePresets,
            'platform_image_styles' => $imageStyles,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.automation.campaigns.edit', $campaign)->with('status', 'Campaign updated.');
    }

    public function runCampaign(AutomationCampaign $campaign)
    {
        RunAutomationCampaignJob::dispatch($campaign->id);

        return back()->with('status', 'Automation run queued. Ensure queue:work is running.');
    }

    public function runs(Request $request)
    {
        $perPage = PerPage::resolve($request);
        $runs = AutomationRun::with(['campaign', 'items.post'])
            ->withCount([
                'items as live_failed_count' => fn ($q) => $q->where('status', 'failed'),
                'items as live_created_count' => fn ($q) => $q->where('status', 'post_created'),
            ])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $runs->getCollection()->transform(function (AutomationRun $run) {
            $run->failed_count = (int) $run->live_failed_count;
            $run->created_count = (int) $run->live_created_count;

            $pending = $run->items->whereNotIn('status', ['post_created', 'failed'])->count();
            if ($pending === 0) {
                if ($run->failed_count > 0 && $run->created_count > 0) {
                    $run->status = 'partial';
                } elseif ($run->failed_count > 0) {
                    $run->status = 'failed';
                } elseif ($run->created_count > 0) {
                    $run->status = 'completed';
                }
            }

            return $run;
        });

        return view('admin.automation.runs', compact('runs', 'perPage'));
    }

    public function showRun(AutomationRun $run)
    {
        $run->load(['campaign', 'items.topic', 'items.post']);
        $run->syncCountsFromItems(finalize: $run->items->whereNotIn('status', ['post_created', 'failed'])->count() === 0);
        $run->refresh()->load(['campaign', 'items.topic', 'items.post']);
        $pipelineSteps = AutomationPromptSchema::pipelineSteps();

        return view('admin.automation.run-show', compact('run', 'pipelineSteps'));
    }

    public function logs(Request $request, AutomationLogReader $logReader)
    {
        $runId = $request->integer('run_id') ?: null;
        $itemId = $request->integer('item_id') ?: null;
        $lines = $logReader->readLines(500, $runId, $itemId);
        $logFile = $logReader->resolveLogFile();

        return view('admin.automation.logs', [
            'lines' => $lines,
            'runId' => $runId,
            'itemId' => $itemId,
            'logFileName' => $logFile['name'] ?? 'automation.log',
        ]);
    }

    public function logsFeed(Request $request, AutomationLogReader $logReader)
    {
        $runId = $request->integer('run_id') ?: null;
        $itemId = $request->integer('item_id') ?: null;
        $excludeHashes = array_filter(array_map('trim', explode(',', (string) $request->input('exclude_hashes', ''))));

        $entries = $logReader->readLines(100, $runId, $itemId, $excludeHashes);
        $logFile = $logReader->resolveLogFile();
        $total = $logReader->countLines($runId, $itemId);

        return response()->json([
            'entries' => $entries,
            'total' => $total,
            'log_file' => $logFile['name'],
            'poll_interval' => 3000,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function runFeed(AutomationRun $run)
    {
        $run->load(['campaign', 'items.topic', 'items.post']);
        $run->syncCountsFromItems(finalize: $run->items->whereNotIn('status', ['post_created', 'failed'])->count() === 0);
        $run->refresh()->load(['campaign', 'items.topic', 'items.post']);
        $pipelineSteps = AutomationPromptSchema::pipelineSteps();

        $items = $run->items->map(function (AutomationRunItem $item) use ($pipelineSteps, $run) {
            $data = AutomationRunItemPresenter::toFeedArray($item, $pipelineSteps);
            $data['html'] = view('admin.automation._run-item-live', [
                'item' => $item,
                'pipelineSteps' => $pipelineSteps,
                'run' => $run,
            ])->render();

            return $data;
        })->values();

        $isActive = $run->status === 'running'
            || $run->items->contains(fn (AutomationRunItem $item) => ! AutomationRunItemPresenter::isTerminalStatus($item->status));

        $pct = $run->planned_count > 0
            ? (int) round(($run->created_count / $run->planned_count) * 100)
            : 0;

        return response()->json([
            'run' => [
                'id' => $run->id,
                'status' => $run->status,
                'planned_count' => $run->planned_count,
                'created_count' => $run->created_count,
                'failed_count' => $run->failed_count,
                'progress_pct' => $pct,
                'started_at_human' => $run->started_at?->diffForHumans() ?? 'not yet',
                'is_active' => $isActive,
            ],
            'items' => $items,
            'poll_interval' => $isActive ? 3000 : 15000,
        ]);
    }

    /**
     * @deprecated Use AutomationLogReader
     */
    protected function readAutomationLogLines(int $limit = 500, ?int $runId = null, ?int $itemId = null): array
    {
        return app(AutomationLogReader::class)->readLines($limit, $runId, $itemId);
    }

    public function retryRunItem(AutomationRunItem $item)
    {
        abort_unless($item->status === 'failed', 422, 'Only failed items can be retried.');

        $run = $item->run;
        $run?->update([
            'status' => 'running',
            'finished_at' => null,
        ]);

        $metadata = $item->metadata ?? [];
        $metadata['last_retry_at'] = now()->toIso8601String();
        $metadata['retry_count'] = (int) ($metadata['retry_count'] ?? 0) + 1;
        $metadata['retried_from_failed'] = true;

        if ($item->error_message) {
            $history = is_array($metadata['previous_errors'] ?? null) ? $metadata['previous_errors'] : [];
            $history[] = [
                'at' => now()->toIso8601String(),
                'message' => $item->error_message,
                'step' => $metadata['failed_at_step'] ?? null,
            ];
            $metadata['previous_errors'] = array_values(array_slice($history, -5));
        }

        $item->update([
            'status' => 'queued',
            'error_message' => null,
            'metadata' => $metadata,
        ]);

        // Keep list counters honest while retry is in flight (no longer count this item as failed).
        $run?->fresh('items')?->syncCountsFromItems(finalize: false);

        GenerateAutomatedPostJob::dispatch($item->id, resume: true);

        return back()->with('status', 'Retry queued for '.$item->platform.' item — will resume from the last completed step.');
    }
}
