<?php

declare(strict_types=1);

namespace CoffeeCode\DataLayer;

use PDOException;
use Throwable;

/**
 * Trait para transações automáticas com closure
 * PHP 8.5: tipagem estrita, union types, never return type
 */
trait HasTransactions
{
 /**
 * Executa operações dentro de uma transação automática
 * Commit em sucesso, rollback em exceção
 *
 * @template T
 * @param callable(): T $callback
 * @return T Resultado do callback
 * @throws Throwable Re-lança exceção após rollback
 */
 public static function transaction(callable $callback): mixed
 {
 $pdo = Connect::getInstance();

 if ($pdo === null) {
 throw new \RuntimeException('Não foi possível obter conexão PDO para transação');
 }

 try {
 $pdo->beginTransaction();

 $result = $callback();

 $pdo->commit();

 return $result;
 } catch (Throwable $exception) {
 if ($pdo->inTransaction()) {
 $pdo->rollBack();
 }

 throw $exception;
 }
 }

 /**
 * Inicia transação manual (uso avançado)
 *
 * @return bool
 */
 public static function beginTransaction(): bool
 {
 $pdo = Connect::getInstance();
 return $pdo?->beginTransaction() ?? false;
 }

 /**
 * Confirma transação manual
 *
 * @return bool
 */
 public static function commit(): bool
 {
 $pdo = Connect::getInstance();
 return $pdo?->commit() ?? false;
 }

 /**
 * Reverte transação manual
 *
 * @return bool
 */
 public static function rollBack(): bool
 {
 $pdo = Connect::getInstance();
 return $pdo?->rollBack() ?? false;
 }

 /**
 * Verifica se há transação ativa
 *
 * @return bool
 */
 public static function inTransaction(): bool
 {
 $pdo = Connect::getInstance();
 return $pdo?->inTransaction() ?? false;
 }
}