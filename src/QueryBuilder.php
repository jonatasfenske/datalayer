<?php

declare(strict_types=1);

namespace CoffeeCode\DataLayer;

use PDO;
use PDOException;

/**
 * QueryBuilder fluente para construção dinâmica de consultas SQL
 * PHP 8.5: tipagem estrita, named arguments, union types
 */
class QueryBuilder
{
    private string $table;
    private ?DatabaseConfig $database;

    /** @var array<string> */
    private array $columns = ['*'];

    /** @var array<int, array{type: string, column: string, operator: string, value: mixed, boolean: string}> */
    private array $wheres = [];

    /** @var array<int, array{column: string, direction: string}> */
    private array $orders = [];

    /** @var array<int, array{type: string, table: string, first: string, operator: string, second: string}> */
    private array $joins = [];

    /** @var array<int, array{column: string}> */
    private array $groups = [];

    /** @var array<int, array{type: string, column: string, operator: string, value: mixed}> */
    private array $havings = [];

    private ?int $limitValue = null;
    private ?int $offsetValue = null;

    /** @var array<string, mixed> */
    private array $bindings = [];

    private int $bindingIndex = 0;

    public function __construct(string $table, ?DatabaseConfig $database = null)
    {
        $this->table = $table;
        $this->database = $database;
    }

    /**
     * Define colunas a selecionar
     *
     * @param string ...$columns
     * @return static
     */
    public function select(string ...$columns): static
    {
        $this->columns = $columns !== [] ? $columns : ['*'];
        return $this;
    }

    /**
     * Adiciona condição WHERE (AND)
     *
     * @param string|array<string, mixed>|\Closure $column
     * @param mixed $operator
     * @param mixed $value
     * @return static
     */
    public function where(string|array|\Closure $column, mixed $operator = null, mixed $value = null): static
    {
        return $this->addWhere($column, $operator, $value, 'AND');
    }

    /**
     * Adiciona condição WHERE (OR)
     *
     * @param string|array<string, mixed>|\Closure $column
     * @param mixed $operator
     * @param mixed $value
     * @return static
     */
    public function orWhere(string|array|\Closure $column, mixed $operator = null, mixed $value = null): static
    {
        return $this->addWhere($column, $operator, $value, 'OR');
    }

