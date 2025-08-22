<?php

namespace App\Filament\Resources\Garden\PlantTypeResource\Pages;

use App\Filament\Resources\Garden\PlantTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePlantType extends CreateRecord
{
    protected static string $resource = PlantTypeResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Plant type created successfully';
    }
}