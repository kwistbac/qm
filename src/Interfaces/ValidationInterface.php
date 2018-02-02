<?php
namespace Qm\Interfaces;

interface ValidationInterface
{
    /**
    * Validate values
    *
    * @param array $values
    * @param array $context
    * @return array Validation messages
    */
    public static function validate(array $values, array $context = []) : array;
}

