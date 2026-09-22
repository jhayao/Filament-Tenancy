<?php

namespace Liern\FilamentTenancy\Tests;

use Filament\Notifications\Livewire\DatabaseNotifications;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Liern\FilamentTenancy\Teams\Teams;
use PHPUnit\Framework\Attributes\DataProvider;

class NotificationSchemaTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('teams.enabled', true);
    }

    protected function creation(): Migration
    {
        return require __DIR__.'/../database/optional-migrations/2026_01_05_000001_create_team_notifications.php';
    }

    protected function repair(): Migration
    {
        return require __DIR__.'/../database/optional-migrations/2026_09_22_000000_fix_postgres_notification_data_type.php';
    }

    protected function requirePostgres(): void
    {
        if (DB::connection('central')->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL conversion is exercised by the existing pgsql CI matrix.');
        }
    }

    protected function createExistingTable(string $type = 'text'): void
    {
        DB::connection('central')->getSchemaBuilder()->create('notifications', function (Blueprint $table) use ($type) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->string('notifiable_id');
            $table->{$type}('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    protected function seedNotifications(): array
    {
        $user = $this->user();
        $other = $this->user('other@example.test');
        $rows = [];
        foreach ([[$user, 'filament', null], [$user, 'filament', '2026-01-02 00:00:00'], [$user, 'other', null], [$other, 'filament', null]] as [$recipient, $format, $readAt]) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'type' => 'test-notification',
                'notifiable_type' => $recipient->getMorphClass(),
                'notifiable_id' => (string) $recipient->getKey(),
                'data' => json_encode(['format' => $format, 'title' => 'Invitation', 'body' => 'Hello 日本語', 'actions' => []], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'read_at' => $readAt,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-02 00:00:00',
            ];
        }
        DB::connection('central')->table('notifications')->insert($rows);
        app(Teams::class)->registerNotificationRelation();
        $this->actingAs($user, 'web');

        return $rows;
    }

    protected function assertFilamentQueries(array $rows): void
    {
        $component = new DatabaseNotifications;
        $this->assertSame(1, $component->getUnreadNotificationsCount());
        $this->assertEqualsCanonicalizing(
            [$rows[0]['id'], $rows[1]['id']],
            $component->getNotificationsQuery()->pluck('id')->all(),
        );
        $component->markNotificationAsRead($rows[0]['id']);
        $this->assertSame(0, $component->getUnreadNotificationsCount());
    }

    public function test_fresh_notifications_support_filament_unread_and_list_queries(): void
    {
        $this->creation()->up();
        $schema = DB::connection('central')->getSchemaBuilder();
        $this->assertSame(DB::connection('central')->getDriverName() === 'pgsql' ? 'json' : 'text', $schema->getColumnType('notifications', 'data'));
        $this->assertFilamentQueries($this->seedNotifications());
    }

    public function test_repair_converts_existing_postgres_text_and_preserves_every_row(): void
    {
        $this->requirePostgres();
        $this->createExistingTable();
        $rows = $this->seedNotifications();
        $before = DB::connection('central')->table('notifications')->orderBy('id')->get()->toArray();
        try {
            (new DatabaseNotifications)->getUnreadNotificationsCount();
            $this->fail('The legacy PostgreSQL text column should reject the JSON operator.');
        } catch (QueryException $exception) {
            $this->assertSame('42883', $exception->errorInfo[0]);
        }

        $this->repair()->up();
        $this->assertSame('json', DB::connection('central')->getSchemaBuilder()->getColumnType('notifications', 'data'));
        $this->assertEquals($before, DB::connection('central')->table('notifications')->orderBy('id')->get()->toArray());
        $this->repair()->up();
        $this->repair()->down();
        $this->assertSame('json', DB::connection('central')->getSchemaBuilder()->getColumnType('notifications', 'data'));
        $this->assertEquals($before, DB::connection('central')->table('notifications')->orderBy('id')->get()->toArray());
        $this->assertFilamentQueries($rows);
    }

    public static function jsonTypes(): array
    {
        return [['json'], ['jsonb']];
    }

    #[DataProvider('jsonTypes')]
    public function test_existing_json_columns_are_unchanged(string $type): void
    {
        $this->requirePostgres();
        $this->createExistingTable($type);
        $rows = $this->seedNotifications();
        $before = DB::connection('central')->table('notifications')->orderBy('id')->get()->toArray();
        $this->creation()->up();
        $this->repair()->up();
        $this->repair()->down();
        $this->assertSame($type, DB::connection('central')->getSchemaBuilder()->getColumnType('notifications', 'data'));
        $this->assertEquals($before, DB::connection('central')->table('notifications')->orderBy('id')->get()->toArray());
        $this->assertFilamentQueries($rows);
    }

    public function test_invalid_json_aborts_conversion_without_modifying_schema_or_rows(): void
    {
        $this->requirePostgres();
        $this->createExistingTable();
        $rows = $this->seedNotifications();
        DB::connection('central')->table('notifications')->where('id', $rows[1]['id'])->update(['data' => 'invalid JSON']);
        $before = DB::connection('central')->table('notifications')->orderBy('id')->get()->toArray();
        try {
            $this->repair()->up();
            $this->fail('Invalid JSON should abort conversion.');
        } catch (QueryException $exception) {
            $this->assertSame('22P02', $exception->errorInfo[0]);
        }
        $this->assertSame('text', DB::connection('central')->getSchemaBuilder()->getColumnType('notifications', 'data'));
        $this->assertEquals($before, DB::connection('central')->table('notifications')->orderBy('id')->get()->toArray());

        DB::connection('central')->table('notifications')->where('id', $rows[1]['id'])->update(['data' => $rows[1]['data']]);
        $this->repair()->up();
        $this->assertFilamentQueries($rows);
    }

    public function test_repair_skips_missing_tables_and_columns(): void
    {
        $schema = DB::connection('central')->getSchemaBuilder();
        $this->repair()->up();
        $this->assertFalse($schema->hasTable('notifications'));
        $schema->create('notifications', fn (Blueprint $table) => $table->id());
        $this->repair()->up();
        $this->assertFalse($schema->hasColumn('notifications', 'data'));
    }

    public function test_other_databases_keep_their_existing_text_payloads(): void
    {
        if (DB::connection('central')->getDriverName() === 'pgsql') {
            $this->markTestSkipped('This scenario covers the non-PostgreSQL drivers.');
        }
        $this->createExistingTable();
        $rows = $this->seedNotifications();
        $before = DB::connection('central')->table('notifications')->orderBy('id')->get()->toArray();
        $this->repair()->up();
        $this->assertSame('text', DB::connection('central')->getSchemaBuilder()->getColumnType('notifications', 'data'));
        $this->assertEquals($before, DB::connection('central')->table('notifications')->orderBy('id')->get()->toArray());
        $this->assertFilamentQueries($rows);
    }

    public function test_migrations_use_the_configured_connection_instead_of_the_default(): void
    {
        $default = DB::getDefaultConnection();
        config(['teams.connection' => 'central', 'filament-tenancy.central_connection' => 'unconfigured']);
        DB::setDefaultConnection('unconfigured');
        try {
            $this->assertSame('central', $this->creation()->getConnection());
            $this->assertSame('central', $this->repair()->getConnection());
            $this->creation()->up();
            $this->repair()->up();
            $this->assertTrue(DB::connection('central')->getSchemaBuilder()->hasTable('notifications'));
        } finally {
            DB::setDefaultConnection($default);
            config(['filament-tenancy.central_connection' => 'central']);
        }
    }

    public function test_upgrade_publish_tag_contains_only_the_repair_migration(): void
    {
        $paths = ServiceProvider::pathsToPublish(null, 'filament-tenancy-notifications-upgrade');
        $this->assertCount(1, $paths);
        $this->assertSame('2026_09_22_000000_fix_postgres_notification_data_type.php', basename(array_key_first($paths)));
        $this->assertFileExists(array_key_first($paths));
    }
}
