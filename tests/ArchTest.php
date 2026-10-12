<?php

declare(strict_types=1);

use RefactorCircus\Keen\Support\Tables;
use RefactorCircus\Keystone\Testing\Arch;

const KEEN_DOMAINS = __DIR__.'/../src/Domains';

arch()->preset()->php();

arch()->preset()->security();

arch('it will not use dd(), ddd(), env(), or exit()')
    ->expect(['dd', 'ddd', 'env', 'exit'])
    ->each->not->toBeUsed();

arch('the package source declares strict types')
    ->expect('RefactorCircus\Keen')
    ->toUseStrictTypes();

arch('actions are final classes')
    ->expect(Arch::domainNamespaces('RefactorCircus\Keen', KEEN_DOMAINS, 'Actions'))
    ->classes()
    ->toBeFinal();

arch('actions take their input as Data objects, not arrays')
    ->expect(fn (): array => array_values(array_filter(
        array_map(function (string $action): ?string {
            // Keen's actions extend Keystone's Action, whose `execute()`
            // forwards to the action's own `handle()`.
            foreach ((new ReflectionMethod($action, 'handle'))->getParameters() as $parameter) {
                $type = $parameter->getType();

                if ($type instanceof ReflectionNamedType && $type->getName() === 'array' && in_array($parameter->getName(), ['data', 'filters', 'input', 'attributes', 'values'], true)) {
                    return $action.'::handle($'.$parameter->getName().')';
                }
            }

            return null;
        }, Arch::domainClasses('RefactorCircus\Keen', KEEN_DOMAINS, 'Actions')),
    )))
    ->toBeEmpty();

arch('action input validates itself')
    ->expect(fn (): array => Arch::dataWithoutRules(Arch::domainClasses('RefactorCircus\Keen', KEEN_DOMAINS, 'Data')))
    ->toBeEmpty();

arch('every action is reachable from the HTTP API, MCP and a UI')
    ->expect(fn (): array => parityGaps())
    ->toBeEmpty();

arch('every action dispatches a starting and a finished event')
    ->expect(fn (): array => Arch::actionEventGaps(KEEN_DOMAINS.'/*/Actions/*.php'))
    ->toBeEmpty();

arch('actions write inside a transaction')
    ->expect(fn (): array => Arch::writesOutsideTransactions(KEEN_DOMAINS.'/*/Actions/*.php'))
    ->toBeEmpty();

arch('every model fires an event for every lifecycle hook')
    ->expect(fn (): array => Arch::modelEventGaps(Arch::domainClasses('RefactorCircus\\Keen', KEEN_DOMAINS, 'Models')))
    ->toBeEmpty();

arch('requests validate once, with the Data rules, and build from what they validated')
    ->expect(fn (): array => array_values(array_filter(
        [...(array) glob(KEEN_DOMAINS.'/*/Http/Requests/*.php'), ...(array) glob(KEEN_DOMAINS.'/*/Mcp/Requests/*.php')],
        fn (string $path): bool => str_contains((string) file_get_contents($path), 'Data::from('),
    )))
    ->toBeEmpty();

arch('every model has a policy in keen.policies')
    ->expect(fn (): array => Arch::modelsWithoutPolicies(
        Arch::domainClasses('RefactorCircus\\Keen', KEEN_DOMAINS, 'Models'),
        (require __DIR__.'/../config/keen.php')['policies'],
    ))
    ->toBeEmpty();

arch('tables are named through Tables, never as literals')
    ->expect(fn (): array => Arch::literalTableNames(
        [...array_values(array_filter(
            array_map(fn (SplFileInfo $file): string => $file->getPathname(), iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../src', FilesystemIterator::SKIP_DOTS)))),
            fn (string $path): bool => str_ends_with($path, '.php') && ! str_ends_with($path, 'Support/Tables.php'),
        )), ...(array) glob(__DIR__.'/../database/migrations/*.php')],
        array_values(Tables::DEFAULTS),
    ))
    ->toBeEmpty();
