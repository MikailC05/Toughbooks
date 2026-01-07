-- Database schema voor Toughbook Configurator

-- Toughbook modellen
CREATE TABLE toughbook_models (
    id INT PRIMARY KEY AUTO_INCREMENT,
    model_name VARCHAR(100) NOT NULL,
    base_model_number VARCHAR(50) NOT NULL,
    description TEXT,
    base_price DECIMAL(10,2),
    image_url VARCHAR(255),
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Vragenlijst vragen (stap 1)
CREATE TABLE questionnaire_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question_text TEXT NOT NULL,
    question_order INT NOT NULL,
    is_active BOOLEAN DEFAULT 1
);

-- Vragenlijst antwoorden
CREATE TABLE questionnaire_answers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    question_id INT,
    answer_text VARCHAR(255) NOT NULL,
    answer_order INT NOT NULL,
    FOREIGN KEY (question_id) REFERENCES questionnaire_questions(id) ON DELETE CASCADE
);

-- Punten per model per antwoord
CREATE TABLE model_scores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    answer_id INT,
    model_id INT,
    points INT NOT NULL,
    FOREIGN KEY (answer_id) REFERENCES questionnaire_answers(id) ON DELETE CASCADE,
    FOREIGN KEY (model_id) REFERENCES toughbook_models(id) ON DELETE CASCADE
);

-- Configuratie categorieën (stap 3 & 4)
CREATE TABLE configuration_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    category_order INT NOT NULL,
    affects_model_number BOOLEAN DEFAULT 0, -- TRUE voor stap 3, FALSE voor stap 4
    is_active BOOLEAN DEFAULT 1
);

-- Configuratie opties
CREATE TABLE configuration_options (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT,
    option_name VARCHAR(255) NOT NULL,
    option_code VARCHAR(50), -- Voor modelnummer wijziging
    price_modifier DECIMAL(10,2) DEFAULT 0,
    option_order INT NOT NULL,
    is_default BOOLEAN DEFAULT 0,
    FOREIGN KEY (category_id) REFERENCES configuration_categories(id) ON DELETE CASCADE
);

-- Welke opties zijn beschikbaar per model
CREATE TABLE model_available_options (
    id INT PRIMARY KEY AUTO_INCREMENT,
    model_id INT,
    option_id INT,
    FOREIGN KEY (model_id) REFERENCES toughbook_models(id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES configuration_options(id) ON DELETE CASCADE,
    UNIQUE KEY unique_model_option (model_id, option_id)
);

-- Configuratie sessies (voor tracking)
CREATE TABLE configuration_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_key VARCHAR(100) UNIQUE NOT NULL,
    selected_model_id INT,
    questionnaire_data JSON,
    configuration_data JSON,
    final_model_number VARCHAR(100),
    total_price DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (selected_model_id) REFERENCES toughbook_models(id)
);

-- Offerte aanvragen
CREATE TABLE quote_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    configuration_session_id INT,
    company_name VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (configuration_session_id) REFERENCES configuration_sessions(id)
);

-- Indexes voor betere performance
CREATE INDEX idx_question_order ON questionnaire_questions(question_order);
CREATE INDEX idx_answer_question ON questionnaire_answers(question_id);
CREATE INDEX idx_category_order ON configuration_categories(category_order);
CREATE INDEX idx_option_category ON configuration_options(category_id);
CREATE INDEX idx_session_key ON configuration_sessions(session_key);
