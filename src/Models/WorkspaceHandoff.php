<?php

namespace Liern\FilamentTenancy\Models;

use Illuminate\Database\Eloquent\Model;
use Liern\FilamentTenancy\Support\TenantModel;

class WorkspaceHandoff extends Model
{
    protected $table = 'workspace_handoffs';

    protected $fillable = [
        'token_hash',
        'workspace_id',
        'user_id',
        'guard',
        'panel_id',
        'target_host',
        'browser_hash',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function getConnectionName()
    {
        return config('filament-tenancy.central_connection');
    }

    public function workspace()
    {
        return $this->belongsTo(TenantModel::get(), 'workspace_id');
    }
}
