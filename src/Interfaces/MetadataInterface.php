<?php
namespace Qm\Interfaces;

interface MetadataInterface
{
    // Integer fields
    const FIELD_TINYINT = 'TINYINT';
    const FIELD_SMALLINT = 'SMALLINT';
    const FIELD_MEDIUMINT = 'MEDIUMINT';
    const FIELD_INT = 'INT';
    const FIELD_BIGINT = 'BIGINT';

    // String fields
    const FIELD_CHAR = 'CHAR';
    const FIELD_VARCHAR = 'VARCHAR';
    const FIELD_BINARY = 'BINARY';
    const FIELD_VARBINARY = 'VARBINARY';
    const FIELD_TINYTEXT = 'TINYTEXT';
    const FIELD_TEXT = 'TEXT';
    const FIELD_MEDIUMTEXT = 'MEDIUMTEXT';
    const FIELD_LONGTEXT = 'LONGTEXT';
    const FIELD_JSON = 'JSON';
    const FIELD_ENUM = 'ENUM';
    const FIELD_DATETIME = 'DATETIME';
    const FIELD_DATE = 'DATE';
    const FIELD_TIME = 'TIME';
    const FIELD_YEAR = 'YEAR';
    const FIELD_DECIMAL = 'DECIMAL';

    // Float fields
    const FIELD_FLOAT = 'FLOAT';
    const FIELD_DOUBLE = 'DOUBLE';

    // Boolean fields
    const FIELD_BOOLEAN = 'BOOLEAN';

    const FIELD_TYPES = [
        self::FIELD_TINYINT => 'integer',
        self::FIELD_SMALLINT => 'integer',
        self::FIELD_MEDIUMINT => 'integer',
        self::FIELD_INT => 'integer',
        self::FIELD_BIGINT => 'string',

        self::FIELD_ENUM => 'string',
        self::FIELD_CHAR => 'string',
        self::FIELD_VARCHAR => 'string',
        self::FIELD_BINARY => 'string',
        self::FIELD_VARBINARY => 'string',
        self::FIELD_TINYTEXT => 'string',
        self::FIELD_TEXT => 'string',
        self::FIELD_MEDIUMTEXT => 'string',
        self::FIELD_LONGTEXT => 'string',
        self::FIELD_JSON => 'string',
        self::FIELD_DECIMAL => 'string',

        self::FIELD_DATETIME => 'string',
        self::FIELD_DATE => 'string',
        self::FIELD_TIME => 'string',
        self::FIELD_YEAR => 'integer',

        self::FIELD_FLOAT => 'float',
        self::FIELD_DOUBLE => 'float',

        self::FIELD_BOOLEAN => 'boolean',
    ];

    const FIELD_FORMATS = [
        self::FIELD_TINYINT => [-128, 127],
        self::FIELD_SMALLINT => [-32768, 32767],
        self::FIELD_MEDIUMINT => [-8388608, 8388607],
        self::FIELD_INT => [-2147483648, 2147483647],
        self::FIELD_BIGINT => ['-9223372036854775808', '9223372036854775807'],

        self::FIELD_TINYTEXT => 255,
        self::FIELD_TEXT => 65535,
        self::FIELD_MEDIUMTEXT => 16777215,
        self::FIELD_LONGTEXT => 4294967295,
        self::FIELD_JSON => 4294967295,

        self::FIELD_DATETIME => 'Y-m-d H:i:s',
        self::FIELD_DATE => 'Y-m-d',
        self::FIELD_TIME => [
            '^[-]?((83[0-8])|(8[0-2][0-9])|([0-7]?[0-9]?[0-9])):[0-5]?[0-9](:[0-5]?[0-9])?$',
            '-838:59:59',
            '838:59:59'
        ],
        self::FIELD_YEAR => [1901, 2155],
    ];

    const AUTO_ID = 'AUTO_ID';
    const AUTO_UUID = 'AUTO_UUID';
    const AUTO_NOW = 'AUTO_NOW';

    /**
    * Get custom create table statement if defined
    *
    * @return ?string
    */
    public static function getCreateTable() : ?string;

    /**
    * Get table name
    *
    * @return string
    */
    public static function getTableName() : string;

    /**
    * Get attributes for a field
    *
    * @param string $fieldName
    * @return array
    */
    public static function getField(string $fieldName) : array;

    /**
    * Get attributes for all fields
    *
    * @return array
    */
    public static function getFields() : array;

    /**
    * Get primary key
    *
    * @return array
    */
    public static function getPrimaryKey() : array;

    /**
    * Get index keys
    *
    * @return ?array
    */
    public static function getIndexKeys() : ?array;

    /**
    * Get unique keys
    *
    * @return ?array
    */
    public static function getUniqueKeys() : ?array;

    /**
    * Get foreign keys
    *
    * @return ?array
    */
    public static function getForeignKeys() : ?array;
}
