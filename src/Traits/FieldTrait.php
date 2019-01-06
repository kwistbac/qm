<?php
namespace Qm\Traits;

use \Qm\Interfaces\MetadataInterface;
use \Qm\Interfaces\FieldInterface;

trait FieldTrait
{
    use MetadataTrait;

    /**
    * Persistent values
    *
    * @var array
    */
    protected $values = [];

    /**
    * Dirty values
    *
    * @var array
    */
    protected $dirty = [];

    /**
    * @inheritDoc
    */
    public function toArray() : array
    {
        if (!empty($this->dirty)) {
            return array_merge($this->values, $this->dirty);
        }
        return $this->values;
    }

    /**
    * @inheritDoc
    */
    public function assign(array $values) : FieldInterface
    {
        foreach ($values as $fieldName => $value) {
            $mutator = 'set' . ucfirst($fieldName);
            if (method_exists($this, $mutator)) {
                $this->{$mutator}($value);
            } elseif (method_exists($this, '__set')) {
                $this->__set($fieldName, $value);
            } elseif (method_exists($this, '__call')) {
                $this->__call($mutator, [$value]);
            } else {
                throw new \Exception("Failed to assign $fieldName");
            }
        }
        return $this;
    }

    /**
    * Get persistent values
    *
    * @return array
    */
    protected function getValues() : array
    {
        return $this->values;
    }

    /**
    * Set persistent values
    *
    * @params array $values
    * @return self
    */
    protected function setValues(array $values) : self
    {
        $this->values = $values;
        return $this;
    }

    /**
    * Get dirty values
    *
    * @return array
    */
    protected function getDirty() : array
    {
        return $this->dirty;
    }

    /**
    * Set dirty values
    *
    * @params array $values
    * @return self
    */
    protected function setDirty(array $values) : self
    {
        $this->dirty = $values;
        return $this;
    }

    /**
    * @inheritDoc
    */
    public function isDirty(?string $fieldName = null) : bool
    {
        if (!isset($fieldName)) {
            return !empty($this->dirty);
        }
        static::getField($fieldName);
        return array_key_exists($fieldName, $this->dirty);
    }

    /**
    * @inheritDoc
    */
    public function isNew() : bool
    {
        return empty($this->values);
    }

    /**
    * @inheritDoc
    */
    public function clean() : FieldInterface
    {
        $this->dirty = [];
        return $this;
    }

    protected function has(string $fieldName) : bool
    {
        static::getField($fieldName);
        return array_key_exists($fieldName, $this->dirty)
            || array_key_exists($fieldName, $this->values);
    }

    protected function get(string $fieldName)
    {
        static::getField($fieldName);
        if (array_key_exists($fieldName, $this->dirty)) {
            return $this->dirty[$fieldName];
        }
        if (array_key_exists($fieldName, $this->values)) {
            return $this->values[$fieldName];
        }
        throw new \RuntimeException(
            get_class($this) . ": value for $fieldName is undefined"
        );
    }

