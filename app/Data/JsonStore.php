<?php

namespace App\Data;

use RuntimeException;

/**
 * File-backed document store. Writes are atomic and each transaction
 * holds an exclusive lock so two requests cannot clobber the file.
 */
class JsonStore
{
    private mixed $lock = null;

    public function __construct(private string $path) {}

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function read(): JsonDocument
    {
        if (! $this->exists()) {
            return new JsonDocument;
        }

        $raw = file_get_contents($this->path);
        $decoded = json_decode($raw === false ? '' : $raw, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('The JSON data file is not a document.');
        }

        return JsonDocument::fromArray($decoded);
    }

    public function write(JsonDocument $document): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the JSON data directory.');
        }

        $temporary = $this->path.'.tmp';
        $encoded = json_encode($document->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (file_put_contents($temporary, $encoded) === false) {
            throw new RuntimeException('Could not write the JSON data file.');
        }

        if (! rename($temporary, $this->path)) {
            throw new RuntimeException('Could not replace the JSON data file.');
        }
    }

    /**
     * @template T
     *
     * @param  callable(JsonDocument): T  $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->acquire();

        try {
            $document = $this->read();
            $result = $callback($document);
            $this->write($document);

            return $result;
        } finally {
            $this->release();
        }
    }

    public function acquire(): void
    {
        if (is_resource($this->lock)) {
            return;
        }

        $directory = dirname($this->path);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the JSON data directory.');
        }

        $handle = fopen($this->path.'.lock', 'c+');

        if ($handle === false) {
            throw new RuntimeException('Could not open the JSON data lock.');
        }

        if (! flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new RuntimeException('Could not lock the JSON data file.');
        }

        $this->lock = $handle;
    }

    public function release(): void
    {
        if (! is_resource($this->lock)) {
            return;
        }

        flock($this->lock, LOCK_UN);
        fclose($this->lock);
        $this->lock = null;
    }
}
