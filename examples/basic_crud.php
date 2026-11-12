<?php

declare(strict_types=1);

/**
 * Exemplo básico de CRUD com DataLayer
 * Execute: php examples/basic_crud.php
 */

require __DIR__ . '/../vendor/autoload.php';

use JonatasFenske\DataLayer\DatabaseConfig;
use JonatasFenske\DataLayer\DataLayer;

// 1. Configuração do banco
$db = new DatabaseConfig(
    driver: 'mysql',
    host: getenv('DB_HOST') ?: 'localhost',
    dbname: getenv('DB_NAME') ?: 'exemplo',
    username: getenv('DB_USER') ?: 'root',
    password: getenv('DB_PASS') ?: '',
);

// 2. Definir Model
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

// 3. CREATE
$user = new User();
$user->name = 'João Silva';
$user->email = 'joao@example.com';
$user->is_active = true;
$user->metadata = ['role' => 'admin', 'department' => 'TI'];

if ($user->save()) {
    echo "✅ Usuário criado com ID: {$user->id}\n";
} else {
    echo "❌ Erro: " . $user->fail()?->getMessage() . "\n";
}

// 4. READ
$found = $user->findById((int)$user->id);
if ($found) {
    echo "📖 Nome: {$found->name}\n";
    echo "📖 Email: {$found->email}\n";
    echo "📖 Ativo: " . ($found->is_active ? 'Sim' : 'Não') . "\n";
    echo "📖 Metadata: " . json_encode($found->metadata) . "\n";
}

// 5. UPDATE
$found->name = 'Maria Silva';
if ($found->save()) {
    echo "✏️  Usuário atualizado com sucesso\n";
}

// 6. QueryBuilder
$activeUsers = $user->query()
    ->select('id', 'name', 'email')
    ->where('is_active', '=', true)
    ->orderByDesc('created_at')
    ->limit(10)
    ->get();

echo "🔍 Usuários ativos encontrados: " . count($activeUsers ?? []) . "\n";

// 7. DELETE
if ($found->destroy()) {
    echo "🗑️  Usuário removido com sucesso\n";
}

echo "\n✅ Exemplo concluído!\n";