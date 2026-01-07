# Toughbook Configurator Systeem

## Overzicht
Dit is een product configurator systeem voor Toughbook laptops, gebaseerd op het PWA-electronics.de model. Het systeem bestaat uit:
- Een 5-stappen configurator voor klanten (modal-based)
- Een CMS voor beheer van alle data
- Een database voor opslag van modellen, vragen, opties en offertes

## Structuur

```
/
├── api/
│   ├── configurator_api.php    # API voor configurator frontend
│   └── cms_api.php              # API voor CMS backend
├── config/
│   └── database.php             # Database connectie
├── configurator.html            # Klant-facing configurator
├── configurator.js              # Configurator logica
├── cms.html                     # CMS admin panel
├── cms.js                       # CMS logica
├── database_schema.sql          # Database structuur
└── sample_data.sql              # Voorbeeld data
```

## Installatie

### 1. Database Opzetten

```sql
-- Maak database aan
CREATE DATABASE toughbook_configurator;

-- Importeer schema
SOURCE database_schema.sql;

-- Importeer voorbeeld data (optioneel)
SOURCE sample_data.sql;
```

### 2. Database Configuratie

Bewerk `config/database.php` en pas de gegevens aan:

```php
private $host = "localhost";
private $db_name = "toughbook_configurator";
private $username = "root";
private $password = "";
```

### 3. Bestanden Plaatsen

Upload alle bestanden naar je webserver. Bijvoorbeeld:
```
/var/www/html/toughbook-configurator/
```

### 4. Rechten Instellen

Zorg dat de web server schrijfrechten heeft voor:
- api/ directory
- config/ directory

```bash
chmod 755 api/
chmod 755 config/
```

## Gebruik

### Configurator (Klant Interface)

Open in browser: `http://jouwebsite.nl/toughbook-configurator/configurator.html`

**5-Stappen Proces:**

1. **Stap 1: Vragenlijst**
   - Klant beantwoordt vragen over gebruik
   - Elk antwoord kent punten toe aan modellen

2. **Stap 2: Aanbevelingen**
   - Toont modellen gesorteerd op score
   - Klant selecteert een model

3. **Stap 3: Hoofdopties**
   - Configureer opties die modelnummer beïnvloeden
   - Bijv: scherm, processor, RAM, opslag, GPS
   - Modelnummer wordt links bijgewerkt

4. **Stap 4: Extra Opties**
   - Configureer opties zonder invloed op modelnummer
   - Bijv: keyboard layout, garantie, accessoires

5. **Stap 5: Samenvatting**
   - Links: finaal modelnummer + configuratie overzicht
   - Rechts: totaalprijs + actieknoppen
   - Offerteaanvraag of Direct Bestellen

### CMS (Admin Interface)

Open in browser: `http://jouwebsite.nl/toughbook-configurator/cms.html`

**CMS Secties:**

1. **Toughbook Modellen**
   - Voeg modellen toe/bewerk/verwijder
   - Stel basis prijs en modelnummer in

2. **Vragenlijst**
   - Beheer vragen en antwoorden
   - Stel volgorde in

3. **Configuratie Categorieën**
   - Maak categorieën voor opties
   - Stel in of ze modelnummer beïnvloeden

4. **Configuratie Opties**
   - Voeg opties toe per categorie
   - Stel prijs wijzigingen en codes in

5. **Offerte Aanvragen**
   - Bekijk ontvangen offertes
   - Zie klantgegevens en configuratie

## Database Structuur

### Belangrijkste Tabellen:

**toughbook_models**
- Basis modellen met prijzen

**questionnaire_questions & questionnaire_answers**
- Vragenlijst met antwoorden

**model_scores**
- Punten per antwoord per model

**configuration_categories**
- Categorieën (stap 3 vs stap 4)

**configuration_options**
- Alle configuratie opties

**configuration_sessions**
- Opgeslagen configuraties

**quote_requests**
- Offerte aanvragen van klanten

## Functionaliteit Details

### Modelnummer Generatie

Het modelnummer wordt dynamisch gegenereerd:

```
Basis: CF-55-MK3
+ Scherm code: FHD-T
+ Processor code: I7
+ RAM code: 16GB
+ Opslag code: 512
+ GPS code: GPS

Resultaat: CF-55-MK3-FHD-T-I7-16GB-512-GPS
```

**Let op:** Alleen opties in categorieën met `affects_model_number = 1` beïnvloeden het modelnummer.

