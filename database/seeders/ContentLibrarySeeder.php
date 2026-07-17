<?php

namespace Database\Seeders;

use App\Models\ContentCategory;
use App\Models\ContentTopic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ContentLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $sort = 1;

        foreach ($this->categories() as $row) {
            $slug = $row['slug'] ?? Str::slug($row['name']);

            $category = ContentCategory::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $row['name'],
                    'niche_prompt' => $row['niche_prompt'],
                    'safety_rules' => $row['safety_rules'],
                    'audience' => $row['audience'],
                    'is_active' => true,
                    'sort_order' => $sort++,
                ]
            );

            foreach ($row['topics'] as $topic) {
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

    /**
     * 20 niches × distinct age groups × mixed educational / listicle / tip / promotional topics.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function categories(): array
    {
        return [
            [
                'name' => 'Teen Study Skills',
                'slug' => 'teen-study-skills',
                'age_group' => '13–17',
                'niche_prompt' => 'Practical study habits, focus, and exam confidence for teenagers. Short actionable tips, peer-friendly tone, no medical claims.',
                'safety_rules' => 'Age-appropriate language. No academic dishonesty advice. Avoid body/weight shaming.',
                'audience' => 'Age group 13–17: teens in middle/high school who want better grades with less stress.',
                'topics' => [
                    ['title' => 'The 25-Minute Focus Block That Beats All-Nighters', 'topic_type' => 'educational'],
                    ['title' => '5 Desk Setup Tweaks That Help You Actually Start Homework', 'topic_type' => 'listicle'],
                    ['title' => 'Remember More With This Simple Rewrite Trick', 'topic_type' => 'tip'],
                    ['title' => 'Free Weekly Study Planner — Download Checklist', 'topic_type' => 'promotional', 'metadata' => ['link_url' => 'https://example.com/teen-study-planner']],
                    ['title' => 'How to Turn One Class Note Into a Instant Review Sheet', 'topic_type' => 'educational'],
                ],
            ],
            [
                'name' => 'Gen Z Money Basics',
                'slug' => 'gen-z-money-basics',
                'age_group' => '18–24',
                'niche_prompt' => 'First-job money literacy for young adults: budgeting, saving, student cash flow. Clear numbers, no get-rich-quick claims.',
                'safety_rules' => 'Not financial advice. No guaranteed returns. Encourage caution with debt and crypto speculation.',
                'audience' => 'Age group 18–24: college students and early-career adults learning money habits.',
                'topics' => [
                    ['title' => 'Build a First Budget That Survives a Low Salary Week', 'topic_type' => 'educational'],
                    ['title' => '7 Money Leaks Gen Z Does Not Notice Until Rent Day', 'topic_type' => 'listicle'],
                    ['title' => 'Automate One Transfer the Day Payday Hits', 'topic_type' => 'tip'],
                    ['title' => 'Starter Emergency Fund Worksheet', 'topic_type' => 'promotional', 'metadata' => ['link_url' => 'https://example.com/genz-emergency-fund']],
                    ['title' => 'How to Split Bills With Roommates Without Drama', 'topic_type' => 'educational'],
                ],
            ],
            [
                'name' => 'Young Professional Careers',
                'slug' => 'young-professional-careers',
                'age_group' => '22–29',
                'niche_prompt' => 'Career growth for early professionals: communication, meetings, portfolio proof, and workplace confidence.',
                'safety_rules' => 'No illegal work advice. Avoid discriminatory stereotypes. Be honest about tradeoffs.',
                'audience' => 'Age group 22–29: early-career professionals navigating first jobs and promotions.',
                'topics' => [
                    ['title' => 'Ask Better Clarifying Questions in Meetings Without Looking Lost', 'topic_type' => 'educational'],
                    ['title' => '6 Portfolio Proof Points Hiring Managers Actually Open', 'topic_type' => 'listicle'],
                    ['title' => 'Write a Same-Day Recap Email After Every Important Call', 'topic_type' => 'tip'],
                    ['title' => 'Career Check-In Template for Your Next 1:1', 'topic_type' => 'promotional', 'metadata' => ['link_url' => 'https://example.com/career-1-1-template']],
                    ['title' => 'How to Document Wins Without Sounding Boastful', 'topic_type' => 'educational'],
                ],
            ],
            [
                'name' => 'Women Wellness 25–40',
                'slug' => 'women-wellness-25-40',
                'age_group' => '25–40',
                'niche_prompt' => 'Evidence-based wellness for women managing energy, stress, sleep, and routines. Calm practical tone.',
                'safety_rules' => 'No cure claims. Use “may help” language. Encourage clinician guidance for medical issues.',
                'audience' => 'Age group 25–40: women balancing work, home, and health habits.',
                'topics' => [
                    ['title' => 'Morning Habits That Support Steady Energy (Without Extreme Routines)', 'topic_type' => 'educational'],
                    ['title' => '5 Stress Resets You Can Do in Under Two Minutes', 'topic_type' => 'listicle'],
                    ['title' => 'Drink Water Within the First Hour of Waking', 'topic_type' => 'tip'],
                    ['title' => 'Free Wellness Weekly Checklist', 'topic_type' => 'promotional', 'metadata' => ['link_url' => 'https://example.com/wellness-checklist']],
                    ['title' => 'Why Short Outdoor Light Breaks Improve Afternoon Focus', 'topic_type' => 'educational'],
                ],
            ],
            [
                'name' => 'New Parents 25–35',
                'slug' => 'new-parents-25-35',
                'age_group' => '25–35',
                'niche_prompt' => 'Practical home systems for new parents: sleep batches, meal shortcuts, mental load sharing. Empathetic, realistic.',
                'safety_rules' => 'No medical advice for infants. Never shame parents. Escalate health concerns to professionals.',
                'audience' => 'Age group 25–35: new moms and dads in the first years of parenting.',
                'topics' => [
                    ['title' => 'A Realistic Evening Reset When Baby Care Takes Over the Day', 'topic_type' => 'educational'],
                    ['title' => '8 Freezer Meals New Parents Can Batch on One Sunday', 'topic_type' => 'listicle'],
                    ['title' => 'Keep a Shared Phone Note for “Next Adult Task”', 'topic_type' => 'tip'],
                    ['title' => 'New Parent Survival Checklist (Printable)', 'topic_type' => 'promotional', 'metadata' => ['link_url' => 'https://example.com/new-parent-checklist']],
                    ['title' => 'How Couples Can Split Invisible Chores More Fairly', 'topic_type' => 'educational'],
                ],
            ],
            [
                'name' => 'Home Cooking 30–45',
                'slug' => 'home-cooking-30-45',
                'age_group' => '30–45',
                'niche_prompt' => 'Weeknight cooking for busy adults: simple techniques, better leftovers, grocery efficiency. Food-forward visuals.',
                'safety_rules' => 'Safe food handling. No extreme diet promises. Allergy-aware wording when relevant.',
                'audience' => 'Age group 30–45: busy adults cooking at home after work.',
                'topics' => [
                    ['title' => 'Build Flavor Fast With One Pan and Three Aromatics', 'topic_type' => 'educational'],
                    ['title' => '7 Leftover Lunches That Still Taste Good on Day Three', 'topic_type' => 'listicle'],
                    ['title' => 'Salt Vegetables Earlier for Better Texture', 'topic_type' => 'tip'],
                    ['title' => 'Weeknight Dinner Planner PDF', 'topic_type' => 'promotional', 'metadata' => ['link_url' => 'https://example.com/weeknight-planner']],
                    ['title' => 'How to Shop Once and Cook Four Distinct Meals', 'topic_type' => 'educational'],
                ],
            ],
            [
                'name' => 'Fitness for Busy Adults',
                'slug' => 'fitness-busy-adults',
                'age_group' => '28–42',
                'niche_prompt' => 'Strength, mobility, and consistency for adults with limited gym time. Form cues over hype.',
                'safety_rules' => 'No injury guarantees. Suggest form checks. Stop advice if pain is sharp. Not medical rehab advice.',
                'audience' => 'Age group 28–42: professionals who want fit routines that fit a packed calendar.',
                'topics' => [
                    ['title' => 'A 20-Minute Full-Body Strength Circuit You Can Do at Home', 'topic_type' => 'educational'],
                    ['title' => '6 Mobility Moves That Loosen Desk Shoulders', 'topic_type' => 'listicle'],
                    ['title' => 'Track Sets Before You Track Body Weight', 'topic_type' => 'tip'],
                    ['title' => 'Busy Adult Training Starter Guide', 'topic_type' => 'promotional', 'metadata' => ['link_url' => 'https://example.com/busy-training-guide']],
                    ['title' => 'Why Consistency Beats Perfect Gym Programs', 'topic_type' => 'educational'],
                ],
            ],
            [
                'name' => 'Remote Work Productivity',
                'slug' => 'remote-work-productivity',
                'age_group' => '26–40',
                'niche_prompt' => 'Focus systems for remote and hybrid workers: deep work blocks, async updates, burnout prevention.',
                'safety_rules' => 'No surveillance advocacy. Respect boundaries and local labor norms.',
                'audience' => 'Age group 26–40: remote/hybrid knowledge workers who need sharper focus.',
                'topics' => [
                    ['title' => 'Protect a Two-Hour Deep Work Block Before Slack Peaks', 'topic_type' => 'educational'],
                    ['title' => '5 Async Update Formats That Reduce Meeting Load', 'topic_type' => 'listicle'],
                    ['title' => 'End the Day With a Three-Line Hand-Off Note', 'topic_type' => 'tip'],
                    ['title' => 'Remote Focus Toolkit Checklist', 'topic_type' => 'promotional', 'metadata' => ['link_url' => 'https://example.com/remote-focus-toolkit']],
                    ['title' => 'How to Signal Availability Without Being Always On', 'topic_type' => 'educational'],
                ],
            ],
            [
                'name' => 'Small Business Marketing',
                'slug' => 'small-business-marketing',
                'age_group' => '30–50',
                'niche_prompt' => 'Practical marketing for local and online small businesses: offers, customer proof, simple content systems. Promo tone is OK when topic_type is promotional.',
                'safety_rules' => 'No fake scarcity. No misleading claims. Disclose paid partnerships when relevant.',
                'audience' => 'Age group 30–50: founders and owners of small local or online businesses.',
                'topics' => [
                    ['title' => 'Turn One Happy Customer Story Into a Week of Content', 'topic_type' => 'educational'],
                    ['title' => '8 Offer Angles That Still Feel Honest', 'topic_type' => 'listicle'],
                    ['title' => 'Ask for a Photo Testimonial Before Loyalty Fades', 'topic_type' => 'tip'],
                    ['title' => 'Local Business Content Calendar Template', 'topic_type' => 'promotional', 'metadata' => ['link_url' => 'https://example.com/local-content-calendar']],
                    ['title' => 'How to Write a Clear Offer Without Hype', 'topic_type' => 'educational'],
                ],
            ],
            [
                'name' => 'Personal Finance 35–50',
                'slug' => 'personal-finance-35-50',
                'age_group' => '35–50',
                'niche_prompt' => 'Household money systems for mid-career adults: debt payoff order, sinking funds, family cash flow.',
                'safety_rules' => 'Educational only — not personalized financial advice. No guaranteed investment returns.',
                'audience' => 'Age group 35–50: mid-career adults managing household money and longer-term goals.',
                'topics' => [
                    ['title' => 'Build Sinking Funds So Big Bills Stop Feeling Like Emergencies', 'topic_type' => 'educational'],
                    ['title' => '7 Money Meetings Couples Should Have Each Quarter', 'topic_type' => 'listicle'],
                    ['title' => 'Name Every Account by Purpose, Not Bank Nickname', 'topic_type' => 'tip'],
                    ['title' => 'Household Cash Flow Worksheet', 'topic_type' => 'promotional', 'metadata' => ['link_url' => 'https://example.com/household-cashflow']],
                    ['title' => 'How to Prioritize Debts Without Panic Math', 'topic_type' => 'educational'],
                ],
            ],
            [
                'name' => 'Menopause Support 45–60',
                'slug' => 'menopause-support-45-60',
                'age_group' => '45–60',
                'niche_prompt' => 'Supportive education for perimenopause and menopause: sleep, temperature comfort, stress, conversations with clinicians.',
                'safety_rules' => 'No cure or hormone protocol claims. Encourage medical advice. Avoid fearmongering.',
                'audience' => 'Age group 45–60: women navigating perimenopause and menopause changes.',
                'topics' => [
                    ['title' => 'Cooling Evening Routines That May Ease Night Discomfort', 'topic_type' => 'educational'],
                    ['title' => '6 Doctor Visit Questions Worth Writing Down First', 'topic_type' => 'listicle'],
                    ['title' => 'Layer Breathable Fabric for Temperature Swings', 'topic_type' => 'tip'],
                    ['title' => 'Menopause Symptom Log Template', 'topic_type' => 'promotional', 'metadata' => ['link_url' => 'https://example.com/menopause-symptom-log']],
                    ['title' => 'How to Talk With Your Clinician About Persistent Symptoms', 'topic_type' => 'educational'],
                ],
            ],
            [
                'name' => 'Home Organization 40–55',
                'slug' => 'home-organization-40-55',
                'age_group' => '40–55',
                'niche_prompt' => 'Decluttering and calm home systems for midlife households: zones, weekend resets, visitor-ready spaces.',
                'safety_rules' => 'No shame language about mess. Avoid unsafe DIY structural advice.',
                'audience' => 'Age group 40–55: homeowners and renters wanting calmer, more organized living spaces.',
                'topics' => [
                    ['title' => 'Create a Drop Zone That Stops Entryway Chaos', 'topic_type' => 'educational'],
                    ['title' => '9 Closet Categories That Make Getting Dressed Faster', 'topic_type' => 'listicle'],
                    ['title' => 'Clear One Flat Surface Every Night Before Bed', 'topic_type' => 'tip'],
                    ['title' => 'Weekend Reset Checklist', 'topic_type' => 'promotional', 'metadata' => ['link_url' => 'https://example.com/weekend-reset']],
                    ['title' => 'How to Declutter Sentimental Items Without Guilt Spirals', 'topic_type' => 'educational'],
                ],
            ],
            [
                'name' => 'Travel Planning 30–55',
                'slug' => 'travel-planning-30-55',
                'age_group' => '30–55',
                'niche_prompt' => 'Smarter leisure travel planning: packing systems, itinerary pacing, budget buffers, family trips.',
                'safety_rules' => 'Respect local laws and visas. No unsafe border advice. Be culturally respectful.',
                'audience' => 'Age group 30–55: leisure travelers planning weekends and longer vacations.',
                'topics' => [
                    ['title' => 'Plan Rest Blocks Into Every Trip Itinerary', 'topic_type' => 'educational'],
                    ['title' => '8 Packing Cube Rules That Shrink Luggage Stress', 'topic_type' => 'listicle'],
                    ['title' => 'Photograph Your Passport Page Before You Leave Home', 'topic_type' => 'tip'],
                    ['title' => 'Trip Budget Buffer Template', 'topic_type' => 'promotional', 'metadata' => ['link_url' => 'https://example.com/trip-budget-buffer']],
                    ['title' => 'How to Build a Flexible Day Plan That Survives Delays', 'topic_type' => 'educational'],
                ],
            ],
            [
                'name' => 'Gardening Beginners 35–60',
                'slug' => 'gardening-beginners-35-60',
                'age_group' => '35–60',
                'niche_prompt' => 'Beginner-friendly gardening: containers, watering, seasonal planting, low-maintenance greens.',
                'safety_rules' => 'Warn about toxic plants/pets when relevant. No pesticide misuse. Climate varies — give adaptable tips.',
                'audience' => 'Age group 35–60: beginners starting balconies, yards, or raised beds.',
                'topics' => [
                    ['title' => 'Choose Container Plants That Forgive Missed Water Days', 'topic_type' => 'educational'],
                    ['title' => '7 Beginner Herbs That Thrive Near a Sunny Window', 'topic_type' => 'listicle'],
                    ['title' => 'Water Deeply Less Often Than You Think', 'topic_type' => 'tip'],
                    ['title' => 'First Season Garden Starter Checklist', 'topic_type' => 'promotional', 'metadata' => ['link_url' => 'https://example.com/garden-starter']],
                    ['title' => 'How to Spot Overwatering Before Roots Rot', 'topic_type' => 'educational'],
                ],
            ],
            [
                'name' => 'Active Aging 60–75',
                'slug' => 'active-aging-60-75',
                'age_group' => '60–75',
                'niche_prompt' => 'Mobility, balance, and joyful movement for active older adults. Encouraging, never infantilizing.',
                'safety_rules' => 'Suggest clinician clearance for new exercise. Stop if dizziness or sharp pain. No miracle anti-aging claims.',
                'audience' => 'Age group 60–75: older adults who want to stay mobile, social, and confident.',
                'topics' => [
                    ['title' => 'Balance Practices You Can Do Holding a Kitchen Counter', 'topic_type' => 'educational'],
                    ['title' => '5 Short Walks That Still Count as Real Movement', 'topic_type' => 'listicle'],
                    ['title' => 'Stand Up Once Every Commercial Break', 'topic_type' => 'tip'],
                    ['title' => 'Active Aging Weekly Movement Card', 'topic_type' => 'promotional', 'metadata' => ['link_url' => 'https://example.com/active-aging-card']],
                    ['title' => 'How to Build Strength Without Intimidating Gym Machines', 'topic_type' => 'educational'],
                ],
            ],
            [
                'name' => 'Tech Made Simple 50–70',
                'slug' => 'tech-made-simple-50-70',
                'age_group' => '50–70',
                'niche_prompt' => 'Patient tech education for midlife and older adults: phones, passwords, scams, useful apps.',
                'safety_rules' => 'Prioritize scam prevention. Never request passwords. Avoid talking down to learners.',
                'audience' => 'Age group 50–70: adults who want clearer, safer everyday tech skills.',
                'topics' => [
                    ['title' => 'Create a Password System You Can Actually Remember Safely', 'topic_type' => 'educational'],
                    ['title' => '6 Scam Texts Older Adults Should Delete Immediately', 'topic_type' => 'listicle'],
                    ['title' => 'Turn On Automatic App Updates Tonight', 'topic_type' => 'tip'],
                    ['title' => 'Digital Safety Starter Checklist', 'topic_type' => 'promotional', 'metadata' => ['link_url' => 'https://example.com/digital-safety-checklist']],
                    ['title' => 'How to Share Photos With Family Without Oversharing Data', 'topic_type' => 'educational'],
                ],
            ],
            [
                'name' => 'College Life Essentials',
                'slug' => 'college-life-essentials',
                'age_group' => '18–22',
                'niche_prompt' => 'Campus life skills: dorm organization, shared housing, nutrition on a budget, mental load management.',
                'safety_rules' => 'Age-appropriate. No substance abuse promotion. Encourage campus resources for mental health crises.',
                'audience' => 'Age group 18–22: college and university students living on or near campus.',
                'topics' => [
                    ['title' => 'Turn a Tiny Dorm Desk Into a Focus Station', 'topic_type' => 'educational'],
                    ['title' => '5 Grocery Staples That Stretch a Student Budget', 'topic_type' => 'listicle'],
                    ['title' => 'Put Tomorrow’s Bag Together Before You Sleep', 'topic_type' => 'tip'],
                    ['title' => 'First Semester Survival Checklist', 'topic_type' => 'promotional', 'metadata' => ['link_url' => 'https://example.com/first-semester-checklist']],
                    ['title' => 'How to Handle Roommate Conflict Before It Explodes', 'topic_type' => 'educational'],
                ],
            ],
            [
                'name' => 'Skincare Science 20–35',
                'slug' => 'skincare-science-20-35',
                'age_group' => '20–35',
                'niche_prompt' => 'Evidence-aware skincare education for younger adults: routines, sunscreen, patch testing, ingredient literacy.',
                'safety_rules' => 'Not dermatology treatment. No cure claims for acne/eczema. Patch-test guidance. See a clinician for flares.',
                'audience' => 'Age group 20–35: adults building simple, science-aware skincare routines.',
                'topics' => [
                    ['title' => 'Why Daily Sunscreen Matters Even on Indoor Days', 'topic_type' => 'educational'],
                    ['title' => '4 Morning Steps That Cover Most Skin Goals', 'topic_type' => 'listicle'],
                    ['title' => 'Patch Test New Actives on the Inner Arm First', 'topic_type' => 'tip'],
                    ['title' => 'Simple Routine Builder Worksheet', 'topic_type' => 'promotional', 'metadata' => ['link_url' => 'https://example.com/skincare-routine-builder']],
                    ['title' => 'How to Read an Ingredient List Without Getting Sold', 'topic_type' => 'educational'],
                ],
            ],
            [
                'name' => 'Sustainable Living 25–45',
                'slug' => 'sustainable-living-25-45',
                'age_group' => '25–45',
                'niche_prompt' => 'Practical sustainability for households: waste reduction, repairs, smarter shopping, energy habits — without perfectionism.',
                'safety_rules' => 'Avoid greenwashing claims. No unsafe DIY chemical advice. Be honest about impact limits.',
                'audience' => 'Age group 25–45: households trying realistic eco-friendly habit changes.',
                'topics' => [
                    ['title' => 'Cut Food Waste With a Midweek Fridge Audit', 'topic_type' => 'educational'],
                    ['title' => '7 Reusable Swaps That Survive Real Family Life', 'topic_type' => 'listicle'],
                    ['title' => 'Keep a Repair Box by the Laundry Area', 'topic_type' => 'tip'],
                    ['title' => 'Low-Waste Home Starter Checklist', 'topic_type' => 'promotional', 'metadata' => ['link_url' => 'https://example.com/low-waste-checklist']],
                    ['title' => 'How to Buy Less Without Feeling Deprived', 'topic_type' => 'educational'],
                ],
            ],
            [
                'name' => 'Creative Hobbies 30–55',
                'slug' => 'creative-hobbies-30-55',
                'age_group' => '30–55',
                'niche_prompt' => 'Creative practice for busy adults: sketching, journaling, photography, craft progress without perfection pressure.',
                'safety_rules' => 'Tool safety where relevant. No copyright infringement advice. Encourage original practice.',
                'audience' => 'Age group 30–55: adults restarting creative hobbies after work and family obligations.',
                'topics' => [
                    ['title' => 'A 15-Minute Sketch Habit That Rebuilds Creative Muscle', 'topic_type' => 'educational'],
                    ['title' => '6 Low-Cost Starter Kits for Weekend Makers', 'topic_type' => 'listicle'],
                    ['title' => 'Leave Your Tools Out Where You’ll See Them', 'topic_type' => 'tip'],
                    ['title' => 'Creative Habit Tracker Printable', 'topic_type' => 'promotional', 'metadata' => ['link_url' => 'https://example.com/creative-habit-tracker']],
                    ['title' => 'How to Finish Small Projects Instead of Collecting Supplies', 'topic_type' => 'educational'],
                ],
            ],
        ];
    }
}
