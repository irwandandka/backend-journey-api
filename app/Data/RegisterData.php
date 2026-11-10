<?php

namespace App\Data;

use App\Http\Middleware\SetLocale;
use Illuminate\Validation\Rules\Password;
use Spatie\LaravelData\Data;

class RegisterData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $locale = null,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'locale' => ['nullable', 'string', 'in:'.implode(',', SetLocale::supportedLocales())],
        ];
    }
}