### Prijs Berekening

Totaalprijs = Basis prijs model + Som van alle price_modifiers van geselecteerde opties

### Session Management

Elke configuratie krijgt een unieke session_key. Deze wordt gebruikt voor:
- Direct bestellen link: `order.php?config={session_key}`
- Ophalen configuratie later
- Koppelen aan offerte aanvragen

### Email Notificaties

Bij offerte aanvraag wordt email verstuurd naar:
```php
$to = "verkoop@voorbeeld.nl"; // Pas aan in api/configurator_api.php
```

## API Endpoints

### Configurator API (`api/configurator_api.php`)

```
GET  ?action=get_questionnaire
POST ?action=calculate_recommendations
GET  ?action=get_configuration_options&model_id={id}
POST ?action=generate_model_number
POST ?action=calculate_total_price
POST ?action=save_configuration
GET  ?action=get_configuration&session_key={key}
POST ?action=submit_quote_request
```

### CMS API (`api/cms_api.php`)

```
# Modellen
GET  ?action=get_models
GET  ?action=get_model&id={id}
POST ?action=save_model
POST ?action=delete_model

# Vragen
GET  ?action=get_questions
GET  ?action=get_question&id={id}
POST ?action=save_question
POST ?action=delete_question

# Categorieën
GET  ?action=get_categories
GET  ?action=get_category&id={id}
POST ?action=save_category
POST ?action=delete_category

# Opties
GET  ?action=get_options&category_id={id}
GET  ?action=get_option&id={id}
POST ?action=save_option
POST ?action=delete_option

# Offertes
GET  ?action=get_quotes
GET  ?action=get_quote_details&id={id}
POST ?action=delete_quote
```

## Aanpassingen & Uitbreidingen

### Nieuwe Vraag Toevoegen

1. Ga naar CMS → Vragenlijst
2. Klik "+ Nieuwe Vraag"
3. Vul vraag tekst en volgorde in
4. Sla op
5. Voeg antwoorden toe (via apart scherm of database)
6. Stel punten in per antwoord per model in `model_scores` tabel

### Nieuw Model Toevoegen

1. Ga naar CMS → Toughbook Modellen
2. Klik "+ Nieuw Model"
3. Vul details in:
   - Model naam
   - Basis modelnummer (bijv: CF-40-MK2)
   - Beschrijving
   - Basis prijs
   - Afbeelding URL
4. Sla op
5. Configureer welke opties beschikbaar zijn via `model_available_options` tabel

### Nieuwe Configuratie Optie

1. Ga naar CMS → Configuratie Categorieën
2. Maak eerst een categorie (indien nodig)
3. Ga naar CMS → Configuratie Opties
4. Klik "+ Nieuwe Optie"
5. Selecteer categorie
6. Vul optie details in:
   - Naam
   - Code (voor modelnummer, als categorie dit beïnvloedt)
   - Prijs wijziging
   - Volgorde
7. Sla op

## Beveiliging

**Let op:** Dit is een basis implementatie. Voor productie:

1. Voeg authenticatie toe aan CMS
2. Implementeer CSRF bescherming
3. Valideer alle input server-side
4. Gebruik prepared statements (al gedaan)
5. Implementeer rate limiting
6. Gebruik HTTPS
7. Beveilig database credentials

## Direct Bestellen Link

Wanneer klant op "Direct Bestellen" klikt, wordt een link gegenereerd:

```
http://jouwebsite.nl/order.php?config={session_key}
```

Je moet nog `order.php` implementeren die:
1. Session key leest
2. Configuratie ophaalt via API
3. Toont in bestelformulier
4. Verwerkt bestelling

## Troubleshooting

### Database Connectie Errors
- Check database credentials in `config/database.php`
- Verifieer dat database bestaat
- Check MySQL/MariaDB service status

### API Errors
- Check browser console voor JavaScript errors
- Verifieer dat API bestanden correct geüpload zijn
- Check Apache/Nginx error logs

### Modelnummer Updates Niet
- Verifieer dat categorie `affects_model_number = 1` heeft
- Check dat optie een `option_code` heeft
- Verifieer JavaScript console voor errors

### Email Niet Verzonden
- Check PHP mail configuratie op server
- Overweeg gebruik van PHPMailer of SMTP
- Check spam folder

## Support & Ontwikkeling

Voor vragen of aanpassingen, contacteer je ontwikkelaar.

## Licentie

Proprietary - Alle rechten voorbehouden
