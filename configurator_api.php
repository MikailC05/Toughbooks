<?php
// api/configurator_api.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Helper functie voor response
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// API Endpoints
switch($action) {
    
    // Stap 1: Vragenlijst ophalen
    case 'get_questionnaire':
        try {
            $query = "SELECT q.id, q.question_text, q.question_order,
                      JSON_ARRAYAGG(
                          JSON_OBJECT(
                              'id', a.id,
                              'text', a.answer_text,
                              'order', a.answer_order
                          )
                      ) as answers
                      FROM questionnaire_questions q
                      LEFT JOIN questionnaire_answers a ON q.id = a.question_id
                      WHERE q.is_active = 1
                      GROUP BY q.id
                      ORDER BY q.question_order";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Parse JSON strings
            foreach($questions as &$question) {
                $question['answers'] = json_decode($question['answers']);
            }
            
            sendResponse(['success' => true, 'questions' => $questions]);
            
        } catch(PDOException $e) {
            sendResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
        break;
    
    // Stap 2: Berekenen aanbevolen modellen op basis van antwoorden
    case 'calculate_recommendations':
        try {
            $input = json_decode(file_get_contents("php://input"), true);
            $answers = $input['answers']; // Array van answer_ids
            
            $placeholders = implode(',', array_fill(0, count($answers), '?'));
            
            $query = "SELECT 
                        m.id,
                        m.model_name,
                        m.base_model_number,
                        m.description,
                        m.base_price,
                        m.image_url,
                        SUM(ms.points) as total_points
                      FROM toughbook_models m
                      LEFT JOIN model_scores ms ON m.id = ms.model_id
                      WHERE ms.answer_id IN ($placeholders)
                      GROUP BY m.id
                      ORDER BY total_points DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute($answers);
            $recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendResponse(['success' => true, 'recommendations' => $recommendations]);
            
        } catch(PDOException $e) {
            sendResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
        break;
    
    // Stap 3 & 4: Configuratie opties ophalen voor geselecteerd model
    case 'get_configuration_options':
        try {
            $model_id = $_GET['model_id'];
            
            $query = "SELECT 
                        c.id as category_id,
                        c.category_name,
                        c.category_order,
                        c.affects_model_number,
                        JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id', o.id,
                                'name', o.option_name,
                                'code', o.option_code,
                                'price', o.price_modifier,
                                'order', o.option_order,
                                'is_default', o.is_default
                            )
                        ) as options
                      FROM configuration_categories c
                      INNER JOIN configuration_options o ON c.id = o.category_id
                      INNER JOIN model_available_options mao ON o.id = mao.option_id
                      WHERE mao.model_id = ? AND c.is_active = 1
                      GROUP BY c.id
                      ORDER BY c.category_order";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$model_id]);
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Parse JSON en splits in stap 3 (affects_model_number=1) en stap 4 (=0)
            $step3_options = [];
            $step4_options = [];
            
            foreach($categories as &$category) {
                $category['options'] = json_decode($category['options']);
                
                if($category['affects_model_number'] == 1) {
                    $step3_options[] = $category;
                } else {
                    $step4_options[] = $category;
                }
            }
            
            sendResponse([
                'success' => true, 
                'step3_options' => $step3_options,
                'step4_options' => $step4_options
            ]);
            
        } catch(PDOException $e) {
            sendResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
        break;
    
    // Modelnummer genereren op basis van configuratie
    case 'generate_model_number':
        try {
            $input = json_decode(file_get_contents("php://input"), true);
            $model_id = $input['model_id'];
            $selected_options = $input['selected_options']; // Array van option_ids
            
            // Haal basis modelnummer op
            $stmt = $db->prepare("SELECT base_model_number FROM toughbook_models WHERE id = ?");
            $stmt->execute([$model_id]);
            $base = $stmt->fetch(PDO::FETCH_ASSOC);
            $model_number = $base['base_model_number'];
            
            // Haal optie codes op die modelnummer beïnvloeden
            $placeholders = implode(',', array_fill(0, count($selected_options), '?'));
            $stmt = $db->prepare("
                SELECT o.option_code 
                FROM configuration_options o
                INNER JOIN configuration_categories c ON o.category_id = c.id
                WHERE o.id IN ($placeholders) 
                AND c.affects_model_number = 1
                AND o.option_code IS NOT NULL
                AND o.option_code != ''
                ORDER BY c.category_order
            ");
            $stmt->execute($selected_options);
            $codes = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Voeg codes toe aan modelnummer
            if(!empty($codes)) {
                $model_number .= '-' . implode('-', $codes);
            }
            
            sendResponse(['success' => true, 'model_number' => $model_number]);
            
        } catch(PDOException $e) {
            sendResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
        break;
    
    // Totale prijs berekenen
    case 'calculate_total_price':
        try {
            $input = json_decode(file_get_contents("php://input"), true);
            $model_id = $input['model_id'];
            $selected_options = $input['selected_options'];
            
            // Basis prijs
            $stmt = $db->prepare("SELECT base_price FROM toughbook_models WHERE id = ?");
            $stmt->execute([$model_id]);
            $base_price = $stmt->fetch(PDO::FETCH_ASSOC)['base_price'];
            
            // Optie prijzen
            $total_price = $base_price;
            
            if(!empty($selected_options)) {
                $placeholders = implode(',', array_fill(0, count($selected_options), '?'));
                $stmt = $db->prepare("SELECT SUM(price_modifier) as total_modifiers FROM configuration_options WHERE id IN ($placeholders)");
                $stmt->execute($selected_options);
                $modifiers = $stmt->fetch(PDO::FETCH_ASSOC)['total_modifiers'];
                $total_price += $modifiers;
            }
            
            sendResponse(['success' => true, 'total_price' => number_format($total_price, 2)]);
            
        } catch(PDOException $e) {
            sendResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
        break;
    
    // Sessie opslaan
    case 'save_configuration':
        try {
            $input = json_decode(file_get_contents("php://input"), true);
            
            $session_key = bin2hex(random_bytes(16));
            
            $stmt = $db->prepare("
                INSERT INTO configuration_sessions 
                (session_key, selected_model_id, questionnaire_data, configuration_data, final_model_number, total_price)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $session_key,
                $input['model_id'],
                json_encode($input['questionnaire_data']),
                json_encode($input['configuration_data']),
                $input['model_number'],
                $input['total_price']
            ]);
            
            sendResponse(['success' => true, 'session_key' => $session_key]);
            
        } catch(PDOException $e) {
            sendResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
        break;
    
    // Configuratie ophalen via session key
    case 'get_configuration':
        try {
            $session_key = $_GET['session_key'];
            
            $stmt = $db->prepare("
                SELECT cs.*, m.model_name, m.image_url, m.base_model_number
                FROM configuration_sessions cs
                INNER JOIN toughbook_models m ON cs.selected_model_id = m.id
                WHERE cs.session_key = ?
            ");
            $stmt->execute([$session_key]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($config) {
                $config['questionnaire_data'] = json_decode($config['questionnaire_data']);
                $config['configuration_data'] = json_decode($config['configuration_data']);
                sendResponse(['success' => true, 'configuration' => $config]);
            } else {
                sendResponse(['success' => false, 'error' => 'Configuratie niet gevonden'], 404);
            }
            
        } catch(PDOException $e) {
            sendResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
        break;
    
    // Offerte aanvraag versturen
    case 'submit_quote_request':
        try {
            $input = json_decode(file_get_contents("php://input"), true);
            
            // Opslaan in database
            $stmt = $db->prepare("
                INSERT INTO quote_requests 
                (configuration_session_id, company_name, contact_name, email, phone, remarks)
                SELECT id, ?, ?, ?, ?, ?
                FROM configuration_sessions
                WHERE session_key = ?
            ");
            
            $stmt->execute([
                $input['company_name'],
                $input['contact_name'],
                $input['email'],
                $input['phone'],
                $input['remarks'],
                $input['session_key']
            ]);
            
            // Email versturen (simpele versie)
            $to = "verkoop@voorbeeld.nl"; // Vervang met jullie email
            $subject = "Nieuwe offerte aanvraag - Toughbook Configurator";
            
            $message = "Nieuwe offerte aanvraag:\n\n";
            $message .= "Bedrijfsnaam: " . $input['company_name'] . "\n";
            $message .= "Contactpersoon: " . $input['contact_name'] . "\n";
            $message .= "Email: " . $input['email'] . "\n";
            $message .= "Telefoon: " . $input['phone'] . "\n";
            $message .= "Opmerkingen: " . $input['remarks'] . "\n\n";
            $message .= "Configuratie link: [BASE_URL]/configurator.php?session=" . $input['session_key'];
            
            $headers = "From: noreply@voorbeeld.nl\r\n";
            $headers .= "Reply-To: " . $input['email'] . "\r\n";
            
            mail($to, $subject, $message, $headers);
            
            sendResponse(['success' => true, 'message' => 'Offerte aanvraag verzonden']);
            
        } catch(PDOException $e) {
            sendResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
        break;
    
    default:
        sendResponse(['success' => false, 'error' => 'Onbekende actie'], 400);
}
?>
