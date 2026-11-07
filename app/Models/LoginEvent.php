<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoginEventType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sign-in attempt, kept so people can spot sign-ins that weren't them.
 *
 * @property LoginEventType $type
 * @property \Illuminate\Support\Carbon $created_at
 */
final class LoginEvent extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'email', 'type', 'ip_address', 'user_agent'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays((int) config('security.login_history_days')));
    }

    protected function casts(): array
    {
        return ['type' => LoginEventType::class];
    }
}
