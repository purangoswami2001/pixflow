<?php

/**
 * Normalize REQUEST_URI / SCRIPT_NAME for Laravel when the app runs in a subdirectory (WAMP + ngrok).
 * When served from a domain root vhost (e.g. http://pixflow.local.in/), the base path stays empty.
 */

if (! function_exists('resolveApplicationBasePath')) {
    function resolveApplicationBasePath(): string
    {
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $requestUri = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?') ?: '/';

        $fromScript = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if ($fromScript === '/' || $fromScript === '.' || $fromScript === '') {
            $fromScript = '';
        }

        $configured = '';
        $envFile = dirname(__DIR__).DIRECTORY_SEPARATOR.'.env';
        if (is_readable($envFile)) {
            $contents = (string) file_get_contents($envFile);

            if (preg_match('/^\s*APP_BASE_PATH\s*=\s*["\']?([^"\'#\s]*)/m', $contents, $matches)) {
                $path = trim($matches[1], " \t\"'/");
                if ($path !== '') {
                    $configured = '/'.trim($path, '/');
                }
            }

            if ($configured === '' && preg_match('/^\s*APP_URL\s*=\s*["\']?([^"\'#\r\n]+)/m', $contents, $matches)) {
                $path = parse_url(trim($matches[1], " \t\"'"), PHP_URL_PATH) ?: '';
                $path = rtrim($path, '/');
                if ($path !== '' && $path !== '/') {
                    $configured = $path;
                }
            }
        }

        // Only honor the configured subdirectory when this request is actually under it.
        if ($configured !== '') {
            if (str_starts_with($requestUri, $configured)
                || str_starts_with($scriptName, $configured.'/')
                || $scriptName === $configured.'/index.php') {
                return $configured;
            }
        }

        return $fromScript;
    }

    function ensureSubdirectoryScriptName(string $basePath): void
    {
        if ($basePath === '') {
            return;
        }

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script === '' || ! str_starts_with($script, $basePath.'/')) {
            $_SERVER['SCRIPT_NAME'] = $basePath.'/index.php';
        }

        $phpSelf = str_replace('\\', '/', (string) ($_SERVER['PHP_SELF'] ?? ''));
        if ($phpSelf === '' || ! str_starts_with($phpSelf, $basePath.'/')) {
            $_SERVER['PHP_SELF'] = $basePath.'/index.php';
        }
    }

    function stripSubdirectoryFromRequest(string $basePath): void
    {
        if ($basePath === '' || ! isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        $uri = strtok($_SERVER['REQUEST_URI'], '?') ?: '/';
        if (! str_starts_with($uri, $basePath)) {
            return;
        }

        $stripped = substr($uri, strlen($basePath)) ?: '/';
        $query = ! empty($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : '';

        $_SERVER['REQUEST_URI'] = $stripped.$query;
    }
}
