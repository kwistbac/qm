<?php
namespace Qm;

/**
 * The Mapper class is a wrapper around a PDOStatement. It exposes three extended
 * fetch modes: Mapper::FETCH_ASSOC_NESTED, Mapper::FETCH_MAP and
 * Mapper::FETCH_MAP_NESTED. These fetch modes require a mapping format to be
 * supplied using the setMappingFormat method.
 *
 * @author Kim Wistbacka <kim.wistbacka@gmail.com>
 */
class Mapper implements \Iterator, \Countable
{
    const FETCH_MAP = -1;
    const FETCH_MAP_NESTED = -2;
    const FETCH_ASSOC_NESTED = -3;

    protected static $fetchModes = [
        self::FETCH_MAP => \PDO::FETCH_ASSOC,
        self::FETCH_MAP_NESTED => \PDO::FETCH_NUM,
        self::FETCH_ASSOC_NESTED => \PDO::FETCH_NUM,
    ];

    protected $statement = null;
    protected $foundCount = null;
    protected $fetchMode = null;

    // Mapping data
    protected $first = null;
    protected $classes = [];
    protected $fields = [];
    protected $nullables = [];
    protected $counts = [];

    // Iterator state
    protected $row = false;
    protected $position = null;

    /**
     * Constructor
     *
     * @param \PDOStatement $statement
     * @param ?int $foundCount
     * @param ?array $mappingFormat
     */
    public function __construct(
        \PDOStatement $statement,
        ?int $foundCount = null,
        ?array $mappingFormat = null
    ) {
        $this->statement = $statement;
        if (isset($foundCount)) {
            $this->foundCount = $foundCount;
        }
        if (isset($mappingFormat)) {
            $this->setMappingFormat($mappingFormat);
        }
    }

    /**
     * Get wrapped statement
     *
     * @return \PDOStatement
     */
    public function getStatement() : \PDOStatement
    {
        return $this->statement;
    }


    /**
    * Set mapping format
    *
    * @param array $mappingFormat
    * @return void
    */
    public function setMappingFormat(array $mappingFormat) : void
    {
        foreach ($mappingFormat as $alias => $format) {
            if (!empty($format)) {
                $this->counts[$alias] = count($format['fields']);
                $this->fields[$alias] = $format['fields'];
                if (isset($format['class'])) {
                    $this->classes[$alias] = $format['class'];
                }
                if (isset($format['null']) && $format['null']) {
                    $this->nullables[$alias] = $format['null'];
                }
            } else {
                $this->counts[$alias] = 1;
            }
        }
        $this->first = array_keys($mappingFormat)[0];

        if (!isset($this->fetchMode)) {
            // Set default fetch mode
            if (count($this->counts) > 1) {
                if (count($this->classes) == 0) {
                    $this->setFetchMode(self::FETCH_ASSOC_NESTED);
                } else {
                    $this->setFetchMode(self::FETCH_MAP_NESTED);
                }
            } elseif (count($this->classes) == 0) {
                $this->setFetchMode(\PDO::FETCH_ASSOC);
            } else {
                $this->setFetchMode(self::FETCH_MAP);
            }
        }
    }

    public function setFetchMode(...$fetchMode) : bool
    {
        $this->fetchMode = $fetchMode[0];
        if (isset(self::$fetchModes[$fetchMode[0]])) {
            return $this->statement->setFetchMode(
                self::$fetchModes[$fetchMode[0]],
                ...array_slice($fetchMode, 1)
            );
        }
        return $this->statement->setFetchMode(...$fetchMode);
    }

    public function fetch(
        ?int $fetchStyle = null,
        int $cursorOrientation = \PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ) {
        if (!isset($fetchStyle)) {
            $fetchStyle = $this->fetchMode;
        }
        if ($fetchStyle > 0) {
            return $this->statement->fetch($fetchStyle, $cursorOrientation, $cursorOffset);
        }
        $row = $this->statement->fetch(
            self::$fetchModes[$fetchStyle],
            $cursorOrientation,
            $cursorOffset
        );
        if ($row === false) {
            return false;
        }

        switch ($fetchStyle) {
            case self::FETCH_MAP:
                if (isset($this->classes[$this->first])) {
                    return new $this->classes[$this->first](
                        $this->connectionName,
                        $row
                    );
                }
                return $row;
            break;
            case self::FETCH_MAP_NESTED:
                $result = [];
                $offset = 0;
                foreach ($this->counts as $alias => $count) {
                    if (isset($this->fields[$alias])) {
                        $values = array_combine(
                            $this->fields[$alias],
                            array_slice($row, $offset, $count)
                        );
                        if (isset($this->nullables[$alias])
                            && empty(array_filter($values))) {
                            $result[$alias] = null;
                        } elseif (isset($this->classes[$alias])) {
                            $result[$alias] = new $this->classes[$alias](
                                $this->connectionName,
                                $values
                            );
                        } else {
                            $result[$alias] = $values;
                        }
                    } else {
                        $result[$alias] = $row[$offset];
                    }
                    $offset += $count;
                }
                return $result;
            break;
            case self::FETCH_ASSOC_NESTED:
                $result = [];
                $offset = 0;
                foreach ($this->counts as $alias => $count) {
                    if (isset($this->fields[$alias])) {
                        $values = array_combine(
                            $this->fields[$alias],
                            array_slice($row, $offset, $count)
                        );
                        if (isset($this->nullables[$alias])
                            && empty(array_filter($values))) {
                            $result[$alias] = null;
                        } else {
                            $result[$alias] = $values;
                        }
                    } else {
                        $result[$alias] = $row[$offset];
                    }
                    $offset += $count;
                }
                return $result;
            break;
            default:
                throw new \Exception("Unknown fetch mode: $fetchStyle");
            break;
        }
    }

