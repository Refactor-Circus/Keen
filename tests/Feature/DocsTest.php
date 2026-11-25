<?php

declare(strict_types=1);

function keenDocsFile(string $path): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/'.$path);
}

/**
 * The `## [Unreleased]` section of the CHANGELOG, up to the next release heading.
 */
function keenDocsUnreleased(): string
{
    $changelog = keenDocsFile('CHANGELOG.md');
    $start = strpos($changelog, '## [Unreleased]');

    expect($start)->not->toBeFalse('CHANGELOG.md has no ## [Unreleased] section');

    $section = substr($changelog, (int) $start);
    $next = strpos($section, "\n## [", 1);

    return $next === false ? $section : substr($section, 0, $next);
}

/**
 * One `### {heading}` subsection of the unreleased CHANGELOG section.
 */
function keenDocsUnreleasedSubsection(string $heading): string
{
    $section = keenDocsUnreleased();
    $start = strpos($section, "### {$heading}\n");

    expect($start)->not->toBeFalse("CHANGELOG.md has no ### {$heading} under ## [Unreleased]");

    $subsection = substr($section, (int) $start);
    $next = strpos($subsection, "\n### ", 1);

    return $next === false ? $subsection : substr($subsection, 0, $next);
}

dataset('documents', [
    'the README' => ['README.md'],
    'the Boost skill' => ['resources/boost/skills/keen-development/SKILL.md'],
    'CLAUDE.md' => ['CLAUDE.md'],
    'AGENTS.md' => ['AGENTS.md'],
]);

dataset('agent documents', [
    'the Boost skill' => ['resources/boost/skills/keen-development/SKILL.md'],
    'CLAUDE.md' => ['CLAUDE.md'],
    'AGENTS.md' => ['AGENTS.md'],
]);

it('documents none of atrium\'s removed history names', function (string $path): void {
    expect(keenDocsFile($path))->not->toBe('')
        ->not->toContain('x-atrium::audit')
        ->not->toContain('atrium.history.show');
})->with('documents');

it('documents none of the removed atrium ui', function (string $path): void {
    expect(keenDocsFile($path))->not->toBe('')
        ->not->toContain('keen.ui.enabled')
        ->not->toContain('keen.atrium')
        ->not->toContain('x-keen::')
        ->not->toContain('atrium.keen.history.show')
        ->not->toContain('registerAtriumPlugin')
        ->not->toContain('test:without-atrium')
        ->not->toContain('without-atrium')
        ->not->toContain('KeenPlugin')
        ->not->toContain('HistoryExtensions')
        ->not->toContain('History in Atrium');
})->with('documents');

it('describes keen as a json api and mcp in the readme', function (): void {
    expect(keenDocsFile('README.md'))
        ->toContain('JSON API')
        ->toContain('MCP')
        ->toContain('Atrium v2');
});

it('documents the packages endpoint in the readme', function (): void {
    expect(keenDocsFile('README.md'))
        ->toContain('GET {prefix}/packages')
        ->toContain('keen.packages.index')
        ->toContain('list-audited-packages-tool');
});

it('documents the openapi document and the typescript client in the readme', function (): void {
    expect(keenDocsFile('README.md'))
        ->toContain('## OpenAPI and TypeScript client')
        ->toContain('openapi.json')
        ->toContain('@refactor-circus/keen-client')
        ->toContain('#v2.0.0')
        ->toContain('createKeenClient({ baseUrl')
        ->toContain('client:generate');
});

it('keeps the security note on the default api in the readme', function (): void {
    expect(keenDocsFile('README.md'))
        ->toContain('`keen.authorization` is off')
        ->toContain('`keen.routes.enabled` is on');
});

it('describes the packages endpoint, the client workflow and the staleness test to agents', function (string $path): void {
    expect(keenDocsFile($path))
        ->toContain('/packages')
        ->toContain('openapi.json')
        ->toContain('client:generate')
        ->toContain('openapi.sha256');
})->with('agent documents');

it('keeps the ring master block in the agent instructions', function (string $path): void {
    expect(keenDocsFile($path))
        ->toContain('<!-- ring-master:start -->')
        ->toContain('<!-- ring-master:end -->');
})->with(['CLAUDE.md', 'AGENTS.md']);

it('has breaking, added and upgrading notes under unreleased in the changelog', function (): void {
    expect(keenDocsUnreleased())
        ->toContain("### Breaking\n")
        ->toContain("### Added\n")
        ->toContain("### Upgrading to v2.0.0\n");
});

it('lists every removed atrium surface as breaking in the changelog', function (): void {
    expect(keenDocsUnreleasedSubsection('Breaking'))
        ->toContain('atrium.keen.entries.')
        ->toContain('atrium.keen.history.show')
        ->toContain('`keen.entries`')
        ->toContain('`keen.entry`')
        ->toContain('`keen.history`')
        ->toContain('`keen.activity`')
        ->toContain('x-keen::audit-trail')
        ->toContain('x-keen::audit.entries')
        ->toContain('x-keen::audit.changes')
        ->toContain('KeenPlugin')
        ->toContain('HistoryExtensions')
        ->toContain('keen.ui.enabled')
        ->toContain('keen.atrium')
        ->toContain('keen-views')
        ->toContain('keen-lang')
        ->toContain('lang/en/keen.php')
        ->toContain('refactor-circus/atrium')
        ->toContain('test:without-atrium');
});

it('lists the packages endpoint, the tool, the openapi document and the client as added in the changelog', function (): void {
    expect(keenDocsUnreleasedSubsection('Added'))
        ->toContain('keen.packages.index')
        ->toContain('list-audited-packages-tool')
        ->toContain('openapi.json')
        ->toContain('@refactor-circus/keen-client');
});

it('points the upgrade note to atrium v2 in the changelog', function (): void {
    expect(keenDocsUnreleasedSubsection('Upgrading to v2.0.0'))
        ->toContain('Atrium v2')
        ->toContain('atrium.integrations.keen');
});
