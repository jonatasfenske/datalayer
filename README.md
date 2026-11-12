# JonatasFenske\DataLayer

<p align="center">
 <strong>Abstração de persistência PHP 8.5 com QueryBuilder fluente</strong>
</p>

<p align="center">
 <img src="https://img.shields.io/badge/PHP-8.5%2B-8892BF?logo=php" alt="PHP 8.5+">
 <img src="https://img.shields.io/badge/license-MIT-blue" alt="MIT License">
</p>

## Instalação

```bash
composer require jonatasfenske/datalayer
```

## Recursos

- **QueryBuilder fluente** — where, orWhere, whereIn, whereBetween, whereNull, whereLike, join (INNER/LEFT/RIGHT), groupBy, having, orderBy, limit/offset
- **Soft Deletes** — trash/restore/forceDelete/withTrashed/onlyTrashed
- **Relacionamentos** — BelongsTo, HasMany, BelongsToMany com eager loading
- **Casting automático** — int, float, string, boolean, json, array, datetime, date, timestamp, decimal:N
- **Transações** — automática com closure e manual (begin/commit/rollback)
- **Eventos de lifecycle** — beforeSave, afterSave, beforeCreate, afterCreate, beforeUpdate, afterUpdate, beforeDelete, afterDelete
- **Paginação nativa** — integrada ao `jonatasfenske/paginator`
- **Batch insert** — via QueryBuilder::insertMany()
- **DatabaseConfig** — value object imutável com #[SensitiveParameter]
- **DatabaseDriver enum** — DSN automático para MySQL, PostgreSQL, SQLite, SQL Server

## Uso Rápido

### Configuração

```php
use JonatasFenske\DataLayer\DatabaseConfig;

$db = new DatabaseConfig(
    driver: 'mysql',
    host: 'localhost',
    dbname: 'meubanco',
    username: 'root',
    password: getenv('DB_PASS') ?: '',
);
```

### Criando um Model

```php
use JonatasFenske\DataLayer\DataLayer;

class User extends DataLayer
{
    protected array $casts = [
        'id' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'json',
        'created_at' => 'datetime',
    ];

    public function __construct()
    {
        parent::__construct(
            entity: 'users',
            required: ['name', 'email'],
            primary: 'id',
            timestamps: true,
        );
    }
}
```

### CRUD

```php
$user = new User();
$user->name = 'João Silva';
$user->email = 'joao@example.com';
$user->save(); // INSERT + reload

$found = $user->findById(1);
$found->name = 'Maria Silva';
$found->save(); // UPDATE automático

$found->destroy(); // DELETE ou soft delete
```

### QueryBuilder

```php
$results = $user->query()
    ->select('id', 'name', 'email')
    ->where('is_active', '=', true)
    ->whereIn('role', ['admin', 'editor'])
    ->orderByDesc('created_at')
    ->limit(20)
    ->get();

$count = $user->query()->where('is_active', '=', true)->count();
```

### Transações

```php
DataLayer::transaction(function () {
    $order->save();
    $payment->save();
});
```

### Eventos

```php
User::beforeSave(fn(User $u) => $u->slug = Str::slug($u->name));
User::afterCreate(fn(User $u) => EmailService::sendWelcome($u->email));
```

## Testes

```bash
composer test
```

## Licença

MIT