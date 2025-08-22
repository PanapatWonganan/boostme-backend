<?php

namespace App\Filament\Widgets;

use App\Models\GardenActivity;
use App\Models\UserGarden;
use App\Models\UserPlant;
use App\Models\UserChallengeProgress;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class GardenEngagementChart extends ChartWidget
{
    protected static ?string $heading = '🌱 Garden Engagement Analytics';
    
    protected static ?int $sort = 2;
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $maxHeight = '300px';
    
    public ?string $filter = '7';

    protected function getData(): array
    {
        $days = (int) $this->filter;
        $endDate = now();
        $startDate = now()->subDays($days - 1)->startOfDay();
        
        // Get daily statistics
        $dailyStats = collect();
        
        for ($i = 0; $i < $days; $i++) {
            $date = now()->subDays($days - 1 - $i);
            $dateStr = $date->format('Y-m-d');
            
            // Count activities for each day
            $gardenVisits = UserGarden::whereDate('last_visited_at', $date)->count();
            $plantsWatered = GardenActivity::whereDate('created_at', $date)
                ->where('activity_type', 'water_plant')
                ->count();
            $plantsPlanted = UserPlant::whereDate('planted_at', $date)->count();
            $challengesCompleted = UserChallengeProgress::whereDate('completed_at', $date)
                ->where('is_completed', true)
                ->count();
            
            $dailyStats->push([
                'date' => $date->format('M d'),
                'visits' => $gardenVisits,
                'watered' => $plantsWatered,
                'planted' => $plantsPlanted,
                'challenges' => $challengesCompleted,
            ]);
        }
        
        return [
            'datasets' => [
                [
                    'label' => 'Garden Visits',
                    'data' => $dailyStats->pluck('visits')->toArray(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.3)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Plants Watered',
                    'data' => $dailyStats->pluck('watered')->toArray(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.3)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Plants Planted',
                    'data' => $dailyStats->pluck('planted')->toArray(),
                    'backgroundColor' => 'rgba(236, 72, 153, 0.3)',
                    'borderColor' => 'rgb(236, 72, 153)',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Challenges Completed',
                    'data' => $dailyStats->pluck('challenges')->toArray(),
                    'backgroundColor' => 'rgba(245, 158, 11, 0.3)',
                    'borderColor' => 'rgb(245, 158, 11)',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $dailyStats->pluck('date')->toArray(),
        ];
    }

    protected function getFilters(): ?array
    {
        return [
            '7' => 'Last 7 days',
            '14' => 'Last 14 days',
            '30' => 'Last 30 days',
            '90' => 'Last 3 months',
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
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
            'maintainAspectRatio' => false,
            'interaction' => [
                'mode' => 'nearest',
                'axis' => 'x',
                'intersect' => false,
            ],
        ];
    }
}