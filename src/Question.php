<?php
require_once __DIR__ . '/Database.php';

class Question
{
    public $id;
    public $text;
    public $type;
    public $description;
    public $weight;
    public $options = [];

    public function __construct($id, $text, $type, $description = null, $weight = 1.0)
    {
        $this->id = $id;
        $this->text = $text;
        $this->type = $type;
        $this->description = $description;
        $this->weight = $weight;
    }

    public static function all()
    {
        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->query('SELECT id, text, type, description, weight FROM questions WHERE is_required = 1 ORDER BY display_order');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $q = new self($r['id'], $r['text'], $r['type'], $r['description'] ?? null, $r['weight'] ?? 1.0);
            $q->options = $q->getOptions();
            $out[] = $q;
        }
        return $out;
    }

    public function getOptions()
    {
        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare('SELECT id, label, value, description FROM options WHERE question_id = :qid ORDER BY display_order');
        $stmt->execute([':qid' => $this->id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}