<?php

namespace App\Filament\Resources\Runs;

use App\Filament\Resources\Runs\Pages\CreateRun;
use App\Filament\Resources\Runs\Pages\EditRun;
use App\Filament\Resources\Runs\Pages\ListRuns;
use App\Filament\Resources\Runs\Schemas\RunForm;
use App\Filament\Resources\Runs\Tables\RunsTable;
use App\Models\Run;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RunResource extends Resource
{
    protected static ?string $model = Run::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return RunForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RunsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRuns::route('/'),
            'create' => CreateRun::route('/create'),
            'edit' => EditRun::route('/{record}/edit'),
        ];
    }
}
