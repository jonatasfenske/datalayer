<?php

declare(strict_types=1);

namespace CoffeeCode\DataLayer;

use PDO;
use PDOException;
use SensitiveParameter;

/**
 * Classe de conexão singleton com banco de dados
 * Modernizada para PHP 8.5 com readonly properties e atributos de segurança
 */
final class Connect
{
 /** @var array<string, PDO> */
 private static array $instances = [];

 private static ?PDOException $error = null;

 /**
 * Obtém uma instância PDO singleton para o banco de dados especificado
 *
 * @param DatabaseConfig|null $config Configuração do banco de dados
 * @return PDO|null Instância PDO ou null em caso de erro
 */
 public static function getInstance(?DatabaseConfig $config = null): ?PDO
 {
 $dbConf = $config ?? self::getDefaultConfig();
 $dbName = self::buildConnectionKey($dbConf);

 if (!isset(self::$instances[$dbName])) {
 try {
 $driver = DatabaseDriver::from($dbConf->driver);
 $dsn = $driver->buildDsn(
 $dbConf->host,
 $dbConf->dbname,
 $dbConf->port
 );

 self::$instances[$dbName] = new PDO(
 $dsn,
 $dbConf->username,
 $dbConf->password,
 $dbConf->options
 );

 self::$instances[$dbName]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
 self::$instances[$dbName]->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
 } catch (PDOException $exception) {
 self::$error = $exception;
 return null;
 }
 }

 return self::$instances[$dbName];
 }

 /**
 * Retorna o último erro de conexão ocorrido
 */
 public static function getError(): ?PDOException
 {
 return self::$error;
 }

 /**
 * Limpa todas as instâncias de conexão (útil para testes)
 */
 public static function flush(): void
 {
 self::$instances = [];
 self::$error = null;
 }

 /**
 * Constrói chave única para identificar a conexão
 */
 private static function buildConnectionKey(DatabaseConfig $config): string
 {
 return "{$config->driver}-{$config->dbname}@{$config->host}:{$config->port}";
 }

 /**
 * Retorna configuração padrão via constante global (compatibilidade retroativa)
 */
 private static function getDefaultConfig(): DatabaseConfig
 {
 if (!defined('DATA_LAYER_CONFIG')) {
 throw new \RuntimeException(
 'Constante DATA_LAYER_CONFIG não definida. Use DatabaseConfig explicitamente.'
 );
 }

 $conf = DATA_LAYER_CONFIG;

 return new DatabaseConfig(
 driver: $conf['driver'],
 host: $conf['host'],
 dbname: $conf['dbname'],
 username: $conf['username'],
 password: $conf['passwd'] ?? '',
 port: $conf['port'] ?? 3306,
 options: $conf['options'] ?? []
 );
 }

 private function __construct() {}
 private function __clone() {}
}