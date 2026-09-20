<?php

namespace Liern\FilamentTenancy\Support;

use Filament\Panel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Liern\FilamentTenancy\Models\WorkspaceDomain;
use Liern\FilamentTenancy\Models\WorkspaceHandoff;
use RuntimeException;

class WorkspaceHandoffManager
{
    public function domainForHost(string $host): ?WorkspaceDomain
    {
        return WorkspaceDomain::query()
            ->where('domain', strtolower(rtrim($host, '.')))
            ->whereNotNull('verified_at')
            ->first();
    }

    public function create(WorkspaceDomain $domain, object $user, Panel $panel, Request $request): string
    {
        $token = bin2hex(random_bytes(32));

        WorkspaceHandoff::query()->create([
            'token_hash' => hash('sha256', $token),
            'workspace_id' => $domain->workspace_id,
            'user_id' => (string) $user->getAuthIdentifier(),
            'guard' => $panel->getAuthGuard(),
            'panel_id' => $panel->getId(),
            'target_host' => $domain->domain,
            'browser_hash' => $this->browserHash($request),
            'expires_at' => now()->addSeconds(60),
        ]);

        return $token;
    }

    public function consume(string $token, Request $request): WorkspaceHandoff
    {
        return DB::connection(config('filament-tenancy.central_connection'))->transaction(function () use ($token, $request): WorkspaceHandoff {
            $handoff = WorkspaceHandoff::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if ($handoff === null || $handoff->used_at !== null || $handoff->expires_at->isPast()) {
                throw new RuntimeException('Invalid workspace handoff.');
            }

            if (! hash_equals($handoff->target_host, strtolower($request->getHost()))) {
                throw new RuntimeException('Invalid workspace handoff host.');
            }

            if (! hash_equals($handoff->browser_hash, $this->browserHash($request))) {
                throw new RuntimeException('Invalid workspace handoff browser state.');
            }

            if ((string) $this->domainForHost($handoff->target_host)?->workspace_id !== (string) $handoff->workspace_id) {
                throw new RuntimeException('Workspace handoff domain is no longer active.');
            }

            $handoff->forceFill(['used_at' => now()])->save();

            return $handoff;
        });
    }

    public function browserHash(Request $request): string
    {
        return hash('sha256', $request->userAgent().'|'.$request->ip());
    }

    public function url(string $token, string $host, Request $request): string
    {
        return $request->getScheme().'://'.$host.'/lona-tenancy/handoff/'.$token;
    }
}
