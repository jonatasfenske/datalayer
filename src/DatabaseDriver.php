<?php

declare(strict_types=1);

namespace JonatasFenske\DataLayer;

/**
 * Enum de drivers de banco de dados suportados
 * PHP 8.5 Backed Enum para type-safety em configurações de conexão
 */
enum DatabaseDriver: string
{
    case MySQL = 'mysql';
    case PostgreSQL = 'pgsql';
    case SQLite = 'sqlite';
    case SQLServer = 'sqlsrv';

    /**
     * Retorna o formato DSN apropriado para cada driver
     */
    public function buildDsn(string $host, string $dbname, int|string $port): string
    {
        return match ($this) {
            self::MySQL => "mysql:host={$host};dbname={$dbname};port={$port};charset=utf8mb4",
            self::PostgreSQL => "pgsql:host={$host};dbname={$dbname};port={$port}",
            self::SQLite => "sqlite:{$dbname}",
            self::SQLServer => "sqlsrv:Server={$host},{$port};Database={$dbname}",
        };
    }

    /**
     * Verifica se o driver requer configuração especial de porta
     */
    public function requiresPort(): bool
    {
        return match ($this) {
            self::SQLite => false,
            default => true,
        };
    }
}