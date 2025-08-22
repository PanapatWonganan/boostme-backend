<?php

namespace App\Filament\Widgets;

use App\Models\UserGarden;
use App\Models\UserPlant;
use App\Models\UserAchievement;
use App\Models\GardenActivity;
use App\Models\DailyChallenge;
use App\Models\UserChallengeProgress;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class GardenOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected int | string | array $columnSpan = 'full';
    
    protected function getStats(): array
    {
        // Calculate statistics
        $totalGardens = UserGarden::count();
        $activeToday = UserGarden::where('last_watered_at', '>=', now()->startOfDay())->count();
        $totalPlants = UserPlant::count();
        $plantsGrowingNow = UserPlant::where('stage', '<', 4)->count();
        $totalAchievements = UserAchievement::count();
        $achievementsToday = UserAchievement::whereDate('earned_at', today())->count();
        $totalStarSeeds = UserGarden::sum('star_seeds');
        $avgLevel = UserGarden::avg('level') ?? 0;
        
        // Calculate daily active rate
        $activeRate = $totalGardens > 0 ? round(($activeToday / $totalGardens) * 100, 1) : 0;
        
        // Get challenge completion rate
        $todaysChallenges = DailyChallenge::whereDate('available_date', today())->count();
        $completedChallenges = UserChallengeProgress::where('is_completed', true)
            ->whereDate('completed_at', today())
            ->count();
        
        // Get 7-day activity trend
        $activityTrend = GardenActivity::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count')
            ->toArray();
            
        return [
            Stat::make('🌱 Active Gardens', number_format($activeToday) . ' / ' . number_format($totalGardens))
                ->description($activeRate . '% active today')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($activeRate > 50 ? 'success' : 'warning')
                ->chart($activityTrend),
                
            Stat::make('🌻 Total Plants', number_format($totalPlants))
                ->description($plantsGrowingNow . ' growing now')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('info')
                ->chart([rand(90, 100), rand(95, 105), rand(100, 110), rand(105, 115), rand(110, 120), rand(115, 125), $plantsGrowingNow]),
                
            Stat::make('🏆 Achievements', number_format($totalAchievements))
                ->description('+' . $achievementsToday . ' earned today')
                ->descriptionIcon('heroicon-m-trophy')
                ->color('warning'),
                
            Stat::make('⭐ Economy', number_format($totalStarSeeds) . ' Seeds')
                ->description('Avg Level: ' . number_format($avgLevel, 1))
                ->descriptionIcon('heroicon-m-star')
                ->color('primary'),
                
            Stat::make('🎯 Daily Challenges', $completedChallenges . ' completed')
                ->description('From ' . $todaysChallenges . ' available')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($completedChallenges > 0 ? 'success' : 'gray'),
                
            Stat::make('📈 Growth Rate', $this->calculateGrowthRate() . '%')
                ->description('Weekly user growth')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($this->calculateGrowthRate() > 0 ? 'success' : 'danger')
                ->chart($this->getWeeklyGrowthChart()),
        ];
    }
    
    private function calculateGrowthRate(): float
    {
        $lastWeek = UserGarden::where('created_at', '>=', now()->subWeek()->startOfWeek())
            ->where('created_at', '<', now()->startOfWeek())
            ->count();
            
        $thisWeek = UserGarden::where('created_at', '>=', now()->startOfWeek())->count();
        
        if ($lastWeek === 0) {
            return $thisWeek > 0 ? 100 : 0;
        }
        
        return round((($thisWeek - $lastWeek) / $lastWeek) * 100, 1);
    }
    
    private function getWeeklyGrowthChart(): array
    {
        return UserGarden::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count')
            ->toArray();
    }
    
    protected function getPollingInterval(): ?string
    {
        return '30s'; // Auto-refresh every 30 seconds
    }
}