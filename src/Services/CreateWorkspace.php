<?php

namespace Liern\FilamentTenancy\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Liern\FilamentTenancy\Enums\ProvisioningStatus;
use Liern\FilamentTenancy\Jobs\ProvisionWorkspace;
use Liern\FilamentTenancy\Models\Tenant;

class CreateWorkspace
{
    public function create(Model $owner, array $data): Tenant
    {
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(Tenant::class, 'slug')],
        ])->validate();

        return DB::connection(config('filament-tenancy.central_connection'))->transaction(function () use ($owner, $validated) {
            $tenant = Tenant::create([...$validated, 'status' => ProvisioningStatus::Pending]);
            $tenant->users()->attach($owner->getKey(), ['is_owner' => true]);
            ProvisionWorkspace::dispatch($tenant->getKey())->afterCommit();

            return $tenant;
        });
    }
}
