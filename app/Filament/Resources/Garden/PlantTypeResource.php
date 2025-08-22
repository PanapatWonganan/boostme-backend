<?php

namespace App\Filament\Resources\Garden;

use App\Filament\Resources\Garden\PlantTypeResource\Pages;
use App\Models\PlantType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;

class PlantTypeResource extends Resource
{
    protected static ?string $model = PlantType::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    
    protected static ?string $navigationGroup = 'Wellness Garden';
    
    protected static ?int $navigationSort = 2;
    
    protected static ?string $navigationLabel = 'Plant Types';
    
    protected static ?string $modelLabel = 'Plant Type';
    
    protected static ?string $pluralModelLabel = 'Plant Types';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Basic Information')
                    ->description('Define plant characteristics')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Plant Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Rose, Sunflower, Lavender'),
                            
                        Forms\Components\TextInput::make('name_th')
                            ->label('Thai Name')
                            ->maxLength(255)
                            ->placeholder('e.g., กุหลาบ, ทานตะวัน, ลาเวนเดอร์'),
                            
                        Forms\Components\Select::make('category')
                            ->label('Category')
                            ->required()
                            ->options([
                                'fitness' => '🌸 Fitness',
                                'nutrition' => '🍎 Nutrition',
                                'mental' => '🧘 Mental',
                                'learning' => '📚 Learning',
                                'special' => '⭐ Special',
                            ]),
                            
                        Forms\Components\Select::make('rarity')
                            ->label('Rarity')
                            ->required()
                            ->options([
                                'common' => 'Common (ธรรมดา)',
                                'rare' => 'Rare (หายาก)',
                                'epic' => 'Epic (พิเศษ)',
                                'legendary' => 'Legendary (ตำนาน)',
                            ])
                            ->default('common'),
                            
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])->columns(2),
                    
                Section::make('Growth Configuration')
                    ->description('Set growth stages and requirements')
                    ->schema([
                        Forms\Components\TextInput::make('growth_stages')
                            ->label('Growth Stages')
                            ->numeric()
                            ->minValue(3)
                            ->maxValue(10)
                            ->default(5)
                            ->required()
                            ->helperText('Number of growth stages (3-10)'),
                            
                        Forms\Components\TextInput::make('growth_time')
                            ->label('Growth Time (hours)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(168)
                            ->default(24)
                            ->required()
                            ->helperText('Time to mature in hours'),
                            
                        Forms\Components\TextInput::make('unlock_level')
                            ->label('Unlock Level')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default(1)
                            ->required()
                            ->helperText('Garden level required to unlock'),
                            
                        Forms\Components\TextInput::make('star_seeds_cost')
                            ->label('Star Seeds Cost')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(10000)
                            ->default(10)
                            ->required()
                            ->helperText('Cost to plant'),
                    ])->columns(2),
                    
                Section::make('Care Requirements')
                    ->description('Define care needs and rewards')
                    ->schema([
                        KeyValue::make('care_requirements')
                            ->label('Care Requirements')
                            ->keyLabel('Requirement')
                            ->valueLabel('Value')
                            ->default([
                                'water_frequency' => 'daily',
                                'sunlight' => 'full',
                                'difficulty' => 'easy',
                            ])
                            ->helperText('Define care requirements as key-value pairs'),
                            
                        KeyValue::make('rewards')
                            ->label('Rewards')
                            ->keyLabel('Reward Type')
                            ->valueLabel('Amount')
                            ->default([
                                'xp_per_water' => '10',
                                'xp_harvest' => '100',
                                'star_seeds_harvest' => '50',
                            ])
                            ->helperText('Define rewards for different actions'),
                    ])->columns(1),
                    
                Section::make('Visual Assets')
                    ->description('Plant images and animations')
                    ->schema([
                        Forms\Components\TextInput::make('icon')
                            ->label('Icon/Emoji')
                            ->maxLength(10)
                            ->placeholder('e.g., 🌹, 🌻, 🌿')
                            ->helperText('Emoji or icon code'),
                            
                        Forms\Components\TextInput::make('image_seed')
                            ->label('Seed Image URL')
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://...'),
                            
                        Forms\Components\TextInput::make('image_sprout')
                            ->label('Sprout Image URL')
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://...'),
                            
                        Forms\Components\TextInput::make('image_mature')
                            ->label('Mature Image URL')
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://...'),
                    ])->columns(2),
                    
                Section::make('Special Abilities')
                    ->description('Advanced features and effects')
                    ->schema([
                        KeyValue::make('special_abilities')
                            ->label('Special Abilities')
                            ->keyLabel('Ability')
                            ->valueLabel('Effect')
                            ->default([])
                            ->helperText('e.g., xp_boost: 1.5, friend_bonus: true'),
                            
                        Forms\Components\Toggle::make('is_seasonal')
                            ->label('Seasonal Plant')
                            ->helperText('Only available during specific seasons'),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Make this plant available to users'),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('icon')
                    ->label('')
                    ->width('50px')
                    ->alignCenter(),
                    
                Tables\Columns\TextColumn::make('name')
                    ->label('Plant Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->name_th),
                    
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'fitness' => '🌸 Fitness',
                        'nutrition' => '🍎 Nutrition',
                        'mental' => '🧘 Mental',
                        'learning' => '📚 Learning',
                        'special' => '⭐ Special',
                        default => $state
                    }),
                    
                Tables\Columns\TextColumn::make('rarity')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'common' => 'gray',
                        'rare' => 'info',
                        'epic' => 'warning',
                        'legendary' => 'danger',
                        default => 'gray'
                    }),
                    
                Tables\Columns\TextColumn::make('unlock_level')
                    ->label('Unlock Lvl')
                    ->sortable()
                    ->alignCenter(),
                    
                Tables\Columns\TextColumn::make('star_seeds_cost')
                    ->label('Cost')
                    ->formatStateUsing(fn ($state) => '⭐ ' . $state)
                    ->sortable()
                    ->alignCenter(),
                    
                Tables\Columns\TextColumn::make('growth_time')
                    ->label('Growth Time')
                    ->formatStateUsing(fn ($state) => $state . 'h')
                    ->sortable()
                    ->alignCenter(),
                    
                Tables\Columns\TextColumn::make('user_plants_count')
                    ->label('Planted')
                    ->counts('userPlants')
                    ->sortable()
                    ->alignCenter(),
                    
                Tables\Columns\IconColumn::make('is_seasonal')
                    ->label('Seasonal')
                    ->boolean()
                    ->trueIcon('heroicon-o-sun')
                    ->falseIcon('heroicon-o-x-mark'),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'fitness' => 'Fitness',
                        'nutrition' => 'Nutrition',
                        'mental' => 'Mental',
                        'learning' => 'Learning',
                        'special' => 'Special',
                    ]),
                    
                Tables\Filters\SelectFilter::make('rarity')
                    ->options([
                        'common' => 'Common',
                        'rare' => 'Rare',
                        'epic' => 'Epic',
                        'legendary' => 'Legendary',
                    ]),
                    
                Tables\Filters\TernaryFilter::make('is_seasonal')
                    ->label('Seasonal'),
                    
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('category', 'asc');
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
            'index' => Pages\ListPlantTypes::route('/'),
            'create' => Pages\CreatePlantType::route('/create'),
            'edit' => Pages\EditPlantType::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)->count();
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}