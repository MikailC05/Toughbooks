# Modelnummer Systeem - Documentatie

## Overzicht

Het modelnummer systeem stelt klanten in staat om een Toughbook te configureren door drie opties te selecteren:
1. **Toetsenbordindeling** (bijv. Qwerty NL, Azerty BE)
2. **Draadloze verbindingen** (bijv. WLAN, WLAN + WWAN + 4G + GPS)
3. **Scherm type** (bijv. HD scherm, Full HD + Touchscreen)

Op basis van deze combinatie wordt automatisch het juiste **modelnummer** getoond (bijv. FZ-55JZ011B4).

## Installatie

### Stap 1: Database migratie uitvoeren

Voer het volgende commando uit in je terminal:

```bash
cd c:\xampp\htdocs\Toughbooks
php run_migration.php
```

Dit maakt de volgende tabellen aan:
- `model_number_rules` - Mapping tussen opties en modelnummers
- `configuration_options` - Beschikbare opties voor elk type (keyboard, wireless, screen)

### Stap 2: Extra modelnummer regels toevoegen (optioneel)

Als je extra voorbeelddata wilt toevoegen (meer Azerty BE combinaties):

```bash
php add_model_rules.php
```

Dit voegt extra modelnummer regels toe voor Azerty (BE) toetsenborden.

### Stap 2: Test de configurator

Ga naar: `http://localhost/Toughbooks/model_configurator.php`

## Admin Panel Beheer

### Toegang tot modelnummer beheer

1. Login op het admin panel: `http://localhost/Toughbooks/admin_login.php`
2. Klik op de tab **"🔢 Modelnummers"**

### Modelnummer regels toevoegen

1. Ga naar het **Admin Panel** ([admin_login.php](http://localhost/Toughbooks/admin_login.php))
2. Login met je admin account
3. Klik op de tab **"🔢 Modelnummers"**
4. Klik op **"➕ Nieuwe Modelnummer Regel"**
5. Selecteer de drie opties:
   - **Toetsenbord** (bijv. Azerty (BE))
   - **Draadloze Verbinding** (bijv. WLAN)
   - **Scherm** (bijv. HD scherm)
6. Voer het **modelnummer** in (bijv. FZ-55G6601Z4)
7. Optioneel: voer een prijs en beschrijving in
8. Klik op **"💾 Regel Toevoegen"**

**Tip:** Voor elk nieuw toetsenbord type moet je meerdere regels aanmaken voor alle combinaties:
- Azerty (BE) + WLAN + HD scherm = FZ-55G6601Z4
- Azerty (BE) + WLAN + Full HD = FZ-55J2601Z4
- Azerty (BE) + WLAN/WWAN/4G/GPS + HD scherm = FZ-55GZ010Z4
- Azerty (BE) + WLAN/WWAN/4G/GPS + Full HD = FZ-55JZ00ZB4

### Configuratie opties toevoegen

Als je een nieuw toetsenbord, draadloze verbinding of scherm type wilt toevoegen:

1. Klik op **"⚙️ Optie Toevoegen"**
2. Selecteer het type (Toetsenbord, Draadloze Verbinding, of Scherm)
3. Voer de waarde in (bijv. "Qwerty (UK)")
4. Klik op **"💾 Optie Toevoegen"**

Deze nieuwe optie verschijnt dan in de dropdowns op de configurator pagina.

### Modelnummer regels bewerken

1. Ga naar het **Admin Panel** → tab **"🔢 Modelnummers"**
2. Zoek de regel die je wilt aanpassen in de tabel
3. Klik op de **"✏️ Bewerken"** knop naast de regel
4. Pas de gewenste velden aan:
   - Toetsenbord type
   - Draadloze verbinding type
   - Scherm type
   - Modelnummer
   - Prijs (optioneel)
   - Beschrijving (optioneel)
   - Actief (aan/uit)
5. Klik op **"💾 Opslaan"**

### Modelnummer regels verwijderen

1. Ga naar het **Admin Panel** → tab **"🔢 Modelnummers"**
2. Klik op de **"🗑️ Verwijderen"** knop naast de regel die je wilt verwijderen
3. Bevestig de verwijdering

## Voorbeelddata

Het systeem bevat al de volgende voorbeeldregels:

| Toetsenbord | Draadloos | Scherm | Modelnummer |
|------------|-----------|--------|-------------|
| Qwerty (NL) | WLAN | HD scherm | FZ-55G6601B4 |
| Qwerty (NL) | WLAN + WWAN + 4G + GPS | HD scherm | FZ-55GZ010B4 |
| Qwerty (NL) | WLAN + WWAN + 4G + GPS | Full HD + Touchscreen | FZ-55JZ011B4 |
| Qwerty (NL) | WLAN | Full HD + Touchscreen | FZ-55J2601B4 |
| Azerty (BE) | WLAN + WWAN + 4G + GPS | Full HD + Touchscreen | FZ-55JZ00ZB4 |

## API Endpoints

### GET /api/model_number_api.php?action=get_options

Haal alle beschikbare configuratie opties op.

**Response:**
```json
{
  "success": true,
  "options": {
    "keyboard": [...],
    "wireless": [...],
    "screen": [...]
  }
}
```

### GET /api/model_number_api.php?action=get_model_number&keyboard=...&wireless=...&screen=...

Haal het modelnummer op voor een specifieke combinatie.

**Response:**
```json
{
  "success": true,
  "model_number": "FZ-55JZ011B4",
  "price_eur": "0.00",
  "description": ""
}
```

## Hoe het werkt

1. **Klant bezoekt de configurator** ([model_configurator.php](model_configurator.php))
2. **Drie dropdowns worden geladen** met beschikbare opties uit de database
3. **Bij elke selectie** wordt de selectie geupdate
4. **Als alle drie opties zijn geselecteerd** wordt automatisch via AJAX het modelnummer opgehaald
5. **Het modelnummer wordt direct getoond** aan de klant, inclusief optionele prijs en beschrijving

## Troubleshooting

### "Geen modelnummer gevonden voor deze combinatie"

Dit betekent dat er geen regel bestaat in de database voor de geselecteerde combinatie. Ga naar het admin panel en voeg een nieuwe regel toe voor deze combinatie.

### Opties verschijnen niet in de dropdown

Controleer of de opties actief zijn (`is_active = 1`) in de `configuration_options` tabel.

### Database fouten

Als je database fouten krijgt, voer dan de migratie opnieuw uit:
```bash
php run_migration.php
```

## Uitbreidingen voor de toekomst

- ✅ Basis modelnummer systeem
- ⚠️ PDF genereren met de configuratie
- ⚠️ Offerte aanvragen functionaliteit
- ⚠️ E-mail notificaties naar admin bij nieuwe configuraties
- ⚠️ Export naar CSV voor bestellingen

## Support

Voor vragen of problemen, neem contact op met de systeembeheerder.
