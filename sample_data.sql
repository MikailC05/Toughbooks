-- Voorbeeld data voor Toughbook Configurator

-- Toughbook modellen invoeren
INSERT INTO toughbook_models (model_name, base_model_number, description, base_price, image_url) VALUES
('Toughbook G2', 'CF-33-G2', 'Volledig robuuste 2-in-1 laptop', 3500.00, 'images/g2.jpg'),
('Toughbook 55', 'CF-55-MK3', 'Semi-robuuste modulaire laptop', 2800.00, 'images/55.jpg'),
('Toughbook 40', 'CF-40-MK2', 'Volledig robuuste laptop met modulaire bays', 4200.00, 'images/40.jpg'),
('Toughbook 33', 'CF-33-MK1', 'Volledig robuuste 2-in-1 detachable', 3800.00, 'images/33.jpg');

-- Vragenlijst vragen
INSERT INTO questionnaire_questions (question_text, question_order) VALUES
('Wilt u de Toughbook gebruiken in stromende regen?', 1),
('Heeft u een touchscreen nodig?', 2),
('Wat is de belangrijkste gebruiksomgeving?', 3);

-- Antwoorden voor vraag 1 (regen)
INSERT INTO questionnaire_answers (question_id, answer_text, answer_order) VALUES
(1, 'Ja', 1),
(1, 'Nee', 2);

-- Antwoorden voor vraag 2 (touchscreen)
INSERT INTO questionnaire_answers (question_id, answer_text, answer_order) VALUES
(2, 'Ja, absoluut noodzakelijk', 1),
(2, 'Optioneel', 2),
(2, 'Niet nodig', 3);

-- Antwoorden voor vraag 3 (omgeving)
INSERT INTO questionnaire_answers (question_id, answer_text, answer_order) VALUES
(3, 'Buitenwerk (bouw, veldwerk)', 1),
(3, 'Voertuig montage', 2),
(3, 'Kantoor met occasioneel buitenwerk', 3);

-- Punten toekennen per antwoord per model
-- Vraag 1: Regen - Ja
INSERT INTO model_scores (answer_id, model_id, points) VALUES
(1, 1, 20), -- G2: +20 punten
(1, 2, 0),  -- 55: 0 punten
(1, 3, 20), -- 40: +20 punten
(1, 4, 20); -- 33: +20 punten

-- Vraag 1: Regen - Nee
INSERT INTO model_scores (answer_id, model_id, points) VALUES
(2, 1, 0),
(2, 2, 10), -- 55 krijgt voordeel als geen regen nodig
(2, 3, 0),
(2, 4, 0);

-- Vraag 2: Touchscreen - Ja
INSERT INTO model_scores (answer_id, model_id, points) VALUES
(3, 1, 15), -- G2: detachable touchscreen
(3, 2, 5),  -- 55: optioneel touchscreen
(3, 3, 10), -- 40: touchscreen optie
(3, 4, 15); -- 33: detachable touchscreen

-- Vraag 2: Touchscreen - Optioneel
INSERT INTO model_scores (answer_id, model_id, points) VALUES
(4, 1, 5),
(4, 2, 10),
(4, 3, 5),
(4, 4, 5);

-- Vraag 2: Touchscreen - Niet nodig
INSERT INTO model_scores (answer_id, model_id, points) VALUES
(5, 1, 0),
(5, 2, 10),
(5, 3, 5),
(5, 4, 0);

-- Vraag 3: Buitenwerk
INSERT INTO model_scores (answer_id, model_id, points) VALUES
(6, 1, 15),
(6, 2, 0),
(6, 3, 20),
(6, 4, 15);

-- Vraag 3: Voertuig montage
INSERT INTO model_scores (answer_id, model_id, points) VALUES
(7, 1, 10),
(7, 2, 15),
(7, 3, 15),
(7, 4, 10);

-- Vraag 3: Kantoor met buitenwerk
INSERT INTO model_scores (answer_id, model_id, points) VALUES
(8, 1, 5),
(8, 2, 20),
(8, 3, 5),
(8, 4, 5);

-- Configuratie categorieën (Stap 3 - beïnvloedt modelnummer)
INSERT INTO configuration_categories (category_name, category_order, affects_model_number) VALUES
('Scherm', 1, 1),
('Processor', 2, 1),
('Geheugen (RAM)', 3, 1),
('Opslag', 4, 1),
('GPS Module', 5, 1),
('4G/5G Modem', 6, 1);

