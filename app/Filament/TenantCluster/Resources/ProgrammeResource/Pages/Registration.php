<?php

namespace App\Filament\TenantCluster\Resources\ProgrammeResource\Pages;

use App\Filament\Resources\ProgrammeResource\Pages\Registration as BaseRegistration;
use App\Filament\TenantCluster\Resources\ProgrammeResource;

class Registration extends BaseRegistration
{
    protected static string $resource = ProgrammeResource::class;

    /**
     * Override route mappings to use TenantCluster routes
     */
    protected function getRouteMap(): array
    {
        return [
            'edit' => 'filament.tenantCluster.resources.programmes.edit',
            'registration' => 'filament.tenantCluster.resources.programmes.registration',
        ];
    }
}
