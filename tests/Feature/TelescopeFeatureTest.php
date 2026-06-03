<?php

use App\Providers\TelescopeServiceProvider;

test('telescope phase 4 config uses expected defaults', function () {
    $telescopeEnabled = filter_var((string) config('telescope.enabled'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;

    expect($telescopeEnabled)->toBeFalse()
        ->and(config('telescope.path'))->toBe('telescope')
        ->and(config('telescope.driver'))->toBe('database')
        ->and(config('telescope.watchers.Laravel\\Telescope\\Watchers\\QueryWatcher.slow'))->toBe(100)
        ->and(config('telescope.watchers.Laravel\\Telescope\\Watchers\\LogWatcher.level'))->toBe('error');
});

test('telescope application provider is registered', function () {
    expect(array_key_exists(TelescopeServiceProvider::class, app()->getLoadedProviders()))->toBeTrue();
});

test('telescope migration scaffold exists', function () {
    expect(glob(database_path('migrations/*_create_telescope_entries_table.php')))->not->toBeEmpty();
});
