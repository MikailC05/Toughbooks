// configurator.js - Configurator Logic

// Globale state
const configuratorState = {
    currentStep: 1,
    totalSteps: 5,
    
    // Stap 1 data
    questions: [],
    selectedAnswers: {},
    
    // Stap 2 data
    recommendations: [],
    selectedModel: null,
    
    // Stap 3 & 4 data
    step3Options: [],
    step4Options: [],
    selectedOptions: [],
    
    // Stap 5 data
    finalModelNumber: '',
    totalPrice: 0,
    sessionKey: ''
};

// API Base URL
const API_URL = 'api/configurator_api.php';

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadQuestionnaire();
    updateUI();
});

// ===== STAP NAVIGATIE =====
function nextStep() {
    // Validatie per stap
    if (!validateCurrentStep()) {
        return;
    }
    
    // Data laden voor volgende stap
    switch(configuratorState.currentStep) {
        case 1:
            loadRecommendations();
            break;
        case 2:
            loadConfigurationOptions();
            break;
        case 3:
            // Stap 4 is al geladen
            break;
        case 4:
            generateSummary();
            break;
    }
    
    if (configuratorState.currentStep < configuratorState.totalSteps) {
        configuratorState.currentStep++;
        updateUI();
    }
}

function previousStep() {
    if (configuratorState.currentStep > 1) {
        configuratorState.currentStep--;
        updateUI();
    }
}

function updateUI() {
    // Update progress bar
    const progress = (configuratorState.currentStep / configuratorState.totalSteps) * 100;
    document.getElementById('progressFill').style.width = progress + '%';
    
    // Update step indicator
    const stepNames = ['Vragenlijst', 'Aanbevelingen', 'Hoofdopties', 'Extra Opties', 'Samenvatting'];
    document.getElementById('stepIndicator').textContent = 
        `Stap ${configuratorState.currentStep} van ${configuratorState.totalSteps}: ${stepNames[configuratorState.currentStep - 1]}`;
    
    // Show/hide step content
    document.querySelectorAll('.step-content').forEach((el, index) => {
        el.classList.toggle('active', index + 1 === configuratorState.currentStep);
    });
    
    // Update navigation buttons
    document.getElementById('prevBtn').disabled = configuratorState.currentStep === 1;
    
    const nextBtn = document.getElementById('nextBtn');
    if (configuratorState.currentStep === configuratorState.totalSteps) {
        nextBtn.style.display = 'none';
    } else {
        nextBtn.style.display = 'block';
        nextBtn.textContent = 'Volgende';
    }
}

function validateCurrentStep() {
    switch(configuratorState.currentStep) {
        case 1:
            // Check if all questions answered
            const answeredCount = Object.keys(configuratorState.selectedAnswers).length;
            if (answeredCount !== configuratorState.questions.length) {
                alert('Beantwoord alle vragen alstublieft.');
                return false;
            }
            return true;
            
        case 2:
            // Check if model selected
            if (!configuratorState.selectedModel) {
                alert('Selecteer een model alstublieft.');
                return false;
            }
            return true;
            
        case 3:
            // Check if all step3 options selected
            const step3Count = configuratorState.step3Options.length;
            const step3Selected = configuratorState.selectedOptions.filter(optId => {
                return configuratorState.step3Options.some(cat => 
                    cat.options.some(opt => opt.id == optId)
                );
            }).length;
            
            if (step3Selected !== step3Count) {
                alert('Selecteer een optie voor elke categorie.');
                return false;
            }
            return true;
            
        case 4:
            // Check if all step4 options selected
            const step4Count = configuratorState.step4Options.length;
            const step4Selected = configuratorState.selectedOptions.filter(optId => {
                return configuratorState.step4Options.some(cat => 
                    cat.options.some(opt => opt.id == optId)
                );
            }).length;
            
            if (step4Selected !== step4Count) {
                alert('Selecteer een optie voor elke categorie.');
                return false;
            }
            return true;
            
        default:
            return true;
    }
}

// ===== STAP 1: VRAGENLIJST =====
async function loadQuestionnaire() {
    try {
        const response = await fetch(`${API_URL}?action=get_questionnaire`);
        const data = await response.json();
        
        if (data.success) {
            configuratorState.questions = data.questions;
            renderQuestionnaire();
        }
    } catch (error) {
        console.error('Error loading questionnaire:', error);
        alert('Fout bij laden van vragenlijst');
    }
}

function renderQuestionnaire() {
    const container = document.getElementById('questionnaireContainer');
    container.innerHTML = '';
    
    configuratorState.questions.forEach((question, qIndex) => {
        const questionBlock = document.createElement('div');
        questionBlock.className = 'question-block';
        
        questionBlock.innerHTML = `
            <div class="question-title">Vraag ${qIndex + 1}: ${question.question_text}</div>
            <div class="answers-container">
                ${question.answers.map(answer => `
                    <div class="answer-option" 
                         data-question-id="${question.id}" 
                         data-answer-id="${answer.id}"
                         onclick="selectAnswer(${question.id}, ${answer.id})">
                        ${answer.text}
                    </div>
                `).join('')}
            </div>
        `;
        
        container.appendChild(questionBlock);
    });
}

