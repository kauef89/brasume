<?php

namespace App\Filament\Resources\CompliancePermits\Pages;

use App\Filament\Resources\CompliancePermits\CompliancePermitResource;
use App\Models\CompliancePermit;
use Carbon\Carbon;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCompliancePermits extends ListRecords
{
    protected static string $resource = CompliancePermitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nova Licença / Alvará'),
        ];
    }

    public function getTabs(): array
    {
        $today = Carbon::today();

        return [
            'todos' => Tab::make('Todos')
                ->badge(CompliancePermit::where('is_active', true)->count()),

            'a_vencer' => Tab::make('A Vencer (30 dias)')
                ->icon('heroicon-m-exclamation-triangle')
                ->badge(
                    CompliancePermit::where('is_active', true)
                        ->whereDate('expires_at', '>=', $today)
                        ->whereDate('expires_at', '<=', $today->copy()->addDays(30))
                        ->count()
                )
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereDate('expires_at', '>=', $today)
                          ->whereDate('expires_at', '<=', $today->copy()->addDays(30))
                ),

            'vencidos' => Tab::make('Vencidos')
                ->icon('heroicon-m-x-circle')
                ->badge(
                    CompliancePermit::where('is_active', true)
                        ->whereDate('expires_at', '<', $today)
                        ->count()
                )
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('expires_at', '<', $today)),

            'vigentes' => Tab::make('Vigentes')
                ->icon('heroicon-m-check-circle')
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('expires_at', '>', $today->copy()->addDays(30))),
        ];
    }
}