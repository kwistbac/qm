<?php
namespace Qm;

/**
* SQL query builder
*
* @author Kim Wistbacka <kim.wistbacka@gmail.com>
*
*/
class Sql
{
    private $dirty = false;

    private $sqlType = null;
    private $sqlParts = [
        'modifiers' => [],
        'fields' => [],
        'from' => [],
        'joins' => [],
        'where' => [],
        'groupBy' => [],
        'having' => [],
        'orderBy' => [],
        'limit' => null,
    ];

    private $sqlQuery = null;
    private $sqlParams = [];

    /**
    * Format
    *
    * Full:
    * [
    *   [operator] => [
    *       [condition, <parameter1>, ...],
    *       ...
    *   ]
    * ]
    *
    * Short (defaults to operator AND):
    * [
    *   [condition, <parameter1>, ...],
    *   ...
    * ]
    *
    * The default operator is AND.
    *
    **/
    public static function parseExpression(
        array $expression,
        array &$parameters,
        $operator = 'AND',
        $level = 0
    ) : string {
        $conditions = [];
        foreach ($expression as $key => $data) {
            if (empty($data)) {
                throw new \Exception('Invalid expression');
            }
            $first = reset($data);
            if (is_array($first)) {
                $conditions[] = self::parseExpression(
                    $data,
                    $parameters,
                    (is_string($key) ? $key : 'AND'),
                    $level + 1
                );
            } elseif ($first) {
                $conditions[] = array_shift($data);
                $parameters = array_merge($parameters, $data);
            }
        }
        if (count($conditions) > 1) {
            $condition = implode(" $operator ", $conditions);
            if ($operator != 'AND' && $level > 0) {
                return "($condition)";
            }
            return $condition;
        }
        if (!empty($conditions)) {
            return $conditions[0];
        }
        throw new \Exception('Invalid expression');
    }

    private static function parseConditions(
        array $conditions,
        array &$parameters
    ) : string {
        $sql = "";
        foreach ($conditions as $key => $condition) {
            if ($key) {
                $sql .= " $condition[0]";
            }
            if (is_array($condition[1])) {
                $sql .= ' ' . self::parseExpression(
                    (is_array(reset($condition[1])) ? $condition[1] : [$condition[1]]),
                    $parameters
                );
            } else {
                $sql .= " $condition[1]";
            }
        }
        return $sql;
    }

    /**
    * Get dirty state
    *
    * @return boolean
    */
    public function isDirty() : bool
    {
        return $this->dirty;
    }

    private function getSelectSql() : string
    {
        if (empty($this->sqlParts['fields'])) {
            throw new \InvalidArgumentException('No fields are defined');
        }

        return "SELECT"
            . (!empty($this->sqlParts['modifiers'])
                ? ' ' . implode(' ', array_keys($this->sqlParts['modifiers']))
                : null
            )
            . ' ' . implode(', ', $this->sqlParts['fields'])
            . (!empty($this->sqlParts['from'])
                ? ' ' . $this->getFromAsSql($this->sqlParams)
                : null
            )
            . (!empty($this->sqlParts['where'])
                ? ' WHERE' . self::parseConditions($this->sqlParts['where'], $this->sqlParams)
                : null
            )
            . (!empty($this->sqlParts['groupBy'])
                ? ' ' . $this->getGroupByAsSql()
                : null
            )
            . (!empty($this->sqlParts['having'])
                ? ' HAVING' . self::parseConditions($this->sqlParts['having'], $this->sqlParams)
                : null
            )
            . (!empty($this->sqlParts['orderBy'])
                ? ' ' . $this->getOrderByAsSql()
                : null
            )
            . (!empty($this->sqlParts['limit'])
                ? ' ' . $this->getLimitAsSql($this->sqlParams)
                : null
            );
    }

    /**
    * Get SQL query string
    *
    * @return string
    */
    public function getSql() : string
    {
        if ($this->dirty) {
            $this->sqlParams = [];
            $this->sqlQuery = null;
            $this->dirty = false;
        }
        if (!isset($this->sqlQuery) || $this->dirty) {
            switch ($this->sqlType) {
                case 'select':
                    $this->sqlQuery = $this->getSelectSql();
                break;
                default:
                    throw new \InvalidArgumentException("Invalid query type");
                break;
            }
        }
        return $this->sqlQuery;
    }

    /**
    * Get SQL query parameters
    *
    * @return array
    */
    public function getParameters() : array
    {
        return $this->sqlParams;
    }

    /**
    * Prepare query for debug
    *
    * @param string $sql
    * @param array $params
    * @return string
    */
    public static function prepareForDebug(string $sql, array $params) : string
    {
        $result = null;
        $parts = explode('?', $sql);
        foreach ($parts as $key => $part) {
            $result .= $part
                . (isset($params[$key])
                    ? (is_numeric($params[$key]) || is_bool($params[$key])
                        ? (int)$params[$key]
                        : "'" . $params[$key] . "'")
                    : null);
        }
        return $result;
    }

