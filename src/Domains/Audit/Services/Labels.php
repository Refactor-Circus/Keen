<?php

declare(strict_types=1);

namespace JayI\Keen\Domains\Audit\Services;

use Illuminate\Database\Eloquent\Model;
use JayI\Foundation\Audit\AuditHooks;

/**
 * How a model is named in an entry: the label its package registered through
 * `AuditHooks::label()`, otherwise the first of its usual naming attributes,
 * otherwise its key.
 */
final readonly class Labels
{
    /** @var array<int, string> */
    private const array ATTRIBUTES = ['name', 'title', 'label', 'email', 'identifier', 'code', 'slug'];

    public function __construct(private AuditHooks $hooks) {}

    public function for(Model $model): string
    {
        $label = $this->hooks->labelFor($model);

        if ($label !== null && $label !== '') {
            return $label;
        }

        foreach (self::ATTRIBUTES as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }

            if (is_array($value) && $value !== [] && is_scalar(reset($value))) {
                // Localized labels: the first locale's text.
                return (string) reset($value);
            }
        }

        return (string) $model->getKey();
    }
}
