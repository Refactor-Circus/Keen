<?php

declare(strict_types=1);

dataset('documents', [
    'the README' => ['README.md'],
    'the Boost skill' => ['resources/boost/skills/keen-development/SKILL.md'],
]);

it('documents none of atrium\'s removed history names', function (string $path): void {
    $contents = (string) file_get_contents(dirname(__DIR__, 2).'/'.$path);

    expect($contents)->not->toBe('')
        ->not->toContain('x-atrium::audit')
        ->not->toContain('atrium.history.show');
})->with('documents');
