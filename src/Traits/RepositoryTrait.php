<?php
namespace Qm\Traits;

use \Qm\Sql;
use \Qm\Query;

use \Qm\Interfaces\PersistenceInterface;

trait RepositoryTrait
{
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
        if (isset($limit) || isset($offset)) {
            $query->limit($limit, $offset);
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
    public static function findColumn(
        string $field,
        $condition = null,
        $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $connectionName = null
    ) : \Traversable {
        $query = (new Query)
            ->select($fieldName)
            ->from(static::class);
        if (isset($condition)) {
            $query->where($condition);
        }
        if (isset($orderBy)) {
            $query->orderBy($orderBy);
        }
        if (isset($limit) || isset($offset)) {
            $query->limit($limit, $offset);
        }
        return $query->execute($connectionName)->setFetchMode(\PDO::FETCH_COLUMN);
    }

    /**
    * @inheritDoc
    */
    public static function findPairs(
        string $keyField,
        string $valueField,
        $condition = null,
        $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $connectionName = null
    ) : array {
        $query = (new Query)
            ->withModifiers('DISTINCT')
            ->select($keyField, $valueField)
            ->from(static::class);
        if (isset($condition)) {
            $query->where($condition);
        }
        if (isset($orderBy)) {
            $query->orderBy($orderBy);
        }
        if (isset($limit) || isset($offset)) {
            $query->limit($limit, $offset);
        }
        return $query->execute($connectionName)
            ->fetchAll(\PDO::FETCH_COLUMN | \PDO::FETCH_UNIQUE);
    }
}
