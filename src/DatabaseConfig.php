<?php

declare(strict_types=1);

namespace CoffeeCode\DataLayer;

use SensitiveParameter;

/**
 * Value Object imutável para configuração de banco de dados
 * PHP 8.5: readonly class com constructor promotion e SensitiveParameter
 */
readonly class DatabaseConfig
{
    /**
     * @param string $driver Driver do banco (mysql, pgsql, sqlite, sqlsrv)
     * @param string $host Host do servidor
     * @param string $dbname Nome do banco de dados
     * @param string $username Usuário de conexão
     * @param string $password Senha de conexão (marcada como sensível)
     * @param int|string $port Porta de conexão
     * @param array<int, mixed> $options Opções PDO adicionais
     */
    public function __construct(
        public string $driver,
        public string $host,
        public string $dbname,
        public string $username,
        #[SensitiveParameter]
        public string $password,
        public int|string $port = 3306,
        public array $options = [],
    ) {}

    /**
     * Cria configuração a partir de array (compatibilidade retroativa)
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            driver: $config['driver'] ?? 'mysql',
            host: $config['host'] ?? 'localhost',
            dbname: $config['dbname'] ?? '',
            username: $config['username'] ?? '',
            password: $config['passwd'] ?? $config['password'] ?? '',
            port: $config['port'] ?? 3306,
            options: $config['options'] ?? [],
        );
    }
}