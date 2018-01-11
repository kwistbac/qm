<?php
namespace Qm\Interfaces;

interface AccessorMutatorInterface
{
    /**
    * Handler for accessor and mutators
    *
    * @param string $name
    * @param array $arguments
    * @return mixed
    */
    public function __call(string $name, array $arguments);
}
