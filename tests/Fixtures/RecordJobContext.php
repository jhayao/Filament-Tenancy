<?php

namespace Liern\FilamentTenancy\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RecordJobContext implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public string $label, public bool $fail = false) {}

    public function handle(): void
    {
        DB::connection('central')->table('job_contexts')->insert([
            'label' => $this->label,
            'tenant_id' => tenant()?->getTenantKey(),
            'connection' => DB::getDefaultConnection(),
        ]);
        if (tenant()) {
            DB::table('notes')->insert(['body' => $this->label]);
        }
        if ($this->fail) {
            throw new RuntimeException('Intentional worker failure');
        }
    }
}
