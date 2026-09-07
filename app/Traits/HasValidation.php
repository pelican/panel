<?php

namespace App\Traits;

use App\Observers\ValidationObserver;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

#[ObservedBy([ValidationObserver::class])]
trait HasValidation
{
    /**
     * Returns the validator instance used by this model.
     */
    public function getValidator(): Validator
    {
        $rules = $this->exists ? static::getRulesForUpdate($this) : static::getRules();

        $validatorFactory = Container::getInstance()->make(ValidationFactory::class);

        return $validatorFactory->make([], $rules);
    }

    /**
     * Returns the rules associated with this model.
     *
     * @return array<array-key, string[]>
     */
    public static function getRules(): array
    {
        return static::$validationRules;
    }

    /**
     * Returns the rules for a specific field. If the field is not found, an empty array is returned.
     *
     * @return string[]|ValidationRule[]
     */
    public static function getRulesForField(string $field): array
    {
        return Arr::get(static::getRules(), $field) ?? [];
    }

    /**
     * Returns the rules associated with the model, specifically for updating the given model rather than just creating it.
     *
     * @return array<array-key, string[]|ValidationRule[]>
     */
    public static function getRulesForUpdate(self $model): array
    {
        [$id, $column] = [$model->getKey(), $model->getKeyName()];

        $rules = static::getRules();
        foreach ($rules as $key => &$data) {
            // For each rule in a given field, iterate over it and confirm if the rule
            // is one for a unique field. If that is the case, append the ID of the current
            // working model, so we don't run into errors due to the way that field validation
            // works.
            foreach ($data as &$datum) {
                if (!Str::startsWith($datum, 'unique')) {
                    continue;
                }

                [, $args] = explode(':', $datum);
                $args = explode(',', $args);

                $datum = Rule::unique($args[0], $args[1] ?? $key)->ignore($id ?? $model, $column);
            }
        }

        return $rules;
    }

    /**
     * Determines if the model is in a valid state or not.
     *
     * @throws ValidationException
     */
    public function validate(): void
    {
        if (isset($this->skipValidation)) {
            return;
        }

        $validator = $this->getValidator();
        $validator->setData(
            // Trying to do self::toArray() here will leave out keys based on the whitelist/blacklist
            // for that model. Doing this will return all the attributes in a format that can
            // properly be validated.
            $this->addCastAttributesToArray(
                $this->getAttributes(),
                $this->getMutatedAttributes() // @phpstan-ignore argument.type (docblock wants array<string, mixed> but the method only in_array()s the values)
            )
        );

        throw_unless($validator->passes(), new ValidationException($validator));
    }
}
