<?php

namespace Tests\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Config;
use App\Core\Database;

class DatabaseTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        Config::reset();
        Config::load(dirname(__DIR__, 2));
        Database::resetInstance();
        $this->db = Database::getInstance();
    }

    public function testGetInstanceReturnsSameInstance(): void
    {
        $db2 = Database::getInstance();
        $this->assertSame($this->db, $db2);
    }

    public function testQueryExecutesPreparedStatement(): void
    {
        $result = $this->db->query("SELECT 1 AS val");
        $this->assertNotNull($result);
    }

    public function testFetchReturnsSingleRow(): void
    {
        $row = $this->db->fetch("SELECT 1 AS val");
        $this->assertEquals(1, $row['val']);
    }

    public function testFetchAllReturnsArray(): void
    {
        $rows = $this->db->fetchAll("SELECT 1 AS val UNION SELECT 2");
        $this->assertCount(2, $rows);
    }

    public function testInsertReturnsId(): void
    {
        $this->db->query("CREATE TEMPORARY TABLE _test_insert (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50))");
        $id = $this->db->insert('_test_insert', ['name' => 'test']);
        $this->assertEquals(1, $id);
    }

    public function testUpdateModifiesRows(): void
    {
        $this->db->query("CREATE TEMPORARY TABLE _test_update (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50))");
        $this->db->insert('_test_update', ['name' => 'old']);
        $affected = $this->db->update('_test_update', ['name' => 'new'], 'id = ?', [1]);
        $this->assertEquals(1, $affected);

        $row = $this->db->fetch("SELECT name FROM _test_update WHERE id = 1");
        $this->assertEquals('new', $row['name']);
    }

    public function testDeleteRemovesRows(): void
    {
        $this->db->query("CREATE TEMPORARY TABLE _test_delete (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50))");
        $this->db->insert('_test_delete', ['name' => 'gone']);
        $affected = $this->db->delete('_test_delete', 'id = ?', [1]);
        $this->assertEquals(1, $affected);

        $row = $this->db->fetch("SELECT * FROM _test_delete WHERE id = 1");
        $this->assertFalse($row);
    }

    public function testTransactionCommit(): void
    {
        $this->db->query("CREATE TEMPORARY TABLE _test_tx (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50))");
        $this->db->beginTransaction();
        $this->db->insert('_test_tx', ['name' => 'committed']);
        $this->db->commit();

        $row = $this->db->fetch("SELECT name FROM _test_tx WHERE id = 1");
        $this->assertEquals('committed', $row['name']);
    }

    public function testTransactionRollback(): void
    {
        $this->db->query("CREATE TEMPORARY TABLE _test_rb (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50))");
        $this->db->insert('_test_rb', ['name' => 'keep']);
        $this->db->beginTransaction();
        $this->db->insert('_test_rb', ['name' => 'discard']);
        $this->db->rollBack();

        $rows = $this->db->fetchAll("SELECT * FROM _test_rb");
        $this->assertCount(1, $rows);
    }
}
