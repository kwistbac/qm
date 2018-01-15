<?php
namespace Qm\Traits;

trait AccessorMutatorTrait
{
    /**
    * @inheritDoc
    */
    public function __call(string $name, array $arguments)
    {
        $method = substr($name, 0, 3);
        switch ($method) {
            case 'get':
            case 'has': {
                if (count($arguments) != 0) {
                    throw new \InvalidArgumentException(
                        "Method " . get_class($this)
                        . "::{$name}() expects exactly 0 parameters, "
                        . count($arguments) . " given"
                    );
                }
                return $this->{$method}(lcfirst(substr($name, 3)));
            }
            case 'set': {
                if (count($arguments) != 1) {
                    throw new \InvalidArgumentException(
                        "Method " . get_class($this)
                        . "::{$name}() expects exactly 1 parameter, "
                        . count($arguments) . " given"
                    );
                }
                return $this->set(lcfirst(substr($name, 3)), $arguments[0]);
            }
        }
        throw new \RuntimeException(
            "Call to undefined method "
            . get_class($this)
            . "::{$name}()"
        );
    }
}
