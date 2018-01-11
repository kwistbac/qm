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
    public static function findOneById($id, ?string $connectionName = null) : ?self;

    /**
    * Find one by condition
    *
    * @param mixed $condition
    * @param ?string $connectionName
    * @return ?self
    */
    public static function findOneBy($condition, ?string $connectionName = null) : ?self;

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
