<?php
namespace Qm\Traits;

use \Qm\Qm;
use \Qm\Sql;

use \Qm\Interfaces\ValidationInterface;
use \Qm\Interfaces\PersistenceInterface;

trait PersistenceTrait
{
    use FieldTrait;

    /**
    * Connection name
    *
    * @var ?string
    */
    protected $connectionName = null;

    /**
    * @inheritDoc
    */
    public static function findOneById(
        $id,
        ?string $connectionName = null
    ) : ?PersistenceInterface {
        $primaryKey = static::getPrimaryKey();

        if (count($primaryKey) > 1) {
            if (!is_array($id)) {
                throw new \InvalidArgumentException("ID must be an array");
            }
            $values = $id;
        } else {
            $values = [$primaryKey[0] => $id];
        }

        $condition = [];
        foreach ($primaryKey as $fieldName) {
            if (!isset($values[$fieldName])) {
                throw new \Exception("$fieldName is undefined");
            }
            $condition[] = ["$fieldName = ?", $values[$fieldName]];
        }

        return static::findOneBy($condition, $connectionName);
    }

    /**
    * @inheritDoc
    */
    public static function findOneBy(
        $condition,
        ?string $connectionName = null
    ) : ?PersistenceInterface {
        $connection = Qm::getConnection($connectionName);

        $sql = (new Sql)
            ->select(...array_keys(static::getFields()))
            ->from(static::getTableName())
            ->where($condition);

        $query = $sql->getSql();
        $params = $sql->getParameters();

        if (Qm::getDebug()) {
            error_log(Sql::prepareForDebug($query, $params));
        }

        $statement = $connection->prepare($query);
        if ($statement->execute($params)
            && ($values = $statement->fetch(\PDO::FETCH_ASSOC))) {
            return new static($connectionName, $values);
        }
        return null;
    }

    /**
    * @inheritDoc
    */
    public function __construct(?string $connectionName = null, ?array $values = null) {
        if (isset($connectionName)) {
            $this->connectionName = $connectionName;
        }
        if (isset($values)) {
            $this->values = $values;
        }
    }

    /**
    * @inheritDoc
    */
    public function getConnectionName() : ?string
    {
        return $this->connectionName;
    }

    /**
    * @inheritDoc
    */
    public function save() : PersistenceInterface
    {
        if (empty($this->getValues())) {
            return $this->executeInsert();
        } elseif ($this->isDirty()) {
            return $this->executeUpdate();
        }
        return $this;
    }

    /**
    * @inheritDoc
    */
    public function refresh() : PersistenceInterface
    {
        return $this->executeSelect();
    }

    /**
    * @inheritDoc
    */
    public function delete() : PersistenceInterface
    {
        return $this->executeDelete();
    }

    private function getPrimaryKeyValues() : array
    {
        $primaryKey = static::getPrimaryKey();
        $values = array_filter(
            array_intersect_key($this->getValues(), array_flip($primaryKey))
        );
        if (count($values) != count($primaryKey)) {
            $undefined = array_keys(array_diff_key($primaryKey, $values));
            throw new \Exception(
                "ID field " . implode(', ', $undefined) . "is undefined"
            );
        }
        return $values;
    }

    private function getFieldDataFor(string $method) : array
    {
        $formats = PersistenceInterface::FIELD_FORMATS;
        $fields = static::getFields();
        $dirty = $this->getDirty();

        $auto = null;
        $values = $defaults = [];
        $trigger = 'on' . ucfirst($method);
        foreach ($fields as $fieldName => $field) {
            if (array_key_exists($fieldName, $dirty)) {
                $values[$fieldName] = $dirty[$fieldName];
                $defaults[$fieldName] = null;
            } elseif (isset($field[$trigger])) {
                switch ($field[$trigger]) {
                    case PersistenceInterface::AUTO_ID:
                        $auto = $fieldName;
                        $values[$fieldName] = null;
                    break;
                    case PersistenceInterface::AUTO_UUID:
                        $values[$fieldName] = \Ramsey\Uuid\Uuid::uuid1();
                    break;
                    case PersistenceInterface::AUTO_NOW:
                        $values[$fieldName] = date(
                            isset($formats[$field['type']])
                                ? $formats[$field['type']]
                                : $formats[PersistenceInterface::FIELD_DATETIME]
                        );
                    break;
                    default:
                        throw new \Exception(
                            "Invalid trigger {$field[$trigger]} for field $fieldName"
                        );
                    break;
                }
                $defaults[$fieldName] = null;
            } elseif ($method == 'insert') {
                if (isset($field['default'])) {
                    if (substr($field['default'], 0, 1) != '=') {
                        $defaults[$fieldName] = $field['default'];
                    }
                } elseif (!isset($field['null']) || $field['null']) {
                    $defaults[$fieldName] = null;
                }
            }
        }
        return [$auto, $values, $defaults];
    }