    /**
     * WHERE coluna IN (valores)
     *
     * @param string $column
     * @param array<mixed> $values
     * @param string $boolean
     * @return static
     */
    public function whereIn(string $column, array $values, string $boolean = 'AND'): static
    {
        if ($values === []) {
            // IN vazio é sempre falso
            $this->wheres[] = [
                'type' => 'raw',
                'column' => '1 = 0',
                'operator' => '',
                'value' => null,
                'boolean' => $boolean,
            ];
            return $this;
        }

        $placeholders = [];
        foreach ($values as $val) {
            $key = $this->nextBindingKey();
            $placeholders[] = ":{$key}";
            $this->bindings[$key] = $val;
        }

        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'operator' => implode(', ', $placeholders),
            'value' => null,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * WHERE coluna NOT IN (valores)
     *
     * @param string $column
     * @param array<mixed> $values
     * @return static
     */
    public function whereNotIn(string $column, array $values): static
    {
        if ($values === []) {
            return $this;
        }

        $placeholders = [];
        foreach ($values as $val) {
            $key = $this->nextBindingKey();
            $placeholders[] = ":{$key}";
            $this->bindings[$key] = $val;
        }

        $this->wheres[] = [
            'type' => 'not_in',
            'column' => $column,
            'operator' => implode(', ', $placeholders),
            'value' => null,
            'boolean' => 'AND',
        ];

        return $this;
    }

    /**
     * WHERE coluna BETWEEN min AND max
     *
     * @param string $column
     * @param mixed $min
     * @param mixed $max
     * @param string $boolean
     * @return static
     */
    public function whereBetween(string $column, mixed $min, mixed $max, string $boolean = 'AND'): static
    {
        $minKey = $this->nextBindingKey();
        $maxKey = $this->nextBindingKey();

        $this->bindings[$minKey] = $min;
        $this->bindings[$maxKey] = $max;

        $this->wheres[] = [
            'type' => 'between',
            'column' => $column,
            'operator' => ":{$minKey} AND :{$maxKey}",
            'value' => null,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * WHERE coluna IS NULL
     *
     * @param string $column
     * @param string $boolean
     * @param bool $not
     * @return static
     */
    public function whereNull(string $column, string $boolean = 'AND', bool $not = false): static
    {
        $this->wheres[] = [
            'type' => $not ? 'not_null' : 'null',
            'column' => $column,
            'operator' => '',
            'value' => null,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * WHERE coluna IS NOT NULL
     *
     * @param string $column
     * @param string $boolean
     * @return static
     */
    public function whereNotNull(string $column, string $boolean = 'AND'): static
    {
        return $this->whereNull($column, $boolean, not: true);
    }

    /**
     * WHERE coluna LIKE pattern
     *
     * @param string $column
     * @param string $pattern
     * @param string $boolean
     * @return static
     */
    public function whereLike(string $column, string $pattern, string $boolean = 'AND'): static
    {
        $key = $this->nextBindingKey();
        $this->bindings[$key] = $pattern;

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => 'LIKE',
            'value' => ":{$key}",
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Agrupa condições WHERE em parênteses via Closure
     *
     * @param \Closure(static): void $callback
     * @param string $boolean
     * @return static
     */
    public function whereGroup(\Closure $callback, string $boolean = 'AND'): static
    {
        $nested = new static($this->table, $this->database);
        $callback($nested);

        if ($nested->wheres !== []) {
            $this->wheres[] = [
                'type' => 'nested',
                'column' => '',
                'operator' => '',
                'value' => $nested,
                'boolean' => $boolean,
            ];
            $this->bindings = array_merge($this->bindings, $nested->bindings);
        }

        return $this;
    }

    /**
     * INNER JOIN
     *
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * @return static
     */
    public function join(string $table, string $first, string $operator, string $second): static
    {
        $this->joins[] = [
            'type' => 'INNER',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    /**
     * LEFT JOIN
     *
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * @return static
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        $this->joins[] = [
            'type' => 'LEFT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    /**
     * RIGHT JOIN
     *
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * @return static
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): static
    {
        $this->joins[] = [
            'type' => 'RIGHT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    /**
     * ORDER BY
     *
     * @param string $column
     * @param string $direction ASC ou DESC
     * @return static
     */
    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'ASC';
        }

        $this->orders[] = [
            'column' => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * ORDER BY DESC atalho
     *
     * @param string $column
     * @return static
     */
    public function orderByDesc(string $column): static
    {
        return $this->orderBy($column, 'DESC');
    }

    /**
     * GROUP BY
     *
     * @param string ...$columns
     * @return static
     */
    public function groupBy(string ...$columns): static
    {
        foreach ($columns as $column) {
            $this->groups[] = ['column' => $column];
        }

        return $this;
    }

    /**
     * HAVING
     *
     * @param string $column
     * @param string $operator
     * @param mixed $value
     * @return static
     */
    public function having(string $column, string $operator, mixed $value): static
    {
        $key = $this->nextBindingKey();
        $this->bindings[$key] = $value;

        $this->havings[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => ":{$key}",
        ];

        return $this;
    }

    /**
     * LIMIT
     *
     * @param int $value
     * @return static
     */
    public function limit(int $value): static
    {
        $this->limitValue = max(0, $value);
        return $this;
    }

    /**
     * OFFSET
     *
     * @param int $value
     * @return static
     */
    public function offset(int $value): static
    {
        $this->offsetValue = max(0, $value);
        return $this;
    }

    /**
     * Executa a consulta SELECT e retorna todos os resultados
     *
     * @return array<object>|null
     */
    public function get(): ?array
    {
        try {
            $pdo = Connect::getInstance($this->database);
            $stmt = $pdo->prepare($this->toSql());
            $stmt->execute($this->bindings);

            if ($stmt->rowCount() === 0) {
                return [];
            }

            return $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];
        } catch (PDOException) {
            return null;
        }
    }

    /**
     * Executa a consulta e retorna o primeiro resultado
     *
     * @return object|null
     */
    public function first(): ?object
    {
        $this->limitValue = 1;
        $results = $this->get();

        return $results[0] ?? null;
    }

    /**
     * Retorna count dos resultados
     *
     * @param string $column
     * @return int
     */
    public function count(string $column = '*'): int
    {
        $originalColumns = $this->columns;
        $this->columns = ["COUNT({$column}) as aggregate"];

        $result = $this->first();
        $this->columns = $originalColumns;

        return (int)($result?->aggregate ?? 0);
    }

    /**
     * Verifica se existem registros matching a query
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * Retorna valor máximo de uma coluna
     *
     * @param string $column
     * @return mixed
     */
    public function max(string $column): mixed
    {
        $originalColumns = $this->columns;
        $this->columns = ["MAX({$column}) as aggregate"];

        $result = $this->first();
        $this->columns = $originalColumns;

        return $result?->aggregate;
    }

    /**
     * Retorna valor mínimo de uma coluna
     *
     * @param string $column
     * @return mixed
     */
    public function min(string $column): mixed
    {
        $originalColumns = $this->columns;
        $this->columns = ["MIN({$column}) as aggregate"];

        $result = $this->first();
        $this->columns = $originalColumns;

        return $result?->aggregate;
    }

    /**
     * Retorna soma de uma coluna
     *
     * @param string $column
     * @return float|int|null
     */
    public function sum(string $column): float|int|null
    {
        $originalColumns = $this->columns;
        $this->columns = ["SUM({$column}) as aggregate"];

        $result = $this->first();
        $this->columns = $originalColumns;

        return $result?->aggregate;
    }

    /**
     * Retorna média de uma coluna
     *
     * @param string $column
     * @return float|null
     */
    public function avg(string $column): ?float
    {
        $originalColumns = $this->columns;
        $this->columns = ["AVG({$column}) as aggregate"];

        $result = $this->first();
        $this->columns = $originalColumns;

        return $result?->aggregate !== null ? (float)$result->aggregate : null;
    }

    /**
     * Insere um registro
     *
     * @param array<string, mixed> $data
     * @return string|false Last insert ID ou false
     */
    public function insert(array $data): string|false
    {
        try {
            $columns = implode(', ', array_keys($data));
            $keys = [];
            $insertBindings = [];

            foreach ($data as $key => $value) {
                $bindKey = "ins_{$key}";
                $keys[] = ":{$bindKey}";
                $insertBindings[$bindKey] = $value;
            }

            $sql = "INSERT INTO {$this->table} ({$columns}) VALUES (" . implode(', ', $keys) . ")";

            $pdo = Connect::getInstance($this->database);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($insertBindings);

            return $pdo->lastInsertId();
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * Insere múltiplos registros em batch
     *
     * @param array<int, array<string, mixed>> $rows
     * @return int Número de linhas inseridas
     */
    public function insertMany(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        try {
            $columns = array_keys($rows[0]);
            $columnList = implode(', ', $columns);
            $allBindings = [];
            $valueGroups = [];

            foreach ($rows as $rowIndex => $row) {
                $placeholders = [];
                foreach ($columns as $col) {
                    $bindKey = "batch_{$rowIndex}_{$col}";
                    $placeholders[] = ":{$bindKey}";
                    $allBindings[$bindKey] = $row[$col] ?? null;
                }
                $valueGroups[] = '(' . implode(', ', $placeholders) . ')';
            }

            $sql = "INSERT INTO {$this->table} ({$columnList}) VALUES " . implode(', ', $valueGroups);

            $pdo = Connect::getInstance($this->database);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($allBindings);

            return $stmt->rowCount();
        } catch (PDOException) {
            return 0;
        }
    }

    /**
     * Atualiza registros matching a query
     *
     * @param array<string, mixed> $data
     * @return int|null Linhas afetadas ou null em erro
     */
    public function update(array $data): ?int
    {
        try {
            $setClauses = [];
            $updateBindings = [];

            foreach ($data as $key => $value) {
                $bindKey = "upd_{$key}";
                $setClauses[] = "{$key} = :{$bindKey}";
                $updateBindings[$bindKey] = $value;
            }

            $whereSql = $this->compileWheres();
            $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses);

            if ($whereSql !== '') {
                $sql .= " WHERE {$whereSql}";
            }

            $pdo = Connect::getInstance($this->database);
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge($updateBindings, $this->bindings));

            return $stmt->rowCount();
        } catch (PDOException) {
            return null;
        }
    }

    /**
     * Deleta registros matching a query
     *
     * @return int|null Linhas afetadas ou null em erro
     */
    public function delete(): ?int
    {
        try {
            $whereSql = $this->compileWheres();
            $sql = "DELETE FROM {$this->table}";

            if ($whereSql !== '') {
                $sql .= " WHERE {$whereSql}";
            }

            $pdo = Connect::getInstance($this->database);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($this->bindings);

            return $stmt->rowCount();
        } catch (PDOException) {
            return null;
        }
    }

    /**
     * Compila a query SQL completa
     *
     * @return string
     */
    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns) . " FROM {$this->table}";

        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        $whereSql = $this->compileWheres();
        if ($whereSql !== '') {
            $sql .= " WHERE {$whereSql}";
        }

        if ($this->groups !== []) {
            $groupColumns = array_map(fn(array $g): string => $g['column'], $this->groups);
            $sql .= ' GROUP BY ' . implode(', ', $groupColumns);
        }

        if ($this->havings !== []) {
            $havingClauses = [];
            foreach ($this->havings as $having) {
                $havingClauses[] = "{$having['column']} {$having['operator']} {$having['value']}";
            }
            $sql .= ' HAVING ' . implode(' AND ', $havingClauses);
        }

        if ($this->orders !== []) {
            $orderClauses = array_map(
                fn(array $o): string => "{$o['column']} {$o['direction']}",
                $this->orders
            );
            $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
        }

        if ($this->limitValue !== null) {
            $sql .= " LIMIT {$this->limitValue}";
        }

        if ($this->offsetValue !== null) {
            $sql .= " OFFSET {$this->offsetValue}";
        }

        return $sql;
    }

    /**
     * Retorna bindings atuais
     *
     * @return array<string, mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Reseta o builder para nova consulta na mesma tabela
     *
     * @return static
     */
    public function reset(): static
    {
        $this->columns = ['*'];
        $this->wheres = [];
        $this->orders = [];
        $this->joins = [];
        $this->groups = [];
        $this->havings = [];
        $this->limitValue = null;
        $this->offsetValue = null;
        $this->bindings = [];
        $this->bindingIndex = 0;

        return $this;
    }

    /**
     * Adiciona condição WHERE genérica
     */
    private function addWhere(string|array|\Closure $column, mixed $operator, mixed $value, string $boolean): static
    {
        // Closure = grupo aninhado
        if ($column instanceof \Closure) {
            return $this->whereGroup($column, $boolean);
        }

        // Array associativo = múltiplas condições AND
        if (is_array($column)) {
            foreach ($column as $col => $val) {
                $this->addWhere($col, '=', $val, $boolean);
            }
            return $this;
        }

        // Se só passou coluna e valor, operador padrão é =
        if ($value === null && $operator !== null && !in_array(strtoupper((string)$operator), ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'], true)) {
            $value = $operator;
            $operator = '=';
        }

        $key = $this->nextBindingKey();
        $this->bindings[$key] = $value;

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => (string)$operator,
            'value' => ":{$key}",
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Compila cláusulas WHERE em string SQL
     */
    private function compileWheres(): string
    {
        if ($this->wheres === []) {
            return '';
        }

        $clauses = [];

        foreach ($this->wheres as $index => $where) {
            $prefix = $index > 0 ? " {$where['boolean']} " : '';

            $clause = match ($where['type']) {
                'basic' => "{$where['column']} {$where['operator']} {$where['value']}",
                'in' => "{$where['column']} IN ({$where['operator']})",
                'not_in' => "{$where['column']} NOT IN ({$where['operator']})",
                'between' => "{$where['column']} BETWEEN {$where['operator']}",
                'null' => "{$where['column']} IS NULL",
                'not_null' => "{$where['column']} IS NOT NULL",
                'raw' => $where['column'],
                'nested' => '(' . $where['value']->compileWheres() . ')',
                default => '',
            };

            $clauses[] = $prefix . $clause;
        }

        return implode('', $clauses);
    }

    /**
     * Gera chave única para binding
     */
    private function nextBindingKey(): string
    {
        return 'qb_' . $this->bindingIndex++;
    }
}