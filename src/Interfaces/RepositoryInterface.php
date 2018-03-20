<?php
namespace Qm\Interfaces;

use \Qm\Interfaces\PersistenceInterface;

interface RepositoryInterface
{
    /**
    * Find one by ID
    *
    * @param mixed $id
    * @param ?string $connectionName
    * @return ?PersistenceInterface
    */
    public static function find($id, ?string $connectionName = null) : ?PersistenceInterface;

    /**
    * Find one by condition
    *
    * @param ?mixed $condition
    * @param ?mixed $orderBy
    * @param ?string $connectionName
    * @return ?PersistenceInterface
    */
    public static function findOneBy(
        $condition,
        $orderBy = null,
        ?string $connectionName = null
    ) : ?PersistenceInterface;

    /**
    * Find all
    *
    * @param ?string $connectionName
    * @return \Traversable
    */
    public static function findAll(?string $connectionName = null) : \Traversable;

    /**
    * Find by condition
    *
    * @param ?mixed $condition
    * @param ?mixed $orderBy
    * @param ?int $limit
    * @param ?int $offset
    * @param ?string $connectionName
    * @return \Traversable
    */
    public static function findBy(
        $condition,
        $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $connectionName = null
    ) : \Traversable;

    /**
    * Count all
    *
    * @param ?string $connectionName
    * @return int
    */
    public static function countAll(?string $connectionName = null) : int;

    /**
    * Count by condition
    *
    * @param ?mixed $condition
    * @param ?string $connectionName
    * @return int
    */
    public static function countBy($condition, ?string $connectionName = null) : int;

    /**
    * Verify any exists
    *
    * @param ?string $connectionName
    * @return boolean
    */
    public static function existsAny(?string $connectionName = null) : bool;

    /**
    * Verify exists by condition
    *
    * @param ?mixed $condition
    * @param ?string $connectionName
    * @return boolean
    */
    public static function existsBy($condition, ?string $connectionName = null) : bool;

    /**
    * Find column
    *
    * @param string $field
    * @param ?mixed $condition
    * @param ?mixed $orderBy
    * @param ?int $limit
    * @param ?int $offset
    * @param ?string $connectionName
    * @return \Traversable
    */
    public static function findColumn(
        string $field,
        $condition = null,
        $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $connectionName = null
    ) : \Traversable;

    /**
    * Find pairs
    *
    * @param string $keyField
    * @param string $valueField
    * @param ?mixed $condition
    * @param ?mixed $orderBy
    * @param ?int $limit
    * @param ?int $offset
    * @param ?string $connectionName
    * @return array
    */
    public static function findPairs(
        string $keyField,
        string $valueField,
        $condition = null,
        $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
        ?string $connectionName = null
    ) : array;
}
