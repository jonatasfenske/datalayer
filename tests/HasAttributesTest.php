<?php

declare(strict_types=1);

namespace JonatasFenske\DataLayer\Tests;

use JonatasFenske\DataLayer\HasAttributes;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários do trait HasAttributes (Casting) para PHP 8.5
 * Valida conversões de tipo sem dependência de banco de dados
 */
final class HasAttributesTest extends TestCase
{
 private object $model;

 protected function setUp(): void
 {
 $this->model = new class {
 use HasAttributes;

 /**
 * Expose protected methods for testing
 */
 public function testCastAttribute(string $key, mixed $value): mixed
 {
 return $this->castAttribute($key, $value);
 }

 public function testSerializeAttribute(string $key, mixed $value): mixed
 {
 return $this->serializeAttribute($key, $value);
 }

 public function testApplyCasts(?object $data): ?object
 {
 return $this->applyCasts($data);
 }

 public function testSerializeCasts(array $data): array
 {
 return $this->serializeCasts($data);
 }

 public function setCasts(array $casts): void
 {
 $this->casts = $casts;
 }
 };

 // Define casts via método para evitar conflito de propriedade com trait
 $this->model->setCasts([
 'id' => 'integer',
 'price' => 'float',
 'name' => 'string',
 'is_active' => 'boolean',
 'metadata' => 'json',
 'tags' => 'array',
 'created_at' => 'datetime',
 'birth_date' => 'date',
 'updated_ts' => 'timestamp',
 'balance' => 'decimal:2',
 'rate' => 'decimal:4',
 ]);
 }

 public function testCastInteger(): void
 {
 self::assertSame(42, $this->model->testCastAttribute('id', '42'));
 self::assertSame(0, $this->model->testCastAttribute('id', '0'));
 }

 public function testCastFloat(): void
 {
 self::assertSame(19.99, $this->model->testCastAttribute('price', '19.99'));
 self::assertSame(0.0, $this->model->testCastAttribute('price', '0'));
 }

 public function testCastString(): void
 {
 self::assertSame('123', $this->model->testCastAttribute('name', 123));
 self::assertSame('', $this->model->testCastAttribute('name', ''));
 }

 public function testCastBoolean(): void
 {
 self::assertTrue($this->model->testCastAttribute('is_active', '1'));
 self::assertTrue($this->model->testCastAttribute('is_active', 1));
 self::assertTrue($this->model->testCastAttribute('is_active', 'true'));
 self::assertFalse($this->model->testCastAttribute('is_active', '0'));
 self::assertFalse($this->model->testCastAttribute('is_active', 0));
 self::assertFalse($this->model->testCastAttribute('is_active', 'false'));
 }

 public function testCastJson(): void
 {
 $result = $this->model->testCastAttribute('metadata', '{"key":"value","num":42}');

 self::assertIsObject($result);
 self::assertSame('value', $result->key);
 self::assertSame(42, $result->num);
 }

 public function testCastJsonReturnsNullForInvalidJson(): void
 {
 self::assertNull($this->model->testCastAttribute('metadata', '{invalid json}'));
 }

 public function testCastJsonReturnsNullForEmptyString(): void
 {
 self::assertNull($this->model->testCastAttribute('metadata', ''));
 }

 public function testCastJsonPassesThroughArrayOrObject(): void
 {
 $arr = ['already' => 'decoded'];
 self::assertSame($arr, $this->model->testCastAttribute('metadata', $arr));
 }

 public function testCastArray(): void
 {
 $result = $this->model->testCastAttribute('tags', '["php","laravel"]');

 // array cast usa decodeJson que retorna object por padrão (assoc=false)
	 self::assertTrue(is_object($result) || is_array($result));
 }

 public function testCastDatetime(): void
 {
 $result = $this->model->testCastAttribute('created_at', '2025-06-15 10:30:00');

 self::assertInstanceOf(\DateTimeImmutable::class, $result);
 self::assertSame('2025-06-15 10:30:00', $result->format('Y-m-d H:i:s'));
 }

 public function testCastDatetimeReturnsNullForInvalidDate(): void
 {
 self::assertNull($this->model->testCastAttribute('created_at', 'not-a-date'));
 }

 public function testCastDatetimeReturnsNullForEmptyString(): void
 {
 self::assertNull($this->model->testCastAttribute('created_at', ''));
 }

