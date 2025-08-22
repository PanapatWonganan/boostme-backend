<?php

namespace App\Filament\Resources\CourseResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LessonsRelationManager extends RelationManager
{
    protected static string $relationship = 'lessons';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->rows(3),
                Forms\Components\TextInput::make('duration_minutes')
                    ->label('Duration (minutes)')
                    ->numeric()
                    ->minValue(1)
                    ->default(15)
                    ->required(),
                Forms\Components\TextInput::make('order_index')
                    ->label('Lesson Order')
                    ->numeric()
                    ->minValue(1)
                    ->default(function () {
                        $courseId = $this->getOwnerRecord()->id;
                        return \App\Models\Lesson::where('course_id', $courseId)->max('order_index') + 1;
                    }),
                Forms\Components\TextInput::make('video_url')
                    ->label('Video URL (Optional)')
                    ->url(),
                Forms\Components\Toggle::make('is_free')
                    ->label('Free Preview'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('order_index')
                    ->label('#')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('title')
                    ->label('Lesson Title')
                    ->searchable()
                    ->wrap()
                    ->description(fn ($record) => $record->description ? \Str::limit($record->description, 50) : null),
                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('Duration')
                    ->formatStateUsing(fn ($state) => $state . ' min')
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_free')
                    ->label('Free')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye')
                    ->falseIcon('heroicon-o-lock-closed')
                    ->trueColor('success')
                    ->falseColor('warning'),
                Tables\Columns\TextColumn::make('video_status')
                    ->label('Video')
                    ->getStateUsing(function ($record) {
                        if ($record->video_url) return 'URL';
                        if ($record->primaryVideo) return 'Uploaded';
                        return 'None';
                    })
                    ->badge()
                    ->color(function ($state) {
                        return match($state) {
                            'URL', 'Uploaded' => 'success',
                            default => 'gray'
                        };
                    }),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_free')
                    ->label('Free Preview'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['course_id'] = $this->getOwnerRecord()->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('order_index', 'asc')
            ->reorderable('order_index');
    }
}
