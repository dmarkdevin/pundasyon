<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use App\Support\AssignableAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->target()) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $target = $this->target();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target)],
            'password' => ['sometimes', 'string', Password::default()],
            /** Role names, replacing the current ones. You can't change your own roles. */
            'roles' => ['sometimes', 'array', Rule::prohibitedIf(fn (): bool => $target->is($this->user()))],
            'roles.*' => ['string', Rule::in(AssignableAccess::roleNames($target))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['roles.prohibited' => 'You can\'t change your own roles. Ask another administrator.'];
    }

    private function target(): User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : abort(404);
    }
}