function selectAnswer(questionId, answerId) {
    configuratorState.selectedAnswers[questionId] = answerId;
    
    // Update UI
    document.querySelectorAll(`[data-question-id="${questionId}"]`).forEach(el => {
        el.classList.remove('selected');
    });
    
    document.querySelector(`[data-question-id="${questionId}"][data-answer-id="${answerId}"]`)
        .classList.add('selected');
}

// ===== STAP 2: AANBEVELINGEN =====
async function loadRecommendations() {
    try {
        const answers = Object.values(configuratorState.selectedAnswers);
        
        const response = await fetch(`${API_URL}?action=calculate_recommendations`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ answers })
        });
        
        const data = await response.json();
        
        if (data.success) {
            configuratorState.recommendations = data.recommendations;
            renderRecommendations();
        }
    } catch (error) {
        console.error('Error loading recommendations:', error);
        alert('Fout bij laden van aanbevelingen');
    }
}

function renderRecommendations() {
    const container = document.getElementById('recommendationsGrid');
    container.innerHTML = '';
    
    configuratorState.recommendations.forEach(model => {
        const card = document.createElement('div');
        card.className = 'recommendation-card';
        card.dataset.modelId = model.id;
        card.onclick = () => selectModel(model.id);
        
        card.innerHTML = `
            <img src="${model.image_url}" alt="${model.model_name}">
            <div class="model-name">${model.model_name}</div>
            <div class="model-points">Score: ${model.total_points} punten</div>
            <div class="model-price">Vanaf € ${parseFloat(model.base_price).toFixed(2)}</div>
        `;
        
        container.appendChild(card);
    });
}

function selectModel(modelId) {
    configuratorState.selectedModel = modelId;
    
    // Update UI
    document.querySelectorAll('.recommendation-card').forEach(el => {
        el.classList.remove('selected');
    });
    
    document.querySelector(`[data-model-id="${modelId}"]`).classList.add('selected');
}

// ===== STAP 3 & 4: CONFIGURATIE OPTIES =====
async function loadConfigurationOptions() {
    try {
        const response = await fetch(
            `${API_URL}?action=get_configuration_options&model_id=${configuratorState.selectedModel}`
        );
        const data = await response.json();
        
        if (data.success) {
            configuratorState.step3Options = data.step3_options;
            configuratorState.step4Options = data.step4_options;
            
            renderConfigurationOptions('step3', data.step3_options);
            renderConfigurationOptions('step4', data.step4_options);
            
            // Pre-select default options
            preselectDefaults();
        }
    } catch (error) {
        console.error('Error loading configuration options:', error);
        alert('Fout bij laden van configuratie opties');
    }
}

function renderConfigurationOptions(step, categories) {
    const container = document.getElementById(`${step}ConfigContainer`);
    container.innerHTML = '';
    
    categories.forEach(category => {
        const categoryDiv = document.createElement('div');
        categoryDiv.className = 'config-category';
        
        categoryDiv.innerHTML = `
            <div class="category-title">${category.category_name}</div>
            <div class="options-container">
                ${category.options.map(option => `
                    <div class="config-option" 
                         data-category-id="${category.category_id}"
                         data-option-id="${option.id}"
                         onclick="selectConfigOption(${category.category_id}, ${option.id})">
                        <span>${option.name}</span>
                        <span class="option-price">
                            ${option.price > 0 ? '+ € ' + parseFloat(option.price).toFixed(2) : 
                              option.price < 0 ? '- € ' + Math.abs(parseFloat(option.price)).toFixed(2) : 
                              'Standaard'}
                        </span>
                    </div>
                `).join('')}
            </div>
        `;
        
        container.appendChild(categoryDiv);
    });
}

function preselectDefaults() {
    const allCategories = [...configuratorState.step3Options, ...configuratorState.step4Options];
    
    allCategories.forEach(category => {
        const defaultOption = category.options.find(opt => opt.is_default == 1);
        if (defaultOption) {
            selectConfigOption(category.category_id, defaultOption.id, false);
        }
    });
}

function selectConfigOption(categoryId, optionId, updateModelNumber = true) {
    // Remove previous selection from this category
    const allCategories = [...configuratorState.step3Options, ...configuratorState.step4Options];
    const category = allCategories.find(cat => cat.category_id == categoryId);
    
    if (category) {
        category.options.forEach(opt => {
            const index = configuratorState.selectedOptions.indexOf(opt.id);
            if (index > -1) {
                configuratorState.selectedOptions.splice(index, 1);
            }
        });
    }
    
    // Add new selection
    configuratorState.selectedOptions.push(optionId);
    
    // Update UI
    document.querySelectorAll(`[data-category-id="${categoryId}"]`).forEach(el => {
        el.classList.remove('selected');
    });
    
    document.querySelector(`[data-category-id="${categoryId}"][data-option-id="${optionId}"]`)
        .classList.add('selected');
    
    // Update model number if in step 3
    if (updateModelNumber && configuratorState.currentStep >= 3) {
        updateModelNumberPreview();
    }
}

