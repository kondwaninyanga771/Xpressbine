<?php

namespace App\Filament\Resources;

use App\Enums\DisputeStatus;
use App\Filament\Resources\DisputeResource\Pages;
use App\Models\Dispute;
use App\Services\DisputeService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DisputeResource extends Resource
{
    protected static ?string $model = Dispute::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Claims';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Textarea::make('reason')->disabled()->columnSpanFull(),
            Forms\Components\Textarea::make('provider_response')->disabled()->columnSpanFull(),
            Forms\Components\Textarea::make('admin_notes')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('booking.reference')->label('Booking'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('outcome'),
                Tables\Columns\TextColumn::make('refund_amount')->label('Refund (K)'),
                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\Action::make('resolve')
                    ->visible(fn (Dispute $record): bool => in_array($record->status, [DisputeStatus::Open, DisputeStatus::ProviderResponded], true))
                    ->form([
                        Forms\Components\Select::make('outcome')
                            ->options([
                                'full_refund' => 'Full refund',
                                'partial_refund' => 'Partial refund',
                                'release' => 'Release to provider',
                                'fix_first' => 'Ask the provider to fix it',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('amount')->numeric()->prefix('K'),
                        Forms\Components\Textarea::make('notes'),
                    ])
                    ->action(function (Dispute $record, array $data): void {
                        app(DisputeService::class)->resolve(
                            $record,
                            auth()->user(),
                            $data['outcome'],
                            isset($data['amount']) && $data['amount'] !== '' ? (string) $data['amount'] : null,
                            $data['notes'] ?? null,
                        );
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDisputes::route('/'),
        ];
    }
}
