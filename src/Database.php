<?php
class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        $dbFile = __DIR__ . '/../data/toughbooks.db';
        $needInit = !file_exists($dbFile);
        if (!is_dir(dirname($dbFile))) {
            mkdir(dirname($dbFile), 0777, true);
        }
        $this->pdo = new PDO('sqlite:' . $dbFile);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($needInit) {
            $this->initialize();
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo()
    {
        return $this->pdo;
    }

    private function initialize()
    {
        $sql = [
            "CREATE TABLE IF NOT EXISTS laptops (id INTEGER PRIMARY KEY, name TEXT UNIQUE NOT NULL);",
            "CREATE TABLE IF NOT EXISTS questions (id INTEGER PRIMARY KEY, text TEXT NOT NULL, type TEXT NOT NULL);",
            "CREATE TABLE IF NOT EXISTS options (id INTEGER PRIMARY KEY, question_id INTEGER NOT NULL, label TEXT NOT NULL, value TEXT NOT NULL, FOREIGN KEY(question_id) REFERENCES questions(id));",
            "CREATE TABLE IF NOT EXISTS scores (laptop_id INTEGER NOT NULL, option_id INTEGER NOT NULL, points INTEGER NOT NULL, FOREIGN KEY(laptop_id) REFERENCES laptops(id), FOREIGN KEY(option_id) REFERENCES options(id));"
        ];
        foreach ($sql as $s) {
            $this->pdo->exec($s);
        }

        // Seed laptops
        $laptops = [
            'Toughbook CF-33MK4',
            'Toughbook FZ-40mk2',
            'Toughbook FZ-55mk3',
            'Toughpad FZ-G2mk3'
        ];
        $stmt = $this->pdo->prepare('INSERT INTO laptops (name) VALUES (:name)');
        foreach ($laptops as $name) {
            $stmt->execute([':name' => $name]);
        }

        // Seed sample questions (Dutch) - boolean yes/no
        $questions = [
            'Heeft u GPS nodig op de laptop?',
            'Moet de laptop waterdicht zijn (regenbestendig)?',
            'Heeft u een touchscreen nodig?',
            'Is lange batterijduur belangrijk?',
            'Gaat de laptop veel in de regen/veld gebruikt worden?',
            'Is gewicht (lichtheid) een belangrijke eis?'
        ];
        $qstmt = $this->pdo->prepare('INSERT INTO questions (text, type) VALUES (:text, :type)');
        $ostmt = $this->pdo->prepare('INSERT INTO options (question_id, label, value) VALUES (:qid, :label, :value)');
        foreach ($questions as $q) {
            $qstmt->execute([':text' => $q, ':type' => 'boolean']);
            $qid = $this->pdo->lastInsertId();
            // Yes / No
            $ostmt->execute([':qid' => $qid, ':label' => 'Ja', ':value' => 'yes']);
            $yesId = $this->pdo->lastInsertId();
            $ostmt->execute([':qid' => $qid, ':label' => 'Nee', ':value' => 'no']);
            $noId = $this->pdo->lastInsertId();

            // Assign example scoring: arbitrary sensible defaults
            // We'll give different laptops different affinities
            $lstmt = $this->pdo->query('SELECT id, name FROM laptops');
            foreach ($lstmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $lName = $row['name'];
                $lId = $row['id'];
                // A simple heuristic: FZ-G2 gets +20 for GPS and touchscreen, FZ-55 is balanced,
                // FZ-40 is good on battery/weight, CF-33 is rugged for rain
                $yesPoints = 0;
                $noPoints = 0;
                if (stripos($q, 'GPS') !== false) {
                    if (stripos($lName, 'G2') !== false) $yesPoints = 20;
                    elseif (stripos($lName, '40') !== false) $yesPoints = 10;
                    elseif (stripos($lName, '55') !== false) $yesPoints = 0;
                    else $yesPoints = 5;
                } elseif (stripos($q, 'waterdicht') !== false || stripos($q, 'regen') !== false) {
                    if (stripos($lName, 'CF-33') !== false) $yesPoints = 20;
                    elseif (stripos($lName, '55') !== false) $yesPoints = 10;
                    else $yesPoints = 0;
                } elseif (stripos($q, 'touchscreen') !== false) {
                    if (stripos($lName, 'G2') !== false) $yesPoints = 20;
                    elseif (stripos($lName, '33') !== false) $yesPoints = 5;
                } elseif (stripos($q, 'batterij') !== false) {
                    if (stripos($lName, '40') !== false) $yesPoints = 20;
                    elseif (stripos($lName, '55') !== false) $yesPoints = 10;
                } elseif (stripos($q, 'gewicht') !== false) {
                    if (stripos($lName, '40') !== false) $yesPoints = 20;
                    else $yesPoints = 5;
                } else {
                    $yesPoints = 0;
                }

                $ins = $this->pdo->prepare('INSERT INTO scores (laptop_id, option_id, points) VALUES (:lid, :oid, :pts)');
                $ins->execute([':lid' => $lId, ':oid' => $yesId, ':pts' => $yesPoints]);
                $ins->execute([':lid' => $lId, ':oid' => $noId, ':pts' => $noPoints]);
            }
        }
    }
}

// Auto bootstrap when included
if (!defined('DB_BOOTSTRAPPED')) {
    define('DB_BOOTSTRAPPED', true);
    Database::getInstance();
}
