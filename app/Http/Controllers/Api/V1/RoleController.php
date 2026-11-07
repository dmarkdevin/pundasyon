<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Roles\SaveRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SaveRoleRequest;
use App\Http\Resources\V1\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @tags Roles
 */
final class RoleController extends Controller
{
    /**
     * List roles.
     *
     * Filter with `filter[name]` (partial match). Sort by `name` or `created_at`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Role::class);

        $roles = QueryBuilder::for(Role::class)
            ->allowedFilters(AllowedFilter::partial('name'))
            ->allowedSorts('name', 'created_at')
            ->defaultSort('name')
            ->with('permissions')
            ->withCount('users')
            ->paginate(UserController::perPage($request))
            ->withQueryString();

        return RoleResource::collection($roles);
    }

    /**
     * Create a role.
     */
    public function store(SaveRoleRequest $request, SaveRole $save): JsonResponse
    {
        /** @var array{name: string, permissions?: list<string>} $data */
        $data = $request->validated();

        return (new RoleResource($save($data)))->response()->setStatusCode(201);
    }

    /**
     * Show a role.
     */
    public function show(Role $role): RoleResource
    {
        Gate::authorize('view', $role);

        return new RoleResource($role->load('permissions')->loadCount('users'));
    }

    /**
     * Update a role. Built-in roles can't be renamed; the super-admin role can't be changed.
     */
    public function update(SaveRoleRequest $request, Role $role, SaveRole $save): RoleResource
    {
        /** @var array{name?: string, permissions?: list<string>} $data */
        $data = $request->validated();

        return new RoleResource($save($data, $role));
    }

    /**
     * Delete a role. Built-in roles can't be deleted.
     */
    public function destroy(Role $role): Response
    {
        Gate::authorize('delete', $role);

        if ($role->isSystem()) {
            abort(403);
        }

        $role->delete();

        return response()->noContent();
    }
}
