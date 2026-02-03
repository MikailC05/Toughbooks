-- Migratie voor dynamisch modelnummer systeem
-- Aangemaakt: 2026-01-28

-- Tabel voor modelnummer regels
CREATE TABLE IF NOT EXISTS model_number_rules (
    id INT(11) NOT NULL AUTO_INCREMENT,
    keyboard_type VARCHAR(50) NOT NULL COMMENT 'Qwerty (NL), Azerty (BE), etc.',
    wireless_type VARCHAR(100) NOT NULL COMMENT 'WLAN, WLAN + WWAN + 4G + GPS, etc.',
    screen_type VARCHAR(100) NOT NULL COMMENT 'HD scherm, Full HD + Touchscreen, etc.',
    model_number VARCHAR(100) NOT NULL COMMENT 'Resultaat modelnummer bijv. FZ-55JZ011B4',
    price_eur DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Prijs voor deze configuratie',
    description TEXT DEFAULT NULL COMMENT 'Extra beschrijving van deze configuratie',
    is_active TINYINT(1) DEFAULT 1 COMMENT '1 = actief, 0 = inactief',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_combination (keyboard_type, wireless_type, screen_type),
    KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel voor de opties (zodat admin deze kan beheren)
CREATE TABLE IF NOT EXISTS configuration_options (
    id INT(11) NOT NULL AUTO_INCREMENT,
    option_type ENUM('keyboard', 'wireless', 'screen') NOT NULL,
    option_value VARCHAR(100) NOT NULL COMMENT 'De waarde bijv. "Qwerty (NL)"',
    display_order INT(11) DEFAULT 0 COMMENT 'Volgorde waarin getoond',
    is_active TINYINT(1) DEFAULT 1 COMMENT '1 = actief, 0 = inactief',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_option (option_type, option_value),
    KEY idx_type_order (option_type, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standaard opties invoegen
INSERT INTO configuration_options (option_type, option_value, display_order) VALUES
('keyboard', 'Qwerty (NL)', 1),
('keyboard', 'Azerty (BE)', 2),
('wireless', 'WLAN', 1),
('wireless', 'WLAN + WWAN + 4G + GPS', 2),
('screen', 'HD scherm', 1),
('screen', 'Full HD + Touchscreen', 2);

-- Voorbeelddata invoegen (zoals aangegeven door gebruiker)
INSERT INTO model_number_rules (keyboard_type, wireless_type, screen_type, model_number, price_eur) VALUES
-- Qwerty (NL) combinaties
('Qwerty (NL)', 'WLAN', 'HD scherm', 'FZ-55G6601B4', 0.00),
('Qwerty (NL)', 'WLAN + WWAN + 4G + GPS', 'HD scherm', 'FZ-55GZ010B4', 0.00),
('Qwerty (NL)', 'WLAN + WWAN + 4G + GPS', 'Full HD + Touchscreen', 'FZ-55JZ011B4', 0.00),
('Qwerty (NL)', 'WLAN', 'Full HD + Touchscreen', 'FZ-55J2601B4', 0.00),

-- Azerty (BE) combinaties
('Azerty (BE)', 'WLAN', 'HD scherm', 'FZ-55G6601Z4', 0.00),
('Azerty (BE)', 'WLAN + WWAN + 4G + GPS', 'HD scherm', 'FZ-55GZ010Z4', 0.00),
('Azerty (BE)', 'WLAN + WWAN + 4G + GPS', 'Full HD + Touchscreen', 'FZ-55JZ00ZB4', 0.00),
('Azerty (BE)', 'WLAN', 'Full HD + Touchscreen', 'FZ-55J2601Z4', 0.00);

-- Opmerking: Admin kan later meer combinaties toevoegen via het admin panel
