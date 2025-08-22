<?php

namespace App\Filament\Resources\Garden\DailyChallengeResource\Pages;

use App\Filament\Resources\Garden\DailyChallengeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDailyChallenges extends ListRecords
{
    protected static string $resource = DailyChallengeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}