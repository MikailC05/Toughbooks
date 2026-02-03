<?php
/**
 * API voor het ophalen van modelnummers op basis van configuratie
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../src/Database.php';

try {
    $pdo = Database::getInstance()->getPdo();

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_options':
            // Haal alle configuratie opties op
            $stmt = $pdo->query('SELECT * FROM configuration_options WHERE is_active = 1 ORDER BY option_type, display_order');
            $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = [
                'keyboard' => array_values(array_filter($options, fn($o) => $o['option_type'] === 'keyboard')),
                'wireless' => array_values(array_filter($options, fn($o) => $o['option_type'] === 'wireless')),
                'screen' => array_values(array_filter($options, fn($o) => $o['option_type'] === 'screen'))
            ];

            echo json_encode([
                'success' => true,
                'options' => $response
            ]);
            break;

        case 'get_model_number':
            // Haal het modelnummer op basis van de geselecteerde opties
            $keyboard = $_GET['keyboard'] ?? '';
            $wireless = $_GET['wireless'] ?? '';
            $screen = $_GET['screen'] ?? '';

            if (empty($keyboard) || empty($wireless) || empty($screen)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Alle opties moeten geselecteerd zijn'
                ]);
                break;
            }

            $stmt = $pdo->prepare(
                'SELECT * FROM model_number_rules
                WHERE keyboard_type = ? AND wireless_type = ? AND screen_type = ? AND is_active = 1
                LIMIT 1'
            );
            $stmt->execute([$keyboard, $wireless, $screen]);
            $rule = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($rule) {
                echo json_encode([
                    'success' => true,
                    'model_number' => $rule['model_number'],
                    'price_eur' => number_format((float)$rule['price_eur'], 2, '.', ''),
                    'description' => $rule['description'] ?? ''
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Geen modelnummer gevonden voor deze combinatie. Neem contact op met de beheerder.'
                ]);
            }
            break;

        default:
            echo json_encode([
                'success' => false,
                'error' => 'Ongeldige actie'
            ]);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server fout: ' . $e->getMessage()
    ]);
}
