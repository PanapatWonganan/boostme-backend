<?php

namespace App\Filament\Resources\Garden\AchievementResource\Pages;

use App\Filament\Resources\Garden\AchievementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAchievements extends ListRecords
{
    protected static string $resource = AchievementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
    
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Achievements')
                ->icon('heroicon-o-trophy'),
                
            'learning' => Tab::make('Learning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category', 'learning'))
                ->badge(fn () => AchievementResource::getModel()::where('category', 'learning')->count())
                ->icon('heroicon-o-academic-cap'),
                
            'fitness' => Tab::make('Fitness')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category', 'fitness'))
                ->badge(fn () => AchievementResource::getModel()::where('category', 'fitness')->count())
                ->icon('heroicon-o-heart'),
                
            'social' => Tab::make('Social')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category', 'social'))
                ->badge(fn () => AchievementResource::getModel()::where('category', 'social')->count())
                ->icon('heroicon-o-user-group'),
                
            'special' => Tab::make('Special')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('category', 'special'))
                ->badge(fn () => AchievementResource::getModel()::where('category', 'special')->count())
                ->badgeColor('warning')
                ->icon('heroicon-o-star'),
        ];
    }
}