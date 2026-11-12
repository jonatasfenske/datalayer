<?php

declare(strict_types=1);

namespace CoffeeCode\DataLayer;

use PDO;

/**
 * Trait para relacionamentos entre models (BelongsTo, HasMany, BelongsToMany)
 * PHP 8.5: tipagem estrita, eager loading via QueryBuilder
 */
trait HasRelationships
{
    /** @var array<string, array<object>> Cache de relacionamentos carregados */
    private array $relations = [];

    /** @var array<string> Relacionamentos a carregar via eager loading */
    private array $eagerLoads = [];

    /**
     * Define quais relacionamentos devem ser carregados automaticamente
     *
     * @param string ...$relations
     * @return static
     */
    public function with(string ...$relations): static
    {
        $this->eagerLoads = array_merge($this->eagerLoads, $relations);
        return $this;
    }

    /**
     * Relacionamento BelongsTo (N:1) — este model pertence a outro
     *
     * @param string $relatedClass Classe do model relacionado
     * @param string $foreignKey Chave estrangeira neste model
     * @param string $ownerKey Chave primária no model relacionado
     * @return object|null
     */
    protected function belongsTo(string $relatedClass, string $foreignKey, string $ownerKey = 'id'): ?object
    {
        $cacheKey = "belongsTo:{$relatedClass}:{$foreignKey}";

        if (isset($this->relations[$cacheKey])) {
            return $this->relations[$cacheKey][0] ?? null;
        }

        $fkValue = $this->data?->$foreignKey;
        if ($fkValue === null) {
            return null;
        }

        /** @var DataLayer $related */
        $related = new $relatedClass();
        $result = $related->find("{$ownerKey} = :id", "id={$fkValue}")->fetch();

        $this->relations[$cacheKey] = [$result];

        return $result;
    }

    /**
     * Relacionamento HasMany (1:N) — este model tem muitos de outro
     *
     * @param string $relatedClass Classe do model relacionado
     * @param string $foreignKey Chave estrangeira no model relacionado
     * @param string $localKey Chave primária neste model
     * @return array<object>
     */
    protected function hasMany(string $relatedClass, string $foreignKey, string $localKey = 'id'): array
    {
        $cacheKey = "hasMany:{$relatedClass}:{$foreignKey}";

        if (isset($this->relations[$cacheKey])) {
            return $this->relations[$cacheKey];
        }

        $localValue = $this->data?->$localKey;
        if ($localValue === null) {
            return [];
        }

        /** @var DataLayer $related */
        $related = new $relatedClass();
        $results = $related->find("{$foreignKey} = :id", "id={$localValue}")->fetch(all: true);

        $this->relations[$cacheKey] = $results ?? [];

        return $this->relations[$cacheKey];
    }

    /**
     * Relacionamento BelongsToMany (N:M) — muitos para muitos via tabela pivot
     *
     * @param string $relatedClass Classe do model relacionado
     * @param string $pivotTable Nome da tabela pivot
     * @param string $foreignPivotKey FK deste model na pivot
     * @param string $relatedPivotKey FK do model relacionado na pivot
     * @param string $localKey Chave primária deste model
     * @param string $relatedKey Chave primária do model relacionado
     * @return array<object>
     */
    protected function belongsToMany(
        string $relatedClass,
        string $pivotTable,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $localKey = 'id',
        string $relatedKey = 'id',
    ): array {
        $cacheKey = "belongsToMany:{$relatedClass}:{$pivotTable}";

        if (isset($this->relations[$cacheKey])) {
            return $this->relations[$cacheKey];
        }

        $localValue = $this->data?->$localKey;
        if ($localValue === null) {
            return [];
        }

        /** @var DataLayer $related */
        $related = new $relatedClass();

        $builder = new QueryBuilder($related->entity, $related->database);
        $results = $builder
            ->select("{$related->entity}.*")
            ->join($pivotTable, "{$pivotTable}.{$relatedPivotKey}", '=', "{$related->entity}.{$relatedKey}")
            ->where("{$pivotTable}.{$foreignPivotKey}", '=', $localValue)
            ->get();

        $this->relations[$cacheKey] = $results ?? [];

        return $this->relations[$cacheKey];
    }

