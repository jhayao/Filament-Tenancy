<?php

namespace Liern\FilamentTenancy\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Liern\FilamentTenancy\Enums\ProvisioningStatus;
use Liern\FilamentTenancy\Jobs\ProvisionWorkspace;
use Liern\FilamentTenancy\Models\Tenant;
use Liern\FilamentTenancy\Support\DatabasePoolAllocator;
use Liern\FilamentTenancy\Support\TenantModel;
use Liern\FilamentTenancy\Teams\Teams;

class CreateWorkspace
{
    public function create(Model $owner, array $data): Tenant
    {
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(TenantModel::get(), 'slug')],
        ])->validate();

        return DB::connection(config('filament-tenancy.central_connection'))->transaction(function () use ($owner, $validated) {
            $extraData = [];

            if ($connection = app(DatabasePoolAllocator::class)->allocate()) {
                $extraData['tenancy_db_connection'] = $connection;
            }

            $tenant = TenantModel::get()::create([...$validated, ...$extraData, 'status' => ProvisioningStatus::Pending]);
            if (config('teams.enabled')) {
                app(Teams::class)->initializeOwner($tenant, $owner);
            } else {
                $tenant->users()->attach($owner->getKey(), ['is_owner' => true]);
            }
            ProvisionWorkspace::dispatch($tenant->getKey())->afterCommit();

            return $tenant;
        });
    }
}
