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

// Add meta box for form configuration
add_action('add_meta_boxes', function () {
    add_meta_box('forms_by_dan_meta', 'Form Configuration', 'render_forms_by_dan_meta_box', 'forms_by_dan_form', 'normal', 'high');
});

// Render the custom meta box
function render_forms_by_dan_meta_box($post) {
    $form_json = get_post_meta($post->ID, '_forms_by_dan_form_json', true);
    $webhook_url = get_post_meta($post->ID, '_forms_by_dan_webhook_url', true);
    wp_nonce_field('save_forms_by_dan_meta', 'forms_by_dan_nonce');
    echo '<p><label for="forms_by_dan_webhook_url">Webhook URL:</label><br>';
    echo '<input type="text" id="forms_by_dan_webhook_url" name="forms_by_dan_webhook_url" value="' . esc_attr($webhook_url) . '" style="width:100%;"></p>';
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
    ob_start();
    ?>
    <div id="formsByDanRoot"></div>
<script type="application/json" id="forms-by-dan-definition"><?php echo $form_json; ?></script>
<script type="text/plain" id="forms-by-dan-webhook-url"><?php echo $webhook_url; ?></script>
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
            const formSteps = JSON.parse(formStepsRaw);

            let currentStep = 0;
            const storageKey = 'multiStepFormData';

            function saveProgress() {
                const form = document.getElementById('formsByDanForm');
                const inputs = form.querySelectorAll('input, select, textarea');
                // Load existing data so we don't lose values from previous steps
                const data = JSON.parse(localStorage.getItem(storageKey) || '{}');
                inputs.forEach(input => {
                    // Use name if available, otherwise fallback to id
                    const key = input.name || input.id;
                    if (!key) return;
                    if (input.type === 'checkbox') {
                        data[key] = input.checked;
                    } else if (input.type !== 'file') {
                        data[key] = input.value;
                    }
                });
                localStorage.setItem(storageKey, JSON.stringify(data));
            }

            function loadProgress() {
                const data = JSON.parse(localStorage.getItem(storageKey) || '{}');
                const inputs = document.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    if (!input.name && !input.id) return;
                    // Use name if available, otherwise fallback to id
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
                    const formStepsData = JSON.parse(document.getElementById('forms-by-dan-definition').textContent.replace(/&quot;/g, '"'));
                    const savedData = JSON.parse(localStorage.getItem('multiStepFormData') || '{}');
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

            function renderForm() {
                const step = formSteps[currentStep];
                const instructionHtml = step.instruction ? `<div class="form-instruction">${step.instruction}</div>` : '';
                const stepContent = step.html;
                const wrappedContent = `<div class="claims-section">${instructionHtml}${stepContent}</div>`;
                let summaryHtml = '';
                if (currentStep === formSteps.length - 1) {
                    const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
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
                        // Try to find a label for this field
                        const labelEl = form.querySelector(`label[for="${field.id}"]`) || (field.closest('label'));
                        if (labelEl) {
                            label = labelEl.textContent.trim();
                        } else {
                            label = key;
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
                        } else if (field.tagName.toLowerCase() === 'select') {
                            if (saved[key]) {
                                const selectedOption = Array.from(field.options).find(opt => opt.value == saved[key]);
                                const display = selectedOption ? selectedOption.text : saved[key];
                                summaryList += `<li>${label}: ${display}</li>`;
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
                    const warningMsg = '<div id="benefitWarning" class="error-message hidden">You have not selected any claim benefits. Please go back and select at least one claim benefit to proceed.</div>';
                    summaryHtml += warningMsg;
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
                    saveProgress();
                    const savedData = JSON.parse(localStorage.getItem('multiStepFormData') || '{}');
                    const form = document.getElementById('formsByDanForm');
                    const payload = { ...savedData, files: {} };
                    const fileInputs = form.querySelectorAll('input[type="file"]');
                    const readFilePromises = [];

                    fileInputs.forEach(input => {
                        if (input.files.length > 0) {
                            payload.files[input.name] = [];
                            for (let i = 0; i < input.files.length; i++) {
                                const file = input.files[i];
                                const reader = new FileReader();
                                const promise = new Promise(resolve => {
                                    reader.onload = () => {
                                        payload.files[input.name].push({
                                            name: file.name,
                                            type: file.type,
                                            data: reader.result.split(',')[1]
                                        });
                                        resolve();
                                    };
                                });
                                reader.readAsDataURL(file);
                                readFilePromises.push(promise);
                            }
                        }
                    });

                    Promise.all(readFilePromises).then(() => {
                        fetch(document.getElementById('forms-by-dan-webhook-url').textContent.trim(), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        }).then(() => alert('Submitted')).catch(() => alert('Submission failed.'));
                    });
                };

                attachConditionalHandlers();
                updateSubmitButtonState();
            }

            function attachConditionalHandlers() {
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
