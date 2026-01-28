<?php
require_once __DIR__ . '/Database.php';

class Configurator
{
    /**
     * Bereken scores voor laptops op basis van antwoorden
     * @param array $answers Array met question_id => option_id
     * @return array Gesorteerde array met laptop resultaten
     */
    public static function score(array $answers)
    {
        $pdo = Database::getInstance()->getPdo();
        $scores = [];
        
        // Haal alle actieve laptops op
        $laptops = $pdo->query('SELECT id, name, model_code, description, price_eur FROM laptops WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC);
        
        // Initialiseer scores
        foreach ($laptops as $l) {
            $scores[$l['id']] = [
                'id' => $l['id'],
                'name' => $l['name'],
                'model_code' => $l['model_code'],
                'description' => $l['description'],
                'price_eur' => $l['price_eur'],
                'points' => 0.0,
                'matches' => 0,
                'reasons' => []
            ];
        }

        // Haal de weging van vragen op
        $weightStmt = $pdo->prepare('SELECT weight FROM questions WHERE id = :qid');
        
        // Bereken scores op basis van antwoorden
        $scoreStmt = $pdo->prepare('SELECT laptop_id, points, reason FROM scores WHERE option_id = :oid');
        
        foreach ($answers as $qid => $oid) {
            // Haal weging van de vraag op
            $weightStmt->execute([':qid' => $qid]);
            $weightRow = $weightStmt->fetch();
            $weight = $weightRow ? (float)$weightRow['weight'] : 1.0;
            
            // Haal scores op voor dit antwoord
            $scoreStmt->execute([':oid' => $oid]);
            $rows = $scoreStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($rows as $r) {
                $lid = $r['laptop_id'];
                $pts = (int)$r['points'];
                
                if (!isset($scores[$lid])) continue;
                
                // Pas weging toe op de punten
                $weightedPoints = $pts * $weight;
                $scores[$lid]['points'] += $weightedPoints;

                if ($pts > 0) {
                    $scores[$lid]['matches'] += 1;
                }
                
                // Voeg reden toe als er punten zijn gescoord
                if ($pts > 0 && !empty($r['reason'])) {
                    $scores[$lid]['reasons'][] = $r['reason'];
                }
            }
        }

        // Converteer naar array en sorteer op punten (hoogste eerst)
        $result = array_values($scores);
        usort($result, function ($a, $b) {
            // 1) Meeste punten wint
            $cmp = $b['points'] <=> $a['points'];
            if ($cmp !== 0) {
                return $cmp;
            }

            // 2) Bij gelijke punten: meeste positieve matches wint
            $cmp = ($b['matches'] ?? 0) <=> ($a['matches'] ?? 0);
            if ($cmp !== 0) {
                return $cmp;
            }

            // 3) Bij gelijke match: goedkoopste eerst (null prijs -> achteraan)
            $aPrice = ($a['price_eur'] === null) ? PHP_FLOAT_MAX : (float)$a['price_eur'];
            $bPrice = ($b['price_eur'] === null) ? PHP_FLOAT_MAX : (float)$b['price_eur'];
            $cmp = $aPrice <=> $bPrice;
            if ($cmp !== 0) {
                return $cmp;
            }

            // 4) Als laatste: alfabetisch
            return strcmp((string)$a['name'], (string)$b['name']);
        });

        return $result;
    }
}