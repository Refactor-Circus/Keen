<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Atrium;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RefactorCircus\Atrium\Atrium;
use RefactorCircus\Atrium\Domains\Screens\Data\Screen;
use RefactorCircus\Atrium\Domains\Screens\Data\SectionDefinition;
use RefactorCircus\Atrium\Domains\Screens\Data\TabDefinition;
use RefactorCircus\Keystone\Audit\History;
use RefactorCircus\Keystone\Packages\PackageRegistry;

/**
 * The History section and the Activity tabs Keen adds to other packages'
 * Atrium screens, from `keen.atrium.history` alone: no host declares an
 * audit concept or names Keen.
 *
 * The section shows a detail screen's record history, or a list screen's
 * package history; a tab shows what was recorded in the screen's record, or
 * its own history. Either shows only while the screen's package audits and
 * its history rule lets the viewer read it.
 */
final class HistoryExtensions
{
    public const string SECTION = 'keen.history';

    public const string TAB = 'keen.activity';

    /**
     * Every screen of one package, as Atrium's registry accepts it.
     */
    private const string PACKAGE_TARGET = '/^[a-z0-9-]+\.\*$/D';

    /**
     * Screens the section never shows on while `exclude` is not set.
     */
    private const array EXCLUDE = ['keen.*', 'atrium.*'];

    /**
     * Register on Atrium's screen registry, unless the installed Atrium
     * predates it.
     *
     * @param  class-string  $atrium
     */
    public static function registerIfSupported(string $atrium = Atrium::class): void
    {
        if (! method_exists($atrium, 'screens')) {
            return;
        }

        self::register();
    }

    /**
     * Register the section on each configured screen target, in the
     * configured slot, and a tab on each configured tab target. A bad target
     * or slot throws in local and testing, and is reported and skipped
     * elsewhere.
     */
    public static function register(): void
    {
        $screens = app(Atrium::class)->screens();
        $targets = self::targets();
        $slot = self::slot();

        if ($slot !== null) {
            $section = SectionDefinition::make(self::SECTION)
                ->label(self::label('keen::keen.history'))
                ->view('keen::ui.history-section')
                ->authorize(fn (Request $request, ?Model $record, Screen $screen): bool => self::visible($request, self::subject($record, $screen), $screen, true))
                ->resolve(fn (?Model $record, Screen $screen): array => ['source' => $screen->package, 'subject' => self::subject($record, $screen)]);

            foreach ($targets as $target) {
                $screens->section($target, $slot, $section);
            }
        }

        foreach (self::tabs() as $target => $scope) {
            $screens->tab($target, TabDefinition::make(self::TAB)
                ->label(self::label('keen::keen.activity'))
                ->icon('clipboard-document-list')
                ->view('keen::ui.activity-tab')
                // Scoped, the tab asks the history rule about the package as
                // a whole; the host's own check guards the record.
                ->authorize(fn (Request $request, ?Model $record, Screen $screen): bool => self::visible($request, $scope ? null : $record, $screen, false))
                ->resolve(fn (?Model $record, Screen $screen): array => [
                    'source' => $screen->package,
                    'scope' => $scope ? $record : null,
                    'subject' => $scope ? null : $record,
                ]));
        }
    }

    /**
     * Whether the viewer may see Keen's history on this screen, about this
     * subject (the package's whole history without one). It answers at the
     * first refusal, cheapest first: an excluded screen key, an unknown or
     * non-auditing package, the package's history rule, and a subject that
     * is not a stored model.
     */
    private static function visible(Request $request, ?Model $subject, Screen $screen, bool $checkExclude): bool
    {
        if ($checkExclude && Str::is(self::strings(config('keen.atrium.history.exclude', self::EXCLUDE)), $screen->key)) {
            return false;
        }

        $package = app(PackageRegistry::class)->find($screen->package);

        if ($package === null || ! $package->audits()) {
            return false;
        }

        $input = $subject === null ? [] : ['subject_type' => $subject->getMorphClass(), 'subject_id' => (string) $subject->getKey()];

        if (! app(History::class)->allows($package, $request->user(), $input)) {
            return false;
        }

        // A record without a key has no history to show.
        return $screen->kind !== Screen::DETAIL || $subject === null || is_int($subject->getKey()) || is_string($subject->getKey());
    }

