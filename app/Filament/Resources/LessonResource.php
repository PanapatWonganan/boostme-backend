<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LessonResource\Pages;
use App\Filament\Resources\LessonResource\RelationManagers;
use App\Models\Lesson;
use App\Models\Video;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class LessonResource extends Resource
{
    protected static ?string $model = Lesson::class;

    protected static ?string $navigationIcon = 'heroicon-o-play-circle';
    
    protected static ?string $navigationGroup = 'คอร์สเรียน';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Lesson Information')
                    ->schema([
                        Forms\Components\Select::make('course_id')
                            ->label('Course')
                            ->relationship('course', 'title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('title')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('description'),
                                Forms\Components\TextInput::make('price')
                                    ->numeric()
                                    ->prefix('฿')
                                    ->default(0),
                            ])
                            ->helperText('Select a course or create a new one'),
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('duration_minutes')
                            ->label('Duration (minutes)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(15)
                            ->helperText('Lesson duration in minutes'),
                        Forms\Components\TextInput::make('order_index')
                            ->label('Lesson Order')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Order of this lesson in the course (auto-calculated if empty)')
                            ->default(function (Forms\Get $get) {
                                $courseId = $get('course_id');
                                if ($courseId) {
                                    return \App\Models\Lesson::where('course_id', $courseId)->max('order_index') + 1;
                                }
                                return 1;
                            }),
                        Forms\Components\Toggle::make('is_free')
                            ->label('Free Preview')
                            ->helperText('Allow users to preview this lesson for free'),
                    ])->columns(2),
                    
                Forms\Components\Section::make('Video Content')
                    ->schema([
                        Forms\Components\FileUpload::make('video_upload')
                            ->label('Upload Video')
                            ->disk('local')
                            ->directory('temp-videos')
                            ->acceptedFileTypes(['video/mp4', 'video/mov', 'video/avi', 'video/webm'])
                            ->maxSize(2097152) // 2GB in KB
                            ->preserveFilenames()
                            ->downloadable()
                            ->previewable(false)
                            ->helperText('Max file size: 2GB. Supported formats: MP4, MOV, AVI, WebM')
                            ->afterStateUpdated(function ($state, $set, $get) {
                                if ($state) {
                                    // Auto-fill duration if possible (this would need FFmpeg)
                                    $set('video_status', 'pending');
                                }
                            })
                            ->dehydrated(false),
                            
                        Forms\Components\TextInput::make('video_url')
                            ->label('External Video URL (Optional)')
                            ->url()
                            ->maxLength(500)
                            ->helperText('YouTube, Vimeo, or other video URL'),
                            
                        Forms\Components\Placeholder::make('video_status')
                            ->label('Video Processing Status')
                            ->content(function ($record) {
                                if (!$record) return 'No video uploaded';
                                
                                $video = $record->primaryVideo;
                                if (!$video) return 'No video uploaded';
                                
                                $statusColors = [
                                    'pending' => 'text-yellow-600',
                                    'processing' => 'text-blue-600',
                                    'ready' => 'text-green-600',
                                    'failed' => 'text-red-600',
                                ];
                                
                                $color = $statusColors[$video->status] ?? 'text-gray-600';
                                $status = ucfirst($video->status);
                                
                                return new \Illuminate\Support\HtmlString(
                                    "<span class='{$color} font-semibold'>{$status}</span>"
                                );
                            })
                            ->visible(fn ($record) => $record && $record->primaryVideo),
                            
                        Forms\Components\Placeholder::make('video_info')
                            ->label('Video Information')
                            ->content(function ($record) {
                                if (!$record || !$record->primaryVideo) return '-';
                                
                                $video = $record->primaryVideo;
                                $info = [];
                                
                                if ($video->duration_seconds) {
                                    $info[] = "Duration: {$video->formatted_duration}";
                                }
                                
                                if ($video->file_size) {
                                    $info[] = "Size: {$video->formatted_size}";
                                }
                                
                                if ($video->processing_error) {
                                    $info[] = "<span class='text-red-600'>Error: {$video->processing_error}</span>";
                                }
                                
                                return new \Illuminate\Support\HtmlString(
                                    implode('<br>', $info)
                                );
                            })
                            ->visible(fn ($record) => $record && $record->primaryVideo),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('course.title')
                    ->label('Course')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('order_index')
                    ->label('Order')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('title')
                    ->label('Lesson Title')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->description(fn ($record) => $record->description ? \Str::limit($record->description, 50) : null),
                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('Duration')
                    ->formatStateUsing(fn ($state) => $state . ' min')
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_free')
                    ->label('Free Preview')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-lock-closed')
                    ->trueColor('success')
                    ->falseColor('warning'),
                Tables\Columns\TextColumn::make('video_status')
                    ->label('Video Status')
                    ->getStateUsing(function ($record) {
                        if ($record->video_url) return 'External URL';
                        if ($record->primaryVideo) {
                            return match($record->primaryVideo->status) {
                                'completed' => 'Ready',
                                'processing' => 'Processing',
                                'failed' => 'Failed',
                                default => 'Pending'
                            };
                        }
                        return 'No Video';
                    })
                    ->badge()
                    ->color(function ($state) {
                        return match($state) {
                            'Ready', 'External URL' => 'success',
                            'Processing' => 'warning',
                            'Failed' => 'danger',
                            default => 'gray'
                        };
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('course_id')
                    ->label('Course')
                    ->relationship('course', 'title')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_free')
                    ->label('Free Preview'),
                Tables\Filters\SelectFilter::make('video_status')
                    ->label('Video Status')
                    ->options([
                        'has_video' => 'Has Video',
                        'no_video' => 'No Video',
                        'external_url' => 'External URL',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] === 'has_video',
                            fn (Builder $query): Builder => $query->whereHas('videos'),
                        )
                        ->when(
                            $data['value'] === 'no_video',
                            fn (Builder $query): Builder => $query->whereDoesntHave('videos')->whereNull('video_url'),
                        )
                        ->when(
                            $data['value'] === 'external_url',
                            fn (Builder $query): Builder => $query->whereNotNull('video_url'),
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('reorder')
                    ->label('Reorder')
                    ->icon('heroicon-o-arrows-up-down')
                    ->color('info')
                    ->url(fn ($record) => static::getUrl('index') . '?course_id=' . $record->course_id)
                    ->openUrlInNewTab(false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('course_id', 'asc')
            ->defaultSort('order_index', 'asc');
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
            'index' => Pages\ListLessons::route('/'),
            'create' => Pages\CreateLesson::route('/create'),
            'edit' => Pages\EditLesson::route('/{record}/edit'),
        ];
    }
}
