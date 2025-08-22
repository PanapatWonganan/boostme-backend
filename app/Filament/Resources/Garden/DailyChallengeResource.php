<?php

namespace App\Filament\Resources\Garden;

use App\Filament\Resources\Garden\DailyChallengeResource\Pages;
use App\Models\DailyChallenge;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\KeyValue;

class DailyChallengeResource extends Resource
{
    protected static ?string $model = DailyChallenge::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    
    protected static ?string $navigationGroup = 'Wellness Garden';
    
    protected static ?int $navigationSort = 4;
    
    protected static ?string $navigationLabel = 'Daily Challenges';
    
    protected static ?string $modelLabel = 'Daily Challenge';
    
    protected static ?string $pluralModelLabel = 'Daily Challenges';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Challenge Information')
                    ->description('Define challenge details and type')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Challenge Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., Morning Warrior, Plant Care Expert'),
                            
                        Forms\Components\TextInput::make('name_th')
                            ->label('Thai Name')
                            ->maxLength(255)
                            ->placeholder('e.g., นักรบยามเช้า, ผู้เชี่ยวชาญดูแลพืช'),
                            
                        Forms\Components\Select::make('challenge_type')
                            ->label('Challenge Type')
                            ->required()
                            ->options([
                                'daily' => 'Daily Challenge',
                                'weekly' => 'Weekly Challenge',
                                'special' => 'Special Event',
                                'community' => 'Community Goal',
                            ])
                            ->default('daily'),
                            
