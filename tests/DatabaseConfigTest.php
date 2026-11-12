<?php

declare(strict_types=1);

namespace JonatasFenske\DataLayer\Tests;

use JonatasFenske\DataLayer\DatabaseConfig;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários do value object DatabaseConfig para PHP 8.5
 */
final class DatabaseConfigTest extends TestCase
{
    public function testConstructorWithAllParameters(): void
    {
        $config = new DatabaseConfig(
            driver: 'mysql',
            host: 'localhost',
            dbname: 'testdb',
            username: 'root',
            password: 'secret',
            port: 3306,
            options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );

        self::assertSame('mysql', $config->driver);
        self::assertSame('localhost', $config->host);
        self::assertSame('testdb', $config->dbname);
        self::assertSame('root', $config->username);
        self::assertSame('secret', $config->password);
        self::assertSame(3306, $config->port);
        self::assertCount(1, $config->options);
    }

    public function testDefaultPortAndOptions(): void
    {
        $config = new DatabaseConfig(
            driver: 'pgsql',
            host: 'db.example.com',
            dbname: 'app',
            username: 'admin',
            password: 'pass',
        );

        self::assertSame(3306, $config->port);
        self::assertSame([], $config->options);
    }

    public function testFromArrayWithFullConfig(): void
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'dbname' => 'mydb',
            'username' => 'user',
            'passwd' => 'pwd',
            'port' => 3307,
            'options' => ['custom' => true],
        ]);

        self::assertSame('mysql', $config->driver);
        self::assertSame('127.0.0.1', $config->host);
        self::assertSame('mydb', $config->dbname);
        self::assertSame('user', $config->username);
        self::assertSame('pwd', $config->password);
        self::assertSame(3307, $config->port);
    }

    public function testFromArrayAcceptsPasswordKey(): void
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'sqlite',
            'host' => '',
            'dbname' => ':memory:',
            'username' => '',
            'password' => 'alt-pass',
        ]);

        self::assertSame('alt-pass', $config->password);
    }

    public function testFromArrayPasswdTakesPrecedenceOverPassword(): void
    {
        $config = DatabaseConfig::fromArray([
            'driver' => 'mysql',
            'host' => 'localhost',
            'dbname' => 'db',
            'username' => 'u',
            'passwd' => 'primary',
            'password' => 'fallback',
        ]);

        self::assertSame('primary', $config->password);
    }

    public function testFromArrayUsesDefaultsForMissingKeys(): void
    {
        $config = DatabaseConfig::fromArray([]);

        self::assertSame('mysql', $config->driver);
        self::assertSame('localhost', $config->host);
        self::assertSame('', $config->dbname);
        self::assertSame('', $config->username);
        self::assertSame('', $config->password);
        self::assertSame(3306, $config->port);
        self::assertSame([], $config->options);
    }

    public function testReadonlyPropertiesCannotBeModified(): void
    {
        $config = new DatabaseConfig(
            driver: 'mysql',
            host: 'localhost',
            dbname: 'db',
            username: 'user',
            password: 'pass',
        );

        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line readonly property assignment should throw
        $config->driver = 'pgsql';
    }
}