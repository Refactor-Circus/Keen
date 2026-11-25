<?php

declare(strict_types=1);

namespace RefactorCircus\Keen\Domains\Audit\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The key an entry stores for a record named by morph type and identifier.
 *
 * Entries store a subject's and a scope's key (`getKey()`), but the screens
 * that show a record another package owns pass its route key, such as an
 * organization's slug (D-019, the stored-identifiers standard). A filter's
 * identifier is looked up by the type's route key when that is not its key,
 * then by its key; when neither finds a record, or the type is not a model
 * (an unknown alias, a free-form type, a record since deleted), the
 * identifier is kept as given, so the filter matches what was stored.
 */
final class StoredKeys
{
    public function for(string $type, string $id): string
    {
        $class = $this->modelClass($type);

        if ($class === null) {
            return $id;
        }

        $model = new $class;

        if ($model->getRouteKeyName() === $model->getKeyName()) {
            return $id;
        }

        $record = $model->newQuery()->where($model->qualifyColumn($model->getRouteKeyName()), $id)->first();

        return $record instanceof Model ? (string) $record->getKey() : $id;
    }

    /**
     * The model class a morph type names: its morph map entry, else the type
     * itself when it is an Eloquent model class.
     *
     * @return class-string<Model>|null
     */
    private function modelClass(string $type): ?string
    {
        $class = Relation::getMorphedModel($type) ?? $type;

        return class_exists($class) && is_subclass_of($class, Model::class) ? $class : null;
    }
}
