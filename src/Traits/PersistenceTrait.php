<?php
namespace Qm\Traits;

use \Qm\Qm;
use \Qm\Sql;
use \Qm\Query;

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
    public static function find(
        $id,
        ?string $connectionName = null
    ) : ?PersistenceInterface {
        $primaryKey = static::getPrimaryKey();

        if (!is_array($id)) {
            if (count($primaryKey) > 1) {
                throw new \InvalidArgumentException('ID must be an array');
            }
            $id = [$primaryKey[0] => $id];
        }

        $condition = [];
        foreach ($primaryKey as $fieldName) {
            if (!isset($id[$fieldName])) {
                throw new \Exception("ID field \"$fieldName\" is undefined");
            }
            $condition[] = ["$fieldName = ?", $id[$fieldName]];
            unset($id[$fieldName]);
        }

        if (!empty($id)) {
            throw new \Exception(
                'ID fields "'
                . implode('", "', array_keys($id))
                . '" are invalid'
            );
        }

        return static::findOneBy($condition, null, $connectionName);
    }

    /**
    * @inheritDoc
    */
    public static function findOneBy(
        $condition,
        $orderBy = null,
        ?string $connectionName = null
    ) : ?PersistenceInterface {

        $query = (new Query)
            ->select('*')
            ->from(static::class)
            ->limit(1);
        if (!empty($condition)) {
            $query->where($condition);
        }
        if (!empty($orderBy)) {
            $query->orderBy($orderBy);
        }
        $found = $query->execute($connectionName);

        if (!$found->count()) {
            return null;
        }
        return $found->fetch();
    }

    /**
    * @inheritDoc
    */
    public static function findAll(
        ?string $connectionName = null
    ) : \Traversable {
        return static::findBy([], null, null, null, $connectionName);
    }

    /**
    * @inheritDoc
    */
    public static function findBy(
        $condition,
        $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $connectionName = null
    ) : \Traversable {

        $query = (new Query)
            ->select('*')
            ->from(static::class);
        if (!empty($condition)) {
            $query->where($condition);
        }
        if (!empty($orderBy)) {
            $query->orderBy($orderBy);
        }
        if (isset($limit)) {
            $query->limit($limit);
        }
        if (isset($offset)) {
            $query->offset($offset);
        }
        return $query->execute($connectionName);
    }

    /**
    * @inheritDoc
    */
    public static function countAll(
        ?string $connectionName = null
    ) : int {
        return static::countBy(null, $connectionName);
    }

    /**
    * @inheritDoc
    */
    public static function countBy(
        $condition,
        ?string $connectionName = null
    ) : int {

        $query = (new Sql)
            ->select('COUNT(*)')
            ->from(static::getTableName());
        if (!empty($condition)) {
            $query->where($condition);
        }
        $count = $query->execute($connectionName);

        return (int)$count->fetchColumn();
    }

    /**
    * @inheritDoc
    */
    public static function existsAny(
        ?string $connectionName = null
    ) : bool {
        return static::existsBy(null, $connectionName);
    }

    /**
    * @inheritDoc
    */
    public static function existsBy(
        $condition,
        ?string $connectionName = null
    ) : bool {

        $query = (new Sql)
            ->select('1')
            ->from(static::getTableName())
            ->limit(1);
        if (!empty($condition)) {
            $query->where($condition);
        }
        $exists = $query->execute($connectionName);

        return (bool)$exists->fetchColumn();
    }

    /**
    * @inheritDoc
    */
    public function __construct(
        ?string $connectionName = null,
        ?array $values = null
    ) {
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
                'ID field ' . implode(', ', $undefined) . 'is undefined'
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

        $query = 'INSERT INTO `' . static::getTableName() . '`';
        $params = [];
        if (!empty($values)) {
            $query .= ' SET `'
                . implode('` = ?, `', array_keys($values))
                . '` = ?';
            $params = array_values($values);
        } else {
            $query .= ' VALUES()';
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

        $query = 'SELECT `'
            . implode('`, `', array_keys(static::getField()))
            . '` FROM `' . static::getTableName() . '` WHERE `'
            . implode('` = ? AND `', array_keys($condition)) . '` = ?';
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