    protected static function checkFormat(array $field, $value) : ?string
    {
        $formats = self::FIELD_FORMATS;
        switch ($field['type']) {
            case FieldInterface::FIELD_TINYINT:
            case FieldInterface::FIELD_SMALLINT:
            case FieldInterface::FIELD_MEDIUMINT:
            case FieldInterface::FIELD_INT:
            case FieldInterface::FIELD_BIGINT:
            case FieldInterface::FIELD_YEAR:
                list($min, $max) = $formats[$field['type']];
                if (isset($field['unsigned']) && $field['unsigned']) {
                    $max += -$min;
                    $min = 0;
                }
                if ($min > $value || $max < $value) {
                    return "must be in range $min - $max, $value given";
                }
            break;
            case FieldInterface::FIELD_DECIMAL:
                $fractional = $field['scale'] ?? 0;
                $integral = ($field['precision'] ?? 10) - $fractional;
                $pattern = $integral ? '\d{1,' . $integral . '}' : '0';
                if ($fractional) {
                    $pattern .= '(\.\d{0,' . $fractional . '})?';
                }
                if (isset($field['unsigned']) && $field['unsigned']) {
                    if (!preg_match("/^{$pattern}$/", $value)) {
                        return "must be in range 0"
                            . ($fractional ? "." . str_repeat('0', $fractional) : null)
                            . " - " . ($integral ? str_repeat('9', $integral) : '0')
                            . ($fractional ? "." . str_repeat('9', $fractional) : null)
                            . ", $value given";
                    }
                } elseif (!preg_match("/^[-]?{$pattern}$/", $value)) {
                    return "must be in range "
                        . "-" . ($integral ? str_repeat('9', $integral) : '0')
                        . ($fractional ? "." . str_repeat('9', $fractional) : null)
                        . " - " . ($integral ? str_repeat('9', $integral) : '0')
                        . ($fractional ? "." . str_repeat('9', $fractional) : null)
                        . ", $value given";
                }
            break;
            case FieldInterface::FIELD_CHAR:
            case FieldInterface::FIELD_VARCHAR:
            case FieldInterface::FIELD_BINARY:
            case FieldInterface::FIELD_VARBINARY:
                $length = $field['length'] ?? 50;
                if (strlen($value) > $length) {
                    return 'must be no longer than'
                        . " {$length} characters"
                        . ', ' . strlen($value) . ' given';
                }
            break;
            case FieldInterface::FIELD_TINYTEXT:
            case FieldInterface::FIELD_TEXT:
            case FieldInterface::FIELD_MEDIUMTEXT:
            case FieldInterface::FIELD_LONGTEXT:
                if (strlen($value) > $formats[$field['type']]) {
                    return 'must be no longer than'
                        . " {$formats[$field['type']]} characters"
                        . ', ' . strlen($value) . ' given, ';
                }
            break;
            case FieldInterface::FIELD_DATETIME:
            case FieldInterface::FIELD_DATE:
                $dt = \DateTime::createFromFormat(
                    $formats[$field['type']],
                    $value
                );
                if ($dt === false || array_sum($dt->getLastErrors())) {
                    return "must be of format {$formats[$field['type']]}"
                        . ', invalid format given';
                }
            break;
            case FieldInterface::FIELD_TIME:
                if (!preg_match("/{$formats[$field['type']][0]}/", $value)) {
                    return "must be in range {$formats[$field['type']][1]} - {$formats[$field['type']][2]}"
                        . ", $value given";
                }
            break;
            case FieldInterface::FIELD_ENUM:
                if (!in_array($value, $field['values'])) {
                    return "must be one of ("
                        . implode(', ', $field['values'])
                        . "), $value given";
                }
            break;
        }
        return null;
    }

    protected function set(string $fieldName, $value) : FieldInterface
    {
        $cast = null;
        $valid = false;
        $field = static::getField($fieldName);
        $types = self::FIELD_TYPES;
        if (isset($value)) {
            switch ($types[$field['type']]) {
                case 'integer': $cast = (int)$value; break;
                case 'string': $cast = (string)$value; break;
                case 'float': $cast = (float)$value; break;
                case 'boolean': $cast = (bool)$value; break;
            }
            if ($value == $cast) {
                $valid = true;
                $value = $cast;
                if ($error = static::checkFormat($field, $value)) {
                    throw new \Exception(
                        get_class($this)
                        . ": value of the field $fieldName $error, "
                    );
                }
            }
        } elseif (!isset($field['null']) || $field['null']) {
            $valid = true;
        }
        if (!$valid) {
            throw new \Exception(
                get_class($this)
                . ": value of the field $fieldName must be of the type "
                . $types[$field['type']] . ', ' . gettype($value) . ' given, '
            );
        }
        if (!array_key_exists($fieldName, $this->values)
            || $this->values[$fieldName] !== $value) {
            $this->dirty[$fieldName] = $value;
        }
        return $this;
    }
}
