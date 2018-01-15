<?php
namespace Qm;

use \Qm\Interfaces\PersistenceInterface;
use \Qm\Traits\PersistenceTrait;

abstract class AbstractModel implements PersistenceInterface
{
    use PersistenceTrait;
}
