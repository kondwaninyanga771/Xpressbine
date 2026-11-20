<?php

namespace App\Filament\Resources;

use App\Enums\VerificationStatus;
use App\Filament\Resources\ProviderProfileResource\Pages;
use App\Models\ProviderProfile;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProviderProfileResource extends Resource
{
    protected static ?string $model = ProviderProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Providers';

    protected static ?string $modelLabel = 'provider';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Textarea::make('bio')->columnSpanFull(),
            Forms\Components\TextInput::make('nrc_number')->label('NRC number')->maxLength(40),
            Forms\Components\TextInput::make('hourly_rate')->numeric()->prefix('K'),
            Forms\Components\TextInput::make('service_radius_km')->numeric()->suffix('km'),
            Forms\Components\Toggle::make('is_featured'),
            Forms\Components\Textarea::make('rejection_reason')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Name')->searchable(),
                Tables\Columns\TextColumn::make('user.area')->label('Area'),
                Tables\Columns\TextColumn::make('verification_status')->badge(),
                Tables\Columns\TextColumn::make('hourly_rate')->label('Hourly (K)'),
                Tables\Columns\TextColumn::make('rating_average')->label('Rating'),
                Tables\Columns\IconColumn::make('is_featured')->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\Action::make('verify')
                    ->visible(fn (ProviderProfile $record): bool => $record->verification_status !== VerificationStatus::Verified)
                    ->requiresConfirmation()
                    ->action(function (ProviderProfile $record): void {
                        $record->verification_status = VerificationStatus::Verified;
                        $record->verified_at = now();
                        $record->verified_by = auth()->id();
                        $record->rejection_reason = null;
                        $record->save();
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProviderProfiles::route('/'),
            'edit' => Pages\EditProviderProfile::route('/{record}/edit'),
        ];
    }
}
