<?php

namespace App\Filament\Resources\Garden\UserGardenResource\Pages;

use App\Filament\Resources\Garden\UserGardenResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewUserGarden extends ViewRecord
{
    protected static string $resource = UserGardenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
    
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Garden Overview')
                    ->schema([
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Owner'),
                        Infolists\Components\TextEntry::make('level')
                            ->badge()
                            ->color(fn (int $state): string => match (true) {
                                $state >= 50 => 'success',
                                $state >= 25 => 'warning',
                                $state >= 10 => 'info',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('xp')
                            ->label('Experience Points')
                            ->formatStateUsing(fn ($state) => number_format($state) . ' XP'),
                        Infolists\Components\TextEntry::make('star_seeds')
                            ->formatStateUsing(fn ($state) => '⭐ ' . number_format($state))
                            ->color('warning'),
                        Infolists\Components\TextEntry::make('theme')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match($state) {
                                'tropical' => '🌴 Tropical Paradise',
                                'zen' => '🎋 Japanese Zen',
                                'cottage' => '🏡 English Cottage',
                                'modern' => '🏙️ Modern Minimalist',
                                'spring' => '🌸 Seasonal Spring',
                                'gold' => '👑 Premium Gold',
                                default => $state
                            }),
                        Infolists\Components\TextEntry::make('plants_count')
                            ->label('Total Plants')
                            ->state(fn ($record) => $record->plants()->count())
                            ->badge()
                            ->color('success'),
                    ])
                    ->columns(3),
                    
                Infolists\Components\Section::make('Activity Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('last_watered_at')
                            ->label('Last Watered')
                            ->dateTime('d/m/Y H:i:s')
                            ->color(fn ($state) => 
                                $state && $state->diffInHours(now()) > 24 ? 'danger' : 'success'
                            ),
                        Infolists\Components\TextEntry::make('last_visited_at')
                            ->label('Last Visited')
                            ->dateTime('d/m/Y H:i:s'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Garden Created')
                            ->dateTime('d/m/Y H:i:s'),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime('d/m/Y H:i:s'),
                    ])
                    ->columns(2),
            ]);
    }
}