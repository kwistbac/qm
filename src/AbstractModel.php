<?php
namespace Qm;

use \Qm\Interfaces\RepositoryInterface;
use \Qm\Traits\RepositoryTrait;

use \Qm\Interfaces\PersistenceInterface;
use \Qm\Traits\PersistenceTrait;

abstract class AbstractModel implements PersistenceInterface, RepositoryInterface
{
    use PersistenceTrait;
    use RepositoryTrait;
}
