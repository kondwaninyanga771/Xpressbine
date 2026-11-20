<?php

namespace App\Data;

/**
 * The whole db.json document: a set of named tables.
 */
class JsonDocument
{
    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     */
    public function __construct(private array $tables = []) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $tables = $payload['tables'] ?? $payload;

        if (! is_array($tables)) {
            $tables = [];
        }

        return new self($tables);
    }

    /**
     * @return array{meta: array<string, string>, tables: array<string, array<int, array<string, mixed>>>}
     */
    public function toArray(): array
    {
        return [
            'meta' => [
                'exported_at' => now()->toIso8601String(),
            ],
            'tables' => $this->tables,
        ];
    }

    public function table(string $name): JsonTable
    {
        if (! isset($this->tables[$name]) || ! is_array($this->tables[$name])) {
            $this->tables[$name] = [];
        }

        return new JsonTable($this->tables[$name]);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function tables(): array
    {
        return $this->tables;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     */
    public function replaceTables(array $tables): void
    {
        $this->tables = $tables;
    }
}
