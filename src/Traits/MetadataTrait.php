<?php
namespace Qm\Traits;

trait MetadataTrait
{
    /**
    * Custom create table statement
    *
    * @var ?string
    */
    protected static $createTable = null;

    /**
    * Table name
    *
    * @var ?string
    */
    protected static $tableName = null;

    /**
    * Fields
    *
    * @var array
    */
    protected static $fields = null;

    /**
    * Primary key
    *
    * @var array
    */
    protected static $primaryKey = null;

    /**
    * Index keys
    *
    * @var ?array
    */
    protected static $indexKeys = null;

    /**
    * Unique keys
    *
    * @var ?array
    */
    protected static $uniqueKeys = null;

    /**
    * Foreign keys
    *
    * @var ?array
    */
    protected static $foreignKeys = null;

    /**
    * @inheritDoc
    */
    public static function getCreateTable() : ?string
    {
        return static::$createTable;
    }

    /**
    * @inheritDoc
    */
    public static function getTableName() : string
    {
        return (!isset(static::$tableName)
            ? get_class_name(static::class)
            : static::$tableName
        );
    }

    /**
    * @inheritDoc
    */
    public static function getField(string $fieldName) : array
    {
        if (!isset(static::$fields[$fieldName])) {
            throw new \InvalidArgumentException(
                "In " . get_called_class()
                . ": undefined field \"$fieldName\"");
        }
        return static::$fields[$fieldName];
    }

    /**
    * @inheritDoc
    */
    public static function getFields() : array
    {
        if (empty(static::$fields)) {
            throw new \Exception("No fields are defined");
        }
        return static::$fields;
    }

    /**
    * @inheritDoc
    */
    public static function getPrimaryKey() : array
    {
        if (empty(static::$primaryKey)) {
            throw new \Exception("No primary key is defined");
        }
        return static::$primaryKey;
    }

    /**
    * @inheritDoc
    */
    public static function getIndexKeys() : ?array
    {
        return static::$indexKeys;
    }

    /**
    * @inheritDoc
    */
    public static function getUniqueKeys() : ?array
    {
        return static::$uniqueKeys;
    }

    /**
    * @inheritDoc
    */
    public static function getForeignKeys() : ?array
    {
        return static::$foreignKeys;
    }
}
