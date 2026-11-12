<?php

declare(strict_types=1);

namespace CoffeeCode\DataLayer;

/**
 * Trait para eventos/hooks de lifecycle do model
 * PHP 8.5: tipagem estrita, suporte a closures e métodos nomeados
 */
trait HasEvents
{
 /** @var array<string, array<callable>> Hooks registrados por evento */
 private static array $eventListeners = [];

 /**
 * Registra hook para evento beforeSave
 *
 * @param callable(static): bool|void $callback Retorne false para cancelar a operação
 */
 public static function beforeSave(callable $callback): void
 {
 static::registerEvent('beforeSave', $callback);
 }

 /**
 * Registra hook para evento afterSave
 *
 * @param callable(static): void $callback
 */
 public static function afterSave(callable $callback): void
 {
 static::registerEvent('afterSave', $callback);
 }

 /**
 * Registra hook para evento beforeDelete
 *
 * @param callable(static): bool|void $callback Retorne false para cancelar a operação
 */
 public static function beforeDelete(callable $callback): void
 {
 static::registerEvent('beforeDelete', $callback);
 }

 /**
 * Registra hook para evento afterDelete
 *
 * @param callable(static): void $callback
 */
 public static function afterDelete(callable $callback): void
 {
 static::registerEvent('afterDelete', $callback);
 }

 /**
 * Registra hook para evento beforeCreate
 *
 * @param callable(static): bool|void $callback
 */
 public static function beforeCreate(callable $callback): void
 {
 static::registerEvent('beforeCreate', $callback);
 }

 /**
 * Registra hook para evento afterCreate
 *
 * @param callable(static): void $callback
 */
 public static function afterCreate(callable $callback): void
 {
 static::registerEvent('afterCreate', $callback);
 }

 /**
 * Registra hook para evento beforeUpdate
 *
 * @param callable(static): bool|void $callback
 */
 public static function beforeUpdate(callable $callback): void
 {
 static::registerEvent('beforeUpdate', $callback);
 }

 /**
 * Registra hook para evento afterUpdate
 *
 * @param callable(static): void $callback
 */
 public static function afterUpdate(callable $callback): void
 {
 static::registerEvent('afterUpdate', $callback);
 }

 /**
 * Dispara evento e retorna false se algum listener cancelou
 *
 * @param string $event Nome do evento
 * @return bool True se todos os listeners permitiram, false se algum cancelou
 */
 protected function fireEvent(string $event): bool
 {
 $class = static::class;
 $listeners = self::$eventListeners[$class][$event] ?? [];

 foreach ($listeners as $listener) {
 $result = $listener($this);
 if ($result === false) {
 return false;
 }
 }

 // Verifica método no próprio model (convenção: onBeforeSave, onAfterSave, etc.)
 $method = 'on' . ucfirst($event);
 if (method_exists($this, $method)) {
 $result = $this->$method();
 if ($result === false) {
 return false;
 }
 }

 return true;
 }

 /**
 * Registra listener para um evento
 */
 private static function registerEvent(string $event, callable $callback): void
 {
 $class = static::class;
 self::$eventListeners[$class][$event][] = $callback;
 }

 /**
 * Limpa todos os listeners de eventos (útil para testes)
 */
 public static function clearEvents(): void
 {
 unset(self::$eventListeners[static::class]);
 }
}