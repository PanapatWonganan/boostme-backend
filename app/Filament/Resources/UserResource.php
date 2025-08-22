<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'ผู้ใช้งาน';

    protected static ?string $modelLabel = 'ผู้ใช้';

    protected static ?string $pluralModelLabel = 'ผู้ใช้งาน';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('ข้อมูลผู้ใช้')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->label('อีเมล')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('password_hash')
                            ->label('รหัสผ่าน')
                            ->password()
                            ->required(fn ($context) => $context === 'create')
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? password_hash($state, PASSWORD_DEFAULT) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->maxLength(255),
                        Forms\Components\TextInput::make('full_name')
                            ->label('ชื่อเต็ม')
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('avatar_url')
                            ->label('รูปโปรไฟล์')
                            ->image()
                            ->directory('avatars'),
                        Forms\Components\Select::make('role')
                            ->label('บทบาท')
                            ->options([
                                'student' => 'ผู้เรียน',
                                'instructor' => 'ผู้สอน',
                                'admin' => 'ผู้ดูแลระบบ',
                            ])
                            ->required()
                            ->default('student'),
                        Forms\Components\Toggle::make('email_verified')
                            ->label('ยืนยันอีเมลแล้ว'),
                        Forms\Components\TextInput::make('phone')
                            ->label('เบอร์โทรศัพท์')
                            ->tel()
                            ->maxLength(20),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_url')
                    ->label('รูปโปรไฟล์')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-avatar.png')),
                Tables\Columns\TextColumn::make('full_name')
                    ->label('ชื่อเต็ม')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('อีเมล')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\BadgeColumn::make('role')
                    ->label('บทบาท')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'student' => 'ผู้เรียน',
                        'instructor' => 'ผู้สอน',
                        'admin' => 'ผู้ดูแลระบบ',
                        default => $state,
                    })
                    ->colors([
                        'success' => 'student',
                        'warning' => 'instructor',
                        'danger' => 'admin',
                    ]),
                Tables\Columns\IconColumn::make('email_verified')
                    ->label('ยืนยันอีเมล')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('เบอร์โทร')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('วันที่สร้าง')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('บทบาท')
                    ->options([
                        'student' => 'ผู้เรียน',
                        'instructor' => 'ผู้สอน',
                        'admin' => 'ผู้ดูแลระบบ',
                    ]),
                Tables\Filters\TernaryFilter::make('email_verified')
                    ->label('ยืนยันอีเมล'),
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
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
