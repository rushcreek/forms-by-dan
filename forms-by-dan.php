<?php
/**
 * Plugin Name: Forms by Dan
 * Description: Embed a multi-step ACEN claim form into pages and posts using the [forms_by_dan] shortcode.
 * Version: 1.0
 * Author: Dan Wegner
 */

// Register the shortcode
add_shortcode('forms_by_dan', 'render_forms_by_dan_form');
add_action('admin_menu', 'forms_by_dan_menu');
add_action('admin_init', 'forms_by_dan_settings');

add_action('init', function() {
    register_post_type('forms_by_dan_form', [
        'labels' => [
            'name' => 'Forms by Dan',
            'singular_name' => 'Form by Dan'
        ],
        'show_in_rest' => false,
        'supports' => ['title'],
        'public' => true,
        'has_archive' => false,
        'menu_position' => 20,
        'menu_icon' => 'dashicons-feedback',
    ]);
});

// CORS: Allow requests from atticuswebhookapi.azure-api.net
add_action('init', function() {
    if (isset($_SERVER['HTTP_ORIGIN']) && strpos($_SERVER['HTTP_ORIGIN'], 'atticuswebhookapi.azure-api.net') !== false) {
        header('Access-Control-Allow-Origin: https://atticuswebhookapi.azure-api.net');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
});

// Add meta box for form configuration
add_action('add_meta_boxes', function () {
    add_meta_box('forms_by_dan_meta', 'Form Configuration', 'render_forms_by_dan_meta_box', 'forms_by_dan_form', 'normal', 'high');
});

// Render the custom meta box
// Add API Key field to meta box
function render_forms_by_dan_meta_box($post) {
    $form_json = get_post_meta($post->ID, '_forms_by_dan_form_json', true);
    $webhook_url = get_post_meta($post->ID, '_forms_by_dan_webhook_url', true);
    $api_key = get_post_meta($post->ID, '_forms_by_dan_api_key', true);
    $redirect_url = get_post_meta($post->ID, '_forms_by_dan_redirect_url', true);
    wp_nonce_field('save_forms_by_dan_meta', 'forms_by_dan_nonce');
    echo '<p><label for="forms_by_dan_webhook_url">Webhook URL:</label><br>';
    echo '<input type="text" id="forms_by_dan_webhook_url" name="forms_by_dan_webhook_url" value="' . esc_attr($webhook_url) . '" style="width:100%;"></p>';
    echo '<p><label for="forms_by_dan_api_key">API Key (ocp-apim-subscription-key):</label><br>';
    echo '<input type="text" id="forms_by_dan_api_key" name="forms_by_dan_api_key" value="' . esc_attr($api_key) . '" style="width:100%;"></p>';
    echo '<p><label for="forms_by_dan_redirect_url">Redirect URL:</label><br>';
    echo '<input type="text" id="forms_by_dan_redirect_url" name="forms_by_dan_redirect_url" value="' . esc_attr($redirect_url) . '" style="width:100%;"></p>';
    echo '<p><label for="forms_by_dan_form_json">Form JSON:</label><br>';
    echo '<textarea id="forms_by_dan_form_json" name="forms_by_dan_form_json" rows="15" style="width:100%;">' . esc_textarea($form_json) . '</textarea></p>';

    // Shortcode display section
    echo '<p><label for="forms_by_dan_shortcode">Shortcode:</label><br>';
    echo '<input type="text" id="forms_by_dan_shortcode" value="[forms_by_dan id=' . esc_attr($post->ID) . ']" readonly style="width:100%;">';
    echo '<button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById(\'forms_by_dan_shortcode\').value)">Copy to Clipboard</button></p>';
}

// Save custom fields
add_action('save_post', function ($post_id) {
    if (!isset($_POST['forms_by_dan_nonce']) || !wp_verify_nonce($_POST['forms_by_dan_nonce'], 'save_forms_by_dan_meta')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (isset($_POST['forms_by_dan_webhook_url'])) {
        update_post_meta($post_id, '_forms_by_dan_webhook_url', sanitize_text_field($_POST['forms_by_dan_webhook_url']));
    }
    if (isset($_POST['forms_by_dan_api_key'])) {
        update_post_meta($post_id, '_forms_by_dan_api_key', sanitize_text_field($_POST['forms_by_dan_api_key']));
    }
    if (isset($_POST['forms_by_dan_redirect_url'])) {
        update_post_meta($post_id, '_forms_by_dan_redirect_url', esc_url_raw($_POST['forms_by_dan_redirect_url']));
    }
    if (isset($_POST['forms_by_dan_form_json'])) {
        update_post_meta($post_id, '_forms_by_dan_form_json', $_POST['forms_by_dan_form_json']);
    }
});

function forms_by_dan_menu() {
    add_options_page('Forms by Dan Settings', 'Forms by Dan', 'manage_options', 'forms-by-dan-settings', 'forms_by_dan_settings_page');
}

function forms_by_dan_settings() {
    // No longer register settings here as options are stored per custom post
}

function forms_by_dan_settings_page() {
    ?>
    <div class="wrap">
        <h1>Forms by Dan Form Settings</h1>
        <p>To create or manage forms, go to the <strong>Forms by Dan</strong> section in the WordPress admin menu.</p>
    </div>
    <?php
}

function render_forms_by_dan_form($atts) {
    $atts = shortcode_atts(['id' => ''], $atts);
    $post = get_post($atts['id']);
    if (!$post || $post->post_type !== 'forms_by_dan_form') return '';

    $form_json = get_post_meta($post->ID, '_forms_by_dan_form_json', true);
    $webhook_url = esc_url(get_post_meta($post->ID, '_forms_by_dan_webhook_url', true));
    $api_key = esc_attr(get_post_meta($post->ID, '_forms_by_dan_api_key', true));
    $redirect_url = esc_url(get_post_meta($post->ID, '_forms_by_dan_redirect_url', true));
    ob_start();
    ?>
    <div id="formsByDanRoot"></div>
<script type="application/json" id="forms-by-dan-definition"><?php echo $form_json; ?></script>
<script type="text/plain" id="forms-by-dan-webhook-url"><?php echo $webhook_url; ?></script>
<script type="text/plain" id="forms-by-dan-api-key"><?php echo $api_key; ?></script>
<script type="text/plain" id="forms-by-dan-redirect-url"><?php echo $redirect_url; ?></script>
    <style>
        /* Inline styles for the form */
        #formsByDanRoot {
            max-width: 800px;
            margin: auto;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            font-family: Arial, sans-serif;
        }
        input, select, textarea {
            width: 100%;
            padding: 12px;
            margin: 8px 0 20px 0;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
        }
        button {
            padding: 12px 24px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            background-color: #0073aa;
            color: #ffffff !important;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #005f8d;
        }
        .hidden { display: none; }
        .error-message { color: red; font-weight: bold; }
        .form-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        .claims-section label {
            display: block;
            margin-top: 10px;
        }

        .claims-section input,
        .claims-section select,
        .claims-section textarea {
            width: 100% !important;
            display: block !important;
            visibility: visible !important;
            margin: 8px 0 20px 0 !important;
            padding: 12px !important;
            box-sizing: border-box !important;
            border: 1px solid #ccc !important;
            border-radius: 4px !important;
            font-size: 16px !important;
            background-color: white !important;
            color: black !important;
        }

        .claims-section input[type="checkbox"]::after,
        .claims-section input[type="checkbox"]::before {
            content: none !important;
            display: none !important;
        }

        .claims-section label {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .claims-section input[type="checkbox"] {
            width: auto !important;
            margin: 0 !important;
        }

        .form-instruction {
            font-size: 1.1em;
            margin-bottom: 16px;
            color: #333;
        }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const formRoot = document.getElementById('formsByDanRoot');
        let formStepsRaw = document.getElementById('forms-by-dan-definition').textContent;
        try {
            formStepsRaw = formStepsRaw.replace(/&quot;/g, '"');
            let formConfig = JSON.parse(formStepsRaw);
            let formSteps, warnings;
            if (Array.isArray(formConfig)) {
                formSteps = formConfig;
                warnings = [];
            } else {
                formSteps = formConfig.steps;
                warnings = formConfig.warnings || [];
            }

            let currentStep = 0;
            const storageKey = 'multiStepFormData';
            let savedFiles = {};

            function saveProgress() {
                const form = document.getElementById('formsByDanForm');
                const inputs = form.querySelectorAll('input, select, textarea');
                const data = JSON.parse(localStorage.getItem(storageKey) || '{}');
                inputs.forEach(input => {
                    const key = input.name || input.id;
                    if (!key) return;
                    if (input.type === 'checkbox') {
                        data[key] = input.checked;
                    } else if (input.type !== 'file') {
                        data[key] = input.value;
                    }
                });
                data.files = savedFiles;
                localStorage.setItem(storageKey, JSON.stringify(data));
            }

            function loadProgress() {
                const data = JSON.parse(localStorage.getItem(storageKey) || '{}');
                savedFiles = data.files || {};
                const inputs = document.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    if (!input.name && !input.id) return;
                    const key = input.name || input.id;
                    if (!(key in data)) return;
                    if (input.type === 'checkbox') {
                        input.checked = !!data[key];
                    } else if (input.type !== 'file') {
                        input.value = data[key];
                    }
                });
            }


            function allStepsValid() {
                try {
                    let formStepsData = JSON.parse(document.getElementById('forms-by-dan-definition').textContent.replace(/&quot;/g, '"'));
                    // Support both array and object with steps
                    if (!Array.isArray(formStepsData)) {
                        formStepsData = formStepsData.steps;
                    }                    const savedData = JSON.parse(localStorage.getItem('multiStepFormData') || '{}');
                    let isValid = true;

                    formStepsData.forEach(step => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(step.html, 'text/html');
                        // Validate all [required] fields
                        const requiredElements = doc.querySelectorAll('[required]');
                        requiredElements.forEach(el => {
                            const name = el.name || el.id;
                            if (el.type === 'checkbox') {
                                if (!savedData[name]) isValid = false;
                            } else if (el.type === 'file') {
                                const form = document.getElementById('formsByDanForm');
                                // Try both name and id for file input
                                let fileInput = null;
                                if (form) {
                                    fileInput = form.querySelector(`[name="${name}"]`) || form.querySelector(`#${name}`);
                                }
                                if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                                    isValid = false;
                                }
                            } else if (!savedData[name] || savedData[name].toString().trim() === '') {
                                isValid = false;
                            }
                        });
                        // Validate all .required-if-visible fields that are visible
                        const requiredIfVisibleElements = doc.querySelectorAll('.required-if-visible');
                        requiredIfVisibleElements.forEach(el => {
                            const name = el.name || el.id;
                            const form = document.getElementById('formsByDanForm');
                            // Try both name and id for file input
                            let liveInput = null;
                            if (form) {
                                liveInput = form.querySelector(`[name="${name}"]`) || form.querySelector(`#${name}`);
                            }
                            if (liveInput && liveInput.offsetParent !== null) {
                                if (el.type === 'checkbox') {
                                    if (!savedData[name]) isValid = false;
                                } else if (el.type === 'file') {
                                    if (!liveInput.files || liveInput.files.length === 0) {
                                        isValid = false;
                                    }
                                } else if (!savedData[name] || savedData[name].toString().trim() === '') {
                                    isValid = false;
                                }
                            }
                        });
                    });

                    return isValid;
                } catch (e) {
                    console.error('Validation check failed:', e);
                    return false;
                }
            }

            function updateSubmitButtonState() {
                const form = document.getElementById('formsByDanForm');
                const submitBtn = document.getElementById('submitBtn');
                if (!submitBtn || !form) return;
                // Only enable submit button on the last step and if all steps are valid
                const isLastStep = (typeof formSteps !== 'undefined') && (typeof currentStep !== 'undefined') && (currentStep === formSteps.length - 1);
                const isValid = allStepsValid();
                submitBtn.disabled = !(isLastStep && isValid);
            }

            function handleFileInputChange(e) {
                const input = e.target;
                const key = input.name || input.id;
                if (!key) return;
                if (!input.files || input.files.length === 0) {
                    delete savedFiles[key];
                    saveProgress();
                    return;
                }
                savedFiles[key] = [];
                const promises = [];
                for (let i = 0; i < input.files.length; i++) {
                    const file = input.files[i];
                    const reader = new FileReader();
                    const p = new Promise(resolve => {
                        reader.onload = () => {
                            savedFiles[key].push({
                                name: file.name,
                                type: file.type,
                                data: reader.result.split(',')[1]
                            });
                            resolve();
                        };
                    });
                    reader.readAsDataURL(file);
                    promises.push(p);
                }
                Promise.all(promises).then(() => {
                    saveProgress();
                });
            }

            // Add this function before renderForm
            function getCustomWarnings(saved, formConfig) {
                const warnings = [];
                if (formConfig && Array.isArray(formConfig.warnings)) {
                    formConfig.warnings.forEach(warn => {
                        // Example: { condition: "!saved['creditMonitoring'] && !saved['claimOrdinary'] && !saved['claimTime'] && !saved['claimExtraordinary']", message: "You have not selected any claim benefits. Please go back and select at least one claim benefit to proceed." }
                        try {
                            // eslint-disable-next-line no-new-func
                            const conditionFn = new Function('saved', `return (${warn.condition});`);
                            if (conditionFn(saved)) {
                                warnings.push(warn.message);
                            }
                        } catch (e) {
                            console.error('Warning condition error:', e, warn);
                        }
                    });
                }
                return warnings;
            }

            function renderForm() {
                const step = formSteps[currentStep];
                const instructionHtml = step.instruction ? `<div class="form-instruction">${step.instruction}</div>` : '';
                const stepContent = step.html;
                const wrappedContent = `<div class="claims-section">${instructionHtml}${stepContent}</div>`;
                let summaryHtml = '';
                if (currentStep === formSteps.length - 1) {
                    const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
                    // Get form config (with warnings) from the JSON definition
                    let formConfig = {};
                    try {
                        formConfig = JSON.parse(document.getElementById('forms-by-dan-definition').textContent.replace(/&quot;/g, '"'));
                        if (Array.isArray(formConfig)) formConfig = { steps: formConfig }; // fallback for old format
                    } catch (e) { formConfig = {}; }
                    // Build a summary of all filled fields, checked boxes, selected files, etc.
                    const form = document.createElement('form');
                    // Combine all step HTML to get all fields
                    formSteps.forEach(s => {
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = s.html;
                        Array.from(tempDiv.children).forEach(child => form.appendChild(child.cloneNode(true)));
                    });
                    // Find all inputs, selects, textareas
                    const allFields = form.querySelectorAll('input, select, textarea');
                    let summaryList = '';
                    allFields.forEach(field => {
                        const key = field.name || field.id;
                        if (!key) return;
                        let label = '';
                        // For select fields, get the label from the parent label element, not the option text
                        if (field.tagName && field.tagName.toLowerCase() === 'select') {
                            // Try to find a label that is the parent of the select
                            if (field.parentElement && field.parentElement.tagName.toLowerCase() === 'label') {
                                label = field.parentElement.textContent.trim();
                                // Remove the text of all child options from the label
                                Array.from(field.options).forEach(opt => {
                                    label = label.replace(opt.text, '').trim();
                                });
                            } else {
                                // Fallback to previous logic
                                const labelEl = form.querySelector(`label[for="${field.id}"]`) || (field.closest('label'));
                                label = labelEl ? labelEl.textContent.trim() : key;
                            }
                        } else {
                            // Non-select fields: use previous logic
                            const labelEl = form.querySelector(`label[for="${field.id}"]`) || (field.closest('label'));
                            label = labelEl ? labelEl.textContent.trim() : key;
                        }
                        if (field.type === 'checkbox') {
                            if (saved[key]) {
                                summaryList += `<li>${label}: Checked</li>`;
                            }
                        } else if (field.type === 'file') {
                            if (saved.files && saved.files[key] && saved.files[key].length > 0) {
                                const fileNames = saved.files[key].map(f => f.name).join(', ');
                                summaryList += `<li>${label}: ${fileNames}</li>`;
                            }
                        } else if (field.tagName && field.tagName.toLowerCase() === 'select') {
                            // Only show if a real value is selected
                            if (saved[key] && saved[key] !== '' && saved[key] !== '--Select--') {
                                // Find the selected option's text from the DOM, fallback to value
                                let selectedText = saved[key];
                                if (field.options && field.options.length > 0) {
                                    for (let i = 0; i < field.options.length; i++) {
                                        if (field.options[i].value == saved[key]) {
                                            selectedText = field.options[i].text;
                                            break;
                                        }
                                    }
                                }
                                summaryList += `<li>${label}: ${selectedText}</li>`;
                            }
                        } else if (field.type === 'radio') {
                            if (saved[key] && field.value === saved[key]) {
                                summaryList += `<li>${label}: ${field.value}</li>`;
                            }
                        } else {
                            if (saved[key] && saved[key].toString().trim() !== '') {
                                summaryList += `<li>${label}: ${saved[key]}</li>`;
                            }
                        }
                    });
                    summaryHtml = `<div class="summary-section"><h3>Summary of Your Submission:</h3><ul>${summaryList}</ul></div>`;
                    // Custom warnings from JSON
                    const customWarnings = getCustomWarnings(saved, formConfig);
                    customWarnings.forEach(msg => {
                        summaryHtml += `<div class="error-message">${msg}</div>`;
                    });
                }

                formRoot.innerHTML = `
                    <form id="formsByDanForm" enctype="multipart/form-data" method="POST">
                        <h2>${step.title}</h2>
                        ${wrappedContent}
                        ${summaryHtml}
                        <div class="form-navigation">
                            <button type="button" id="prevBtn">Back</button>
                            <button type="button" id="nextBtn">Next</button>
                            <button type="submit" id="submitBtn">Submit</button>
                        </div>
                    </form>`;

                // Inject query parameters into hidden inputs
                const form = document.getElementById('formsByDanForm');
                const params = new URLSearchParams(window.location.search);
                ['id', 'lastName', 'salt', 'token'].forEach(name => {
                    let input = form.querySelector(`input[name="${name}"]`);
                    if (!input) {
                        input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = name;
                        form.appendChild(input);
                    }
                    input.value = params.get(name) || '';
                });

                loadProgress();
                updateSubmitButtonState();

                // Remove logic that disables submit button based on current step's validation
                // const submitBtn = document.getElementById('submitBtn');
                // submitBtn.disabled = true;

                const inputs = document.querySelectorAll('input, select, textarea');
                inputs.forEach(el => {
                    el.addEventListener('input', () => {
                        updateSubmitButtonState();
                        saveProgress(); // also save state as user fills it out
                    });
                    if (el.type === 'checkbox') {
                        el.addEventListener('change', updateSubmitButtonState);
                    }
                    if (el.type === 'file') {
                        el.addEventListener('change', handleFileInputChange);
                    }
                });

                if (currentStep === formSteps.length - 1) {
                    const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
                    const anyClaimed = !!(saved['creditMonitoring'] || saved['claimOrdinary'] || saved['claimTime'] || saved['claimExtraordinary']);
                    console.log('Benefit claim status:', {
                        creditMonitoring: saved['creditMonitoring'],
                        claimOrdinary: saved['claimOrdinary'],
                        claimTime: saved['claimTime'],
                        claimExtraordinary: saved['claimExtraordinary'],
                        anyClaimed
                    });
                    const warning = document.getElementById('benefitWarning');
                    if (warning) {
                        if (!anyClaimed) {
                            warning.classList.remove('hidden');
                        } else {
                            warning.classList.add('hidden');
                        }
                    }
                }

                document.getElementById('prevBtn').onclick = () => {
                    saveProgress();
                    if (currentStep > 0) currentStep--;
                    renderForm();
                };

                document.getElementById('nextBtn').onclick = () => {
                    const form = document.getElementById('formsByDanForm');
                    // Log all required fields for this step
                    const requiredFields = Array.from(form.querySelectorAll('[required]')).map(el => ({
                        name: el.name || el.id,
                        type: el.type,
                        value: el.value
                    }));
                    console.log('Required fields at this step:', requiredFields);
                    if (!form.checkValidity()) {
                        form.reportValidity();
                        return;
                    }
                    saveProgress();
                    currentStep++;
                    renderForm();
                };

                document.getElementById('formsByDanForm').onsubmit = e => {
                    e.preventDefault();
                    const form = document.getElementById('formsByDanForm');
                    const fileInputs = form.querySelectorAll('input[type="file"]');
                    const readPromises = [];
                    fileInputs.forEach(input => {
                        const key = input.name || input.id;
                        if (!key) return;
                        if (!input.files || input.files.length === 0) {
                            delete savedFiles[key];
                            return;
                        }
                        savedFiles[key] = [];
                        for (let i = 0; i < input.files.length; i++) {
                            const file = input.files[i];
                            const reader = new FileReader();
                            const p = new Promise(resolve => {
                                reader.onload = () => {
                                    savedFiles[key].push({
                                        name: file.name,
                                        type: file.type,
                                        data: reader.result.split(',')[1]
                                    });
                                    resolve();
                                };
                            });
                            reader.readAsDataURL(file);
                            readPromises.push(p);
                        }
                    });
                    Promise.all(readPromises).then(() => {
                        saveProgress();
                        const savedData = JSON.parse(localStorage.getItem(storageKey) || '{}');
                        savedData.files = savedFiles;
                        const redirectUrlEl = document.getElementById('forms-by-dan-redirect-url');
                        const redirectUrl = redirectUrlEl ? redirectUrlEl.textContent.trim() : '';
                        fetch(document.getElementById('forms-by-dan-webhook-url').textContent.trim(), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Ocp-Apim-Subscription-Key': document.getElementById('forms-by-dan-api-key').textContent.trim()
                            },
                            body: JSON.stringify(savedData)
                        }).then(() => {
                            if (redirectUrl) {
                                window.location.href = redirectUrl;
                            } else {
                                alert('Submitted');
                            }
                        }).catch(() => alert('Submission failed.'));
                    });
                };

                attachConditionalHandlers();
                updateSubmitButtonState();
            }

            // In attachConditionalHandlers, support data-toggle-target attribute for checkboxes
            function attachConditionalHandlers() {
                const checkboxes = document.querySelectorAll('input[type="checkbox"][data-toggle-target]');
                checkboxes.forEach(box => {
                    const targetSelector = box.getAttribute('data-toggle-target');
                    if (!targetSelector) return;
                    const targets = document.querySelectorAll(targetSelector);
                    box.onchange = () => {
                        targets.forEach(div => {
                            div.classList.toggle('hidden', !box.checked);
                        });
                    };
                    // Initial state
                    box.dispatchEvent(new Event('change'));
                });
                const toggle = (id, fields, required = []) => {
                    const box = document.getElementById(id);
                    const div = document.getElementById(fields);
                    if (!box || !div) return;
                    box.onchange = () => {
                        div.classList.toggle('hidden', !box.checked);
                        required.forEach(name => {
                            const input = document.querySelector(`[name="${name}"]`);
                            if (input) input.required = box.checked;
                        });
                    };
                    box.dispatchEvent(new Event('change'));
                };
                toggle('claimOrdinary', 'ordinaryFields', ['ordinaryAmount', 'ordinaryExplanation']);
                toggle('claimTime', 'timeFields', ['hoursSpent', 'timeDescription']);
                toggle('claimExtraordinary', 'extraFields', ['extraAmount', 'extraExplanation']);

                const noEmail = document.getElementById('noEmail');
                const email = document.querySelector('input[name="email"]');
                if (noEmail && email) {
                    noEmail.onchange = () => {
                        email.required = !noEmail.checked;
                        email.closest('label').style.display = noEmail.checked ? 'none' : 'block';
                    };
                    noEmail.dispatchEvent(new Event('change'));
                }
            }

            renderForm();
        } catch (e) {
            console.error('Failed to parse form JSON:', e);
            formRoot.innerHTML = "<p style='color: red;'>There was an error rendering the form. Please check the JSON definition.</p>";
        }
    });
    </script>
    <?php
    return ob_get_clean();
}
