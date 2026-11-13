<?php

declare(strict_types=1);

namespace JonatasFenske\DataLayer\Tests;

use JonatasFenske\DataLayer\DatabaseDriver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários do enum DatabaseDriver para PHP 8.5
 */
final class DatabaseDriverTest extends TestCase
{
    public function testEnumValues(): void
    {
        self::assertSame('mysql', DatabaseDriver::MySQL->value);
        self::assertSame('pgsql', DatabaseDriver::PostgreSQL->value);
        self::assertSame('sqlite', DatabaseDriver::SQLite->value);
        self::assertSame('sqlsrv', DatabaseDriver::SQLServer->value);
    }

    public function testBuildDsnForMysql(): void
    {
        $dsn = DatabaseDriver::MySQL->buildDsn('localhost', 'mydb', 3306);

        self::assertStringContainsString('mysql:host=localhost', $dsn);
        self::assertStringContainsString('dbname=mydb', $dsn);
        self::assertStringContainsString('port=3306', $dsn);
        self::assertStringContainsString('charset=utf8mb4', $dsn);
    }

    public function testBuildDsnForPostgresql(): void
    {
        $dsn = DatabaseDriver::PostgreSQL->buildDsn('db.host.com', 'appdb', 5432);

        self::assertStringContainsString('pgsql:host=db.host.com', $dsn);
        self::assertStringContainsString('dbname=appdb', $dsn);
        self::assertStringContainsString('port=5432', $dsn);
    }

    public function testBuildDsnForSqlite(): void
    {
        $dsn = DatabaseDriver::SQLite->buildDsn('', '/path/to/db.sqlite', '');

        self::assertSame('sqlite:/path/to/db.sqlite', $dsn);
    }

    public function testBuildDsnForSqlServer(): void
    {
        $dsn = DatabaseDriver::SQLServer->buildDsn('sqlserver.local', 'erpdb', 1433);

        self::assertStringContainsString('sqlsrv:Server=sqlserver.local,1433', $dsn);
        self::assertStringContainsString('Database=erpdb', $dsn);
    }

    public function testRequiresPortReturnsFalseForSqlite(): void
    {
        self::assertFalse(DatabaseDriver::SQLite->requiresPort());
    }

    public function testRequiresPortReturnsTrueForNetworkDrivers(): void
    {
        self::assertTrue(DatabaseDriver::MySQL->requiresPort());
        self::assertTrue(DatabaseDriver::PostgreSQL->requiresPort());
        self::assertTrue(DatabaseDriver::SQLServer->requiresPort());
    }

    public function testFromValidValue(): void
    {
        $driver = DatabaseDriver::from('mysql');

        self::assertSame(DatabaseDriver::MySQL, $driver);
    }

    public function testFromInvalidValueThrowsException(): void
    {
        $this->expectException(\ValueError::class);
        DatabaseDriver::from('oracle');
    }

    #[DataProvider('invalidDriverProvider')]
    public function testTryFromReturnsNullForInvalidValue(string $value): void
    {
        $driver = DatabaseDriver::tryFrom($value);

        self::assertNull($driver);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidDriverProvider(): array
    {
        return [
            'unknown driver' => ['oracle'],
        ];
    }

    public function testTryFromReturnsEnumForValidValue(): void
    {
        $driver = DatabaseDriver::tryFrom('pgsql');

        self::assertSame(DatabaseDriver::PostgreSQL, $driver);
    }
}