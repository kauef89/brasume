<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployee extends CreateRecord
{
    use \App\Filament\Traits\RedirectsToIndex;

    protected static string $resource = EmployeeResource::class;
}