    /**
     * Carrega relacionamentos via eager loading para coleção de resultados
     *
     * @param array<DataLayer> $models
     * @param string $relationName Nome do método de relacionamento
     * @param string $relationType Tipo: belongsTo, hasMany, belongsToMany
     * @param string $relatedClass Classe do model relacionado
     * @param string $foreignKey Chave estrangeira
     * @param string $localKey Chave local
     * @return array<DataLayer> Models com relacionamentos carregados
     */
    protected function eagerLoadRelation(
        array $models,
        string $relationName,
        string $relationType,
        string $relatedClass,
        string $foreignKey,
        string $localKey = 'id',
    ): array {
        if ($models === []) {
            return $models;
        }

        // Coleta todos os IDs para query única
        $ids = array_unique(array_filter(
            array_map(fn(DataLayer $model): mixed => $model->data?->$localKey, $models),
            fn(mixed $id): bool => $id !== null
        ));

        if ($ids === []) {
            return $models;
        }

        /** @var DataLayer $related */
        $related = new $relatedClass();

        match ($relationType) {
            'belongsTo' => $this->eagerLoadBelongsTo($models, $related, $relationName, $foreignKey, $localKey, $ids),
            'hasMany' => $this->eagerLoadHasMany($models, $related, $relationName, $foreignKey, $localKey, $ids),
            default => null,
        };

        return $models;
    }

    /**
     * Eager load para BelongsTo
     */
    private function eagerLoadBelongsTo(
        array $models,
        DataLayer $related,
        string $relationName,
        string $foreignKey,
        string $localKey,
        array $ids,
    ): void {
        $builder = new QueryBuilder($related->entity, $related->database);
        $results = $builder->whereIn($localKey, $ids)->get() ?? [];

        // Indexa por chave primária
        $indexed = [];
        foreach ($results as $row) {
            $pk = $row->$localKey ?? null;
            if ($pk !== null) {
                $indexed[$pk] = $row;
            }
        }

        foreach ($models as $model) {
            $fkValue = $model->data?->$foreignKey;
            $cacheKey = "belongsTo:{$related::class}:{$foreignKey}";
            $model->relations[$cacheKey] = [($fkValue !== null && isset($indexed[$fkValue])) ? $indexed[$fkValue] : null];
        }
    }

    /**
     * Eager load para HasMany
     */
    private function eagerLoadHasMany(
        array $models,
        DataLayer $related,
        string $relationName,
        string $foreignKey,
        string $localKey,
        array $ids,
    ): void {
        $builder = new QueryBuilder($related->entity, $related->database);
        $results = $builder->whereIn($foreignKey, $ids)->get() ?? [];

        // Agrupa por chave estrangeira
        $grouped = [];
        foreach ($results as $row) {
            $fkValue = $row->$foreignKey ?? null;
            if ($fkValue !== null) {
                $grouped[$fkValue][] = $row;
            }
        }

        foreach ($models as $model) {
            $localValue = $model->data?->$localKey;
            $cacheKey = "hasMany:{$related::class}:{$foreignKey}";
            $model->relations[$cacheKey] = ($localValue !== null && isset($grouped[$localValue])) ? $grouped[$localValue] : [];
        }
    }

    /**
     * Obtém relacionamento carregado ou null
     *
     * @param string $key
     * @return mixed
     */
    protected function getRelation(string $key): mixed
    {
        return $this->relations[$key] ?? null;
    }

    /**
     * Limpa cache de relacionamentos
     *
     * @return static
     */
    public function clearRelations(): static
    {
        $this->relations = [];
        return $this;
    }
}