    /**
     * A detail screen's record; a list screen has no subject.
     */
    private static function subject(?Model $record, Screen $screen): ?Model
    {
        return $screen->kind === Screen::DETAIL ? $record : null;
    }

    /**
     * The section's screen targets: valid ones only, without duplicates or
     * those a wider target covers.
     *
     * @return list<string>
     */
    private static function targets(): array
    {
        $targets = [];

        foreach (self::strings(config('keen.atrium.history.screens', ['*'])) as $target) {
            if (! self::validTarget($target)) {
                self::invalid(new InvalidArgumentException("keen.atrium.history.screens holds the invalid screen target [{$target}]: use *, {package}.* or a screen key ({package}.{screen})."));

                continue;
            }

            $targets[] = $target;
        }

        $targets = array_values(array_unique($targets));

        if (in_array('*', $targets, true)) {
            return ['*'];
        }

        return array_values(array_filter(
            $targets,
            fn (string $target): bool => str_ends_with($target, '.*') || ! in_array(strstr($target, '.', true).'.*', $targets, true),
        ));
    }

    /**
     * The section's slot, or null when it is invalid.
     */
    private static function slot(): ?string
    {
        $slot = config('keen.atrium.history.slot', 'after');
        $slot = is_string($slot) || is_int($slot) ? (string) $slot : 'after';

        if (preg_match(Screen::NAME_PATTERN, $slot) !== 1) {
            self::invalid(new InvalidArgumentException("keen.atrium.history.slot holds the invalid slot [{$slot}]: it must match ".Screen::NAME_PATTERN.'.'));

            return null;
        }

        return $slot;
    }

    /**
     * The tab targets, each with whether its tab is scoped to the record.
     *
     * @return array<string, bool>
     */
    private static function tabs(): array
    {
        $tabs = config('keen.atrium.history.tabs', []);
        $valid = [];

        foreach (is_array($tabs) ? $tabs : [] as $target => $options) {
            $target = (string) $target;

            if (! self::validTarget($target)) {
                self::invalid(new InvalidArgumentException("keen.atrium.history.tabs holds the invalid screen target [{$target}]: use *, {package}.* or a screen key ({package}.{screen})."));

                continue;
            }

            $options = is_array($options) ? $options : [];
            $valid[$target] = (bool) ($options['scope'] ?? true);
        }

        return $valid;
    }

    /**
     * `*`, `{package}.*` or an exact screen key, the targets Atrium accepts.
     */
    private static function validTarget(string $target): bool
    {
        return $target === '*' || preg_match(self::PACKAGE_TARGET, $target) === 1 || preg_match(Screen::KEY_PATTERN, $target) === 1;
    }

    /**
     * A bad entry throws where a developer will see it (local, testing and
     * outside an application); elsewhere it is reported once and skipped, so
     * boot carries on.
     */
    private static function invalid(InvalidArgumentException $exception): void
    {
        $app = Container::getInstance();

        if (! $app instanceof Application || $app->environment('local', 'testing') === true) {
            throw $exception;
        }

        report($exception);
    }

    private static function label(string $key): string
    {
        $label = __($key);

        return is_string($label) ? $label : $key;
    }

    /**
     * The value as a list of strings: a lone value is wrapped, and anything
     * but a string dropped.
     *
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        $strings = [];

        foreach (is_array($value) ? $value : [$value] as $entry) {
            if (is_string($entry)) {
                $strings[] = $entry;
            }
        }

        return $strings;
    }
}