    public function fetchAll(?int $fetchStyle = null, ...$args)
    {
        if (!isset($fetchStyle)) {
            $fetchStyle = $this->fetchMode;
        }
        if ($fetchStyle > 0) {
            return $this->statement->fetchAll($fetchStyle, ...$args);
        }

        switch ($fetchStyle) {
            case self::FETCH_MAP:
                if (isset($this->classes[$this->first])) {
                    $result = [];
                    foreach ($this->statement as $row) {
                        $result[] = new $this->classes[$this->first](
                            $this->connectionName,
                            $row
                        );
                    }
                    return $result;
                } else {
                    return $this->statement->fetchAll(
                        self::$fetchModes[$fetchStyle],
                        ...$args
                    );
                }
            break;
            case self::FETCH_MAP_NESTED:
                foreach ($this->statement as $row) {
                    $result = [];
                    $offset = 0;
                    foreach ($this->counts as $alias => $count) {
                        if (isset($this->fields[$alias])) {
                            $values = array_combine(
                                $this->fields[$alias],
                                array_slice($row, $offset, $count)
                            );
                            if (isset($this->nullables[$alias])
                                && empty(array_filter($values))) {
                                $result[$alias] = null;
                            } elseif (isset($this->classes[$alias])) {
                                $result[$alias] = new $this->classes[$alias](
                                    $this->connectionName,
                                    $values
                                );
                            } else {
                                $result[$alias] = $values;
                            }
                        } else {
                            $result[$alias] = $row[$offset];
                        }
                        $offset += $count;
                    }
                    $results[] = $result;
                }
                return $results;
            break;
            case self::FETCH_ASSOC_NESTED:
                foreach ($this->statement as $row) {
                    $result = [];
                    $offset = 0;
                    foreach ($this->counts as $alias => $count) {
                        if (isset($this->fields[$alias])) {
                            $values = array_combine(
                                $this->fields[$alias],
                                array_slice($row, $offset, $count)
                            );
                            if (isset($this->nullables[$alias])
                                && empty(array_filter($values))) {
                                $result[$alias] = null;
                            } else {
                                $result[$alias] = $values;
                            }
                        } else {
                            $result[$alias] = $row[$offset];
                        }
                        $offset += $count;
                    }
                    $results[] = $result;
                }
                return $results;
            break;
            /* case self::FETCH_ASSOC_GRAPH:
                $current = null;
                $result = [];
                $offset = 0;
                foreach ($this->counts as $alias => $count) {
                    $values = array_combine(
                        $this->fields[$alias],
                        array_slice($row, $offset, $count)
                    );
                    if (!isset($this->nullables[$alias])
                        || !empty(array_filter($values))) {
                        $id = $values[$this->ids[$alias]];
                        if ($offset == 0 && $id != $current) {
                            $result[$id] = $values;
                            $current = $id;
                        } elseif ($offset != 0
                            && !isset($result[$current][$alias][$id])) {
                            $result[$current][$alias][$id] = $values;
                        }
                    }
                    $offset += $count;
                }
                return $result;
            break; */
            default:
                throw new \Exception("Unknown fetch mode: $fetchStyle");
            break;
        }
    }

    public function fetchColumn(int $columnNumber = 0)
    {
        return $this->statement->fetchColumn($columnNumber);
    }

    public function getColumnMeta(int $column) : array
    {
        return $this->statement->getColumnMeta($column);
    }

    public function nextRowset() : bool
    {
        return $this->statement->nextRowset();
    }

    public function bindColumn(
        $column,
        &$param,
        int $type = null,
        int $maxlen = null,
        $driverdata = null
    ) : bool {
        return $this->statement->bindColumn($column, $param, $type, $maxlen, $driverdata);
    }

    public function closeCursor() : bool
    {
        return $this->statement->closeCursor();
    }

    public function columnCount() : int
    {
        return $this->statement->columnCount();
    }

    public function debugDumpParams() : void
    {
        $this->statement->debugDumpParams();
    }

    public function errorCode() : string
    {
        return $this->statement->errorCode();
    }

    public function errorInfo() :  array
    {
        return $this->statement->errorInfo();
    }

    public function setAttribute(int $attribute, $value) : bool
    {
        return $this->statement->setAttribute($attribute, $value);
    }

    public function getAttribute(int $attribute)
    {
        return $this->statement->getAttribute($attribute);
    }

    /**
    * Get row count
    * @param integer
    */
    public function rowCount() : int
    {
        return $this->statement->rowCount();
    }

    /**
     * Get found count
     *
     * @param integer
     */
    public function foundCount() : int
    {
        if (!isset($this->foundCount)) {
            throw new \Exception('Found count is not defined');
        }
        return $this->foundCount;
    }



    /**
     * Implementation of \Iterator interface
     */

    /**
     * @inheritDoc
     */
    public function current()
    {
        return $this->row;
    }

    /**
     * @inheritDoc
     */
    public function next()
    {
        $this->row = $this->fetch(null, \PDO::FETCH_ORI_ABS, ++$this->position);
    }

    /**
     * @inheritDoc
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * @inheritDoc
     */
    public function valid()
    {
        return ($this->row !== false);
    }

    /**
     * @inheritDoc
     */
    public function rewind()
    {
        $this->position = 0;
        $this->row = $this->fetch(null, \PDO::FETCH_ORI_ABS);
    }



    /**
     * Implementation of \Countable interface
     */

     /**
     * @inheritDoc
     */
    public function count()
    {
        return $this->statement->rowCount();
    }
}
