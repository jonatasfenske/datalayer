<?php

declare(strict_types=1);

namespace CoffeeCode\DataLayer;

use DateTimeImmutable;
use PDOException;

/**
 * Trait para operações CRUD com tipagem estrita PHP 8.5
 */
trait CrudTrait
{
 /**
 * @param array<string, mixed> $data
 * @return string|false ID do último registro inserido ou false em caso de erro
 */
 protected function create(array $data): string|false
 {
 if ($this->timestamps) {
 $data['created_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
 }

 try {
 $columns = implode(', ', array_keys($data));
 $placeholders = ':' . implode(', :', array_keys($data));

 $stmt = Connect::getInstance($this->database);
 $prepare = $stmt->prepare("INSERT INTO {$this->entity} ({$columns}) VALUES ({$placeholders})");
 $prepare->execute($this->filter($data));

 return $stmt->lastInsertId();
 } catch (PDOException $exception) {
 $this->fail = $exception;
 return false;
 }
 }

 /**
 * @param array<string, mixed> $data
 * @param string $terms
 * @param string $params
 * @return int|null Número de linhas afetadas ou null em caso de erro
 */
 protected function update(array $data, string $terms, string $params): ?int
 {
 if ($this->timestamps) {
 $data['updated_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
 }

 try {
 $dateSet = [];
 foreach ($data as $bind => $value) {
 $dateSet[] = "{$bind} = :{$bind}";
 }
 $dateSet = implode(', ', $dateSet);
 parse_str($params, $parsedParams);

 $stmt = Connect::getInstance($this->database);
 $prepare = $stmt->prepare("UPDATE {$this->entity} SET {$dateSet} WHERE {$terms}");
 $prepare->execute($this->filter(array_merge($data, $parsedParams)));

 return $prepare->rowCount();
 } catch (PDOException $exception) {
 $this->fail = $exception;
 return null;
 }
 }

 /**
 * @param string $terms
 * @param string|null $params
 * @return bool
 */
 public function delete(string $terms, ?string $params = null): bool
 {
 try {
 $stmt = Connect::getInstance($this->database);
 $prepare = $stmt->prepare("DELETE FROM {$this->entity} WHERE {$terms}");

 if ($params !== null) {
 parse_str($params, $parsedParams);
 $prepare->execute($parsedParams);
 return true;
 }

 $prepare->execute();
 return true;
 } catch (PDOException $exception) {
 $this->fail = $exception;
 return false;
 }
 }

 /**
 * Filtra dados para prevenção de injeção
 *
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
 private function filter(array $data): array
 {
 $filter = [];
 foreach ($data as $key => $value) {
 $filter[$key] = is_null($value) ? null : filter_var($value, FILTER_DEFAULT);
 }
 return $filter;
 }
}