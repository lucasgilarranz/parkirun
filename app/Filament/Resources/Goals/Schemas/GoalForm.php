<?php

namespace App\Filament\Resources\Goals\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GoalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Runner')
                    ->options(User::query()->orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                TextInput::make('open_season_target_km')
                    ->label('Open season target (km)')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                TextInput::make('closed_season_target_km')
                    ->label('Closed season target (km)')
                    ->numeric()
                    ->required()
                    ->minValue(0),
            ]);
    }
}
