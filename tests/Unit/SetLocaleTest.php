<?php

namespace Tests\Unit;

use App\Http\Middleware\SetLocale;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SetLocaleTest extends TestCase
{
    /** @return array<string, array{mixed, list<string>}> */
    public static function configShapes(): array
    {
        return [
            'two locales' => ['en,id', ['en', 'id']],
            'single locale' => ['en', ['en']],
            'empty string' => ['', ['en']],
            'only commas' => [',,,', ['en']],
            'padded and mixed case' => ['  EN , Id ', ['en', 'id']],
            'empty slot in the middle' => ['en,,id', ['en', 'id']],
            'duplicates' => ['en,id,en', ['en', 'id']],
            'already an array' => [['en', 'id'], ['en', 'id']],
            'null' => [null, ['en']],
            'zero string' => ['0', ['0']],
            'numeric' => [0, ['0']],
        ];
    }

    #[DataProvider('configShapes')]
    public function test_it_normalises_every_shape_the_env_value_can_take(mixed $raw, array $expected): void
    {
        config(['app.supported_locales' => $raw]);

        $this->assertSame($expected, SetLocale::supportedLocales());
    }
}
