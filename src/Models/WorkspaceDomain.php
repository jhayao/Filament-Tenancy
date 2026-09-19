<?php

namespace Liern\FilamentTenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Liern\FilamentTenancy\Support\TenantModel;
use Stancl\Tenancy\Contracts\Domain;

class WorkspaceDomain extends Model implements Domain
{
    protected $table = 'workspace_domains';

    protected $fillable = [
        'domain',
        'workspace_id',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(TenantModel::get(), 'workspace_id');
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getTenantKey(): string|int
    {
        return $this->workspace_id;
    }

    public function tenant()
    {
        return $this->workspace();
    }
}
