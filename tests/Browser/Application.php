<?php

namespace Liern\FilamentTenancy\Tests\Browser;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Liern\FilamentTenancy\TenancyServiceProvider;
use Liern\FilamentTenancy\Tests\Fixtures\TestPanelProvider;
use Liern\FilamentTenancy\Tests\Fixtures\User;
use Livewire\LivewireServiceProvider;

class Application extends \Orchestra\Testbench\Foundation\Application
{
    protected function getPackageProviders($app): array
    {
        return [
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            LivewireServiceProvider::class,
            \Stancl\Tenancy\TenancyServiceProvider::class,
            TenancyServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $directory = getenv('TENANCY_BROWSER_DIRECTORY');
        if (! $directory || ! is_dir($directory)) {
            throw new \RuntimeException('Set TENANCY_BROWSER_DIRECTORY to a temporary browser-test directory.');
        }
        $app->useDatabasePath($directory);
        $app->usePublicPath($directory.'/public');
        $app['config']->set([
            'app.key' => 'base64:'.base64_encode(str_repeat('b', 32)),
            'app.url' => getenv('TENANCY_BROWSER_URL') ?: 'http://127.0.0.1:8765',
            'database.default' => 'central',
            'database.connections.central' => ['driver' => 'sqlite', 'database' => $directory.'/central.sqlite', 'prefix' => '', 'foreign_key_constraints' => true],
            'filament-tenancy.central_connection' => 'central',
            'filament-tenancy.user_model' => User::class,
            'filament-tenancy.queue_connection' => 'database',
            'filament-tenancy.migration_path' => dirname(__DIR__).'/Fixtures/migrations',
            'auth.providers.users.model' => User::class,
            'session.driver' => 'file',
            'session.files' => $directory.'/sessions',
            'cache.default' => 'file',
            'cache.stores.file.path' => $directory.'/cache',
            'cache.stores.file.lock_path' => $directory.'/cache',
            'queue.default' => 'database',
            'queue.failed.driver' => 'null',
        ]);
    }
}
