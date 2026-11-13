<?php

declare(strict_types=1);

namespace JonatasFenske\DataLayer;

use DateTimeImmutable;
use JsonException;

/**
 * Trait para casting automático de atributos e mutators/accessors
 * PHP 8.5: tipagem estrita, match expressions, union types
 */
trait HasAttributes
{
    /**
     * Definição de casts por coluna
     * Override no model filho: protected array $casts = ['is_active' => 'boolean', 'metadata' => 'json'];
     *
     * Tipos suportados: int, integer, float, double, string, bool, boolean,
     *                   json, array, datetime, date, timestamp, decimal:<precision>
     *
     * @var array<string, string>
     */
    protected array $casts = [];

    /**
     * Aplica casting ao ler um atributo
     *
     * @param string $key Nome da coluna
     * @param mixed $value Valor bruto do banco
     * @return mixed Valor convertido
     */
    protected function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null || !isset($this->casts[$key])) {
            return $value;
        }

        $castType = $this->casts[$key];

        return match (true) {
            $castType === 'int' || $castType === 'integer' => (int)$value,
            $castType === 'float' || $castType === 'double' => (float)$value,
            $castType === 'string' => (string)$value,
            $castType === 'bool' || $castType === 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            $castType === 'json' || $castType === 'array' => $this->decodeJson($value),
            $castType === 'datetime' => $this->toDateTime($value),
            $castType === 'date' => $this->toDate($value),
            $castType === 'timestamp' => $this->toTimestamp($value),
            str_starts_with($castType, 'decimal:') => $this->toDecimal($value, $castType),
            default => $value,
        };
    }

    /**
     * Aplica casting reverso ao salvar um atributo
     *
     * @param string $key Nome da coluna
     * @param mixed $value Valor do model
     * @return mixed Valor pronto para persistência
     */
    protected function serializeAttribute(string $key, mixed $value): mixed
    {
        if ($value === null || !isset($this->casts[$key])) {
            return $value;
        }

        $castType = $this->casts[$key];

        return match (true) {
            $castType === 'json' || $castType === 'array' => $this->encodeJson($value),
            $castType === 'datetime' && $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $castType === 'date' && $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            $castType === 'bool' || $castType === 'boolean' => $value ? 1 : 0,
            default => $value,
        };
    }

    /**
     * Aplica casts em todos os dados carregados
     *
     * @param object|null $data Dados brutos do banco
     * @return object|null Dados com casts aplicados
     */
    protected function applyCasts(?object $data): ?object
    {
        if ($data === null || $this->casts === []) {
            return $data;
        }

        foreach ($this->casts as $key => $type) {
            if (property_exists($data, $key)) {
                $data->$key = $this->castAttribute($key, $data->$key);
            }
        }

        return $data;
    }

    /**
     * Serializa atributos com cast antes de salvar
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function serializeCasts(array $data): array
    {
        foreach ($this->casts as $key => $type) {
            if (array_key_exists($key, $data)) {
                $data[$key] = $this->serializeAttribute($key, $data[$key]);
            }
        }

        return $data;
    }

    /**
     * Decodifica JSON com segurança
     */
    private function decodeJson(mixed $value): array|object|null
    {
        if (is_array($value) || is_object($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return json_decode($value, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Codifica valor para JSON com segurança
     */
    private function encodeJson(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Converte string de data para DateTimeImmutable
     */
    private function toDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTime) {
            return DateTimeImmutable::createFromMutable($value);
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Converte string de data para DateTimeImmutable (apenas data)
     */
    private function toDate(mixed $value): ?DateTimeImmutable
    {
        $dt = $this->toDateTime($value);
        return $dt?->setTime(0, 0, 0);
    }

    /**
     * Converte para timestamp Unix
     */
    private function toTimestamp(mixed $value): ?int
    {
        if (is_numeric($value)) {
            return (int)$value;
        }

        $dt = $this->toDateTime($value);
        return $dt?->getTimestamp();
    }

    /**
     * Converte para decimal com precisão definida
     * Formato: "decimal:2" → 2 casas decimais
     */
    private function toDecimal(mixed $value, string $castType): ?string
    {
        $precision = (int)substr($castType, 8);
        if ($precision < 0) {
            $precision = 2;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float)$value, $precision, '.', '');
    }
}