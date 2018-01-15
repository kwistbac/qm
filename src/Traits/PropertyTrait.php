<?php
namespace Qm\Traits;

use \Qm\Interfaces\FieldInterface;

trait PropertyTrait
{
    /**
    * @inheritDoc
    */
    public function __set($fieldName, $value)
    {
        $this->set($fieldName, $value);
    }

    /**
    * @inheritDoc
    */
    public function __get($fieldName)
    {
        return $this->get($fieldName);
    }

    /**
    * @inheritDoc
    */
    public function __isset($fieldName)
    {
        return $this->has($fieldName);
    }

    /**
    * @inheritDoc
    */
    public function __unset($fieldName)
    {
        if ($this->has($fieldName)) {
            $this->set($fieldName, null);
        }
    }
}
