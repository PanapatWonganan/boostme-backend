<?php

namespace App\Filament\Resources\Garden\DailyChallengeResource\Pages;

use App\Filament\Resources\Garden\DailyChallengeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDailyChallenge extends CreateRecord
{
    protected static string $resource = DailyChallengeResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Daily challenge created successfully';
    }
}