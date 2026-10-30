<?php

declare(strict_types=1);

/*
 * Bootstrap of the WithoutAtrium suite (phpunit.without-atrium.xml).
 *
 * Loads Composer's autoloader, then drops the `RefactorCircus\Atrium\` PSR-4
 * prefix from every registered class loader, so no Atrium class can load
 * while Keen boots. Atrium is still installed as a dev dependency, so this
 * in-process proof cannot see classmap or Composer `files` leaks: the
 * `without-atrium` CI job, which uninstalls Atrium, is the authoritative one.
 */

use Composer\Autoload\ClassLoader;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$prefix = 'RefactorCircus\\Atrium\\';

foreach (ClassLoader::getRegisteredLoaders() as $loader) {
    $mapped = array_filter(array_keys($loader->getClassMap()), fn (string $class): bool => str_starts_with($class, $prefix));

    // Loaders that know nothing of Atrium (a tool's phar, such as Herd's
    // dump.phar prepended to every PHP run) can never load it: skip them.
    if (! array_key_exists($prefix, $loader->getPrefixesPsr4()) && $mapped === []) {
        continue;
    }

    if ($loader->isClassMapAuthoritative() || $loader->getApcuPrefix() !== null || $mapped !== []) {
        throw new RuntimeException('The WithoutAtrium suite cannot run with an optimized, authoritative or APCu classmap: run `composer dump-autoload` first.');
    }

    (function () use ($prefix): void {
        unset($this->prefixDirsPsr4[$prefix], $this->prefixLengthsPsr4[$prefix[0]][$prefix]);
    })->call($loader);

    if ($loader->findFile($prefix.'Atrium') !== false) {
        throw new RuntimeException('The WithoutAtrium suite could not unload Atrium: RefactorCircus\\Atrium\\Atrium still resolves.');
    }
}
