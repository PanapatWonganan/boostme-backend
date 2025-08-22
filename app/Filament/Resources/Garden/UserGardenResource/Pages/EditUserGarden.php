<?php

namespace App\Filament\Resources\Garden\UserGardenResource\Pages;

use App\Filament\Resources\Garden\UserGardenResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserGarden extends EditRecord
{
    protected static string $resource = UserGardenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Garden updated successfully';
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}