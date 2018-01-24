<?php
namespace Qm\Interfaces;

interface FieldInterface extends MetadataInterface
{
    /**
    * Assign values from an array
    *
    * @param array $values
    * @return self
    */
    public function assign(array $values) : self;

    /**
    * Check dirty state
    *
    * If a field name is supplied the dirty state for that field is returned.
    * Otherwise the combined dirty state of all field is returned.
    *
    * @param string $fieldName
    * @return boolean
    */
    public function isDirty(?string $fieldName = null) : bool;

    /**
    * Clean dirty values
    *
    * @return self
    */
    public function clean() : self;

    /**
    * Convert to array, merging persistent and dirty values
    *
    * @return array
    */
    public function toArray() : array;
}
