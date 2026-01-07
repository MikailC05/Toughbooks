#!/bin/bash

# Toughbook Configurator - Installatie Script
# Dit script helpt bij het opzetten van de configurator

echo "=================================="
echo "Toughbook Configurator Installer"
echo "=================================="
echo ""

# Check of we in de juiste directory zijn
if [ ! -f "database_schema.sql" ]; then
    echo "❌ Error: database_schema.sql niet gevonden!"
    echo "   Voer dit script uit vanuit de hoofdmap van het project."
    exit 1
fi

echo "✅ Bestanden gevonden"
echo ""

# Vraag database gegevens
echo "Database Configuratie"
echo "---------------------"
read -p "Database host [localhost]: " DB_HOST
DB_HOST=${DB_HOST:-localhost}

read -p "Database naam [toughbook_configurator]: " DB_NAME
DB_NAME=${DB_NAME:-toughbook_configurator}

read -p "Database gebruiker [root]: " DB_USER
DB_USER=${DB_USER:-root}

read -sp "Database wachtwoord: " DB_PASS
echo ""
echo ""

# Update database.php
echo "📝 Database configuratie bijwerken..."
cat > config/database.php << EOF
<?php
// config/database.php

class Database {
    private \$host = "$DB_HOST";
    private \$db_name = "$DB_NAME";
    private \$username = "$DB_USER";
    private \$password = "$DB_PASS";
    private \$conn;

    public function getConnection() {
        \$this->conn = null;

        try {
            \$this->conn = new PDO(
                "mysql:host=" . \$this->host . ";dbname=" . \$this->db_name,
                \$this->username,
                \$this->password
            );
            \$this->conn->exec("set names utf8");
            \$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException \$exception) {
            echo "Connection error: " . \$exception->getMessage();
        }

        return \$this->conn;
    }
}
?>
EOF

echo "✅ Database configuratie bijgewerkt"
echo ""

# Vraag of database moet worden aangemaakt
read -p "Wil je de database en tabellen nu aanmaken? (j/n): " CREATE_DB

if [ "$CREATE_DB" = "j" ] || [ "$CREATE_DB" = "J" ]; then
    echo ""
    echo "🗄️  Database aanmaken..."
    
    # Check of mysql command beschikbaar is
    if ! command -v mysql &> /dev/null; then
        echo "❌ mysql command niet gevonden!"
        echo "   Importeer handmatig: mysql -u $DB_USER -p < database_schema.sql"
    else
        # Database aanmaken
        mysql -u "$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME;"
        
        if [ $? -eq 0 ]; then
            echo "✅ Database '$DB_NAME' aangemaakt"
            
            # Schema importeren
            echo "📥 Database schema importeren..."
            mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < database_schema.sql
            
            if [ $? -eq 0 ]; then
                echo "✅ Database schema geïmporteerd"
                
                # Vraag of sample data moet worden geïmporteerd
                read -p "Wil je de voorbeeld data importeren? (j/n): " IMPORT_SAMPLE
                
                if [ "$IMPORT_SAMPLE" = "j" ] || [ "$IMPORT_SAMPLE" = "J" ]; then
                    echo "📥 Voorbeeld data importeren..."
                    mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < sample_data.sql
                    
                    if [ $? -eq 0 ]; then
                        echo "✅ Voorbeeld data geïmporteerd"
                    else
                        echo "❌ Fout bij importeren voorbeeld data"
                    fi
                fi
            else
                echo "❌ Fout bij importeren database schema"
            fi
        else
            echo "❌ Fout bij aanmaken database"
        fi
    fi
fi

echo ""
echo "📁 Images directory aanmaken..."
mkdir -p images
echo "✅ Images directory aangemaakt"

echo ""
echo "🔒 Bestandsrechten instellen..."
chmod 755 api/
chmod 755 config/
chmod 755 images/
chmod 644 *.html
chmod 644 *.js
echo "✅ Bestandsrechten ingesteld"

echo ""
echo "=================================="
echo "✅ Installatie Voltooid!"
echo "=================================="
echo ""
echo "Volgende stappen:"
echo ""
echo "1. Upload product afbeeldingen naar de images/ directory"
echo "   - g2.jpg (Toughbook G2)"
echo "   - 55.jpg (Toughbook 55)"
echo "   - 40.jpg (Toughbook 40)"
echo "   - 33.jpg (Toughbook 33)"
echo ""
echo "2. Open de configurator:"
echo "   http://jouwdomein.nl/toughbook-configurator/configurator.html"
echo ""
echo "3. Open het CMS:"
echo "   http://jouwdomein.nl/toughbook-configurator/cms.html"
echo ""
echo "4. Test alle functionaliteit"
echo ""
echo "⚠️  BELANGRIJK:"
echo "   - Beveilig het CMS met authenticatie voor productie!"
echo "   - Pas email settings aan in api/configurator_api.php"
echo "   - Gebruik HTTPS in productie"
echo ""
echo "Voor meer informatie: zie README.md"
echo ""
