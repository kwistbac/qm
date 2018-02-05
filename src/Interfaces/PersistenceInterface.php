<?php
namespace Qm\Interfaces;

interface PersistenceInterface extends FieldInterface
{
    /**
    * Find one by ID
    *
    * @param mixed $id
    * @param ?string $connectionName
    * @return ?self
    */
    public static function find($id, ?string $connectionName = null) : ?self;

    /**
    * Find one by condition
    *
    * @param mixed $condition
    * @param ?string $connectionName
    * @return ?self
    */
    public static function findOneBy($condition, ?string $connectionName = null) : ?self;

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
    * @param mixed $condition
    * @param ?string $connectionName
    * @return \Traversable
    */
    public static function findBy($condition, ?string $connectionName = null) : \Traversable;

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
    * @param mixed $condition
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
    * @param mixed $condition
    * @param ?string $connectionName
    * @return boolean
    */
    public static function existsBy($condition, ?string $connectionName = null) : bool;

    /**
    * Constructor
    *
    * @param ?string $connectionName
    * @param ?array $values
    */
    public function __construct(?string $connectionName = null, ?array $values = null);

    /**
    * Get connection name
    *
    * @return ?string
    */
    public function getConnectionName() : ?string;

    /**
    * Save state to database
    *
    * @return self
    */
    public function save() : self;

    /**
    * Refresh state from database
    *
    * @return self
    */
    public function refresh() : self;

    /**
    * Delete state from database
    *
    * @return self
    */
    public function delete() : self;
}
