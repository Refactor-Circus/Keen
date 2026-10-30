<?php

declare(strict_types=1);

use RefactorCircus\Atrium\Domains\Screens\Data\SectionDefinition;
use RefactorCircus\Atrium\Facades\Atrium;
use RefactorCircus\Atrium\Testing\ResetsScreenExtensions;

/*
 * tests/Pest.php resets Atrium's screen registry around every Feature test
 * (criterion 16, D-031): what a test registers is gone after it, and what
 * the providers registered at boot stays.
 */

/**
 * The keys of the sections on every screen's `after` slot.
 *
 * @return list<string>
 */
function resetsScreenExtensionsKeys(): array
{
    return array_map(fn (SectionDefinition $section): string => $section->key, Atrium::screens()->sections('*', 'after'));
}

it('drops what a test registered and keeps keen\'s own history section', function (): void {
    expect(class_uses_recursive($this))->toContain(ResetsScreenExtensions::class);

    $this->setUpResetsScreenExtensions();

    Atrium::screens()->section('*', 'after', SectionDefinition::make('test.probe')->view('keen::ui.history-section')->public());

    expect(resetsScreenExtensionsKeys())->toContain('test.probe')->toContain('keen.history');

    $this->tearDownResetsScreenExtensions();

    expect(resetsScreenExtensionsKeys())->not->toContain('test.probe')
        ->toContain('keen.history');
});
