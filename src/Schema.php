<?php
namespace Qm;

use \Qm\Interfaces\MetadataInterface;

final class Schema
{
    public static function dropTable(
        string $class,
        ?string $connectionName = null
    ) : void {
        Qm::getConnection($connectionName)->exec(
            static::getDropTable($class)
        );
    }

    public static function dropTableIfExists(
        string $class,
        ?string $connectionName = null
    ) : void {
        Qm::getConnection($connectionName)->exec(
            static::getDropTable($class, true)
        );
    }

    public static function createTable(
        string $class,
        ?string $connectionName = null
    ) : void {
        Qm::getConnection($connectionName)->exec(
            static::getCreateTable($class)
        );
    }

    public static function createTableIfNotExists(
        string $class,
        ?string $connectionName = null
    ) : void {
        Qm::getConnection($connectionName)->exec(
            static::getCreateTable($class, true)
        );
    }

    public static function getDropTable(
        string $class,
        bool $ifExists = false
    ) : string {
        $table = $class::getTableName();
        $sql = "DROP TABLE";
        if ($ifExists) {
            $sql .= " IF EXISTS";
        }
        $sql .= " `$table`";
        return $sql;
    }

    public static function getCreateTable(
        string $class,
        bool $ifNotExists = false
    ) : string {
        if ($createTable = $class::getCreateTable()) {
            return $createTable;
        }

        $table = $class::getTableName();
        $fields = static::getFieldsAsSql($class);
        $options = [static::getPrimaryKeyAsSql($class)];
        if ($keys = static::getIndexKeysAsSql($class)) {
            $options[] = $keys;
        }
        if ($unique = static::getUniqueKeysAsSql($class)) {
            $options[] = $unique;
        }
        if ($foreignKeys = static::getForeignKeysAsSql($class)) {
            $options[] = $foreignKeys;
        }
        $sql = "CREATE TABLE";
        if ($ifNotExists) {
            $sql .= " IF NOT EXISTS";
        }
        $sql .= " `$table`";
        $sql .= " ($fields, " . implode(', ', $options) . ")";
        $sql .= " ENGINE=InnoDB";
        $sql .= " DEFAULT CHARSET=utf8 COLLATE=utf8_swedish_ci";
        return $sql;
    }

