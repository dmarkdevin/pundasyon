<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Users\SaveUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @tags Users
 */
final class UserController extends Controller
{
    /**
     * List users.
     *
     * Filters: `filter[search]` (name or email), `filter[status]` (active or suspended),
     * `filter[role]` (role name), `filter[trashed]` (with or only).
     * Sort by `name`, `email` or `created_at`; prefix with `-` for descending (default `-created_at`).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $users = QueryBuilder::for(User::class)
            ->allowedFilters(
                AllowedFilter::callback('search', fn (Builder $query, mixed $value) => $query->where(fn (Builder $q) => $q
                    ->where('name', 'like', '%'.(string) $value.'%')
                    ->orWhere('email', 'like', '%'.(string) $value.'%'))),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('role', fn (Builder $query, mixed $value) => $query->whereHas('roles', fn (Builder $q) => $q->where('name', (string) $value))),
                AllowedFilter::trashed(),
            )
            ->allowedSorts('name', 'email', 'created_at')
            ->defaultSort('-created_at')
            ->with('roles')
            ->paginate(self::perPage($request))
            ->withQueryString();

        return UserResource::collection($users);
    }

    /**
     * Create a user. They're marked as having confirmed their email.
     */
    public function store(StoreUserRequest $request, SaveUser $save): JsonResponse
    {
        /** @var array{name: string, email: string, password: string, roles?: list<string>} $data */
        $data = $request->validated();

        return (new UserResource($save($data)))->response()->setStatusCode(201);
    }

    /**
     * Show a user.
     */
    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user->load('roles'));
    }

    /**
     * Update a user. Only the fields you send change.
     */
    public function update(UpdateUserRequest $request, User $user, SaveUser $save): UserResource
    {
        /** @var array{name?: string, email?: string, password?: string, roles?: list<string>} $data */
        $data = $request->validated();

        return new UserResource($save($data, $user));
    }

    /**
     * Delete a user. It can be undone with restore.
     */
    public function destroy(User $user): Response
    {
        Gate::authorize('delete', $user);
        $user->delete();

        return response()->noContent();
    }

    /**
     * Restore a deleted user.
     */
    public function restore(User $user): UserResource
    {
        Gate::authorize('restore', $user);
        $user->restore();

        return new UserResource($user->load('roles'));
    }

    /**
     * Between 1 and 100, 15 by default.
     */
    public static function perPage(Request $request): int
    {
        return max(1, min(100, $request->integer('per_page', 15)));
    }
}
