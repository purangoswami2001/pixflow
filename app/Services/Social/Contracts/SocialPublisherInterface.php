<?php

namespace App\Services\Social\Contracts;

use App\Models\PostTarget;

interface SocialPublisherInterface
{
    /**
     * Publish the post target to the social network.
     *
     * @param PostTarget $target
     * @return array{
     *     status: string,
     *     provider_post_id: ?string,
     *     provider_post_url: ?string,
     *     metadata: array,
     *     error: ?string,
     *     request_payload?: array,
     *     response_payload?: array
     * }
     */
    public function publish(PostTarget $target): array;
}
