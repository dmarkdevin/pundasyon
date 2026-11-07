<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            /** What the token is for, shown in the token list, such as "Accounting sync". */
            'device_name' => ['required', 'string', 'max:100'],
            /** The 6-digit code from an authenticator app, when the account uses two-factor sign-in. */
            'code' => ['nullable', 'string', 'max:10'],
            /** Permissions this token may use. Leave out for every permission you have. */
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', Rule::in(Permissions::all())],
            /** Days until the token stops working, up to 365. Leave out for 90. */
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }
}
