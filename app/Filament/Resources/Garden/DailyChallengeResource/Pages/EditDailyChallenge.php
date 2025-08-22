<?php

namespace App\Filament\Resources\Garden\DailyChallengeResource\Pages;

use App\Filament\Resources\Garden\DailyChallengeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDailyChallenge extends EditRecord
{
    protected static string $resource = DailyChallengeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Daily challenge updated successfully';
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}