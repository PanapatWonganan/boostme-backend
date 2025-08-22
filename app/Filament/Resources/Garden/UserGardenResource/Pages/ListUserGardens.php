<?php

namespace App\Filament\Resources\Garden\UserGardenResource\Pages;

use App\Filament\Resources\Garden\UserGardenResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUserGardens extends ListRecords
{
    protected static string $resource = UserGardenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
    
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Gardens')
                ->icon('heroicon-o-home'),
                
            'active' => Tab::make('Active Today')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('last_watered_at', '>=', now()->startOfDay()))
                ->badge(fn () => UserGardenResource::getModel()::where('last_watered_at', '>=', now()->startOfDay())->count())
                ->badgeColor('success')
                ->icon('heroicon-o-check-circle'),
                
            'inactive' => Tab::make('Inactive (3+ days)')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('last_watered_at', '<', now()->subDays(3)))
                ->badge(fn () => UserGardenResource::getModel()::where('last_watered_at', '<', now()->subDays(3))->count())
                ->badgeColor('danger')
                ->icon('heroicon-o-exclamation-circle'),
                
            'high_level' => Tab::make('High Level (25+)')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('level', '>=', 25))
                ->badge(fn () => UserGardenResource::getModel()::where('level', '>=', 25)->count())
                ->badgeColor('warning')
                ->icon('heroicon-o-star'),
        ];
    }
    
    protected function getHeaderWidgets(): array
    {
        return [
            UserGardenResource\Widgets\GardenStatsOverview::class,
        ];
    }
}