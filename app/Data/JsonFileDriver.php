<?php

namespace App\Data;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Loads db.json into a private in-memory SQLite connection for the request,
 * then writes every table back to the JSON file. Eloquent keeps its usual
 * queries. Set XPRESSBINE_DATA_DRIVER=database to use a normal SQL connection
 * and leave the JSON file untouched.
 */
class JsonFileDriver
{
    private bool $open = false;

    public function __construct(private JsonStore $store) {}

    public function path(): string
    {
        return $this->store->path();
    }

    public function begin(bool $import = true): void
    {
        if ($this->open) {
            return;
        }

        $this->store->acquire();
        $this->open = true;

        config([
            'database.default' => 'xpressbine_json',
            'session.driver' => config('session.driver') === 'database' ? 'file' : config('session.driver'),
            'cache.default' => config('cache.default') === 'database' ? 'file' : config('cache.default'),
        ]);

        DB::purge('xpressbine_json');
        DB::setDefaultConnection('xpressbine_json');

        $this->migrate();

        if ($import && $this->store->exists()) {
            $this->import($this->store->read());
        }
    }

    public function commit(): void
    {
        if (! $this->open) {
            return;
        }

        try {
            $this->store->write($this->export());
        } finally {
            $this->store->release();
            $this->open = false;
        }
    }

    public function abort(): void
    {
        if (! $this->open) {
            return;
        }

        $this->store->release();
        $this->open = false;
    }

    public function export(): JsonDocument
    {
        $document = new JsonDocument;
        $tables = [];

        foreach ($this->tableNames() as $name) {
            $tables[$name] = array_map(
                fn ($row) => (array) $row,
                DB::table($name)->get()->all(),
            );
        }

        $document->replaceTables($tables);

        return $document;
    }

    public function import(JsonDocument $document): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            foreach ($document->tables() as $name => $rows) {
                if (! Schema::hasTable($name) || $name === 'sqlite_sequence') {
                    continue;
                }

                $columns = array_flip(Schema::getColumnListing($name));
                DB::table($name)->delete();

                $prepared = [];

                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $prepared[] = array_intersect_key($row, $columns);
                }

                foreach (array_chunk($prepared, 100) as $chunk) {
                    if ($chunk !== []) {
                        DB::table($name)->insert($chunk);
                    }
                }

                $this->advanceSequence($name);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function migrate(): void
    {
        $migrator = app('migrator');
        $migrator->setConnection(DB::getDefaultConnection());

        if (! $migrator->repositoryExists()) {
            $migrator->getRepository()->createRepository();
        }

        $migrator->run([database_path('migrations')]);
    }

    /**
     * @return list<string>
     */
    private function tableNames(): array
    {
        $rows = DB::select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name");

        return array_map(fn ($row) => $row->name, $rows);
    }

    private function advanceSequence(string $table): void
    {
        try {
            $max = (int) DB::table($table)->max('id');

            if ($max < 1) {
                return;
            }

            $exists = DB::select('select 1 from sqlite_sequence where name = ?', [$table]);

            if ($exists === []) {
                DB::statement('insert into sqlite_sequence (name, seq) values (?, ?)', [$table, $max]);
            } else {
                DB::statement('update sqlite_sequence set seq = ? where name = ?', [$max, $table]);
            }
        } catch (Throwable) {
            // Tables without an autoincrement id have no sqlite_sequence row.
        }
    }
}
