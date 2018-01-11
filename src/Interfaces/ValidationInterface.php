<?php
namespace Qm\Interfaces;

interface ValidationInterface
{
    /**
    * Validate values
    *
    * @param string $values
    * @param ?PersistenceInterface $instance
    * @return array Validation messages
    */
    public static function validate(array $values, ?PersistenceInterface $instance = null) : array;
}

