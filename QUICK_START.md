# Toughbook Configurator - Quick Start Guide

## 🚀 Snelle Installatie (5 minuten)

### Stap 1: Bestanden Plaatsen
```bash
# Upload alle bestanden naar je webserver
# Bijvoorbeeld via FTP naar: /var/www/html/toughbook/
```

### Stap 2: Database Aanmaken
```bash
# Optie A: Automatisch met install.sh
chmod +x install.sh
./install.sh

# Optie B: Handmatig
mysql -u root -p
CREATE DATABASE toughbook_configurator;
USE toughbook_configurator;
SOURCE database_schema.sql;
SOURCE sample_data.sql;
```

### Stap 3: Database Configuratie
Open `config/database.php` en pas aan:
```php
private $host = "localhost";
private $db_name = "toughbook_configurator";
private $username = "jouw_gebruiker";
private $password = "jouw_wachtwoord";
```

### Stap 4: Testen
Open in browser:
- **Homepage**: `http://jouwdomein.nl/toughbook/`
- **Configurator**: `http://jouwdomein.nl/toughbook/configurator.html`
- **CMS**: `http://jouwdomein.nl/toughbook/cms.html`

## 📁 Bestandsstructuur

```
toughbook-configurator/
├── index.html                 # Demo homepage
├── configurator.html          # Klant configurator
├── configurator.js            # Configurator logica
├── cms.html                   # Admin CMS panel
├── cms.js                     # CMS logica
├── api/
│   ├── configurator_api.php   # Configurator endpoints
│   └── cms_api.php            # CMS endpoints
├── config/
│   └── database.php           # Database connectie
├── database_schema.sql        # Database structuur
├── sample_data.sql            # Voorbeeld data
├── install.sh                 # Installatie script
└── README.md                  # Volledige documentatie
```

## ✨ Belangrijkste Features

### Configurator (Klant)
1. **Stap 1** - Vragenlijst: beantwoord 3 vragen
2. **Stap 2** - Aanbevelingen: zie gesorteerde modellen op basis van score
3. **Stap 3** - Hoofdopties: configureer scherm, processor, RAM, etc.
4. **Stap 4** - Extra opties: keyboard, garantie, accessoires
5. **Stap 5** - Samenvatting: modelnummer + prijs + offerte/bestellen

### CMS (Admin)
- Beheer Toughbook modellen
- Beheer vragenlijst vragen & antwoorden
- Beheer configuratie categorieën
- Beheer configuratie opties
- Bekijk offerte aanvragen

## 🎨 Styling Highlights

