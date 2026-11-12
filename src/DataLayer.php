<?php

declare(strict_types=1);

namespace CoffeeCode\DataLayer;

use PDO;
use PDOException;
use stdClass;

/**
 * Classe abstrata DataLayer evoluída para PHP 8.5
 * Integra QueryBuilder, Soft Deletes, Relacionamentos, Casting, Transações e Eventos
 */
abstract class DataLayer
{
    use CrudTrait;
    use SoftDeletes;
    use HasRelationships;
    use HasAttributes;
    use HasTransactions;
    use HasEvents;

    /** @var string Nome da tabela no banco de dados */
    private readonly string $entity;

    /** @var string Campo chave primária */
    private readonly string $primary;

    /** @var array<string> Campos obrigatórios */
    private readonly array $required;

    /** @var bool Controle de timestamps automáticos */
    private readonly bool $timestamps;

    /** @var DatabaseConfig|null Configuração de conexão personalizada */
    protected readonly ?DatabaseConfig $database;

    /** @var bool Se true, habilita soft deletes neste model */
    protected bool $softDeletesEnabled = false;

    /** @var bool Flag interna: incluir registros trashed na query */
    protected bool $withTrashedFlag = false;

    /** @var bool Flag interna: retornar apenas registros trashed */
    protected bool $onlyTrashedFlag = false;

    protected ?string $statement = null;

    /** @var array<string, mixed>|null */
    protected ?array $params = null;

    protected ?string $group = null;
    protected ?string $order = null;
    protected ?string $limit = null;
    protected ?string $offset = null;
    protected ?PDOException $fail = null;
    protected ?object $data = null;

    /**
     * @param string $entity Nome da tabela
     * @param array<string> $required Campos obrigatórios
     * @param string $primary Chave primária (padrão: 'id')
     * @param bool $timestamps Habilitar created_at/updated_at automáticos
     * @param DatabaseConfig|null $database Configuração de conexão personalizada
     */
    public function __construct(
        string $entity,
        array $required,
        string $primary = 'id',
        bool $timestamps = true,
        ?DatabaseConfig $database = null,
    ) {
        $this->entity = $entity;
        $this->required = $required;
        $this->primary = $primary;
        $this->timestamps = $timestamps;
        $this->database = $database;
    }

