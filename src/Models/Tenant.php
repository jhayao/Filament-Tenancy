<?php

namespace Liern\FilamentTenancy\Models;

use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Liern\FilamentTenancy\Enums\ProvisioningStatus;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase, HasName
{
    use HasDatabase;

    protected $table = 'workspaces';

    protected $casts = ['status' => ProvisioningStatus::class];

    public static function getCustomColumns(): array
    {
        return ['id', 'name', 'slug', 'status', 'created_at', 'updated_at'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(config('filament-tenancy.user_model'), 'workspace_user', 'workspace_id', 'user_id')->withPivot('is_owner')->withTimestamps();
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }
}
