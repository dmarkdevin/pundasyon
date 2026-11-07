<?php

declare(strict_types=1);

namespace App\Modules;

use RuntimeException;

/**
 * Reads and writes modules.json: which modules exist, their permissions and
 * every file make:module created for them, so remove:module can take them out.
 */
final class ModuleRegistry
{
    /**
     * @return array<string, array{permission: string, actions: list<string>, table: string, title: string, files: list<string>, created_at: string}>
     */
    public static function all(): array
    {
        $path = self::path();

        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        /** @var array<string, array{permission: string, actions: list<string>, table: string, title: string, files: list<string>, created_at: string}> */
        return is_array($data) && is_array($data['modules'] ?? null) ? $data['modules'] : [];
    }

    /**
     * @return array{permission: string, actions: list<string>, table: string, title: string, files: list<string>, created_at: string}|null
     */
    public static function find(string $model): ?array
    {
        return self::all()[$model] ?? null;
    }

    /**
     * @param  array{permission: string, actions: list<string>, table: string, title: string, files: list<string>, created_at: string}  $entry
     */
    public static function put(string $model, array $entry): void
    {
        $modules = self::all();
        $modules[$model] = $entry;
        ksort($modules);
        self::write($modules);
    }

    public static function forget(string $model): void
    {
        $modules = self::all();
        unset($modules[$model]);
        self::write($modules);
    }

    public static function titleAttributeFor(string $model): string
    {
        return match (true) {
            $model === 'User' => 'name',
            isset(self::all()[$model]) => self::all()[$model]['title'],
            default => 'id',
        };
    }

    public static function path(): string
    {
        return base_path('modules.json');
    }

    /**
     * @param  array<string, mixed>  $modules
     */
    private static function write(array $modules): void
    {
        $json = json_encode(['modules' => (object) $modules], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false || file_put_contents(self::path(), $json.PHP_EOL) === false) {
            throw new RuntimeException('Could not write modules.json.');
        }
    }
}
