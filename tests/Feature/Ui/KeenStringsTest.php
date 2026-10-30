<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Symfony\Component\Finder\Finder;

/**
 * Every Blade view Keen ships, by path relative to resources/views.
 *
 * @return array<string, string>
 */
function keenViews(): array
{
    $views = [];

    foreach (Finder::create()->files()->in(dirname(__DIR__, 3).'/resources/views')->name('*.blade.php') as $file) {
        $views[$file->getRelativePathname()] = $file->getContents();
    }

    ksort($views);

    return $views;
}

it('ships the audit components', function (): void {
    expect(array_keys(keenViews()))->toContain(
        'components/audit-trail.blade.php',
        'components/audit/entries.blade.php',
        'components/audit/changes.blade.php',
    );
});

it('uses its own audit components, not atrium\'s removed ones', function (): void {
    $offending = array_keys(array_filter(keenViews(), fn (string $contents): bool => str_contains($contents, 'x-atrium::audit')));

    expect($offending)->toBe([])
        ->and(keenViews()['ui/index.blade.php'])->toMatch('/<x-keen::audit\.entries\s/')
        ->and(keenViews()['ui/show.blade.php'])->toMatch('/<x-keen::audit\.changes\s/');
});

it('uses no atrium translation key in its views', function (): void {
    $offending = array_keys(array_filter(keenViews(), fn (string $contents): bool => str_contains($contents, 'atrium::atrium.')));

    expect($offending)->toBe([]);
});

it('defines every keen string its views use', function (): void {
    /** @var array<string, mixed> $strings */
    $strings = require dirname(__DIR__, 3).'/lang/en/keen.php';

    $used = [];

    foreach (keenViews() as $contents) {
        preg_match_all('/keen::keen\.([a-z0-9_]+(?:\.[a-z0-9_]+)*)/', $contents, $matches);
        array_push($used, ...$matches[1]);
    }

    $used = array_values(array_unique($used));
    $missing = array_values(array_filter($used, fn (string $key): bool => ! Arr::has($strings, $key)));

    expect($used)->toContain(
        'history', 'empty', 'when', 'app_badge', 'view_all', 'no_changes', 'field', 'before', 'after',
        'action', 'subject', 'actor', 'surface', 'none', 'system',
    )->and($missing)->toBe([]);
});

it('defines the history strings', function (string $key, string $text): void {
    expect(trans("keen::keen.{$key}"))->toBe($text);
})->with([
    ['history', 'History'],
    ['activity', 'Activity'],
    ['history_for', ':package audit log'],
    ['empty', 'Nothing has been recorded yet.'],
    ['when', 'When'],
    ['app_badge', 'App'],
    ['view_all', 'View all'],
    ['newest', 'Newest'],
    ['older', 'Older'],
    ['no_changes', 'No fields changed.'],
    ['field', 'Field'],
    ['before', 'Before'],
    ['after', 'After'],
]);
