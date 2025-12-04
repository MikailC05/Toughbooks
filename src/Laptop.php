<?php
require_once __DIR__ . '/Database.php';

class Laptop
{
    public $id;
    public $name;

    public function __construct($id, $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    public static function all()
    {
        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->query('SELECT id, name FROM laptops');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) $out[] = new self($r['id'], $r['name']);
        return $out;
    }
}
