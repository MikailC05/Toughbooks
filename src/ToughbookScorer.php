<?php

class ToughbookScorer {
    private $pdo;
    private $configurationsFile;
    private $configs;
    
    public function __construct($pdo, $configurationsFile) {
        $this->pdo = $pdo;
        $this->configurationsFile = $configurationsFile;
        $this->loadConfigurations();
    }
    
    private function loadConfigurations() {
        if (file_exists($this->configurationsFile)) {
            $jsonData = file_get_contents($this->configurationsFile);
            $this->configs = json_decode($jsonData, true) ?? [];
        } else {
            $this->configs = [];
        }
    }
    
    /**
     * Ken automatisch scores toe op basis van een spec criterium
     * 
     * @param int $yesOptionId - Option ID voor "Ja" antwoord
     * @param int $noOptionId - Option ID voor "Nee" antwoord  
     * @param string $specKey - De spec die gecheckt moet worden (bijv. 'gps', 'touchscreen')
     * @param string $specValue - De waarde die de spec moet hebben (bijv. 'Yes', '4G')
     * @param int $pointsIfMatch - Punten als laptop de spec heeft
     * @param int $pointsIfNoMatch - Punten als laptop de spec NIET heeft
     */
    public function assignScoresBySpec($yesOptionId, $noOptionId, $specKey, $specValue, $pointsIfMatch = 30, $pointsIfNoMatch = 0) {
        // Haal alle actieve laptops op met hun specs
        $laptops = $this->pdo->query('SELECT id, model_code FROM laptops WHERE is_active = 1')->fetchAll();
        
        $scoreStmt = $this->pdo->prepare('INSERT INTO scores (laptop_id, option_id, points, reason) VALUES (?, ?, ?, ?)');
        
        foreach ($laptops as $laptop) {
            $laptopSpecs = $this->getLaptopSpecs($laptop['model_code']);
            
            // Check of deze laptop de gewenste spec heeft
            $hasSpec = isset($laptopSpecs[$specKey]) && $laptopSpecs[$specKey] === $specValue;
            
            if ($hasSpec) {
                // Ja-antwoord: geef punten
                $scoreStmt->execute([
                    $laptop['id'], 
                    $yesOptionId, 
                    $pointsIfMatch, 
                    "Laptop heeft $specKey: $specValue"
                ]);
                
                // Nee-antwoord: geef geen punten
                $scoreStmt->execute([
                    $laptop['id'], 
                    $noOptionId, 
                    $pointsIfNoMatch, 
                    "Laptop heeft $specKey, maar gebruiker heeft dit niet nodig"
                ]);
            } else {
                // Ja-antwoord: geef geen punten (laptop heeft spec niet)
                $scoreStmt->execute([
                    $laptop['id'], 
                    $yesOptionId, 
                    $pointsIfNoMatch, 
                    "Laptop heeft geen $specKey: $specValue"
                ]);
                
                // Nee-antwoord: geef punten (laptop heeft spec niet, dat is goed)
                $scoreStmt->execute([
                    $laptop['id'], 
                    $noOptionId, 
                    $pointsIfMatch, 
                    "Laptop heeft geen $specKey, past bij gebruiker"
                ]);
            }
        }
    }
    
    /**
     * Haal specs op voor een specifiek model code
     */
    private function getLaptopSpecs($modelCode) {
        foreach ($this->configs as $modelName => $configurations) {
            foreach ($configurations as $config) {
                if ($config['model_code'] === $modelCode) {
                    return $config['specs'] ?? [];
                }
            }
        }
        return [];
    }
    
    /**
     * Ken standaard scores toe (geen spec matching)
     */
    public function assignDefaultScores($yesOptionId, $noOptionId) {
        $laptops = $this->pdo->query('SELECT id FROM laptops WHERE is_active = 1')->fetchAll(PDO::FETCH_COLUMN);
        $scoreStmt = $this->pdo->prepare('INSERT INTO scores (laptop_id, option_id, points, reason) VALUES (?, ?, ?, ?)');
        
        foreach ($laptops as $laptopId) {
            $scoreStmt->execute([$laptopId, $yesOptionId, 0, 'Standaard score']);
            $scoreStmt->execute([$laptopId, $noOptionId, 0, 'Standaard score']);
        }
    }
    
