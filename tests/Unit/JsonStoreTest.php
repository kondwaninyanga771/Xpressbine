<?php

namespace Tests\Unit;

use App\Data\JsonDocument;
use App\Data\JsonStore;
use Tests\TestCase;

class JsonStoreTest extends TestCase
{
    public function test_transactions_persist_ids_and_survive_a_new_reader(): void
    {
        $path = storage_path('framework/testing/json-store.json');
        @unlink($path);
        @unlink($path.'.lock');

        $store = new JsonStore($path);
        $first = $store->transaction(function (JsonDocument $document) {
            return $document->table('notes')->insert(['body' => 'hello']);
        });
        $second = $store->transaction(function (JsonDocument $document) {
            return $document->table('notes')->insert(['body' => 'again']);
        });

        $saved = (new JsonStore($path))->read();

        $this->assertSame(1, $first['id']);
        $this->assertSame(2, $second['id']);
        $this->assertNotNull($first['created_at']);
        $this->assertSame('hello', $saved->table('notes')->find(1)['body']);
        $this->assertCount(1, $saved->table('notes')->where(['body' => 'again']));

        @unlink($path);
        @unlink($path.'.lock');
    }
}
