<?php
namespace Qm\Interfaces;

interface PropertyInterface
{
    /**
    * Set field value
    *
    * @param string $fieldName
    * @param mixed $value
    * @return void
    */
    public function __set(string $fieldName, $value) : void;

    /**
    * Get field value
    *
    * @param string $fieldName
    * @return mixed
    */
    public function __get(string $fieldName);

    /**
    * Check if field value is set
    *
    * @param string $fieldName
    * @return bool
    */
    public function __isset(string $fieldName) : bool;

    /**
    * Unset field value
    *
    * @param string $fieldName
    * @return void
    */
    public function __unset(string $fieldName) : void;
}