 public function testCastDate(): void
 {
 $result = $this->model->testCastAttribute('birth_date', '1990-05-20 14:30:00');

 self::assertInstanceOf(\DateTimeImmutable::class, $result);
 self::assertSame('00:00:00', $result->format('H:i:s'));
 self::assertSame('1990-05-20', $result->format('Y-m-d'));
 }

 public function testCastTimestamp(): void
 {
 $result = $this->model->testCastAttribute('updated_ts', '2025-01-01 00:00:00');

 self::assertIsInt($result);
 self::assertSame((new \DateTimeImmutable('2025-01-01 00:00:00'))->getTimestamp(), $result);
 }

 public function testCastTimestampFromNumeric(): void
 {
 self::assertSame(1704067200, $this->model->testCastAttribute('updated_ts', 1704067200));
 self::assertSame(1704067200, $this->model->testCastAttribute('updated_ts', '1704067200'));
 }

 public function testCastDecimal(): void
 {
 self::assertSame('19.99', $this->model->testCastAttribute('balance', 19.99));
 self::assertSame('0.00', $this->model->testCastAttribute('balance', 0));
 self::assertSame('1234.50', $this->model->testCastAttribute('balance', '1234.5'));
 }

 public function testCastDecimalWithPrecision(): void
 {
 self::assertSame('3.1416', $this->model->testCastAttribute('rate', 3.14159265));
 }

 public function testCastReturnsNullForNullValue(): void
 {
 self::assertNull($this->model->testCastAttribute('id', null));
 self::assertNull($this->model->testCastAttribute('metadata', null));
 self::assertNull($this->model->testCastAttribute('created_at', null));
 }

 public function testCastReturnsOriginalForUnknownColumn(): void
 {
 self::assertSame('raw_value', $this->model->testCastAttribute('unknown_column', 'raw_value'));
 }

 public function testSerializeJson(): void
 {
 $result = $this->model->testSerializeAttribute('metadata', ['key' => 'value']);

 self::assertSame('{"key":"value"}', $result);
 }

 public function testSerializeJsonReturnsNullForNull(): void
 {
 self::assertNull($this->model->testSerializeAttribute('metadata', null));
 }

 public function testSerializeDatetime(): void
 {
 $dt = new \DateTimeImmutable('2025-06-15 10:30:00');
 $result = $this->model->testSerializeAttribute('created_at', $dt);

 self::assertSame('2025-06-15 10:30:00', $result);
 }

 public function testSerializeDate(): void
 {
 $dt = new \DateTimeImmutable('1990-05-20 14:30:00');
 $result = $this->model->testSerializeAttribute('birth_date', $dt);

 self::assertSame('1990-05-20', $result);
 }

 public function testSerializeBoolean(): void
 {
 self::assertSame(1, $this->model->testSerializeAttribute('is_active', true));
 self::assertSame(0, $this->model->testSerializeAttribute('is_active', false));
 }

 public function testSerializeReturnsOriginalForNonCastColumn(): void
 {
 self::assertSame('raw', $this->model->testSerializeAttribute('unknown', 'raw'));
 }

 public function testApplyCastsToDataObject(): void
 {
 $data = (object)[
 'id' => '42',
 'price' => '19.99',
 'is_active' => '1',
 'metadata' => '{"lang":"pt"}',
 'name' => 'Test',
 ];

 $result = $this->model->testApplyCasts($data);

 self::assertSame(42, $result->id);
 self::assertSame(19.99, $result->price);
 self::assertTrue($result->is_active);
 self::assertIsObject($result->metadata);
 self::assertSame('pt', $result->metadata->lang);
 }

 public function testApplyCastsReturnsNullForNullData(): void
 {
 self::assertNull($this->model->testApplyCasts(null));
 }

 public function testSerializeCastsInArray(): void
 {
 $data = [
 'metadata' => ['key' => 'val'],
 'is_active' => true,
 'created_at' => new \DateTimeImmutable('2025-01-01 12:00:00'),
 'name' => 'unchanged',
 ];

 $result = $this->model->testSerializeCasts($data);

 self::assertSame('{"key":"val"}', $result['metadata']);
 self::assertSame(1, $result['is_active']);
 self::assertSame('2025-01-01 12:00:00', $result['created_at']);
 self::assertSame('unchanged', $result['name']);
 }
}