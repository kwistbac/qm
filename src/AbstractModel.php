<?php
namespace Qm;

use \Qm\Interfaces\PersistenceInterface;
use \Qm\Traits\PersistenceTrait;

use \Qm\Interfaces\AccessorMutatorInterface;
use \Qm\Traits\AccessorMutatorTrait;

abstract class AbstractModel implements PersistenceInterface, AccessorMutatorInterface
{
    use PersistenceTrait;
    use AccessorMutatorTrait;
}
