<?php

/**
 * Normalize REQUEST_URI for Laravel when the app runs in a subdirectory (WAMP + ngrok).
 */

if (!function_exists('resolveApplicationBasePath')) {
    function resolveApplicationBasePath(): string
    {
        $candidates = [];

        if ($script = $_SERVER['SCRIPT_NAME'] ?? '') {
            $fromScript = rtrim(str_replace('\\', '/', dirname($script)), '/');
            if ($fromScript !== '' && $fromScript !== '/') {
                $candidates[] = $fromScript;
            }
        }

        $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        if (is_readable($envFile)) {
            $contents = file_get_contents($envFile);

            if (preg_match('/^\s*APP_BASE_PATH\s*=\s*["\']?([^"\'#\s]*)/m', $contents, $matches)) {
                $path = trim($matches[1], " \t\"'/");
                if ($path !== '') {
                    $candidates[] = '/' . trim($path, '/');
                }
            }

            if (preg_match('/^\s*APP_URL\s*=\s*["\']?([^"\'#\r\n]+)/m', $contents, $matches)) {
                $path = parse_url(trim($matches[1], " \t\"'"), PHP_URL_PATH) ?: '';
                $path = rtrim($path, '/');
                if ($path !== '' && $path !== '/') {
                    $candidates[] = $path;
                }
            }
        }

        if ($candidates === []) {
            return '';
        }

        usort($candidates, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        return $candidates[0];
    }

    function stripSubdirectoryFromRequest(string $basePath): void
    {
        if ($basePath === '' || !isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        $uri = strtok($_SERVER['REQUEST_URI'], '?') ?: '/';
        if (!str_starts_with($uri, $basePath)) {
            return;
        }

        $stripped = substr($uri, strlen($basePath)) ?: '/';
        $query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';

        $_SERVER['REQUEST_URI'] = $stripped . $query;
    }
}