    /**
    * Execute query
    *
    * @param string $connectionName
    * @return Mapper
    */
    public function execute(?string $connectionName = null) : Mapper
    {
        $query = $this->getSql();
        $params = $this->getParameters();

        if (Qm::getDebug()) {
            error_log(Sql::prepareForDebug($query, $params));
        }

        $connection = Qm::getConnection($connectionName);
        $statement = $connection->prepare($query);
        if (!$statement->execute($params)) {
            throw new \Exception(
                "Failed to execute query: " . Sql::prepareForDebug($query, $params)
            );
        }
        $foundCount = null;
        if ($this->getModifier('SQL_CALC_FOUND_ROWS')) {
            $foundCount = $connection->query('SELECT FOUND_ROWS()')->fetchColumn();
        }
        return new Mapper($statement, $foundCount);
    }

    /**
    * Set select fields
    *
    * @param string|array $fields,...
    * @return self
    */
    public function select(...$fields) : self
    {
        $this->sqlType = __FUNCTION__;
        $this->sqlParts['fields'] = $fields;
        $this->dirty = true;
        return $this;
    }

    /**
    * Add select field
    *
    * @param string|array $field
    * @return self
    */
    public function addSelect($field) : self
    {
        $this->sqlType = 'select';
        $this->sqlParts['fields'][] = $field;
        $this->dirty = true;
        return $this;
    }

    /**
    * Get select fields
    *
    * @return array
    */
    public function getSelect() : array
    {
        return $this->sqlParts['fields'];
    }

    /**
    * Activate modifiers
    *
    * @param string $modifiers,...
    * @return self
    */
    public function withModifiers(string ...$modifiers) : self
    {
        $this->sqlParts['modifiers'] = array_fill_keys($modifiers, true);
        $this->dirty = true;
        return $this;
    }

    /**
    * Get modifier state
    *
    * @param string $modifier
    * @return bool
    */
    public function getModifier(string $modifier) : bool
    {
        return isset($this->sqlParts['modifiers'][$modifier]);
    }

    /**
    * Get active modifiers
    *
    * @return array
    */
    public function getModifiers() : array
    {
        return array_keys($this->sqlParts['modifiers']);
    }

    /**
    * Set from
    *
    * @param string|array $tables,...
    * @return self
    */
    public function from(...$tables) : self
    {
        $this->sqlParts['from'] = $tables;
        $this->dirty = true;
        return $this;
    }

    /**
    * Add from
    *
    * @param string|array $table,...
    * @return self
    */
    public function addFrom($table) : self
    {
        $this->sqlParts['from'][] = $table;
        $this->dirty = true;
        return $this;
    }

    /**
    * Get from
    *
    * @return array
    */
    public function getFrom() : array
    {
        return $this->sqlParts['from'];
    }

    public function innerJoin($table, $expression) : self
    {
        $this->sqlParts['joins'][] = ['INNER', $table, $expression];
        $this->dirty = true;
        return $this;
    }

    public function leftJoin($table, $expression) : self
    {
        $this->sqlParts['joins'][] = ['LEFT', $table, $expression];
        $this->dirty = true;
        return $this;
    }

    public function rightJoin($table, $expression) : self
    {
        $this->sqlParts['joins'][] = ['RIGHT', $table, $expression];
        $this->dirty = true;
        return $this;
    }

    public function setJoins(array $joins) : self
    {
        $this->sqlParts['joins'] = $joins;
        $this->dirty = true;
        return $this;
    }

    public function getJoins() : array
    {
        return $this->sqlParts['joins'];
    }

    private function getFromAsSql(array &$params) : string
    {
        $sql = "FROM " . implode(', ', $this->sqlParts['from']);
        if (empty($this->sqlParts['joins'])) {
            return $sql;
        }
        $joins = [];
        foreach ($this->sqlParts['joins'] as $join) {
            if (is_array($join[2])) {
                $condition = self::parseExpression(
                    (is_array(reset($join[2])) ? $join[2] : [$join[2]]),
                    $params
                );
            } else {
                $condition = $join[2];
            }
            if (empty($condition)) {
                throw new \Exception('Invalid join condition');
            }
            $joins[] = "{$join[0]} JOIN {$join[1]} ON $condition";
        }
        return $sql . ' ' . implode(' ', $joins);
    }




    /**
    * Format
    *
    * [condition, <parameter1>, ...]
    *
    * See Sql::setWhere for full format
    *
    **/
    public function where($expression) : self
    {
        $this->sqlParts['where'] = [['AND', $expression]];
        $this->dirty = true;
        return $this;
    }

    /**
    * Format
    *
    * [condition, <parameter1>, ...]
    *
    * See Sql::setWhere for full format
    *
    **/
    public function andWhere($expression) : self
    {
        $this->sqlParts['where'][] = ['AND', $expression];
        $this->dirty = true;
        return $this;
    }


    /**
    * Format
    *
    * [condition, <parameter1>, ...]
    *
    * See Sql::setWhere for full format
    *
    **/
    public function orWhere($expression) : self
    {
        $this->sqlParts['where'][] = ['OR', $expression];
        $this->dirty = true;
        return $this;
    }