                        Forms\Components\Select::make('category')
                            ->label('Category')
                            ->required()
                            ->options([
                                'garden_care' => '🌱 Garden Care',
                                'learning' => '📚 Learning',
                                'fitness' => '🌸 Fitness',
                                'mental' => '🧘 Mental Health',
                                'social' => '👥 Social',
                                'mixed' => '🎯 Mixed Goals',
                            ])
                            ->default('garden_care'),
                            
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->required()
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull()
                            ->placeholder('Describe what users need to do'),
                    ])->columns(2),
                    
                Section::make('Requirements & Targets')
                    ->description('Set challenge requirements and goals')
                    ->schema([
                        KeyValue::make('requirements')
                            ->label('Challenge Requirements')
                            ->keyLabel('Requirement')
                            ->valueLabel('Target Value')
                            ->default([
                                'water_plants' => '3',
                                'complete_lessons' => '1',
                            ])
                            ->helperText('Define what users need to complete')
                            ->columnSpanFull(),
                            
                        Forms\Components\TextInput::make('target_value')
                            ->label('Target Value')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1000)
                            ->default(1)
                            ->required()
                            ->helperText('Main target to complete'),
                            
                        Forms\Components\Select::make('difficulty')
                            ->label('Difficulty')
                            ->required()
                            ->options([
                                'easy' => '⭐ Easy',
                                'medium' => '⭐⭐ Medium',
                                'hard' => '⭐⭐⭐ Hard',
                                'expert' => '⭐⭐⭐⭐ Expert',
                            ])
                            ->default('easy'),
                            
                        Forms\Components\TextInput::make('time_limit')
                            ->label('Time Limit (hours)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(168)
                            ->default(24)
                            ->helperText('Hours to complete (24 for daily)'),
                    ])->columns(3),
                    
                Section::make('Rewards')
                    ->description('Configure rewards for completing the challenge')
                    ->schema([
                        Forms\Components\TextInput::make('xp_reward')
                            ->label('XP Reward')
                            ->numeric()
                            ->minValue(10)
                            ->maxValue(5000)
                            ->default(50)
                            ->required(),
                            
                        Forms\Components\TextInput::make('star_seeds_reward')
                            ->label('Star Seeds Reward')
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(1000)
                            ->default(20)
                            ->required(),
                            
                        Forms\Components\TextInput::make('bonus_multiplier')
                            ->label('Streak Bonus Multiplier')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(5)
                            ->step(0.1)
                            ->default(1.5)
                            ->helperText('Multiplier for consecutive completions'),
                            
                        KeyValue::make('special_rewards')
                            ->label('Special Rewards')
                            ->keyLabel('Reward Type')
                            ->valueLabel('Amount/ID')
                            ->default([])
                            ->helperText('Additional rewards like plants or themes'),
                    ])->columns(3),
                    
                Section::make('Availability')
                    ->description('Control when this challenge is available')
                    ->schema([
                        Forms\Components\DatePicker::make('available_date')
                            ->label('Available Date')
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->default(now())
                            ->helperText('When this challenge becomes available'),
                            
                        Forms\Components\DatePicker::make('expiry_date')
                            ->label('Expiry Date')
                            ->displayFormat('d/m/Y')
                            ->after('available_date')
                            ->helperText('When this challenge expires (optional)'),
                            
                        Forms\Components\Toggle::make('is_recurring')
                            ->label('Recurring Challenge')
                            ->helperText('Repeats daily/weekly based on type'),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Enable/disable this challenge'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Challenge')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->description),
                    
                Tables\Columns\TextColumn::make('challenge_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'daily' => 'info',
                        'weekly' => 'warning',
                        'special' => 'danger',
                        'community' => 'success',
                        default => 'gray'
                    }),
                    
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'garden_care' => '🌱 Garden',
                        'learning' => '📚 Learning',
                        'fitness' => '🌸 Fitness',
                        'mental' => '🧘 Mental',
                        'social' => '👥 Social',
                        'mixed' => '🎯 Mixed',
                        default => $state
                    }),
                    
                Tables\Columns\TextColumn::make('difficulty')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'easy' => '⭐',
                        'medium' => '⭐⭐',
                        'hard' => '⭐⭐⭐',
                        'expert' => '⭐⭐⭐⭐',
                        default => $state
                    }),
                    
                Tables\Columns\TextColumn::make('xp_reward')
                    ->label('XP')
                    ->formatStateUsing(fn ($state) => '+' . $state)
                    ->sortable()
                    ->alignCenter(),
                    
                Tables\Columns\TextColumn::make('star_seeds_reward')
                    ->label('Seeds')
                    ->formatStateUsing(fn ($state) => '⭐ ' . $state)
                    ->sortable()
                    ->alignCenter(),
                    
                Tables\Columns\TextColumn::make('available_date')
                    ->label('Available')
                    ->date('d/m')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('completions_today')
                    ->label('Completed Today')
                    ->getStateUsing(fn ($record) => 
                        $record->userProgress()
                            ->where('is_completed', true)
                            ->whereDate('completed_at', today())
                            ->count() . ' users'
                    ),
                    
                Tables\Columns\IconColumn::make('is_recurring')
                    ->label('Recurring')
                    ->boolean(),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('challenge_type')
                    ->options([
                        'daily' => 'Daily',
                        'weekly' => 'Weekly',
                        'special' => 'Special',
                        'community' => 'Community',
                    ]),
                    
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'garden_care' => 'Garden Care',
                        'learning' => 'Learning',
                        'fitness' => 'Fitness',
                        'mental' => 'Mental Health',
                        'social' => 'Social',
                        'mixed' => 'Mixed',
                    ]),
                    
                Tables\Filters\Filter::make('available_today')
                    ->label('Available Today')
                    ->query(fn ($query) => $query->whereDate('available_date', today())),
                    
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function ($record) {
                        $newChallenge = $record->replicate();
                        $newChallenge->name = $record->name . ' (Copy)';
                        $newChallenge->available_date = now()->addDay();
                        $newChallenge->save();
                    })
                    ->successNotificationTitle('Challenge duplicated'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('available_date', 'desc');
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
            'index' => Pages\ListDailyChallenges::route('/'),
            'create' => Pages\CreateDailyChallenge::route('/create'),
            'edit' => Pages\EditDailyChallenge::route('/{record}/edit'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        $todayCount = static::getModel()::whereDate('available_date', today())
            ->where('is_active', true)
            ->count();
        return $todayCount > 0 ? $todayCount : null;
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }
}