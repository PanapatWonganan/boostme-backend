<?php

namespace App\Filament\Resources\Garden;

use App\Filament\Resources\Garden\AchievementResource\Pages;
use App\Models\Achievement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\KeyValue;

class AchievementResource extends Resource
{
    protected static ?string $model = Achievement::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';
    
    protected static ?string $navigationGroup = 'Wellness Garden';
    
    protected static ?int $navigationSort = 3;
    
    protected static ?string $navigationLabel = 'Achievements';
    
    protected static ?string $modelLabel = 'Achievement';
    
    protected static ?string $pluralModelLabel = 'Achievements';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Achievement Information')
                    ->description('Define achievement details and category')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Achievement Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., First Plant, Garden Master'),
                            
                        Forms\Components\TextInput::make('name_th')
                            ->label('Thai Name')
                            ->maxLength(255)
                            ->placeholder('e.g., นักปลูกมือใหม่, ปราชญ์แห่งสุขภาพ'),
                            
                        Forms\Components\Select::make('category')
                            ->label('Category')
                            ->required()
                            ->options([
                                'learning' => '📚 Learning',
                                'fitness' => '🌸 Fitness',
                                'mental' => '🧘 Mental',
                                'social' => '👥 Social',
                                'special' => '⭐ Special',
                                'seasonal' => '🎃 Seasonal',
                            ]),
                            
                        Forms\Components\TextInput::make('icon')
                            ->label('Icon/Badge Emoji')
                            ->maxLength(10)
                            ->placeholder('e.g., 🏆, 🥇, 🌟')
                            ->helperText('Emoji or icon for the achievement'),
                            
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->required()
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->placeholder('Describe what this achievement represents'),
                    ])->columns(2),
                    
                Section::make('Achievement Criteria')
                    ->description('Set the requirements to earn this achievement')
                    ->schema([
                        Forms\Components\Select::make('criteria_type')
                            ->label('Criteria Type')
                            ->required()
                            ->options([
                                'plant_count' => 'Plant Count',
                                'garden_level' => 'Garden Level',
                                'xp_earned' => 'XP Earned',
                                'courses_completed' => 'Courses Completed',
                                'lessons_completed' => 'Lessons Completed',
                                'consecutive_days' => 'Consecutive Days',
                                'friend_count' => 'Friend Count',
                                'community_likes' => 'Community Likes',
                                'special_event' => 'Special Event',
                            ])
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('criteria', [])),
                            
                        KeyValue::make('criteria')
                            ->label('Criteria Requirements')
                            ->keyLabel('Requirement')
                            ->valueLabel('Value')
                            ->default([])
                            ->helperText('Define specific requirements based on criteria type')
                            ->columnSpanFull(),
                            
                        Forms\Components\TextInput::make('points')
                            ->label('Achievement Points')
                            ->numeric()
                            ->minValue(10)
                            ->maxValue(1000)
                            ->default(100)
                            ->required()
                            ->helperText('Points value for this achievement'),
                    ])->columns(2),
                    
                Section::make('Rewards')
                    ->description('Configure rewards for earning this achievement')
                    ->schema([
                        Forms\Components\TextInput::make('xp_reward')
                            ->label('XP Reward')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(10000)
                            ->default(100)
                            ->required()
                            ->helperText('XP awarded when earned'),
                            
                        Forms\Components\TextInput::make('star_seeds_reward')
                            ->label('Star Seeds Reward')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(5000)
                            ->default(50)
                            ->required()
                            ->helperText('Star Seeds awarded when earned'),
                            
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
                            
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Display Order')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Order in achievement list'),
                    ])->columns(2),
                    
                Section::make('Visibility Settings')
                    ->description('Control when and how this achievement is shown')
                    ->schema([
                        Forms\Components\Toggle::make('is_hidden')
                            ->label('Hidden Achievement')
                            ->helperText('Hide until earned (secret achievement)'),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Make this achievement earnable'),
                            
                        Forms\Components\DateTimePicker::make('available_from')
                            ->label('Available From')
                            ->displayFormat('d/m/Y')
                            ->helperText('Start date for seasonal achievements'),
                            
                        Forms\Components\DateTimePicker::make('available_until')
                            ->label('Available Until')
                            ->displayFormat('d/m/Y')
                            ->helperText('End date for seasonal achievements'),
                    ])->columns(2),
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
                    ->label('Achievement')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->description),
                    
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'learning' => '📚 Learning',
                        'fitness' => '🌸 Fitness',
                        'mental' => '🧘 Mental',
                        'social' => '👥 Social',
                        'special' => '⭐ Special',
                        'seasonal' => '🎃 Seasonal',
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
                    
                Tables\Columns\TextColumn::make('xp_reward')
                    ->label('XP')
                    ->formatStateUsing(fn ($state) => '+' . $state . ' XP')
                    ->sortable()
                    ->alignCenter(),
                    
                Tables\Columns\TextColumn::make('star_seeds_reward')
                    ->label('Seeds')
                    ->formatStateUsing(fn ($state) => '⭐ ' . $state)
                    ->sortable()
                    ->alignCenter(),
                    
                Tables\Columns\TextColumn::make('user_achievements_count')
                    ->label('Earned By')
                    ->counts('userAchievements')
                    ->formatStateUsing(fn ($state) => $state . ' users')
                    ->sortable()
                    ->alignCenter(),
                    
                Tables\Columns\IconColumn::make('is_hidden')
                    ->label('Hidden')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye-slash')
                    ->falseIcon('heroicon-o-eye'),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'learning' => 'Learning',
                        'fitness' => 'Fitness',
                        'mental' => 'Mental',
                        'social' => 'Social',
                        'special' => 'Special',
                        'seasonal' => 'Seasonal',
                    ]),
                    
                Tables\Filters\SelectFilter::make('rarity')
                    ->options([
                        'common' => 'Common',
                        'rare' => 'Rare',
                        'epic' => 'Epic',
                        'legendary' => 'Legendary',
                    ]),
                    
                Tables\Filters\TernaryFilter::make('is_hidden')
                    ->label('Hidden'),
                    
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
            'index' => Pages\ListAchievements::route('/'),
            'create' => Pages\CreateAchievement::route('/create'),
            'edit' => Pages\EditAchievement::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)->count();
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}