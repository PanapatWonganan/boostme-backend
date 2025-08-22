<?php

namespace App\Filament\Resources\Garden\PlantTypeResource\Pages;

use App\Filament\Resources\Garden\PlantTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlantType extends EditRecord
{
    protected static string $resource = PlantTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Plant type updated successfully';
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}