    public function __set(string $name, mixed $value): void
    {
        if ($this->data === null) {
            $this->data = new stdClass();
        }

        $this->data->$name = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data?->$name);
    }

    public function __get(string $name): mixed
    {
        // Verifica accessor customizado (getNomeAttribute)
        $accessor = 'get' . str_replace('_', '', ucwords($name, '_')) . 'Attribute';
        if (method_exists($this, $accessor)) {
            return $this->$accessor($this->data?->$name ?? null);
        }

        // Verifica relacionamento carregado
        foreach ($this->relations as $key => $value) {
            if (str_contains($key, ":{$name}") || str_ends_with($key, ":{$name}")) {
                return is_array($value) && count($value) === 1 && !str_contains($key, 'hasMany') && !str_contains($key, 'belongsToMany')
                    ? $value[0]
                    : $value;
            }
        }

        // Aplica cast se definido
        $raw = $this->data?->$name ?? null;
        return $this->castAttribute($name, $raw);
    }

    /**
     * Retorna nova instância do QueryBuilder para esta entidade
     *
     * @return QueryBuilder
     */
    public function query(): QueryBuilder
    {
        $builder = new QueryBuilder($this->entity, $this->database);

        // Aplica escopo de soft deletes automaticamente
        if ($this->softDeletesEnabled) {
            $this->applySoftDeleteScope($builder);
        }

        return $builder;
    }

    /**
     * Inclui registros deletados logicamente na próxima query
     *
     * @return static
     */
    public function withTrashed(): static
    {
        $this->withTrashedFlag = true;
        return $this;
    }

    /**
     * Retorna apenas registros deletados logicamente
     *
     * @return static
     */
    public function onlyTrashed(): static
    {
        $this->onlyTrashedFlag = true;
        return $this;
    }

    /**
     * Reseta flags de soft delete
     *
     * @return static
     */
    public function withoutTrashed(): static
    {
        $this->withTrashedFlag = false;
        $this->onlyTrashedFlag = false;
        return $this;
    }

    /**
     * Retorna as colunas da tabela
     *
     * @return array<int, object>|null
     */
    public function columns(): ?array
    {
        $stmt = Connect::getInstance($this->database)?->prepare("DESCRIBE {$this->entity}");
        $stmt?->execute($this->params);
        return $stmt?->fetchAll(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Retorna os dados carregados com casts aplicados
     */
    public function data(): ?object
    {
        return $this->applyCasts($this->data);
    }

    /**
     * Retorna o último erro ocorrido
     */
    public function fail(): ?PDOException
    {
        return $this->fail;
    }

    /**
     * Constrói consulta SELECT com cláusula WHERE opcional
     *
     * @param string|null $terms Condição WHERE
     * @param string|null $params Parâmetros no formato query string
     * @param string $columns Colunas a selecionar
     * @return static
     */
    public function find(?string $terms = null, ?string $params = null, string $columns = '*'): static
    {
        if ($terms !== null) {
            $this->statement = "SELECT {$columns} FROM {$this->entity} WHERE {$terms}";
            parse_str($params ?? '', $this->params);
        } else {
            $this->statement = "SELECT {$columns} FROM {$this->entity}";
            $this->params = null;
        }

        // Aplica soft delete scope na query legada
        if ($this->softDeletesEnabled && !$this->withTrashedFlag && !$this->onlyTrashedFlag) {
            if ($terms !== null) {
                $this->statement .= " AND {$this->deletedAtColumn} IS NULL";
            } else {
                $this->statement .= " WHERE {$this->deletedAtColumn} IS NULL";
            }
        } elseif ($this->onlyTrashedFlag) {
            if ($terms !== null) {
                $this->statement .= " AND {$this->deletedAtColumn} IS NOT NULL";
            } else {
                $this->statement .= " WHERE {$this->deletedAtColumn} IS NOT NULL";
            }
        }

        return $this;
    }

    /**
     * Busca registro por ID
     */
    public function findById(int $id, string $columns = '*'): ?static
    {
        return $this->find("{$this->primary} = :id", "id={$id}", $columns)->fetch();
    }

    /**
     * Adiciona cláusula GROUP BY
     */
    public function group(string $column): static
    {
        $this->group = " GROUP BY {$column}";
        return $this;
    }

    /**
     * Adiciona cláusula ORDER BY
     */
    public function order(string $columnOrder): static
    {
        $this->order = " ORDER BY {$columnOrder}";
        return $this;
    }

    /**
     * Define limite de resultados
     */
    public function limit(int $limit): static
    {
        $this->limit = " LIMIT {$limit}";
        return $this;
    }

    /**
     * Adiciona cláusula IN para filtragem por múltiplos valores
     *
     * @param string $column Nome da coluna
     * @param array<int|string> $values Valores para filtro
     * @return static
     */
    public function in(string $column, array $values): static
    {
        $index = 0;
        $params = [];
        $statement = "{$column} IN (";

        foreach ($values as $value) {
            $index++;
            $separator = ($value === end($values)) ? '' : ',';
            $statement .= ":in_{$index}{$separator}";
            $params["in_{$index}"] = $value;
        }

        $statement .= ')';

        if (!str_contains($this->statement ?? '', 'WHERE')) {
            $this->statement .= " WHERE {$statement}";
        } else {
            $this->statement .= " AND {$statement}";
        }

        $this->params = $this->params !== null
            ? array_merge($this->params, $params)
            : $params;

        return $this;
    }

    /**
     * Define offset para paginação
     */
    public function offset(int $offset): static
    {
        $this->offset = " OFFSET {$offset}";
        return $this;
    }

    /**
     * Executa a consulta e retorna resultados com casts aplicados
     *
     * @param bool $all Se true, retorna todos os registros; se false, retorna apenas o primeiro
     * @return static|array<static>|null
     */
    public function fetch(bool $all = false): static|array|null
    {
        try {
            $stmt = Connect::getInstance($this->database)?->prepare(
                ($this->statement ?? '') . ($this->group ?? '') . ($this->order ?? '') . ($this->limit ?? '') . ($this->offset ?? '')
            );

            $stmt?->execute($this->params);

            if (!$stmt || $stmt->rowCount() === 0) {
                return null;
            }

            if ($all) {
                $results = $stmt->fetchAll(PDO::FETCH_CLASS, static::class);
                if ($results === false) {
                    return null;
                }
                // Aplica casts em cada resultado
                foreach ($results as $result) {
                    $result->data = $this->applyCasts($result->data);
                }
                return $results;
            }

            $result = $stmt->fetchObject(static::class);
            if ($result !== false) {
                $result->data = $this->applyCasts($result->data);
            }

            return $result !== false ? $result : null;
        } catch (PDOException $exception) {
            $this->fail = $exception;
            return null;
        }
    }

    /**
     * Conta o número de registros da consulta atual
     */
    public function count(): int
    {
        $stmt = Connect::getInstance($this->database)?->prepare($this->statement);
        $stmt?->execute($this->params);
        return $stmt?->rowCount() ?? 0;
    }

    /**
     * Paginação nativa integrada ao Paginator
     *
     * @param int $page Página atual
     * @param int $limit Registros por página
     * @param int $range Páginas adjacentes
     * @param string|null $hash Fragmento de URL
     * @return array{results: array<static>|null, paginator: \CoffeeCode\Paginator\Paginator}
     */
    public function paginate(int $page = 1, int $limit = 10, int $range = 3, ?string $hash = null): array
    {
        $total = $this->count();
        $offset = ($page - 1) * $limit;

        $results = $this
            ->limit($limit)
            ->offset($offset)
            ->fetch(all: true);

        $paginator = new \CoffeeCode\Paginator\Paginator();
        $paginator->pager(
            rows: $total,
            limit: $limit,
            page: $page,
            range: $range,
            hash: $hash,
        );

        return [
            'results' => $results,
            'paginator' => $paginator,
        ];
    }

    /**
     * Salva o registro atual (cria ou atualiza) com eventos e casting
     */
    public function save(): bool
    {
        $primary = $this->primary;
        $id = null;
        $save = null;
        $isUpdate = !empty($this->data?->$primary);

        try {
            // Dispara evento beforeSave
            if (!$this->fireEvent('beforeSave')) {
                return false;
            }

            // Dispara evento específico beforeCreate/beforeUpdate
            if (!$this->fireEvent($isUpdate ? 'beforeUpdate' : 'beforeCreate')) {
                return false;
            }

            if (!$this->required()) {
                throw new PDOException('Preencha os campos necessários');
            }

            $safeData = $this->serializeCasts($this->safe());

            // Atualização
            if ($isUpdate) {
                $id = $this->data->$primary;
                $save = $this->update($safeData, "{$this->primary} = :id", "id={$id}");
            }

            // Criação
            if (!$isUpdate) {
                $id = $this->create($safeData);
                $save = $id;
            }

            if ($save === false) {
                return false;
            }

            $this->data = $this->findById((int)$id)?->data();

            // Dispara eventos after
            $this->fireEvent($isUpdate ? 'afterUpdate' : 'afterCreate');
            $this->fireEvent('afterSave');

            return true;
        } catch (PDOException $exception) {
            $this->fail = $exception;
            return false;
        }
    }

    /**
     * Remove o registro atual (soft delete se habilitado, senão delete físico)
     */
    public function destroy(): bool
    {
        // Dispara evento beforeDelete
        if (!$this->fireEvent('beforeDelete')) {
            return false;
        }

        $result = $this->softDeletesEnabled
            ? $this->softDelete()
            : parent::destroy();

        if ($result) {
            $this->fireEvent('afterDelete');
        }

        return $result;
    }

    /**
     * Valida se todos os campos obrigatórios estão preenchidos
     */
    protected function required(): bool
    {
        $data = (array)$this->data();
        foreach ($this->required as $field) {
            if (empty($data[$field]) && !is_int($data[$field] ?? null)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Retorna dados seguros para persistência (exclui chave primária)
     *
     * @return array<string, mixed>
     */
    protected function safe(): array
    {
        $safe = (array)$this->data;
        unset($safe[$this->primary]);
        return $safe;
    }

    /**
     * Converte snake_case para camelCase
     */
    protected function toCamelCase(string $string): string
    {
        $camelCase = str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
        $camelCase[0] = strtolower($camelCase[0]);
        return $camelCase;
    }
}