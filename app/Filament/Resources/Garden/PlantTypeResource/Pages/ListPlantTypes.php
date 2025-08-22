<?php

namespace App\Filament\Resources\Garden\PlantTypeResource\Pages;

use App\Filament\Resources\Garden\PlantTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlantTypes extends ListRecords
{
    protected static string $resource = PlantTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}