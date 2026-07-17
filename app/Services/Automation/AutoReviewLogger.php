<?php

namespace App\Services\Automation;

use Illuminate\Support\Facades\Log;

class AutoReviewLogger
{
    public static function info(string $message, array $context = []): void
    {
        Log::channel('automation')->info('[AutoReview] '.$message, $context);
        Log::info('[AutoReview] '.$message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        Log::channel('automation')->warning('[AutoReview] '.$message, $context);
        Log::warning('[AutoReview] '.$message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        Log::channel('automation')->error('[AutoReview] '.$message, $context);
        Log::error('[AutoReview] '.$message, $context);
    }

    public static function decision(int $postId, string $decision, array $payload = []): void
    {
        self::info("Post #{$postId} decision={$decision}", $payload);
    }
}