    /**
    * Format
    *
    * Full:
    * [
    *   [operator] => [
    *       [condition, <parameter1>, ...],
    *       ...
    *   ]
    * ]
    *
    * Short (defaults to operator AND):
    * [
    *   [condition, <parameter1>, ...],
    *   ...
    * ]
    *
    * The default operator is AND.
    *
    **/
    public function setWhere(array $expression) : self
    {
        $this->sqlParts['where'] = $expression;
        $this->dirty = true;
        return $this;
    }

    public function getWhere() : array
    {
        return $this->sqlParts['where'];
    }


    /**
    * Format
    *
    *   [fieldName, direction],
    *   ...
    *
    **/
    public function groupBy(array ...$groupBy) : self
    {
        $this->sqlParts['groupBy'] = [];
        foreach ($groupBy as $values) {
            $this->addGroupBy(...$values);
        }
        return $this;
    }

    public function addGroupBy(string $fieldName, ?string $direction = null) : self
    {
        $values = [$fieldName];
        if (isset($direction)) {
            $dir = strtoupper($direction);
            if ($dir != 'ASC' && $dir != 'DESC') {
                throw new \InvalidArgumentException(
                    "Invalid direction \"$direction\""
                );
            }
            $values[] = $dir;
        }
        $this->sqlParts['groupBy'][] = $values;
        $this->dirty = true;
        return $this;
    }

    public function getGroupBy() : array
    {
        return $this->sqlParts['groupBy'];
    }

    private function getGroupByAsSql() : string
    {
        $groupBy = [];
        foreach ($this->sqlParts['groupBy'] as $values) {
            $groupBy[] = $values[0] . (isset($values[1]) ? " {$values[1]}" : null);
        }
        return "GROUP BY " . implode(', ', $groupBy);
    }



    /**
    * Format
    *
    * [condition, <parameter1>, ...]
    *
    * See Sql::setHaving for full format
    *
    **/
    public function having($condition) : self
    {
        $this->sqlParts['having'] = [['AND', $condition]];
        $this->dirty = true;
        return $this;
    }

    /**
    * Format
    *
    * [condition, <parameter1>, ...]
    *
    * See Sql::setHaving for full format
    *
    **/
    public function andHaving($condition) : self
    {
        $this->sqlParts['having'][] = ['AND', $condition];
        $this->dirty = true;
        return $this;
    }

    /**
    * Format
    *
    * [condition, <parameter1>, ...]
    *
    * See Sql::setHaving for full format
    *
    **/
    public function orHaving($condition) : self
    {
        $this->sqlParts['having'][] = ['OR', $condition];
        $this->dirty = true;
        return $this;
    }

    /**
    * Format
    *
    * Full:
    * [
    *   [operator] => [
    *       [condition, <parameter1>, ...],
    *       ...
    *   ]
    * ]
    *
    * Short (defaults to operator AND):
    * [
    *   [condition, <parameter1>, ...],
    *   ...
    * ]
    *
    * The default operator is AND.
    *
    **/
    public function setHaving(array $having) : self
    {
        $this->sqlParts['having'] = $having;
        $this->dirty = true;
        return $this;
    }

    public function getHaving() : array
    {
        return $this->sqlParts['having'];
    }


    /**
    * Format
    *
    * [
    *   [fieldName, direction],
    *   ...
    * ]
    *
    **/

    public function orderBy(array ...$orderBy) : self
    {
        $this->sqlParts['orderBy'] = [];
        foreach ($orderBy as $values) {
            $this->addOrderBy(...$values);
        }
        return $this;
    }

    public function addOrderBy(string $fieldName, ?string $direction = null) : self
    {
        $values = [$fieldName];
        if (isset($direction)) {
            $dir = strtoupper($direction);
            if ($dir != 'ASC' && $dir != 'DESC') {
                throw new \InvalidArgumentException(
                    "Invalid direction \"$direction\""
                );
            }
            $values[] = $dir;
        }
        $this->sqlParts['orderBy'][] = $values;
        $this->dirty = true;
        return $this;
    }

    public function getOrderBy() : array
    {
        return $this->sqlParts['orderBy'];
    }

    private function getOrderByAsSql() : string
    {
        $orderBy = [];
        foreach ($this->sqlParts['orderBy'] as $values) {
            $orderBy[] = $values[0] . (isset($values[1]) ? " {$values[1]}" : null);
        }
        return "ORDER BY " . implode(', ', $orderBy);
    }


    public function limit(?int $limit, ?int $offset = null) : self
    {
        if (isset($limit)) {
            $this->sqlParts['limit'] = [$limit, $offset];
        } else {
            if (isset($offset)) {
                $this->sqlParts['limit'] = [PHP_INT_MAX, $offset];
            } else {
                $this->sqlParts['limit'] = [];
            }
        }
        $this->dirty = true;
        return $this;
    }

    public function getLimit() : array
    {
        return $this->sqlParts['limit'];
    }

    private function getLimitAsSql(array &$params) : string
    {
        $params[] = $this->sqlParts['limit'][0];
        if (isset($this->sqlParts['limit'][1])) {
            $params[] = $this->sqlParts['limit'][1];
            return "LIMIT ? OFFSET ?";
        }
        return "LIMIT ?";
    }
}
