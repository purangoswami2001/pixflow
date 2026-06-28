<?php

namespace Database\Seeders;

use App\Models\ContentCategory;
use App\Models\ContentTopic;
use App\Models\PromptTemplate;
use App\Services\Automation\AutomationMediaService;
use Illuminate\Database\Seeder;

class AutomationSeeder extends Seeder
{
    public function run(): void
    {
        AutomationMediaService::aiAgentUser();

        $category = ContentCategory::updateOrCreate(
            ['slug' => 'health-wellness'],
            [
                'name' => 'Health & Wellness',
                'niche_prompt' => 'Pinterest business account focused on health and wellness for women. Evidence-based, feminine aesthetic, actionable tips.',
                'safety_rules' => '100% accurate and evidence-based. No cure claims, no misleading medical advice. Use may help language. Encourage consulting healthcare professionals.',
                'audience' => 'Women interested in health, wellness, menopause, stress, fitness, and self-care',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $topics = [
            'Menopause Hot Flashes Driving You Crazy? The Secret Hack You Haven\'t Tried to Cool Them Instantly!',
            'Fed Up with Hormonal Acne Breakouts? What If One Skincare Trick Cleared It for Good?',
            'Constant Stress Ruining Your Life? Discover the 2-Minute Buster That Brings Instant Zen!',
            'Hate Feeling Out of Shape? The Fun Walking Trick You\'re Missing to Drop 10 Pounds!',
            'Weak Immunity Catching Every Cold? The Drink Secret That Supercharges You Overnight!',
            'Lacking Confidence Daily? What If This One Affirmation Could Skyrocket It Forever!',
        ];

        foreach ($topics as $title) {
            ContentTopic::updateOrCreate(
                ['content_category_id' => $category->id, 'title' => $title],
                ['topic_type' => 'educational', 'is_active' => true]
            );
        }

        ContentTopic::updateOrCreate(
            ['content_category_id' => $category->id, 'title' => 'Free Wellness Checklist — Save This Pin!'],
            [
                'topic_type' => 'promotional',
                'is_active' => true,
                'metadata' => ['link_url' => 'https://example.com/wellness-checklist'],
            ]
        );

        $this->seedPrompts();
    }

    protected function seedPrompts(): void
    {
        $templates = [
            ['pinterest', 'idea', 'Pinterest Pin Idea Planner', $this->ideaPrompt('pinterest')],
            ['facebook', 'idea', 'Facebook Post Idea', $this->ideaPrompt('facebook')],
            ['instagram', 'idea', 'Instagram Post Idea', $this->ideaPrompt('instagram')],
            ['pinterest', 'copy', 'Pinterest Pin Copy', $this->copyPrompt('pinterest')],
            ['facebook', 'copy', 'Facebook Post Copy', $this->copyPrompt('facebook')],
            ['instagram', 'copy', 'Instagram Caption', $this->copyPrompt('instagram')],
            ['pinterest', 'image', 'Pinterest Infographic Image', $this->imagePrompt('pinterest')],
            ['facebook', 'image', 'Facebook Image', $this->imagePrompt('facebook')],
            ['instagram', 'image', 'Instagram Image', $this->imagePrompt('instagram')],
        ];

        foreach ($templates as [$platform, $step, $name, $template]) {
            PromptTemplate::updateOrCreate(
                ['platform' => $platform, 'step' => $step, 'name' => $name],
                ['template' => $template, 'is_default' => true, 'is_active' => true, 'version' => 1]
            );
        }
    }

    protected function ideaPrompt(string $platform): string
    {
        return "Act as a top-tier digital marketer and {$platform} strategist.\n"
            . "Niche: {{niche}}\nAudience: {{audience}}\nSafety: {{safety_rules}}\n"
            . "Topic headline: {{topic}}\nPost type: {{topic_type}}\n\n"
            . "Create a high-impact post idea. Respond JSON with keys:\n"
            . "- pin_title (string, max 100 chars)\n- angle (string)\n"
            . "- key_points (array of 3-5 short strings)\n- seo_keywords (array)\n- safety_notes (string)";
    }

    protected function copyPrompt(string $platform): string
    {
        $length = $platform === 'pinterest'
            ? 'caption 300-600 characters with emojis and 5-7 hashtags'
            : 'caption appropriate for ' . $platform . ' with 4-8 hashtags';

        return "Write {$platform} post copy.\nNiche: {{niche}}\nTopic: {{topic}}\n"
            . "Title idea: {{pin_title}}\nAngle: {{angle}}\nKey points:\n{{key_points}}\n"
            . "Type: {{topic_type}}\nPromo link: {{link_url}}\n\n"
            . "JSON keys: title, caption ({$length}), hashtags (array), link_url (optional for promotional)";
    }

    protected function imagePrompt(string $platform): string
    {
        return "Create a detailed text-to-image prompt for a {{width}}x{{height}}px {$platform} informational infographic.\n"
            . "Title: {{pin_title}}\nTopic: {{topic}}\nNiche: {{niche}}\nAudience: {{audience}}\n"
            . "Key points — turn each into an on-image section (heading + icon + short tip text):\n{{key_points}}\n"
            . "Colors: {{color_palette}}. Feminine, high contrast, mobile-readable.\n"
            . "STYLE: Educational Canva-style wellness graphic with typography, icons, and color blocks — NOT a plain lifestyle photo.\n"
            . "LAYOUT: Full-bleed edge-to-edge {{width}}x{{height}}px canvas, max 5–6 sections, include main title on image.\n"
            . "Respond JSON: design_prompt (string, full prompt for image model)";
    }
}
