<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * One line, so no legend: the heading names it. #0d9488 passes contrast on
 * both the light and the dark panel background.
 */
final class SignUpsChart extends ChartWidget
{
    private const DAYS = 30;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Sign-ups, last 30 days';

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return Auth::user()?->can('users.view') ?? false;
    }

    /**
     * Counted in PHP, so it works the same on MySQL, PostgreSQL and SQLite.
     *
     * @return array<string, int>
     */
    public function countsPerDay(): array
    {
        // Days as the people reading the dashboard see them.
        $zone = (string) config('app.display_timezone');
        $start = now($zone)->subDays(self::DAYS - 1)->startOfDay();
        $counts = [];

        for ($day = $start->copy(); $day->lte(now($zone)); $day->addDay()) {
            $counts[$day->toDateString()] = 0;
        }

        User::query()->withTrashed()->where('created_at', '>=', $start->copy()->utc())->pluck('created_at')
            ->each(function (mixed $createdAt) use (&$counts, $zone): void {
                $date = Carbon::parse($createdAt, 'UTC')->setTimezone($zone)->toDateString();

                if (isset($counts[$date])) {
                    $counts[$date]++;
                }
            });

        return $counts;
    }

    protected function getData(): array
    {
        $counts = $this->countsPerDay();

        return [
            'datasets' => [[
                'label' => 'Sign-ups',
                'data' => array_values($counts),
                'borderColor' => '#0d9488',
                'backgroundColor' => '#0d9488',
                'borderWidth' => 2,
                'pointRadius' => 0,
                'pointHoverRadius' => 5,
                'pointHitRadius' => 12,
                // Smooth, but never drawn below zero or above the real values between days.
                'cubicInterpolationMode' => 'monotone',
                'fill' => false,
            ]],
            'labels' => array_map(fn (string $date): string => Carbon::parse($date)->format('j M'), array_keys($counts)),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => ['mode' => 'index', 'intersect' => false],
            ],
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0], 'grid' => ['color' => 'rgba(128,128,128,0.15)']],
                'x' => ['grid' => ['display' => false], 'ticks' => ['maxTicksLimit' => 8]],
            ],
        ];
    }
}
