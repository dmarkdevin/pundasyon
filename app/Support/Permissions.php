<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The list of every permission, from config/permissions.php plus generated modules.
 */
final class Permissions
{
    /** @var array<string, true>|null */
    private static ?array $known = null;

    /**
     * Whether an ability is one of our permission names, as opposed to a policy
     * ability like "update". Remembered for the request; reset() clears it.
     */
    public static function exists(string $ability): bool
    {
        self::$known ??= array_fill_keys(self::all(), true);

        return isset(self::$known[$ability]);
    }

    public static function reset(): void
    {
        self::$known = null;
    }

    /**
     * @return array<string, list<string>> module => actions
     */
    public static function modules(): array
    {
        /** @var array<string, list<string>> $core */
        $core = config('permissions.modules', []);

        // A generated module can't redefine a core one, such as "users".
        return [...self::generatedModules(), ...$core];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $names = [];

        foreach (self::modules() as $module => $actions) {
            foreach ($actions as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        return $names;
    }

    /**
     * Permissions the admin role gets on its own.
     *
     * @return list<string>
     */
    public static function forAdmins(): array
    {
        /** @var list<string> $excluded */
        $excluded = config('permissions.not_for_admins', []);

        return array_values(array_diff(self::all(), $excluded));
    }

    /**
     * "users.update" becomes "Users: update".
     */
    public static function label(string $name): string
    {
        [$module, $action] = array_pad(explode('.', $name, 2), 2, '');

        return Str::headline($module).': '.Str::lower(Str::headline($action));
    }

    /**
     * @return array<string, list<string>>
     */
    private static function generatedModules(): array
    {
        $path = base_path('modules.json');

        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        $modules = [];

        foreach (is_array($data) && is_array($data['modules'] ?? null) ? $data['modules'] : [] as $module) {
            if (is_array($module) && is_string($module['permission'] ?? null) && is_array($module['actions'] ?? null)) {
                $modules[$module['permission']] = array_values(array_filter($module['actions'], is_string(...)));
            }
        }

        return $modules;
    }
}
