<?php

class ModelNumber
{
    public static function ensureSchema(PDO $pdo): void
    {
        // Idempotent schema (MySQL/MariaDB)
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS laptop_model_rules (\n"
            . "  id INT(11) NOT NULL AUTO_INCREMENT,\n"
            . "  laptop_id INT(11) NOT NULL,\n"
            . "  model_number VARCHAR(255) NOT NULL,\n"
            . "  conditions_json TEXT NOT NULL,\n"
            . "  sort_order INT(11) DEFAULT 0,\n"
            . "  is_active TINYINT(1) DEFAULT 1,\n"
            . "  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . "  PRIMARY KEY (id),\n"
            . "  KEY idx_laptop (laptop_id),\n"
            . "  CONSTRAINT fk_laptop_model_rules_laptop FOREIGN KEY (laptop_id) REFERENCES laptops(id) ON DELETE CASCADE\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * @return array<int, array{id:int,laptop_id:int,model_number:string,conditions:array<string,string>,sort_order:int,is_active:int}>
     */
    public static function getRules(PDO $pdo, int $laptopId): array
    {
        $stmt = $pdo->prepare('SELECT id, laptop_id, model_number, conditions_json, sort_order, is_active FROM laptop_model_rules WHERE laptop_id = ? ORDER BY sort_order DESC, id DESC');
        $stmt->execute([$laptopId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            $conditions = [];
            $raw = (string)($r['conditions_json'] ?? '');
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $k => $v) {
                        if (is_string($k) && (is_string($v) || is_numeric($v))) {
                            $conditions[$k] = (string)$v;
                        }
                    }
                }
            }

            $out[] = [
                'id' => (int)$r['id'],
                'laptop_id' => (int)$r['laptop_id'],
                'model_number' => (string)$r['model_number'],
                'conditions' => $conditions,
                'sort_order' => (int)($r['sort_order'] ?? 0),
                'is_active' => (int)($r['is_active'] ?? 1),
            ];
        }

        return $out;
    }

    /**
     * @param array<string,string> $selectedByKey
     * @param array<int, array{model_number:string,conditions:array<string,string>,sort_order?:int,is_active?:int}> $rules
     */
    public static function compute(?string $fallback, array $selectedByKey, array $rules): string
    {
        $best = '';
        $bestSpec = -1;
        $bestSort = -999999;

        foreach ($rules as $rule) {
            $active = (int)($rule['is_active'] ?? 1);
            if ($active !== 1) {
                continue;
            }

            $conditions = (array)($rule['conditions'] ?? []);
            if (!self::matches($conditions, $selectedByKey)) {
                continue;
            }

            $spec = count($conditions);
            $sort = (int)($rule['sort_order'] ?? 0);

            if ($spec > $bestSpec || ($spec === $bestSpec && $sort > $bestSort)) {
                $best = (string)($rule['model_number'] ?? '');
                $bestSpec = $spec;
                $bestSort = $sort;
            }
        }

        if ($best !== '') {
            return $best;
        }

        return (string)($fallback ?? '');
    }

    /**
     * @param array<string,string> $conditions
     * @param array<string,string> $selectedByKey
     */
    private static function matches(array $conditions, array $selectedByKey): bool
    {
        foreach ($conditions as $k => $v) {
            if (!array_key_exists($k, $selectedByKey)) {
                return false;
            }
            if ((string)$selectedByKey[$k] !== (string)$v) {
                return false;
            }
        }
        return true;
    }
}
