<?php

namespace App\Data;

/**
 * In-memory helpers for one collection inside the JSON document.
 * Rows are associative arrays. The file store persists the whole document.
 */
class JsonTable
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(private array &$rows) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int|string $id): ?array
    {
        foreach ($this->rows as $row) {
            if ((string) ($row['id'] ?? '') === (string) $id) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array<int, array<string, mixed>>
     */
    public function where(array $criteria): array
    {
        return array_values(array_filter($this->rows, function (array $row) use ($criteria) {
            foreach ($criteria as $column => $value) {
                if (($row[$column] ?? null) != $value) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function insert(array $attributes): array
    {
        $now = now()->toDateTimeString();
        $row = array_merge($attributes, [
            'id' => $this->nextId(),
            'created_at' => $attributes['created_at'] ?? $now,
            'updated_at' => $attributes['updated_at'] ?? $now,
        ]);
        $this->rows[] = $row;

        return $row;
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>|null
     */
    public function update(int|string $id, array $changes): ?array
    {
        foreach ($this->rows as $index => $row) {
            if ((string) ($row['id'] ?? '') !== (string) $id) {
                continue;
            }

            $changes['updated_at'] = $changes['updated_at'] ?? now()->toDateTimeString();
            $this->rows[$index] = array_merge($row, $changes);

            return $this->rows[$index];
        }

        return null;
    }

    public function delete(int|string $id): bool
    {
        foreach ($this->rows as $index => $row) {
            if ((string) ($row['id'] ?? '') === (string) $id) {
                array_splice($this->rows, $index, 1);

                return true;
            }
        }

        return false;
    }

    public function nextId(): int
    {
        $max = 0;

        foreach ($this->rows as $row) {
            $max = max($max, (int) ($row['id'] ?? 0));
        }

        return $max + 1;
    }
}
