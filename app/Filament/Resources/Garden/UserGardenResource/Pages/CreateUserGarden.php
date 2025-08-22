<?php

namespace App\Filament\Resources\Garden\UserGardenResource\Pages;

use App\Filament\Resources\Garden\UserGardenResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUserGarden extends CreateRecord
{
    protected static string $resource = UserGardenResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'User garden created successfully';
    }
}