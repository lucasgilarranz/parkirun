<?php

namespace App\Filament\Resources\Goals\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GoalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Runner')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('open_season_target_km')
                    ->label('Open target (km)')
                    ->sortable(),
                TextColumn::make('closed_season_target_km')
                    ->label('Closed target (km)')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
