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
        'verification_token',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function getConnectionName()
    {
        return config('filament-tenancy.central_connection');
    }

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

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function verificationRecordName(): string
    {
        return config('filament-tenancy.custom_domains.verification_prefix', '_lona-verify.') . $this->domain;
    }

    public function verificationRecordValue(): string
    {
        return (string) $this->verification_token;
    }
}
