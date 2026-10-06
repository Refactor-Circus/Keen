<?php

declare(strict_types=1);

namespace JayI\Keen\Domains\Audit\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use JayI\Foundation\Audit\AuditHooks;
use JayI\Foundation\Audit\Contracts\Auditable;
use JayI\Foundation\Contracts\ActionFinishedEvent;
use JayI\Foundation\Contracts\ActionStartingEvent;
use JayI\Foundation\Packages\PackageRegistry;
use JayI\Foundation\Support\Surface;
use JayI\Keen\Domains\Audit\Models\AuditEntryModel;
use ReflectionObject;
use ReflectionProperty;
use stdClass;

/**
 * Turns every package's action events into audit entries.
 *
 * The starting event snapshots the model the action is about to touch; the
 * finished event diffs it against its new state and appends the entry. Reads
 * (`*Listed`, `*Shown`) are not recorded.
 *
 * The entry's source is the package that owns the event class. Its subject is
 * the one the event names through `Auditable`, else the one a package's
 * `AuditHooks::subject()` picks, else the event's first model.
 *
 * Bound per request: snapshots must not outlive it.
 */
final class AuditRecorder
{
    /** @var array<int, string> */
    private const array READ_VERBS = ['Listed', 'Shown', 'Listing', 'Showing'];

    /** @var array<string, array<string, mixed>> */
    private array $snapshots = [];

    public function __construct(
        private readonly Snapshots $snapshotter,
        private readonly Labels $labels,
        private readonly AuditLog $log,
        private readonly Surface $surface,
        private readonly AuditHooks $hooks,
        private readonly PackageRegistry $packages,
        private readonly Repository $config,
    ) {}

    public function starting(ActionStartingEvent $event): void
    {
        if ($this->ignores($event)) {
            return;
        }

        $subject = $this->subject($event, $this->models($event));

        // Read back from the database, as the finished side is, so columns
        // filled by database defaults never show as changes.
        if ($subject !== null && $subject->exists) {
            $this->snapshots[$this->key($subject)] ??= $this->snapshotter->of($subject->fresh() ?? $subject);
        }
    }

    public function finished(ActionFinishedEvent $event): void
    {
        if ($this->ignores($event)) {
            return;
        }

        [$noun, $verb] = $this->name($event);
        $models = $this->models($event);
        $subject = $this->subject($event, $models);

        $context = [
            ...$this->context($event, $subject),
            ...($event instanceof Auditable ? $event->auditContext() : []),
        ];

        $this->write(
            source: $this->packages->for($event)->key ?? AuditEntryModel::SOURCE_APP,
            action: $noun.'.'.$verb,
            subject: $subject,
            changes: $subject === null ? [] : $this->changes($subject, $verb),
            context: $context,
            scope: $this->hooks->scopeFor($event, $models, $subject),
            event: $event,
        );
    }

    /**
     * Append one entry, filling in who made it, through which surface, and
     * the context every package adds.
     *
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes
     * @param  array<string, mixed>  $context
     */
    public function write(
        string $source,
        string $action,
        ?Model $subject = null,
        array $changes = [],
        array $context = [],
        ?Model $scope = null,
        ?Model $actor = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $subjectLabel = null,
        ?object $event = null,
    ): AuditEntryModel {
        $surface = $this->surface->current();
        $request = in_array($surface, ['cli', 'code', 'cortex'], true) ? null : $this->surface->request();
        $actor ??= $this->surface->actor();
        $context = [...$context, ...$this->hooks->contextFor($event ?? new stdClass, $subject)];

        return $this->log->append([
            'source' => $source,
            'action' => $action,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor === null ? null : (string) $actor->getKey(),
            'actor_label' => $actor === null ? null : $this->labels->for($actor),
            'subject_type' => $subject?->getMorphClass() ?? $subjectType,
            'subject_id' => $subject === null ? $subjectId : (string) $subject->getKey(),
            'subject_label' => $subject === null ? $subjectLabel : $this->labels->for($subject),
            'scope_type' => $scope?->getMorphClass(),
            'scope_id' => $scope === null ? null : (string) $scope->getKey(),
            'surface' => $surface,
            'ip' => $request?->ip(),
            'user_agent' => $request === null ? null : Str::limit((string) $request->userAgent(), 500, ''),
            'changes' => $this->snapshotter->redact($changes),
            'context' => $this->snapshotter->redact($context),
        ]);
    }

    /**
     * A create lists every field as new and a delete every field as gone;
     * anything else diffs against the snapshot taken when the action started.
     * A model with no snapshot records no field changes.
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function changes(Model $subject, string $verb): array
    {
        $key = $this->key($subject);
        $before = $this->snapshots[$key] ?? null;
        unset($this->snapshots[$key]);

        return match (true) {
            $verb === 'created' => $this->snapshotter->diff([], $this->snapshotter->of($subject)),
            $verb === 'deleted' => $before === null ? [] : $this->snapshotter->diff($before, []),
            $before === null => [],
            default => $this->snapshotter->diff($before, $this->snapshotter->of($subject->fresh() ?? $subject)),
        };
    }

    /**
     * Reads, Keen's own actions, and anything `keen.ignore` names are not
     * recorded.
     */
    private function ignores(object $event): bool
    {
        $package = $this->packages->for($event);

        if ($package?->key === 'keen') {
            return true;
        }

        /** @var array<int, string> $ignored */
        $ignored = (array) $this->config->get('keen.ignore', []);

        if (in_array($event::class, $ignored, true) || ($package !== null && in_array($package->key, $ignored, true))) {
            return true;
        }

        foreach (self::READ_VERBS as $verb) {
            if (str_ends_with(class_basename($event), $verb.'ActionEvent')) {
                return true;
            }
        }

        return false;
    }

    /**
     * `UserSuspendedActionEvent` => ['user', 'suspended'];
     * `TeamMemberAddedActionEvent` => ['team_member', 'added'].
     *
     * @return array{0: string, 1: string}
     */
    private function name(object $event): array
    {
        $words = explode(' ', Str::headline(Str::beforeLast(class_basename($event), 'ActionEvent')));
        $verb = strtolower((string) array_pop($words));

        return [Str::snake(implode('', $words)), $verb];
    }

    /**
     * @return array<string, Model>
     */
    private function models(object $event): array
    {
        $models = [];

        foreach ((new ReflectionObject($event))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $value = $property->getValue($event);

            if ($value instanceof Model) {
                $models[$property->getName()] = $value;
            }
        }

        return $models;
    }

    /**
     * @param  array<string, Model>  $models
     */
    private function subject(object $event, array $models): ?Model
    {
        if ($event instanceof Auditable) {
            return $event->auditSubject();
        }

        return $this->hooks->subjectFor($event, $models) ?? ($models === [] ? null : reset($models));
    }

    /**
     * The event's other models and scalar values, by property name.
     *
     * @return array<string, mixed>
     */
    private function context(object $event, ?Model $subject): array
    {
        $context = [];

        foreach ((new ReflectionObject($event))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $value = $property->getValue($event);
            $name = $property->getName();

            if ($value instanceof Model) {
                if ($subject !== null && $value->is($subject)) {
                    continue;
                }

                $context[$name] = ['type' => $value->getMorphClass(), 'id' => (string) $value->getKey(), 'label' => $this->labels->for($value)];
            } elseif (is_scalar($value) || is_array($value)) {
                $context[$name] = $value;
            }
        }

        return $context;
    }

    private function key(Model $model): string
    {
        return $model::class.':'.$model->getKey();
    }
}