-- Configuratie categorieën (Stap 4 - beïnvloedt modelnummer NIET)
INSERT INTO configuration_categories (category_name, category_order, affects_model_number) VALUES
('Keyboard Layout', 7, 0),
('Garantie Uitbreiding', 8, 0),
('Extra Accu', 9, 0),
('Draagtas', 10, 0),
('Schouderriem', 11, 0);

-- Scherm opties
INSERT INTO configuration_options (category_id, option_name, option_code, price_modifier, option_order, is_default) VALUES
(1, '14" HD Activematrix-LCD (1366 x 768)', 'HD', 0.00, 1, 1),
(1, '14" Full HD Touch (1920 x 1080)', 'FHD-T', 450.00, 2, 0);

-- Processor opties
INSERT INTO configuration_options (category_id, option_name, option_code, price_modifier, option_order, is_default) VALUES
(2, 'Intel Core i5-1145G7 vPro', 'I5', 0.00, 1, 1),
(2, 'Intel Core i7-1185G7 vPro', 'I7', 600.00, 2, 0);

-- Geheugen opties
INSERT INTO configuration_options (category_id, option_name, option_code, price_modifier, option_order, is_default) VALUES
(3, '8GB RAM', '8GB', 0.00, 1, 1),
(3, '16GB RAM', '16GB', 200.00, 2, 0),
(3, '32GB RAM', '32GB', 500.00, 3, 0);

-- Opslag opties
INSERT INTO configuration_options (category_id, option_name, option_code, price_modifier, option_order, is_default) VALUES
(4, '256GB SSD', '256', 0.00, 1, 1),
(4, '512GB SSD', '512', 150.00, 2, 0),
(4, '1TB SSD', '1TB', 350.00, 3, 0);

-- GPS opties
INSERT INTO configuration_options (category_id, option_name, option_code, price_modifier, option_order, is_default) VALUES
(5, 'Geen GPS', '', 0.00, 1, 1),
(5, 'GPS Module', 'GPS', 180.00, 2, 0);

-- 4G/5G opties
INSERT INTO configuration_options (category_id, option_name, option_code, price_modifier, option_order, is_default) VALUES
(6, 'Geen Modem', '', 0.00, 1, 1),
(6, '4G LTE Modem', '4G', 280.00, 2, 0),
(6, '5G Modem', '5G', 450.00, 3, 0);

-- Keyboard opties (geen invloed op modelnummer)
INSERT INTO configuration_options (category_id, option_name, option_code, price_modifier, option_order, is_default) VALUES
(7, 'QWERTY Nederlands', '', 0.00, 1, 1),
(7, 'QWERTY US International', '', 0.00, 2, 0),
(7, 'QWERTZ Duits', '', 0.00, 3, 0);

-- Garantie opties
INSERT INTO configuration_options (category_id, option_name, option_code, price_modifier, option_order, is_default) VALUES
(8, 'Standaard 3 jaar', '', 0.00, 1, 1),
(8, 'Uitgebreid 5 jaar', '', 350.00, 2, 0);

-- Extra accu opties
INSERT INTO configuration_options (category_id, option_name, option_code, price_modifier, option_order, is_default) VALUES
(9, 'Geen extra accu', '', 0.00, 1, 1),
(9, 'Extra accu (6-cell)', '', 120.00, 2, 0),
(9, 'Extra accu (9-cell)', '', 180.00, 3, 0);

-- Draagtas opties
INSERT INTO configuration_options (category_id, option_name, option_code, price_modifier, option_order, is_default) VALUES
(10, 'Geen draagtas', '', 0.00, 1, 1),
(10, 'Standaard draagtas', '', 45.00, 2, 0),
(10, 'Premium robuuste draagtas', '', 95.00, 3, 0);

-- Schouderriem opties
INSERT INTO configuration_options (category_id, option_name, option_code, price_modifier, option_order, is_default) VALUES
(11, 'Geen schouderriem', '', 0.00, 1, 1),
(11, 'Schouderriem', '', 25.00, 2, 0);

-- Model beschikbare opties koppelen (voorbeeld voor CF-55)
-- Alle opties beschikbaar maken voor model 2 (CF-55)
INSERT INTO model_available_options (model_id, option_id)
SELECT 2, id FROM configuration_options;

-- Voor andere modellen ook alle opties beschikbaar maken (in praktijk kun je dit selectiever doen)
INSERT INTO model_available_options (model_id, option_id)
SELECT 1, id FROM configuration_options;

INSERT INTO model_available_options (model_id, option_id)
SELECT 3, id FROM configuration_options;

INSERT INTO model_available_options (model_id, option_id)
SELECT 4, id FROM configuration_options;
