<?php

namespace App\Services\Ai\Cursor;

use Illuminate\Support\Facades\Cache;

class CursorModelResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolvePayload(): array
    {
        $requested = trim((string) config('ai.cursor.chat_model', ''));
        if ($requested === '') {
            return [];
        }

        $resolved = $this->resolveId($requested);
        if ($resolved === null) {
            return [];
        }

        $payload = ['model' => ['id' => $resolved]];

        $params = config('ai.cursor.model_params', []);
        if (is_array($params) && $params !== []) {
            $payload['model']['params'] = $params;
        }

        return $payload;
    }

    public function resolveId(string $requested): ?string
    {
        $requested = strtolower(trim($requested));
        if ($requested === '' || in_array($requested, ['auto', 'default'], true)) {
            $index = $this->modelIndex();

            return $index['auto'] ?? $index['default'] ?? 'default';
        }

        $index = $this->modelIndex();

        if (isset($index[$requested])) {
            return $index[$requested];
        }

        return $index['default'] ?? null;
    }

    /**
     * @return array<string, string> lowercase key => canonical id
     */
    protected function modelIndex(): array
    {
        return Cache::remember('cursor_api_models_index', 3600, function () {
            $index = [];

            try {
                $items = app(CursorCloudAgentsApi::class)->listModels();
            } catch (\Throwable) {
                return ['composer-2.5' => 'composer-2.5', 'default' => 'default'];
            }

            foreach ($items as $item) {
                $id = $item['id'] ?? null;
                if (! is_string($id) || $id === '') {
                    continue;
                }

                $index[strtolower($id)] = $id;

                foreach ($item['aliases'] ?? [] as $alias) {
                    if (is_string($alias) && $alias !== '') {
                        $index[strtolower($alias)] = $id;
                    }
                }
            }

            return $index;
        });
    }
}
