<?php

namespace App\Filament\Resources\Garden\UserGardenResource\Widgets;

use App\Models\UserGarden;
use App\Models\UserPlant;
use App\Models\UserAchievement;
use App\Models\GardenActivity;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GardenStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalGardens = UserGarden::count();
        $activeToday = UserGarden::where('last_watered_at', '>=', now()->startOfDay())->count();
        $totalPlants = UserPlant::count();
        $totalAchievements = UserAchievement::count();
        $avgLevel = UserGarden::avg('level') ?? 0;
        $totalStarSeeds = UserGarden::sum('star_seeds');
        
        return [
            Stat::make('Total Gardens', number_format($totalGardens))
                ->description($activeToday . ' active today')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart([7, 8, 10, 9, 12, 15, $activeToday]),
                
            Stat::make('Total Plants', number_format($totalPlants))
                ->description('Across all gardens')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('info'),
                
            Stat::make('Average Level', number_format($avgLevel, 1))
                ->description('Garden progression')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('warning'),
                
            Stat::make('Total Star Seeds', '⭐ ' . number_format($totalStarSeeds))
                ->description('Economy circulation')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('primary'),
        ];
    }
}