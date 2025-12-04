<?php
require_once __DIR__ . '/Database.php';

class Configurator
{
    // $answers: array question_id => option_id
    public static function score(array $answers)
    {
        $pdo = Database::getInstance()->getPdo();
        $scores = [];
        // initialize laptop scores
        $laptops = $pdo->query('SELECT id, name FROM laptops')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($laptops as $l) $scores[$l['id']] = ['name' => $l['name'], 'points' => 0];

        $ins = $pdo->prepare('SELECT laptop_id, points FROM scores WHERE option_id = :oid');
        foreach ($answers as $qid => $oid) {
            $ins->execute([':oid' => $oid]);
            $rows = $ins->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $lid = $r['laptop_id'];
                $pts = (int)$r['points'];
                if (!isset($scores[$lid])) continue;
                $scores[$lid]['points'] += $pts;
            }
        }

        // sort descending
        usort($laptops, function ($a, $b) use ($scores) {
            return $scores[$b['id']]['points'] <=> $scores[$a['id']]['points'];
        });

        $result = [];
        foreach ($laptops as $l) {
            $id = $l['id'];
            $result[] = ['id' => $id, 'name' => $l['name'], 'points' => $scores[$id]['points']];
        }
        return $result;
    }
}