    /**
     * Update bestaande scores op basis van een spec
     */
    public function updateScoresBySpec($questionId, $specKey, $specValue, $pointsIfMatch = 30, $pointsIfNoMatch = 0) {
        // Haal de optie IDs op voor deze vraag
        $options = $this->pdo->prepare('SELECT id, value FROM options WHERE question_id = ?');
        $options->execute([$questionId]);
        $optionData = $options->fetchAll();
        
        $yesOptionId = null;
        $noOptionId = null;
        
        foreach ($optionData as $opt) {
            if ($opt['value'] === 'yes') $yesOptionId = $opt['id'];
            if ($opt['value'] === 'no') $noOptionId = $opt['id'];
        }
        
        if (!$yesOptionId || !$noOptionId) {
            return false;
        }
        
        // Haal alle laptops op
        $laptops = $this->pdo->query('SELECT id, model_code FROM laptops WHERE is_active = 1')->fetchAll();
        
        $updateStmt = $this->pdo->prepare('UPDATE scores SET points = ?, reason = ? WHERE laptop_id = ? AND option_id = ?');
        
        foreach ($laptops as $laptop) {
            $laptopSpecs = $this->getLaptopSpecs($laptop['model_code']);
            $hasSpec = isset($laptopSpecs[$specKey]) && $laptopSpecs[$specKey] === $specValue;
            
            if ($hasSpec) {
                $updateStmt->execute([
                    $pointsIfMatch, 
                    "Laptop heeft $specKey: $specValue", 
                    $laptop['id'], 
                    $yesOptionId
                ]);
                $updateStmt->execute([
                    $pointsIfNoMatch, 
                    "Laptop heeft $specKey, maar niet nodig", 
                    $laptop['id'], 
                    $noOptionId
                ]);
            } else {
                $updateStmt->execute([
                    $pointsIfNoMatch, 
                    "Laptop heeft geen $specKey: $specValue", 
                    $laptop['id'], 
                    $yesOptionId
                ]);
                $updateStmt->execute([
                    $pointsIfMatch, 
                    "Laptop heeft geen $specKey, past bij gebruiker", 
                    $laptop['id'], 
                    $noOptionId
                ]);
            }
        }
        
        return true;
    }
    
    /**
     * Haal beschikbare spec opties op
     */
    public static function getAvailableSpecOptions() {
        return [
            'none' => 'Geen automatische scoring (handmatig instellen)',
            'gps' => 'GPS Module',
            'touchscreen' => 'Touchscreen',
            'cellular_4g' => '4G/LTE Ondersteuning',
            'cellular_5g' => '5G Ondersteuning',
            'cellular_none' => 'Geen mobiel internet (WLAN only)',
            'keyboard_azerty' => 'AZERTY Toetsenbord',
            'keyboard_qwerty' => 'QWERTY Toetsenbord',
            'form_factor_tablet' => 'Tablet Only',
            'form_factor_2in1' => '2-in-1 Detachable',
            'display_fhd' => 'Full HD Display',
            'display_hd' => 'HD Display'
        ];
    }
    
    /**
     * Vertaal spec optie naar spec key en value
     */
    public static function getSpecMapping($specOption) {
        $mappings = [
            'gps' => ['key' => 'gps', 'value' => 'Yes'],
            'touchscreen' => ['key' => 'touchscreen', 'value' => 'Yes'],
            'cellular_4g' => ['key' => 'cellular', 'value' => '4G'],
            'cellular_5g' => ['key' => 'cellular', 'value' => '5G'],
            'cellular_none' => ['key' => 'cellular', 'value' => 'None'],
            'keyboard_azerty' => ['key' => 'keyboard', 'value' => 'AZERTY (BE)'],
            'keyboard_qwerty' => ['key' => 'keyboard', 'value' => 'QWERTY (US/INT)'],
            'form_factor_tablet' => ['key' => 'form_factor', 'value' => 'Tablet Only'],
            'form_factor_2in1' => ['key' => 'form_factor', 'value' => '2-in-1 Detachable'],
            'display_fhd' => ['key' => 'display', 'value' => 'FHD'],
            'display_hd' => ['key' => 'display', 'value' => 'HD']
        ];
        
        return $mappings[$specOption] ?? null;
    }
}