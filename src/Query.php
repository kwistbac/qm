<?php
namespace Qm;

use \Qm\Interfaces\PersistenceInterface;

/**
* Query trait for PersistenceInterface
*
* @author Kim Wistbacka <kim.wistbacka@gmail.com>
*
*/
class Query extends Sql
{
    /**
    * Class for fetching a recordset of models
    */
    private $fields = [];
    private $from = [];
    private $joins = [];
    private $nullable = [];
    private $mapping = [];
    private $dirty = false;

    /**
    * Constructuor
    * @param array $model
    */
    private function prepareFields() : void
    {
        $this->dirty = false;
        $this->mapping = [];
        parent::select();

        $classes = array_merge($this->from, $this->joins);
        foreach ($this->fields as $key => $field) {
            if (is_array($field)) {
                $alias = trim(array_keys($field)[0]);
            } else {
                $alias = trim($field);
            }
            if (is_array($field)) {
                if (isset($classes[$alias])) {
                    // Select specified fields
                    $this->prepareClassFields($alias, $classes[$alias], $field[$alias]);
                } elseif (is_array($field[$alias])) {
                    $this->prepareAssocFields($alias, $field[$alias]);
                } else {
                    $this->prepareAssocFields($alias, [$field[$alias]]);
                }
            } elseif ($alias == '*') {
                if ($key > 0) {
                    throw new \Exception("Invalid field \"{$alias}\" selected");
                }
                foreach ($classes as $alias => $class) {
                    $this->prepareClassFields($alias, $class);
                }
            } elseif (isset($classes[$alias])) {
                // Field is a table alias, select all fields
                if (!isset($classes[$alias])) {
                    throw new \Exception("Invalid table alias \"{$alias}\" selected");
                }
                $this->prepareClassFields($alias, $classes[$alias]);
            } elseif (substr($alias, -2) == '.*') {
                // Field is a table alias, select all fields
                $classAlias = substr($alias, 0, -2);
                if (!isset($classes[$classAlias])) {
                    throw new \Exception("Invalid table alias \"{$classAlias}\" selected");
                }
                $this->prepareClassFields($classAlias, $classes[$classAlias]);
            } else {
                $this->prepareAssocFields($alias, [$alias]);
            }
        }
    }

    private function prepareClassFields(
        string $alias,
        string $class,
        ?array $fields = null
    ) {
        if (isset($this->mapping[$alias])) {
            throw new \Exception("Duplicate field selected: {$alias}");
        }
        $fieldNames = array_keys($class::getFields());
        if (!isset($fields)) {
            $fields = $fieldNames;
        } else {
            $invalid = array_diff($fields, $fieldNames);
            if (!empty($diff)) {
                throw new \Exception(
                    "Invalid fields " . implode(', ', $invalid)
                    . " selected from {$class}"
                );
            }
        }

        $primaryKey = $class::getPrimaryKey();
        $this->mapping[$alias] = [
            'class' => $class,
            'fields' => $fields,
            'null' => $this->nullable[$alias],
            'id' => count($primaryKey) == 1 ? reset($primaryKey) : null
        ];

        foreach ($fields as $fieldName) {
            parent::addSelect("{$alias}.{$fieldName}");
        }
    }

    private function prepareAssocFields($alias, array $fields)
    {
        if (isset($this->mapping[$alias])) {
            throw new \Exception("Duplicate field selected: {$alias}");
        }
        if (count($fields) == 1
            && is_integer(array_keys($fields)[0])) {
            $this->mapping[$alias] = null;
        } else {
            $this->mapping[$alias] = ['fields' => $fields];
        }
        foreach ($fields as $name => $sql) {
            if (is_integer($name)) {
                parent::addSelect("{$sql}");
            } else {
                parent::addSelect("{$sql} {$name}");
            }
        }
    }

    /**
    * Add class
    *
    * @param array $class [$className, $alias]
    * @param string|array $condition join condition
    */
    private function addClass(string $method, $class, $condition = null) : self
    {
        if (is_array($class)) {
            list($className, $alias) = $class;
        } else {
            $className = $class;
        }

        if (!class_exists($className)) {
            throw new \Exception("Invalid class name {$className}");
        }
        if (!isset(class_implements($className)[PersistenceInterface::class])) {
            throw new \Exception(
                "Class {$className} does not implement " . PersistenceInterface::class
            );
        }

        $tableName = $className::getTableName();
        $tableAlias = (!empty($alias) ? $alias : get_class_name($className));

        if (isset($this->from[$tableAlias])
            || isset($this->joins[$tableAlias])) {
            throw new \Exception("Alias {$tableAlias} is already defined");
        }

        $this->dirty = true;
        $tableNameSql = $tableName . ($tableName != $tableAlias ? " $tableAlias" : null);
        if (isset($condition)) {
            if ($method == 'rightJoin') {
                $aliases = array_keys(array_merge($this->from, $this->joins));
                $this->nullable = array_fill_keys($aliases, true);
                $this->nullable[$tableAlias] = false;
            } elseif ($method == 'leftJoin' && !isset($this->nullableFrom)) {
                $this->nullable[$tableAlias] = true;
            } else {
                $this->nullable[$tableAlias] = false;
            }
            $this->joins[$tableAlias] = $className;
            return parent::{$method}($tableNameSql, $condition);
        }
        $this->nullable[$tableAlias] = false;
        $this->from[$tableAlias] = $className;
        return parent::{$method}($tableNameSql);
    }

    /**
    * Fetch single model from database
    * @return extent model recordset
    */
    public function execute(?string $connectionName = null) : Mapper
    {
        $statement = parent::execute($connectionName);
        $statement->setMappingFormat($this->mapping);
        return $statement;
    }

    public function getSql() : string
    {
        if ($this->dirty) {
            $this->prepareFields();
        }
        return parent::getSql();
    }

    public function getMapping() : array
    {
        if ($this->dirty) {
            $this->prepareFields();
        }
        return $this->mapping;
    }

    public function select(...$fields) : Sql
    {
        $this->fields = $fields;
        $this->dirty = true;
        return $this;

    }

    public function addSelect($class) : Sql
    {
        $this->fields[] = $class;
        $this->dirty = true;
        return $this;
    }

    public function getSelect() : array
    {
        return $this->fields;
    }

    public function from(...$classes) : Sql
    {
        $this->from = [];
        foreach ($classes as $class) {
            $this->addFrom($class);
        }
        return $this;
    }

    public function addFrom($class) : Sql
    {
        return $this->addClass(__FUNCTION__, $class);
    }

    /**
    * Inner join on condition
    *
    * @param string|array $class [$className, $alias]
    * @param string|array $condition join condition
    */
    public function innerJoin($class, $condition) : Sql
    {
        return $this->addClass(__FUNCTION__, $class, $condition);
    }

    /**
    * Left join on condition
    *
    * @param string|array $class [$className, $alias]
    * @param string|array $condition join condition
    */
    public function leftJoin($class, $condition) : Sql
    {
        return $this->addClass(__FUNCTION__, $class, $condition);
    }

    /**
    * Right join on condition
    *
    * @param string|array $class [$className, $alias]
    * @param string|array $condition join condition
    */
    public function rightJoin($class, $condition) : Sql
    {
        return $this->addClass(__FUNCTION__, $class, $condition);
    }

    public function setJoins(array $joins) : Sql
    {
        foreach ($joins as $join) {
            $this->addClass(
                strtolower($join[0]) . 'Join',
                $join[1],
                $join[2]
            );
        }
        return $this;
    }
}
