<?php

namespace Liern\FilamentTenancy\Models;

use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Liern\FilamentTenancy\Enums\ProvisioningStatus;
use Liern\FilamentTenancy\Relations\WorkspaceUsers;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements HasName, TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $table = 'workspaces';

    protected $casts = ['status' => ProvisioningStatus::class];

    public static function getCustomColumns(): array
    {
        return ['id', 'name', 'slug', 'status', 'created_at', 'updated_at'];
    }

    public function users(): BelongsToMany
    {
        $user = $this->newRelatedInstance(config('filament-tenancy.user_model'));

        return (new WorkspaceUsers($user->newQuery(), $this, 'workspace_user', 'workspace_id', 'user_id', $this->getKeyName(), $user->getKeyName(), 'users'))
            ->withPivot('is_owner')->withTimestamps();
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }
}
