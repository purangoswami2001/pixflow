<?php

namespace App\Services\Automation;

use App\Support\AiDiagnostics;
use Illuminate\Support\Facades\File;

class AutomationLogReader
{
    /**
     * @return array{path: ?string, name: ?string}
     */
    public function resolveLogFile(): array
    {
        $path = storage_path('logs/automation-'.now()->format('Y-m-d').'.log');
        if (! File::exists($path)) {
            $files = collect(File::glob(storage_path('logs/automation-*.log')))
                ->sortByDesc(fn ($f) => File::lastModified($f));
            $path = $files->first();
        }

        if (! $path || ! File::exists($path)) {
            return ['path' => null, 'name' => null];
        }

        return ['path' => $path, 'name' => basename($path)];
    }

    /**
     * @param  array<int, string>  $excludeHashes
     * @return array<int, array{hash: string, at: string, level: string, message: string, context: array<string, mixed>, raw: string}>
     */
    public function readLines(int $limit = 500, ?int $runId = null, ?int $itemId = null, array $excludeHashes = []): array
    {
        $file = $this->resolveLogFile();
        if ($file['path'] === null) {
            return [];
        }

        $raw = File::get($file['path']);
        $blocks = preg_split('/(?=^\[\d{4}-\d{2}-\d{2})/m', $raw) ?: [];
        $entries = [];
        $exclude = array_flip($excludeHashes);

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            $hash = md5($block);
            if (isset($exclude[$hash])) {
                continue;
            }

            if (! preg_match('/^\[([^\]]+)\]\s+(\w+)\.(\w+):\s+(\S+?)(?:\s+(\{.*\}))?\s*$/s', $block, $m)) {
                continue;
            }

            $context = [];
            if (! empty($m[5])) {
                $decoded = json_decode($m[5], true);
                if (is_array($decoded)) {
                    $context = $decoded;
                }
            }

            if ($runId && ($context['run_id'] ?? null) != $runId) {
                continue;
            }
            if ($itemId && ($context['item_id'] ?? null) != $itemId) {
                continue;
            }

            $entries[] = [
                'hash' => $hash,
                'at' => $m[1],
                'level' => $m[3],
                'message' => AiDiagnostics::forDisplay($m[4]),
                'context' => AiDiagnostics::forDisplay($context),
                'raw' => AiDiagnostics::forDisplay($block),
            ];
        }

        return array_slice(array_reverse($entries), 0, $limit);
    }

    public function countLines(?int $runId = null, ?int $itemId = null): int
    {
        return count($this->readLines(10000, $runId, $itemId));
    }
}
