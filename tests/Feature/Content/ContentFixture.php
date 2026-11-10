<?php

namespace Tests\Feature\Content;

class ContentFixture
{
    /** @return array<string, mixed> */
    public static function minimal(string $correctLabel = 'Use SET NX EX'): array
    {
        return [
            'roadmap' => [
                'slug' => 'be',
                'position' => 1,
                'is_published' => true,
                'translations' => [
                    'en' => ['title' => 'Backend'],
                    'id' => ['title' => 'Backend'],
                ],
            ],
            'tags' => [
                ['slug' => 'redis', 'name' => 'Redis'],
            ],
            'tracks' => [
                [
                    'slug' => 'caching',
                    'abbreviation' => 'CA',
                    'color_dark' => '#FF9A2E',
                    'color_light' => '#C2410C',
                    'position' => 1,
                    'is_published' => true,
                    'translations' => [
                        'en' => ['name' => 'Caching'],
                        'id' => ['name' => 'Caching & Invalidasi'],
                    ],
                    'journeys' => [
                        [
                            'slug' => 'stampede',
                            'tier' => 'intermediate',
                            'position' => 1,
                            'translations' => [
                                'en' => ['title' => 'Cache Stampede'],
                            ],
                            'quiz' => [
                                'slug' => 'stampede-core',
                                'question_count' => 1,
                                'translations' => ['en' => ['title' => 'Cache Stampede']],
                                'questions' => ['q-stampede'],
                            ],
                        ],
                    ],
                ],
            ],
            'questions' => [
                [
                    'key' => 'q-stampede',
                    'track' => 'caching',
                    'type' => 'single_choice',
                    'difficulty' => 'medium',
                    'tags' => ['redis'],
                    'translations' => [
                        'en' => ['stem_md' => 'The key expired. What now?', 'explanation_md' => 'One rebuild only.'],
                    ],
                    'options' => [
                        ['is_correct' => false, 'translations' => ['en' => ['label_md' => 'Lower the TTL']]],
                        ['is_correct' => true, 'translations' => ['en' => ['label_md' => $correctLabel]]],
                    ],
                ],
            ],
            'achievements' => [
                [
                    'slug' => 'first-journey',
                    'icon' => 'flag',
                    'position' => 1,
                    'rule' => ['type' => 'journeys_completed', 'count' => 1],
                    'translations' => ['en' => ['title' => 'First Journey']],
                ],
            ],
        ];
    }
}
