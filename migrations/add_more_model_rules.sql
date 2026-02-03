-- Extra modelnummer regels toevoegen voor Azerty (BE)
-- Aangemaakt: 2026-01-29

-- Voeg extra Azerty (BE) combinaties toe
INSERT IGNORE INTO model_number_rules (keyboard_type, wireless_type, screen_type, model_number, price_eur) VALUES
('Azerty (BE)', 'WLAN', 'HD scherm', 'FZ-55G6601Z4', 0.00),
('Azerty (BE)', 'WLAN + WWAN + 4G + GPS', 'HD scherm', 'FZ-55GZ010Z4', 0.00),
('Azerty (BE)', 'WLAN', 'Full HD + Touchscreen', 'FZ-55J2601Z4', 0.00);

-- Opmerking: De combinatie ('Azerty (BE)', 'WLAN + WWAN + 4G + GPS', 'Full HD + Touchscreen', 'FZ-55JZ00ZB4') bestaat al
