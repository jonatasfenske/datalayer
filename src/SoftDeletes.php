<?php

declare(strict_types=1);

namespace JonatasFenske\DataLayer;

use DateTimeImmutable;

/**
 * Trait para suporte a Soft Deletes (exclusão lógica)
 * PHP 8.5: tipagem estrita, integração com QueryBuilder
 */
trait SoftDeletes
{
    /**
     * Nome da coluna que armazena a data de exclusão lógica
     */
    protected string $deletedAtColumn = 'deleted_at';

    /**
     * Escopo global: exclui registros deletados logicamente por padrão
     */
    protected function applySoftDeleteScope(QueryBuilder $builder): void
    {
        if (!$this->softDeletesEnabled) {
            return;
        }

        if ($this->withTrashedFlag) {
            return;
        }

        if ($this->onlyTrashedFlag) {
            $builder->whereNotNull($this->deletedAtColumn);
            return;
        }

        $builder->whereNull($this->deletedAtColumn);
    }

    /**
     * Realiza soft delete no registro atual
     */
    public function softDelete(): bool
    {
        if (!$this->softDeletesEnabled) {
            return false;
        }

        $primary = $this->primary;
        $id = $this->data?->$primary;

        if (empty($id)) {
            return false;
        }

        try {
            $builder = new QueryBuilder($this->entity, $this->database);
            $affected = $builder
                ->where($primary, '=', $id)
                ->update([$this->deletedAtColumn => (new DateTimeImmutable())->format('Y-m-d H:i:s')]);

            return $affected !== null && $affected > 0;
        } catch (\PDOException) {
            return false;
        }
    }

    /**
     * Restaura um registro deletado logicamente
     */
    public function restore(): bool
    {
        if (!$this->softDeletesEnabled) {
            return false;
        }

        $primary = $this->primary;
        $id = $this->data?->$primary;

        if (empty($id)) {
            return false;
        }

        try {
            $builder = new QueryBuilder($this->entity, $this->database);
            $affected = $builder
                ->where($primary, '=', $id)
                ->whereNotNull($this->deletedAtColumn)
                ->update([$this->deletedAtColumn => null]);

            return $affected !== null && $affected > 0;
        } catch (\PDOException) {
            return false;
        }
    }

    /**
     * Verifica se o registro atual está deletado logicamente
     */
    public function isTrashed(): bool
    {
        return !empty($this->data->{$this->deletedAtColumn});
    }

    /**
     * Força exclusão física mesmo com soft deletes ativo
     */
    public function forceDelete(): bool
    {
        $primary = $this->primary;
        $id = $this->data?->$primary;

        if (empty($id)) {
            return false;
        }

        return $this->delete("{$this->primary} = :id", "id={$id}");
    }
}