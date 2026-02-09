<?php

namespace App\Filament\Resources\Runs\Schemas;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RunForm
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
                DatePicker::make('date')
                    ->required(),
                TextInput::make('distance_km')
                    ->numeric()
                    ->required()
                    ->minValue(0),
            ]);
    }
}
