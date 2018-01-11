<?php
namespace Qm;

final class Qm
{
    protected static $debug = false;
    protected static $connections = [];
    protected static $connectionName = null;

    public static function getDebug() : bool
    {
        return self::$debug;
    }

    public static function setDebug(bool $debug) : void
    {
        self::$debug = $debug;
    }

    public static function createConnection(
        string $connectionName,
        string $dsn,
        ?string $username = null,
        ?string $passwd = null,
        array $options = []
    ) : void {
        if (isset(static::$connections[$connectionName])) {
            throw new \Exception("Connection $connectionName exists");
        }
        if (!isset(static::$connectionName)) {
            static::$connectionName = $connectionName;
        }
        static::$connections[$connectionName] = function() use(
            $dsn,
            $username,
            $passwd,
            $options
        ) {
            $options = array_replace(
                $options,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_STRINGIFY_FETCHES => false,
                    \PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            return new \PDO($dsn, $username, $passwd, $options);
        };
    }

    public static function addConnection(
        string $connectionName,
        \PDO $connection
    ) : void {
        if (isset(static::$connections[$connectionName])) {
            throw new \Exception("Connection $connectionName exists");
        }
        if (!isset(static::$connectionName)) {
            static::$connectionName = $connectionName;
        }
        static::$connections[$connectionName] = $connection;
    }

    public static function removeConnection(string $connectionName) : void {
        if (isset(static::$connections[$connectionName])) {
            throw new \Exception("Connection $connectionName does not exist");
        }
        unset(static::$connections[$connectionName]);
        if (static::$connectionName == $connectionName) {
            static::$connectionName = reset(array_keys(static::$connections));
        }
    }

    public static function getConnection(?string $connectionName = null) : \PDO
    {
        if (!isset($connectionName)) {
            $connectionName = static::$connectionName;
        }
        if (!isset(static::$connections[$connectionName])) {
            throw new \Exception("Invalid connection");
        }
        if (static::$connections[$connectionName] instanceof \Closure) {
            static::$connections[$connectionName]= static::$connections[$connectionName]();
        }
        return static::$connections[$connectionName];
    }
}