    protected static function getFieldsAsSql(string $class) : string
    {
        $sql = [];
        $fieldsMeta = $class::getFields();
        foreach ($fieldsMeta as $field => $meta) {
            switch ($meta['type']) {
                case MetadataInterface::FIELD_TINYINT:
                case MetadataInterface::FIELD_SMALLINT:
                case MetadataInterface::FIELD_MEDIUMINT:
                case MetadataInterface::FIELD_INT:
                case MetadataInterface::FIELD_BIGINT:
                case MetadataInterface::FIELD_FLOAT:
                case MetadataInterface::FIELD_DOUBLE:
                    $sql[] = "`{$field}` " . strtolower($meta['type'])
                        . (isset($meta['unsigned']) && $meta['unsigned']
                            ? " unsigned"
                            : null
                        )
                        . (!isset($meta['null']) || $meta['null']
                            ? (isset($meta['default'])
                                ? " DEFAULT {$meta['default']}"
                                : " DEFAULT NULL"
                            ) : " NOT NULL" . (isset($meta['default'])
                                ? " DEFAULT {$meta['default']}"
                                : null
                            )
                        )
                        . (isset($meta['onInsert'])
                            && $meta['onInsert'] == MetadataInterface::AUTO_ID
                            ? ' AUTO_INCREMENT'
                            : null
                        );
                break;
                case MetadataInterface::FIELD_CHAR:
                case MetadataInterface::FIELD_VARCHAR:
                case MetadataInterface::FIELD_BINARY:
                case MetadataInterface::FIELD_VARBINARY:
                    $sql[] = "`{$field}` " . strtolower($meta['type']) . '('
                        . (isset($meta['length'])
                            ? $meta['length']
                            : 50
                        ). ')'
                        . (!isset($meta['null']) || $meta['null']
                            ? (isset($meta['default'])
                                ? " DEFAULT '{$meta['default']}'"
                                : " DEFAULT NULL"
                            ) : " NOT NULL" . (isset($meta['default'])
                                ? " DEFAULT '{$meta['default']}'"
                                : null
                            )
                        );
                break;
                case MetadataInterface::FIELD_TINYTEXT:
                case MetadataInterface::FIELD_TEXT:
                case MetadataInterface::FIELD_MEDIUMTEXT:
                case MetadataInterface::FIELD_LONGTEXT:
                case MetadataInterface::FIELD_JSON:
                case MetadataInterface::FIELD_DATE:
                case MetadataInterface::FIELD_DATETIME:
                case MetadataInterface::FIELD_TIME:
                case MetadataInterface::FIELD_YEAR:
                    $sql[] = "`{$field}` " . strtolower($meta['type'])
                        . (!isset($meta['null']) || $meta['null']
                            ? (isset($meta['default'])
                                ? " DEFAULT '{$meta['default']}'"
                                : " DEFAULT NULL"
                            ) : " NOT NULL" . (isset($meta['default'])
                                ? " DEFAULT '{$meta['default']}'"
                                : null
                            )
                        );
                break;
                case MetadataInterface::FIELD_ENUM:
                    $sql[] = "`{$field}` enum"
                        . "('" . implode("', '", $meta['values']) . "')"
                        . (!isset($meta['null']) || $meta['null']
                            ? (isset($meta['default'])
                                ? " DEFAULT '{$meta['default']}'"
                                : " DEFAULT NULL"
                            ) : " NOT NULL" . (isset($meta['default'])
                                ? " DEFAULT '{$meta['default']}'"
                                : null
                            )
                        );
                break;
                case MetadataInterface::FIELD_BOOLEAN:
                    $sql[] = "`{$field}` boolean"
                        . (!isset($meta['null']) || $meta['null']
                            ? (isset($meta['default'])
                                ? " DEFAULT {$meta['default']}"
                                : " DEFAULT NULL"
                            ) : " NOT NULL" . (isset($meta['default'])
                                ? " DEFAULT {$meta['default']}"
                                : null
                            )
                        );
                break;
            }
        }
        return implode(', ', $sql);
    }

    protected static function getPrimaryKeyAsSql(string $class) : string
    {
        return 'PRIMARY KEY (`' . implode('`, `', $class::getPrimaryKey()) . '`)';
    }

    protected static function getIndexKeysAsSql(string $class)
    {
        $indexKeys = $class::getIndexKeys();
        if (!isset($indexKeys)) {
            return null;
        }

        $sql = [];
        foreach ($indexKeys as $keyName => $fieldNames) {
            $sql[] = "KEY"
                . (is_string($keyName) ? " `{$keyName}`" : null)
                . " (`" . implode('`, `', $fieldNames) . "`)";
        }
        return implode(', ', $sql);
    }

    protected static function getUniqueKeysAsSql(string $class) : ?string
    {
        $uniqueKeys = $class::getUniqueKeys();
        if (!isset($uniqueMeta)) {
            return null;
        }

        $sql = [];
        foreach ($uniqueMeta as $uniqueName => $fieldNames) {
            $sql[] = "UNIQUE KEY"
                . (is_string($uniqueName) ? " `{$uniqueName}`" : null)
                . " (`" . implode('`, `', $fieldNames) . "`)";
        }
        return implode(', ', $sql);
    }

    protected static function getForeignKeysAsSql(string $class) : ?string
    {
        $foreignKeysMeta = $class::getForeignKeys();
        if (!isset($foreignKeysMeta)) {
            return null;
        }

        $sql = [];
        foreach ($foreignKeysMeta as $fieldName => $meta) {
            $table = $meta['references']['class']::getTableName();
            $sql[] = (isset($meta['name']) ? "CONSTRAINT `{$meta['name']}` " : null)
                . "FOREIGN KEY (`$fieldName`)"
                . " REFERENCES `{$table}` (`{$meta['references']['field']}`)"
                . (isset($meta['onUpdate'])
                    ? " ON UPDATE {$meta['onUpdate']}"
                    : " ON UPDATE NO ACTION"
                )
                . (isset($meta['onDelete'])
                    ? " ON DELETE {$meta['onDelete']}"
                    : " ON DELETE NO ACTION"
                );
        }
        return implode(', ', $sql);
    }
}

