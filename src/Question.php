<?php
require_once __DIR__ . '/Database.php';

class Question
{
    public $id;
    public $text;
    public $type;
    public $options = [];

    public function __construct($id, $text, $type)
    {
        $this->id = $id;
        $this->text = $text;
        $this->type = $type;
    }

    public static function all()
    {
        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->query('SELECT id, text, type FROM questions ORDER BY id');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $q = new self($r['id'], $r['text'], $r['type']);
            $q->options = $q->getOptions();
            $out[] = $q;
        }
        return $out;
    }

    public function getOptions()
    {
        $pdo = Database::getInstance()->getPdo();
        $stmt = $pdo->prepare('SELECT id, label, value FROM options WHERE question_id = :qid ORDER BY id');
        $stmt->execute([':qid' => $this->id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
