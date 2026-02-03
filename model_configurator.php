<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Toughbook Configurator</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .config-container {
            max-width: 900px;
            margin: 40px auto;
        }

        .config-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }

        .config-card {
            background: white;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .config-card h3 {
            margin: 0 0 16px 0;
            font-size: 1.2em;
            color: #333;
        }

        .config-card select {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            border: 2px solid #ddd;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            transition: border-color 0.3s;
        }

        .config-card select:hover {
            border-color: #666;
        }

        .config-card select:focus {
            outline: none;
            border-color: #2196F3;
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.1);
        }

        .result-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 32px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .result-card.loading {
            background: #f5f5f5;
            color: #666;
        }

        .result-card h2 {
            margin: 0 0 16px 0;
            font-size: 2.5em;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .result-card .price {
            font-size: 1.8em;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .result-card .description {
            font-size: 0.95em;
            opacity: 0.9;
            max-width: 500px;
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
        }

        .loading-spinner {
            border: 4px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top: 4px solid white;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .selection-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        .selection-summary h4 {
            margin: 0 0 12px 0;
            color: #666;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .selection-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .selection-item:last-child {
            border-bottom: none;
        }

        .selection-item .label {
            font-weight: 600;
            color: #333;
        }

        .selection-item .value {
            color: #666;
        }
    </style>
</head>
<body>
<header class="site-header">
    <div class="brand">
        <div class="logo">TB</div>
        <div>
            <h1>Toughbook Configurator</h1>
            <div class="muted">Configureer jouw ideale Toughbook</div>
        </div>
    </div>
</header>

<main class="container">
    <div class="config-container">
        <section class="hero">
            <h2>Selecteer jouw configuratie</h2>
            <p>Kies de gewenste opties en ontdek direct het bijbehorende modelnummer</p>
        </section>

        <div id="errorMessage" class="error-message" style="display:none;"></div>

        <div class="config-grid">
            <div class="config-card">
                <h3>1. Toetsenbordindeling</h3>
                <select id="keyboardSelect">
                    <option value="">-- Selecteer toetsenbord --</option>
                </select>
            </div>

            <div class="config-card">
                <h3>2. Draadloze verbindingen</h3>
                <select id="wirelessSelect">
                    <option value="">-- Selecteer verbinding --</option>
                </select>
            </div>

            <div class="config-card">
                <h3>3. Scherm type</h3>
                <select id="screenSelect">
                    <option value="">-- Selecteer scherm --</option>
                </select>
            </div>
        </div>

        <div id="selectionSummary" class="selection-summary" style="display:none;">
            <h4>Jouw selectie:</h4>
            <div class="selection-item">
                <span class="label">Toetsenbord:</span>
                <span class="value" id="summaryKeyboard">-</span>
            </div>
            <div class="selection-item">
                <span class="label">Draadloos:</span>
                <span class="value" id="summaryWireless">-</span>
            </div>
            <div class="selection-item">
                <span class="label">Scherm:</span>
                <span class="value" id="summaryScreen">-</span>
            </div>
        </div>

        <div id="resultCard" class="result-card loading">
            <div id="loadingState">
                <p style="font-size:1.2em; margin:0;">Selecteer alle opties om het modelnummer te zien</p>
            </div>
            <div id="resultContent" style="display:none;">
                <div style="font-size:0.9em; opacity:0.9; margin-bottom:8px;">Modelnummer:</div>
                <h2 id="modelNumber">-</h2>
                <div id="priceDisplay" class="price" style="display:none;"></div>
                <div id="descriptionDisplay" class="description" style="display:none;"></div>
            </div>
        </div>
    </div>
</main>

<script>
// State
let configOptions = {
    keyboard: [],
    wireless: [],
    screen: []
};

let selectedOptions = {
    keyboard: '',
    wireless: '',
    screen: ''
};

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadConfigOptions();

    // Event listeners
    document.getElementById('keyboardSelect').addEventListener('change', (e) => {
        selectedOptions.keyboard = e.target.value;
        updateSelection();
    });

    document.getElementById('wirelessSelect').addEventListener('change', (e) => {
        selectedOptions.wireless = e.target.value;
        updateSelection();
    });

    document.getElementById('screenSelect').addEventListener('change', (e) => {
        selectedOptions.screen = e.target.value;
        updateSelection();
    });
});

// Load configuration options
async function loadConfigOptions() {
    try {
        const response = await fetch('api/model_number_api.php?action=get_options');
        const data = await response.json();

        if (data.success) {
            configOptions = data.options;
            populateDropdowns();
        } else {
            showError('Fout bij laden van opties: ' + data.error);
        }
    } catch (error) {
        showError('Fout bij laden van opties: ' + error.message);
    }
}

// Populate dropdowns
function populateDropdowns() {
    const keyboardSelect = document.getElementById('keyboardSelect');
    const wirelessSelect = document.getElementById('wirelessSelect');
    const screenSelect = document.getElementById('screenSelect');

    // Keyboard
    configOptions.keyboard.forEach(opt => {
        const option = document.createElement('option');
        option.value = opt.option_value;
        option.textContent = opt.option_value;
        keyboardSelect.appendChild(option);
    });

    // Wireless
    configOptions.wireless.forEach(opt => {
        const option = document.createElement('option');
        option.value = opt.option_value;
        option.textContent = opt.option_value;
        wirelessSelect.appendChild(option);
    });

    // Screen
    configOptions.screen.forEach(opt => {
        const option = document.createElement('option');
        option.value = opt.option_value;
        option.textContent = opt.option_value;
        screenSelect.appendChild(option);
    });
}

// Update selection and fetch model number
function updateSelection() {
    // Update summary
    document.getElementById('summaryKeyboard').textContent = selectedOptions.keyboard || '-';
    document.getElementById('summaryWireless').textContent = selectedOptions.wireless || '-';
    document.getElementById('summaryScreen').textContent = selectedOptions.screen || '-';

    // Show summary if any option is selected
    const hasAnySelection = selectedOptions.keyboard || selectedOptions.wireless || selectedOptions.screen;
    document.getElementById('selectionSummary').style.display = hasAnySelection ? 'block' : 'none';

    // Check if all options are selected
    if (selectedOptions.keyboard && selectedOptions.wireless && selectedOptions.screen) {
        fetchModelNumber();
    } else {
        resetResult();
    }
}

// Fetch model number from API
async function fetchModelNumber() {
    const resultCard = document.getElementById('resultCard');
    const loadingState = document.getElementById('loadingState');
    const resultContent = document.getElementById('resultContent');

    // Show loading
    resultCard.className = 'result-card loading';
    loadingState.innerHTML = '<div class="loading-spinner"></div>';
    loadingState.style.display = 'block';
    resultContent.style.display = 'none';
    hideError();

    try {
        const params = new URLSearchParams({
            action: 'get_model_number',
            keyboard: selectedOptions.keyboard,
            wireless: selectedOptions.wireless,
            screen: selectedOptions.screen
        });

        const response = await fetch(`api/model_number_api.php?${params}`);
        const data = await response.json();

        if (data.success) {
            // Show result
            document.getElementById('modelNumber').textContent = data.model_number;

            // Show price if available
            const priceDisplay = document.getElementById('priceDisplay');
            if (data.price_eur && parseFloat(data.price_eur) > 0) {
                priceDisplay.textContent = '€ ' + parseFloat(data.price_eur).toFixed(2).replace('.', ',');
                priceDisplay.style.display = 'block';
            } else {
                priceDisplay.style.display = 'none';
            }

            // Show description if available
            const descriptionDisplay = document.getElementById('descriptionDisplay');
            if (data.description) {
                descriptionDisplay.textContent = data.description;
                descriptionDisplay.style.display = 'block';
            } else {
                descriptionDisplay.style.display = 'none';
            }

            resultCard.className = 'result-card';
            loadingState.style.display = 'none';
            resultContent.style.display = 'block';
        } else {
            showError(data.error);
            resetResult();
        }
    } catch (error) {
        showError('Fout bij ophalen van modelnummer: ' + error.message);
        resetResult();
    }
}

// Reset result display
function resetResult() {
    const resultCard = document.getElementById('resultCard');
    const loadingState = document.getElementById('loadingState');
    const resultContent = document.getElementById('resultContent');

    resultCard.className = 'result-card loading';
    loadingState.innerHTML = '<p style="font-size:1.2em; margin:0;">Selecteer alle opties om het modelnummer te zien</p>';
    loadingState.style.display = 'block';
    resultContent.style.display = 'none';
}

// Show error message
function showError(message) {
    const errorDiv = document.getElementById('errorMessage');
    errorDiv.textContent = message;
    errorDiv.style.display = 'block';
}

// Hide error message
function hideError() {
    document.getElementById('errorMessage').style.display = 'none';
}
</script>
</body>
</html>
