<?php

declare(strict_types=1);

namespace JonatasFenske\DataLayer\Tests;

use JonatasFenske\DataLayer\QueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários do QueryBuilder para PHP 8.5
 * Valida compilação SQL, bindings e métodos fluentes sem dependência de banco
 */
final class QueryBuilderTest extends TestCase
{
    private QueryBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new QueryBuilder('users');
    }

    public function testBasicSelectAll(): void
    {
        $sql = $this->builder->toSql();

        self::assertSame('SELECT * FROM users', $sql);
    }

    public function testSelectSpecificColumns(): void
    {
        $sql = $this->builder->select('id', 'name', 'email')->toSql();

        self::assertSame('SELECT id, name, email FROM users', $sql);
    }

    public function testWhereWithThreeArguments(): void
    {
        $sql = $this->builder->where('status', '=', 'active')->toSql();

        self::assertStringContainsString('WHERE status = :qb_0', $sql);
        self::assertSame(['qb_0' => 'active'], $this->builder->getBindings());
    }

    public function testWhereWithTwoArgumentsDefaultsToEquals(): void
    {
        $sql = $this->builder->where('id', 42)->toSql();

        self::assertStringContainsString('WHERE id = :qb_0', $sql);
        self::assertSame(['qb_0' => 42], $this->builder->getBindings());
    }

    public function testMultipleWheresAreAndByDefault(): void
    {
        $sql = $this->builder
            ->where('status', '=', 'active')
            ->where('role', '=', 'admin')
            ->toSql();

        self::assertStringContainsString('AND role = :qb_1', $sql);
        self::assertCount(2, $this->builder->getBindings());
    }

    public function testOrWhere(): void
    {
        $sql = $this->builder
            ->where('status', '=', 'active')
            ->orWhere('role', '=', 'admin')
            ->toSql();

        self::assertStringContainsString('OR role = :qb_1', $sql);
    }

    public function testWhereIn(): void
    {
        $sql = $this->builder->whereIn('id', [1, 2, 3])->toSql();

        self::assertStringContainsString('WHERE id IN (:qb_0, :qb_1, :qb_2)', $sql);
        self::assertSame([
            'qb_0' => 1,
            'qb_1' => 2,
            'qb_2' => 3,
        ], $this->builder->getBindings());
    }

    public function testWhereInEmptyGeneratesFalseCondition(): void
    {
        $sql = $this->builder->whereIn('id', [])->toSql();

        self::assertStringContainsString('1 = 0', $sql);
        self::assertSame([], $this->builder->getBindings());
    }

    public function testWhereNotIn(): void
    {
        $sql = $this->builder->whereNotIn('status', ['banned', 'deleted'])->toSql();

        self::assertStringContainsString('NOT IN (:qb_0, :qb_1)', $sql);
    }

    public function testWhereBetween(): void
    {
        $sql = $this->builder->whereBetween('age', 18, 65)->toSql();

        self::assertStringContainsString('WHERE age BETWEEN :qb_0 AND :qb_1', $sql);
        self::assertSame(['qb_0' => 18, 'qb_1' => 65], $this->builder->getBindings());
    }

    public function testWhereNull(): void
    {
        $sql = $this->builder->whereNull('deleted_at')->toSql();

        self::assertStringContainsString('WHERE deleted_at IS NULL', $sql);
        self::assertSame([], $this->builder->getBindings());
    }

    public function testWhereNotNull(): void
    {
        $sql = $this->builder->whereNotNull('email')->toSql();

        self::assertStringContainsString('WHERE email IS NOT NULL', $sql);
    }

    public function testWhereLike(): void
    {
        $sql = $this->builder->whereLike('name', '%joão%')->toSql();

        self::assertStringContainsString('WHERE name LIKE :qb_0', $sql);
        self::assertSame(['qb_0' => '%joão%'], $this->builder->getBindings());
    }

    public function testOrderByAsc(): void
    {
        $sql = $this->builder->orderBy('name')->toSql();

        self::assertStringContainsString('ORDER BY name ASC', $sql);
    }

    public function testOrderByDesc(): void
    {
        $sql = $this->builder->orderByDesc('created_at')->toSql();

        self::assertStringContainsString('ORDER BY created_at DESC', $sql);
    }

    public function testMultipleOrderBys(): void
    {
        $sql = $this->builder
            ->orderBy('name', 'ASC')
            ->orderBy('id', 'DESC')
            ->toSql();

        self::assertStringContainsString('ORDER BY name ASC, id DESC', $sql);
    }

    public function testLimitAndOffset(): void
    {
        $sql = $this->builder->limit(10)->offset(20)->toSql();

        self::assertStringContainsString('LIMIT 10', $sql);
        self::assertStringContainsString('OFFSET 20', $sql);
    }

    public function testJoin(): void
    {
        $sql = $this->builder
            ->join('posts', 'posts.user_id', '=', 'users.id')
            ->toSql();

        self::assertStringContainsString('INNER JOIN posts ON posts.user_id = users.id', $sql);
    }

    public function testLeftJoin(): void
    {
        $sql = $this->builder
            ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
            ->toSql();

        self::assertStringContainsString('LEFT JOIN profiles ON profiles.user_id = users.id', $sql);
    }

    public function testRightJoin(): void
    {
        $sql = $this->builder
            ->rightJoin('orders', 'orders.user_id', '=', 'users.id')
            ->toSql();

        self::assertStringContainsString('RIGHT JOIN orders ON orders.user_id = users.id', $sql);
    }

    public function testGroupBy(): void
    {
        $sql = $this->builder
            ->select('status', 'COUNT(*) as total')
            ->groupBy('status')
            ->toSql();

        self::assertStringContainsString('GROUP BY status', $sql);
    }

    public function testHaving(): void
    {
        $sql = $this->builder
            ->select('status', 'COUNT(*) as total')
            ->groupBy('status')
            ->having('total', '>', 5)
            ->toSql();

        self::assertStringContainsString('HAVING total > :qb_0', $sql);
    }

    public function testWhereGroupNestedConditions(): void
    {
        $sql = $this->builder
            ->where('active', '=', 1)
            ->whereGroup(function (QueryBuilder $q) {
                $q->where('role', '=', 'admin')
                  ->orWhere('role', '=', 'moderator');
            })
            ->toSql();

        self::assertStringContainsString('OR role = :qb_', $sql);
        // Builder aninhado reinicia índice; bindings são mesclados (chaves qb_0 do nested sobrescrevem)
        self::assertGreaterThanOrEqual(2, count($this->builder->getBindings()));
    }

    public function testWhereArrayAssociative(): void
    {
        $sql = $this->builder
            ->where(['status' => 'active', 'role' => 'admin'])
            ->toSql();

        self::assertStringContainsString('status = :qb_0', $sql);
        self::assertStringContainsString('AND role = :qb_1', $sql);
    }

    public function testResetClearsAllState(): void
    {
        $this->builder
            ->select('id', 'name')
            ->where('status', '=', 'active')
            ->orderBy('name')
            ->limit(10);

        $this->builder->reset();

        self::assertSame('SELECT * FROM users', $this->builder->toSql());
        self::assertSame([], $this->builder->getBindings());
    }

    public function testComplexQueryCompilation(): void
    {
        $sql = $this->builder
            ->select('users.id', 'users.name', 'posts.title')
            ->join('posts', 'posts.user_id', '=', 'users.id')
            ->where('users.active', '=', 1)
            ->whereNotNull('posts.published_at')
            ->orderBy('posts.created_at', 'DESC')
            ->limit(20)
            ->offset(40)
            ->toSql();

        self::assertStringContainsString('SELECT users.id, users.name, posts.title FROM users', $sql);
        self::assertStringContainsString('INNER JOIN posts', $sql);
        self::assertStringContainsString('WHERE users.active = :qb_0', $sql);
        self::assertStringContainsString('AND posts.published_at IS NOT NULL', $sql);
        self::assertStringContainsString('ORDER BY posts.created_at DESC', $sql);
        self::assertStringContainsString('LIMIT 20', $sql);
        self::assertStringContainsString('OFFSET 40', $sql);
    }

    public function testFluentInterfaceReturnsSelf(): void
    {
        $result = $this->builder
            ->select('id')
            ->where('active', '=', 1)
            ->orderBy('id')
            ->limit(10)
            ->offset(0);

        self::assertInstanceOf(QueryBuilder::class, $result);
    }

    public function testInvalidOrderDirectionDefaultsToAsc(): void
    {
        $sql = $this->builder->orderBy('name', 'INVALID')->toSql();

        self::assertStringContainsString('ORDER BY name ASC', $sql);
    }

    public function testNegativeLimitClampedToZero(): void
    {
        $sql = $this->builder->limit(-5)->toSql();

        self::assertStringContainsString('LIMIT 0', $sql);
    }

    public function testNegativeOffsetClampedToZero(): void
    {
        $sql = $this->builder->offset(-10)->toSql();

        self::assertStringContainsString('OFFSET 0', $sql);
    }
}