async function updateModelNumberPreview() {
    try {
        const response = await fetch(`${API_URL}?action=generate_model_number`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                model_id: configuratorState.selectedModel,
                selected_options: configuratorState.selectedOptions
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            configuratorState.finalModelNumber = data.model_number;
        }
    } catch (error) {
        console.error('Error generating model number:', error);
    }
}

// ===== STAP 5: SAMENVATTING =====
async function generateSummary() {
    // Calculate total price
    await calculateTotalPrice();
    
    // Update model number
    await updateModelNumberPreview();
    
    // Save configuration
    await saveConfiguration();
    
    // Render summary
    renderSummary();
}

async function calculateTotalPrice() {
    try {
        const response = await fetch(`${API_URL}?action=calculate_total_price`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                model_id: configuratorState.selectedModel,
                selected_options: configuratorState.selectedOptions
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            configuratorState.totalPrice = data.total_price;
        }
    } catch (error) {
        console.error('Error calculating price:', error);
    }
}

async function saveConfiguration() {
    try {
        const response = await fetch(`${API_URL}?action=save_configuration`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                model_id: configuratorState.selectedModel,
                questionnaire_data: configuratorState.selectedAnswers,
                configuration_data: configuratorState.selectedOptions,
                model_number: configuratorState.finalModelNumber,
                total_price: configuratorState.totalPrice
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            configuratorState.sessionKey = data.session_key;
        }
    } catch (error) {
        console.error('Error saving configuration:', error);
    }
}

function renderSummary() {
    // Model number
    document.getElementById('finalModelNumber').textContent = configuratorState.finalModelNumber;
    
    // Total price
    document.getElementById('finalTotalPrice').textContent = '€ ' + configuratorState.totalPrice;
    
    // Selected model
    const selectedModelData = configuratorState.recommendations.find(
        m => m.id == configuratorState.selectedModel
    );
    
    if (selectedModelData) {
        document.getElementById('selectedModelSummary').innerHTML = `
            <div class="summary-item"><strong>${selectedModelData.model_name}</strong></div>
            <div class="summary-item">${selectedModelData.description}</div>
        `;
    }
    
    // Configuration options (step 3)
    const step3Summary = configuratorState.step3Options.map(category => {
        const selectedOption = category.options.find(opt => 
            configuratorState.selectedOptions.includes(opt.id)
        );
        return `<div class="summary-item"><strong>${category.category_name}:</strong> ${selectedOption ? selectedOption.name : 'Niet geselecteerd'}</div>`;
    }).join('');
    
    document.getElementById('configurationSummary').innerHTML = step3Summary;
    
    // Extra options (step 4)
    const step4Summary = configuratorState.step4Options.map(category => {
        const selectedOption = category.options.find(opt => 
            configuratorState.selectedOptions.includes(opt.id)
        );
        return `<div class="summary-item"><strong>${category.category_name}:</strong> ${selectedOption ? selectedOption.name : 'Niet geselecteerd'}</div>`;
    }).join('');
    
    document.getElementById('extraOptionsSummary').innerHTML = step4Summary;
}

// ===== OFFERTE AANVRAGEN =====
function showQuoteForm() {
    document.getElementById('quoteForm').classList.add('active');
}

function hideQuoteForm() {
    document.getElementById('quoteForm').classList.remove('active');
}

document.getElementById('quoteRequestForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = {
        session_key: configuratorState.sessionKey,
        company_name: formData.get('company_name'),
        contact_name: formData.get('contact_name'),
        email: formData.get('email'),
        phone: formData.get('phone'),
        remarks: formData.get('remarks')
    };
    
    try {
        const response = await fetch(`${API_URL}?action=submit_quote_request`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Offerte aanvraag verzonden! We nemen zo spoedig mogelijk contact met u op.');
            hideQuoteForm();
        } else {
            alert('Fout bij versturen: ' + result.error);
        }
    } catch (error) {
        console.error('Error submitting quote:', error);
        alert('Fout bij versturen van offerte aanvraag');
    }
});

// ===== DIRECT BESTELLEN =====
function directOrder() {
    // Genereer link met session key
    const orderUrl = `${window.location.origin}/order.php?config=${configuratorState.sessionKey}`;
    
    // In productie zou je hier doorsturen naar een order pagina
    alert('Direct bestellen link:\n' + orderUrl);
    
    // Optioneel: open in nieuw tabblad
    // window.open(orderUrl, '_blank');
}
