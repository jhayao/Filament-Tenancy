<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Liern\FilamentTenancy\Tests\Fixtures\User;

$app = require __DIR__.'/bootstrap.php';
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
});
Schema::create('jobs', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->string('queue')->index();
    $table->longText('payload');
    $table->unsignedTinyInteger('attempts');
    $table->unsignedInteger('reserved_at')->nullable();
    $table->unsignedInteger('available_at');
    $table->unsignedInteger('created_at');
});
Artisan::call('migrate', ['--path' => dirname(__DIR__, 2).'/database/migrations', '--realpath' => true, '--force' => true]);
(require dirname(__DIR__, 2).'/database/optional-migrations/2026_01_05_000001_create_team_notifications.php')->up();
Artisan::call('filament:assets');
User::create(['name' => 'Browser Owner', 'email' => 'owner@example.test', 'password' => Hash::make('browser-password')]);
