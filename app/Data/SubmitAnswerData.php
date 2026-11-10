<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class SubmitAnswerData extends Data
{
    public function __construct(
        public int $position,
        /** @var list<int> */
        public array $optionIds,
        public ?int $timeSpentMs = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'position' => ['required', 'integer', 'min:0'],
            'optionIds' => ['required', 'array', 'min:1', 'max:10'],
            'optionIds.*' => ['required', 'integer', 'distinct'],
            'timeSpentMs' => ['nullable', 'integer', 'min:0', 'max:86400000'],
        ];
    }
}
