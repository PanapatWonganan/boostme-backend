<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\User;
use App\Models\Course;
use App\Models\Enrollment;

class SimpleStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected function getStats(): array
    {
        // ใช้ simple queries ไม่ซับซ้อน
        $totalUsers = User::count();
        $totalCourses = Course::count();
        $totalEnrollments = Enrollment::count();
        $completedEnrollments = Enrollment::where('payment_status', 'completed')->count();
        
        return [
            Stat::make('👥 ผู้ใช้ทั้งหมด', number_format($totalUsers))
                ->description('ผู้ใช้ในระบบ')
                ->color('primary'),
                
            Stat::make('📚 คอร์สทั้งหมด', number_format($totalCourses))
                ->description('คอร์สในระบบ')
                ->color('success'),
                
            Stat::make('📝 การลงทะเบียน', number_format($totalEnrollments))
                ->description('ทั้งหมด')
                ->color('info'),
                
            Stat::make('✅ ชำระเงินแล้ว', number_format($completedEnrollments))
                ->description('จาก ' . number_format($totalEnrollments) . ' รายการ')
                ->color('warning'),
        ];
    }
}