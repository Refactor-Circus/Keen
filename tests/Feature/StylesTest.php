<?php

declare(strict_types=1);

use JayI\Atrium\Testing\AtriumStyles;

it('uses only atrium components and styles', function (): void {
    $views = dirname(__DIR__, 2).'/resources/views';

    expect(AtriumStyles::missingClasses($views))->toBe([])
        ->and(AtriumStyles::inlineStyles($views))->toBe([]);
});
