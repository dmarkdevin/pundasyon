<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Role;
use App\Support\AssignableAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating and updating a role. Built-in roles keep their names, and the
 * super-admin role has no permission list to change.
 */
final class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->role();

        return $role === null
            ? ($this->user()?->can('create', Role::class) ?? false)
            : ($this->user()?->can('update', $role) ?? false) && ! $role->isSuperAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $role = $this->role();

        return [
            'name' => [
                $role === null ? 'required' : 'sometimes',
                $role?->isSystem() ? 'prohibited' : 'string',
                'max:100',
                'alpha_dash',
                Rule::unique('roles', 'name')->ignore($role),
            ],
            /** Permission names, replacing the current ones. You can only give permissions you have. */
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::in(AssignableAccess::permissionNames($role))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.prohibited' => 'Built-in roles can\'t be renamed.'];
    }

    private function role(): ?Role
    {
        $role = $this->route('role');

        return $role instanceof Role ? $role : null;
    }
}
