<?php

namespace App\Support\Tenant;

class TenantContext
{
    private ?string $tenantId = null;

    public function setTenantId(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function tenantId(): ?string
    {
        return $this->tenantId;
    }

    public function hasTenant(): bool
    {
        return $this->tenantId !== null && $this->tenantId !== '';
    }

    public function setWorkshopId(string $workshopId): void
    {
        $this->setTenantId($workshopId);
    }

    public function workshopId(): ?string
    {
        return $this->tenantId();
    }

    public function hasWorkshop(): bool
    {
        return $this->hasTenant();
    }
}