### Configurator
- **Kleurschema**: Professional blue (#0052cc)
- **Gradient backgrounds**: Moderne look
- **Smooth animations**: 0.3s transitions
- **Responsive**: Mobile-friendly
- **Hover effects**: Lift & shadow

### CMS
- **Kleurschema**: Purple gradient (#667eea)
- **Clean tables**: Easy data management
- **Modal forms**: Intuitive editing
- **Status badges**: Visual feedback

## 🔧 Belangrijke Configuratie

### Email Notificaties
In `api/configurator_api.php`, regel 247:
```php
$to = "jouw-verkoop@email.nl"; // ← Pas dit aan!
```

### Session Management
Sessions worden opgeslagen in `configuration_sessions` tabel.
Link format: `order.php?config={session_key}`

### Modelnummer Generatie
Formaat: `BASE-CODE1-CODE2-CODE3`
Voorbeeld: `CF-55-MK3-FHD-T-I7-16GB-512-GPS`

Alleen opties met `affects_model_number = 1` beïnvloeden het modelnummer.

## 📊 Database Structuur Uitleg

### Kern Tabellen
- `toughbook_models` - Basis modellen
- `questionnaire_questions` - Vragen
- `questionnaire_answers` - Antwoorden
- `model_scores` - Punten per antwoord per model
- `configuration_categories` - Opties categorieën
- `configuration_options` - Alle opties
- `configuration_sessions` - Opgeslagen configs
- `quote_requests` - Offerte aanvragen

### Relaties
```
model_scores
├── answer_id → questionnaire_answers
└── model_id → toughbook_models

configuration_options
└── category_id → configuration_categories

configuration_sessions
└── selected_model_id → toughbook_models

quote_requests
└── configuration_session_id → configuration_sessions
```

## 🎯 Eerste Stappen na Installatie

### 1. Product Afbeeldingen Toevoegen
Plaats afbeeldingen in `/images/`:
- `g2.jpg` - Toughbook G2
- `55.jpg` - Toughbook 55
- `40.jpg` - Toughbook 40
- `33.jpg` - Toughbook 33

### 2. Test de Configurator
- Open `configurator.html`
- Doorloop alle 5 stappen
- Test offerte aanvraag formulier
- Controleer of email verstuurd wordt

### 3. Test het CMS
- Open `cms.html`
- Bewerk een model
- Voeg een nieuwe optie toe
- Verwijder een test record

### 4. Bekijk Database
```sql
-- Check data
SELECT * FROM toughbook_models;
SELECT * FROM configuration_sessions;
SELECT * FROM quote_requests;
```

## ⚠️ Productie Checklist

Voordat je live gaat:

- [ ] Database credentials beveiligen
- [ ] CMS authenticatie toevoegen
- [ ] HTTPS instellen
- [ ] Email settings testen
- [ ] Backup systeem opzetten
- [ ] Error logging configureren
- [ ] Rate limiting implementeren
- [ ] Input validatie server-side
- [ ] CSRF protection toevoegen
- [ ] Producten & prijzen controleren

## 🐛 Troubleshooting

### Database connectie error
```
Check: config/database.php credentials
Test: mysql -u username -p
```

### API errors
```
Check: Browser console (F12)
Check: Apache/Nginx error logs
Verify: api/ files zijn geüpload
```

### Modelnummer update niet
```
Verify: category.affects_model_number = 1
Verify: option.option_code is ingevuld
Check: JavaScript console
```

### Email niet verzonden
```
Check: PHP mail() configuratie
Test: SMTP settings
Alternative: PHPMailer gebruiken
```

## 📞 Support

Voor vragen of problemen:
- Check eerst: `README.md` (volledige docs)
- Check ook: `PROJECT_STRUCTURE.md` (technische details)
- Check styling: `STYLING_GUIDE.md`
- Design info: `DESIGN_COMPARISON.md`

## 🎓 Tutorials

### Nieuw Model Toevoegen
1. Open CMS → Toughbook Modellen
2. Klik "+ Nieuw Model"
3. Vul in:
   - Naam: "Toughbook X1"
   - Basis modelnummer: "CF-X1-MK1"
   - Prijs: 2500.00
4. Opslaan

### Nieuwe Vraag Toevoegen
1. Open CMS → Vragenlijst
2. Klik "+ Nieuwe Vraag"
3. Vul vraag in
4. Stel volgorde in
5. Voeg antwoorden toe in database
6. Stel punten in via model_scores tabel

### Nieuwe Configuratie Optie
1. Open CMS → Configuratie Categorieën
2. Maak eerst categorie (indien nodig)
3. Ga naar Configuratie Opties
4. Klik "+ Nieuwe Optie"
5. Selecteer categorie
6. Vul optie details in
7. Code invullen indien affects_model_number = 1

## 🚀 Volgende Stappen

### Kort Termijn (Week 1)
1. Product afbeeldingen toevoegen
2. Echte product data invoeren
3. Email template verbeteren
4. Testing op verschillende devices

### Middellang Termijn (Maand 1)
1. CMS authenticatie implementeren
2. PDF export toevoegen
3. Saved configurations feature
4. Analytics integratie

### Lang Termijn (Kwartaal 1)
1. ERP integratie
2. Multi-language support
3. Advanced reporting
4. Mobile app overwegen

## 📈 Performance Tips

- Cache configuratie opties (Redis)
- CDN voor afbeeldingen
- Database indexes optimaliseren
- Minify CSS/JS voor productie
- Enable gzip compression

## 🔐 Security Tips

**Prioriteit 1:**
- CMS password protection
- HTTPS enforced
- SQL injection protection (already done)

**Prioriteit 2:**
- Rate limiting API
- CSRF tokens
- Input sanitization
- XSS protection

**Prioriteit 3:**
- Regular backups
- Log monitoring
- Security headers
- Pen testing

## 🎉 Klaar!

Je Toughbook Configurator is nu klaar voor gebruik!

Start met:
```
http://jouwdomein.nl/toughbook/
```

Veel succes! 🚀