    protected function executeInsert() : PersistenceInterface
    {
        $connection = Qm::getConnection($this->connectionName);

        list($auto, $values, $defaults) = $this->getFieldDataFor('insert');

        $query = "INSERT INTO `" . static::getTableName() . "`";
        $params = [];
        if (!empty($values)) {
            $query .= " SET `"
                . implode('` = ?, `', array_keys($values))
                . '` = ?';
            $params = array_values($values);
        } else {
            $query .= " VALUES()";
        }

        if (Qm::getDebug()) {
            error_log(Sql::prepareForDebug($query, $params));
        }

        $statement = $connection->prepare($query);
        if (!$statement->execute($params)) {
            throw new \Exception(
                __METHOD__ . ': Failed to execute statement'
            );
        }

        $values = array_merge($defaults, $values);
        if (isset($auto)) {
            $values[$auto] = (int)$connection->lastInsertID();
        }
        $this->setValues($values)->clean();

        return $this;
    }

    protected function executeSelect() : PersistenceInterface
    {
        $connection = Qm::getConnection($this->connectionName);

        $condition = $this->getPrimaryKeyValues();

        $query = "SELECT `"
            . implode('`, `', array_keys(static::getField()))
            . "` FROM `" . static::getTableName() . "` WHERE `"
            . implode("` = ? AND `", array_keys($condition)) . "` = ?";
        $params = array_values($condition);

        if (Qm::getDebug()) {
            error_log(Sql::prepareForDebug($query, $params));
        }

        $statement = $connection->prepare($query);
        if (!$statement->execute($params)) {
            throw new \Exception(
                __METHOD__ . ': Failed to execute statement'
            );
        }

        if ($values = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $this->setValues($values);
        }

        return $this;
    }

    protected function executeUpdate() : PersistenceInterface
    {
        $connection = Qm::getConnection($this->connectionName);

        $condition = $this->getPrimaryKeyValues();

        list($auto, $values) = $this->getFieldDataFor('update');

        $query = 'UPDATE `' . static::getTableName() . '`'
            . ' SET `' . implode('` = ?, `', array_keys($values)) . '` = ?'
            . ' WHERE `' . implode('` = ? AND `', array_keys($condition)) . '` = ?';
        $params = array_merge(
            array_values($values),
            array_values($condition)
        );

        if (Qm::getDebug()) {
            error_log(Sql::prepareForDebug($query, $params));
        }

        $statement = $connection->prepare($query);
        if (!$statement->execute($params)) {
            throw new \Exception(
                __METHOD__ . ': Failed to execute statement'
            );
        }

        if (isset($auto)) {
            $values[$auto] = (int)$connection->lastInsertID();
        }
        $this->setValues(array_merge($this->getValues(), $values))->clean();

        return $this;
    }

    protected function executeDelete() : PersistenceInterface
    {
        $connection = Qm::getConnection($this->connectionName);

        $condition = $this->getPrimaryKeyValues();

        $query = 'DELETE FROM `' . static::getTableName()
            . '` WHERE `' . implode('` = ? AND `', array_keys($condition)) . '` = ?';
        $params = array_values($condition);

        if (Qm::getDebug()) {
            error_log(Sql::prepareForDebug($query, $params));
        }

        $statement = $connection->prepare($query);
        if (!$statement->execute($params)) {
            throw new \Exception(
                __METHOD__ . ': Failed to execute statement'
            );
        }

        return $this;
    }
}
