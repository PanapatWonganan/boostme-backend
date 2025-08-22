<?php

namespace App\Filament\Resources\Garden;

use App\Filament\Resources\Garden\UserGardenResource\Pages;
use App\Models\UserGarden;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Support\Enums\IconPosition;

class UserGardenResource extends Resource
{
    protected static ?string $model = UserGarden::class;

    protected static ?string $navigationIcon = 'heroicon-o-home';
    
    protected static ?string $navigationGroup = 'Wellness Garden';
    
    protected static ?int $navigationSort = 1;
    
    protected static ?string $navigationLabel = 'User Gardens';
    
    protected static ?string $modelLabel = 'User Garden';
    
    protected static ?string $pluralModelLabel = 'User Gardens';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Garden Information')
                    ->description('Manage user garden details and progress')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('User')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn ($record) => $record !== null),
                            
                        Forms\Components\TextInput::make('level')
                            ->label('Garden Level')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->required()
                            ->helperText('Current garden level (1-100)'),
                            
                        Forms\Components\TextInput::make('xp')
                            ->label('Experience Points (XP)')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->helperText('Total XP earned'),
                            
                        Forms\Components\TextInput::make('star_seeds')
                            ->label('Star Seeds')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->helperText('Garden currency'),
                    ])->columns(2),
                    
                Section::make('Garden Customization')
                    ->description('Theme and layout settings')
                    ->schema([
                        Forms\Components\Select::make('theme')
                            ->label('Garden Theme')
                            ->options([
                                'tropical' => 'Tropical Paradise',
                                'zen' => 'Japanese Zen',
                                'cottage' => 'English Cottage',
                                'modern' => 'Modern Minimalist',
                                'spring' => 'Seasonal Spring',
                                'gold' => 'Premium Gold',
                            ])
                            ->default('tropical')
                            ->required(),
                            
                        Forms\Components\Textarea::make('garden_layout')
                            ->label('Garden Layout (JSON)')
                            ->rows(4)
                            ->columnSpanFull()
                            ->helperText('JSON data for garden layout configuration'),
                    ])->columns(2),
                    
                Section::make('Activity Tracking')
                    ->description('Track user engagement')
                    ->schema([
                        Forms\Components\DateTimePicker::make('last_watered_at')
                            ->label('Last Watered')
                            ->displayFormat('d/m/Y H:i')
                            ->helperText('Last time garden was watered'),
                            
                        Forms\Components\DateTimePicker::make('last_visited_at')
                            ->label('Last Visited')
                            ->displayFormat('d/m/Y H:i')
                            ->helperText('Last time user visited garden'),
                            
                        Forms\Components\TextInput::make('total_plants_grown')
                            ->label('Total Plants Grown')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                            
                        Forms\Components\TextInput::make('achievements_earned')
                            ->label('Achievements Earned')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('level')
                    ->label('Level')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 50 => 'success',
                        $state >= 25 => 'warning',
                        $state >= 10 => 'info',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('xp')
                    ->label('XP')
                    ->sortable()
                    ->numeric()
                    ->formatStateUsing(fn (int $state): string => number_format($state)),
                    
                Tables\Columns\TextColumn::make('star_seeds')
                    ->label('Star Seeds')
                    ->sortable()
                    ->numeric()
                    ->formatStateUsing(fn (int $state): string => '⭐ ' . number_format($state))
                    ->color('warning'),
                    
                Tables\Columns\TextColumn::make('theme')
                    ->label('Theme')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'tropical' => '🌴 Tropical',
                        'zen' => '🎋 Zen',
                        'cottage' => '🏡 Cottage',
                        'modern' => '🏙️ Modern',
                        'spring' => '🌸 Spring',
                        'gold' => '👑 Gold',
                        default => $state
                    }),
                    
                Tables\Columns\TextColumn::make('plants_count')
                    ->label('Plants')
                    ->counts('plants')
                    ->badge()
                    ->color('success'),
                    
                Tables\Columns\TextColumn::make('last_watered_at')
                    ->label('Last Watered')
                    ->dateTime('d/m H:i')
                    ->sortable()
                    ->color(fn ($state) => 
                        $state && $state->diffInHours(now()) > 24 ? 'danger' : 'gray'
                    ),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Garden Created')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('theme')
                    ->label('Theme')
                    ->options([
                        'tropical' => 'Tropical Paradise',
                        'zen' => 'Japanese Zen',
                        'cottage' => 'English Cottage',
                        'modern' => 'Modern Minimalist',
                        'spring' => 'Seasonal Spring',
                        'gold' => 'Premium Gold',
                    ]),
                    
                Tables\Filters\Filter::make('active_users')
                    ->label('Active (24h)')
                    ->query(fn (Builder $query): Builder => $query->where('last_watered_at', '>=', now()->subDay())),
                    
                Tables\Filters\Filter::make('high_level')
                    ->label('High Level (25+)')
                    ->query(fn (Builder $query): Builder => $query->where('level', '>=', 25)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('level', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserGardens::route('/'),
            'create' => Pages\CreateUserGarden::route('/create'),
            'edit' => Pages\EditUserGarden::route('/{record}/edit'),
            'view' => Pages\ViewUserGarden::route('/{record}'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}