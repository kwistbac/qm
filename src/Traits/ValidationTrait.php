<?php
namespace Qm\Traits;

trait ValidationTrait
{
    /**
    * @inheritDoc
    */
    public static function validate(array $values, array $context = []) : array
    {
        return static::validateFormat($values);
    }

    protected static function validateFormat(array $values) : array
    {
        $messages = [];
        $fields = static::getFields();
        $types = self::FIELD_TYPES;
        foreach ($values as $fieldName => $value) {
            if (!isset($fields[$fieldName])) {
                continue;
            }
            $field = $fields[$fieldName];
            if (isset($value)) {
                $cast = null;
                switch ($types[$field['type']]) {
                    case 'integer': $cast = (int)$value; break;
                    case 'string': $cast = (string)$value; break;
                    case 'float': $cast = (float)$value; break;
                    case 'boolean': $cast = (bool)$value; break;
                }
                if ($value == $cast) {
                    $value = $cast;
                    if ($error = static::checkFormat($field, $value)) {
                        $messages[$fieldName] = ucfirst($error);
                    }
                } else {
                    $messages[$fieldName] = 'Must be of the type '
                        . $types[$field['type']]
                        . ', ' . gettype($value) . ' given';
                }
            } elseif (isset($field['null']) && !$field['null']) {
                $messages[$fieldName] = 'Must not be empty';
            }
        }
        return $messages;
    }
}
