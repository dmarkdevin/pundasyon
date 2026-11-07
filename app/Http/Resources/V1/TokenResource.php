<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\NewAccessToken;

/**
 * The only time a token is shown. It can't be read back later.
 *
 * @property NewAccessToken $resource
 */
final class TokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $token = $this->resource->accessToken;

        return [
            'token' => $this->resource->plainTextToken,
            'token_type' => 'Bearer',
            'name' => $token->name,
            /** @var list<string> */
            'abilities' => $token->abilities,
            'expires_at' => $token->expires_at?->toIso8601String(),
            'user' => new UserResource($token->tokenable?->loadMissing('roles')),
        ];
    }
}
