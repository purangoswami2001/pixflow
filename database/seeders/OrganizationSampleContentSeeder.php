<?php

namespace Database\Seeders;

use App\Models\ContentCategory;
use App\Models\ContentTopic;
use App\Models\Organization;
use Illuminate\Database\Seeder;
class OrganizationSampleContentSeeder extends Seeder
{
    /**
     * Seed one sample category + a few topics for an organization.
     * Use this for new firms; keep ContentLibrarySeeder for the full niche pack.
     */
    public function run(?int $organizationId = null): void
    {
        $organizationId = $organizationId
            ?? Organization::query()->orderBy('id')->value('id');

        if (! $organizationId) {
            return;
        }

        $category = ContentCategory::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'slug' => 'sample-niche',
            ],
            [
                'name' => 'Sample Niche',
                'niche_prompt' => 'Practical, friendly tips for a general audience. Clear benefit-led hooks, short actionable advice, and scroll-stopping visuals.',
                'safety_rules' => 'No medical, legal, or financial guarantees. Avoid misleading claims. Keep language inclusive and age-appropriate.',
                'audience' => 'General audience exploring helpful everyday tips and inspiration.',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $topics = [
            [
                'title' => 'One Small Habit That Makes Busy Days Feel Easier',
                'topic_type' => 'educational',
            ],
            [
                'title' => '5 Quick Wins You Can Try Before Lunch',
                'topic_type' => 'listicle',
            ],
            [
                'title' => 'Save This Simple Checklist for Tomorrow Morning',
                'topic_type' => 'tip',
            ],
            [
                'title' => 'Free Starter Checklist — Grab Your Copy',
                'topic_type' => 'promotional',
                'metadata' => ['link_url' => 'https://example.com/sample-checklist'],
            ],
        ];

        foreach ($topics as $topic) {
            ContentTopic::updateOrCreate(
                [
                    'content_category_id' => $category->id,
                    'title' => $topic['title'],
                ],
                [
                    'topic_type' => $topic['topic_type'],
                    'is_active' => true,
                    'metadata' => $topic['metadata'] ?? null,
                ]
            );
        }
    }
}
