<?php
/**
 * Plugin Name: Forms by Dan
 * Description: Embed a multi-step ACEN claim form into pages and posts using the [forms_by_dan] shortcode.
 * Version: 1.5
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

// CORS: Allow requests from https://auth-webhook.azurewebsites.net
add_action('init', function() {
    if (isset($_SERVER['HTTP_ORIGIN']) && strpos($_SERVER['HTTP_ORIGIN'], 'https://auth-webhook.azurewebsites.net') !== false) {
        header('Access-Control-Allow-Origin: https://auth-webhook.azurewebsites.net');
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
    $project_id = get_post_meta($post->ID, '_forms_by_dan_project_id', true);
    wp_nonce_field('save_forms_by_dan_meta', 'forms_by_dan_nonce');
    echo '<p><label for="forms_by_dan_webhook_url">Webhook URL:</label><br>';
    echo '<input type="text" id="forms_by_dan_webhook_url" name="forms_by_dan_webhook_url" value="' . esc_attr($webhook_url) . '" style="width:100%;"></p>';
    echo '<p><label for="forms_by_dan_api_key">API Key (ocp-apim-subscription-key):</label><br>';
    echo '<input type="text" id="forms_by_dan_api_key" name="forms_by_dan_api_key" value="' . esc_attr($api_key) . '" style="width:100%;"></p>';
    echo '<p><label for="forms_by_dan_project_id">Project ID:</label><br>';
    echo '<input type="text" id="forms_by_dan_project_id" name="forms_by_dan_project_id" value="' . esc_attr($project_id) . '" style="width:100%;"></p>';
    echo '<p><label for="forms_by_dan_redirect_url">Redirect URL:</label><br>';
    echo '<input type="text" id="forms_by_dan_redirect_url" name="forms_by_dan_redirect_url" value="' . esc_attr($redirect_url) . '" style="width:100%;"></p>';
    echo '<p><label for="forms_by_dan_form_json">Form JSON:</label><br>';
    echo '<textarea id="forms_by_dan_form_json" name="forms_by_dan_form_json" rows="15" style="width:100%;">' . esc_textarea($form_json) . '</textarea></p>';

    // Shortcode display section
    echo '<p><label for="forms_by_dan_shortcode">Shortcode:</label><br>';
    echo '<input type="text" id="forms_by_dan_shortcode" value="[forms_by_dan id=' . esc_attr($post->ID) . ']" readonly style="width:100%;">';
    echo '<button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById(\'forms_by_dan_shortcode\').value)">Copy to Clipboard</button></p>';
}
// Allow unfiltered HTML in the signin form.
add_action('admin_init', function() {
    $role = get_role('editor'); // Or any role you're targeting
    if ($role) {
        $role->add_cap('unfiltered_html');
    }
});

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
    if (isset($_POST['forms_by_dan_project_id'])) {
        update_post_meta($post_id, '_forms_by_dan_project_id', sanitize_text_field($_POST['forms_by_dan_project_id']));
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
    $project_id = esc_attr(get_post_meta($post->ID, '_forms_by_dan_project_id', true));
    ob_start();
    ?>
    <div id="formsByDanRoot"></div>
<script type="text/plain" id="forms-by-dan-webhook-url"><?php echo $webhook_url; ?></script>
<script type="text/plain" id="forms-by-dan-api-key"><?php echo $api_key; ?></script>
<script type="text/plain" id="forms-by-dan-redirect-url"><?php echo $redirect_url; ?></script>
<script type="text/plain" id="forms-by-dan-pid"><?php echo $project_id; ?></script>
    <style>
        /* Modern, visually attractive form styles */
        #formsByDanRoot {
            max-width: 700px;
            margin: 40px auto;
            background: #f9fafd;
            padding: 36px 32px 32px 32px;
            border-radius: 18px;
            box-shadow: 0 4px 32px rgba(0,0,0,0.10), 0 1.5px 6px rgba(0,0,0,0.04);
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        .claims-section label {
            display: block;
            margin-top: 18px;
            font-weight: 500;
            color: #2d3a4a;
            letter-spacing: 0.01em;
        }
        .claims-section input,
        .claims-section select,
        .claims-section textarea {
            width: 100% !important;
            display: block !important;
            margin: 10px 0 24px 0 !important;
            padding: 14px 12px !important;
            box-sizing: border-box !important;
            border: 1.5px solid #d1d9e6 !important;
            border-radius: 7px !important;
            font-size: 17px !important;
            background-color: #fff !important;
            color: #222 !important;
            transition: border-color 0.2s;
        }
        .claims-section input:focus,
        .claims-section select:focus,
        .claims-section textarea:focus {
            border-color: #0073aa !important;
            outline: none;
            box-shadow: 0 0 0 2px #e6f2fa;
        }
        .claims-section input[type="checkbox"] {
            width: 24px !important;
            margin: 0 8px 0 0 !important;
            accent-color: #0073aa;
        }
        .claims-section input[type="checkbox"]::after {
            content: none !important;
        }

        
        .claims-section label {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-instruction {
            font-size: 1.13em;
            margin-bottom: 18px;
            color: #3a4a5d;
            background: #eaf6ff;
            padding: 10px 16px;
            border-radius: 6px;
        }
        .form-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 36px;
            gap: 12px;
        }
        button {
            padding: 13px 28px;
            font-size: 17px;
            border: none;
            border-radius: 7px;
            background: linear-gradient(90deg, #0073aa 60%, #005f8d 100%);
            color: #fff !important;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: background 0.2s, box-shadow 0.2s;
        }
        button:hover, button:focus {
            background: linear-gradient(90deg, #005f8d 60%, #0073aa 100%);
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }
        .summary-section {
            background: #f3f7fa;
            border-radius: 8px;
            padding: 18px 20px 10px 20px;
            margin: 30px 0 18px 0;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .summary-section h3 {
            margin-top: 0;
            color: #0073aa;
            font-size: 1.18em;
        }
        .summary-section ul {
            padding-left: 20px;
            margin: 0;
        }
        .error-message {
            color: #d00;
            font-weight: 600;
            margin: 12px 0 0 0;
            background: #fff0f0;
            border-left: 4px solid #d00;
            padding: 8px 14px;
            border-radius: 5px;
        }
        .file-list {
            margin-top: 6px;
            margin-bottom: 10px;
            display: block;
            width: 100%;
        }
        .file-entry {
            display: block;
            margin-bottom: 2px;
            font-size: 15px;
            color: #2d3a4a;
            word-break: break-word;
            white-space: normal;
            width: 100%;
        }
        .file-entry .delete-file-x {
            color: #d00;
            font-size: 18px;
            font-weight: bold;
            margin-left: 8px;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0 4px;
            line-height: 1;
        }
        .file-entry .delete-file-x:hover {
            color: #a00;
        }
        .hidden { display: none; }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const formRoot = document.getElementById('formsByDanRoot');
        // Only use localStorage for form JSON. If not present, show error and stop.
        let formStepsRaw = null;
        let formConfig = null;
        // 1. Check localStorage ONLY
        try {
            const localJson = localStorage.getItem('formsByDanFormJson');
            if (localJson) {
                formStepsRaw = localJson;
            }
        } catch (e) {}
        if (!formStepsRaw) {
            formRoot.innerHTML = "<p style='color: red;'>No form JSON found in localStorage. Please set 'formsByDanFormJson' in localStorage to render the form.</p>";
            return;
        }
        try {
            formStepsRaw = formStepsRaw.replace(/&quot;/g, '"');
            formConfig = JSON.parse(formStepsRaw);
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
                // Track which fields are required-if-visible and not visible
                const requiredIfVisibleInputs = form.querySelectorAll('.required-if-visible');
                requiredIfVisibleInputs.forEach(input => {
                    // Only remove if not visible
                    let node = input;
                    let isVisible = true;
                    while (node) {
                        if (node.classList && node.classList.contains('hidden')) {
                            isVisible = false;
                            break;
                        }
                        const style = window.getComputedStyle(node);
                        if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
                            isVisible = false;
                            break;
                        }
                        node = node.parentElement;
                    }
                    const key = input.name || input.id;
                    if (!isVisible && key) {
                        // Remove from data if not visible
                        delete data[key];
                        if (input.type === 'file') {
                            if (data.files && data.files[key]) {
                                delete data.files[key];
                            }
                            if (savedFiles && savedFiles[key]) {
                                delete savedFiles[key];
                            }
                        }
                    }
                });
                inputs.forEach(input => {
                    const key = input.name || input.id;
                    if (!key) return;
                    // Skip required-if-visible fields if not visible (already handled above)
                    if (input.classList && input.classList.contains('required-if-visible')) {
                        let node = input;
                        let isVisible = true;
                        while (node) {
                            if (node.classList && node.classList.contains('hidden')) {
                                isVisible = false;
                                break;
                            }
                            const style = window.getComputedStyle(node);
                            if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
                                isVisible = false;
                                break;
                            }
                            node = node.parentElement;
                        }
                        if (!isVisible) return;
                    }
                    if (input.type === 'checkbox') {
                        data[key] = input.checked;
                    } else if (input.type !== 'file') {
                        data[key] = input.value;
                    }
                });
                data.files = savedFiles;
                localStorage.setItem(storageKey, JSON.stringify(data));
            }

            function handleFileInputChange(e) {
                const input = e.target;
                const key = input.name || input.id;
                if (!key) return;
                const maxFiles = parseInt(input.getAttribute('data-max-files') || '0', 10) || 1;
                if (!input.files || input.files.length === 0) {
                    delete savedFiles[key];
                    saveProgress();
                    renderFileListForInput(input);
                    return;
                }
                let filesArr = Array.from(input.files);
                // Only allow one file at a time (flow: upload one, then another)
                filesArr = filesArr.slice(0, 1);
                if (!savedFiles[key]) savedFiles[key] = [];
                // If maxFiles is reached, do not add more
                if (savedFiles[key].length >= maxFiles) {
                    alert('You can only upload up to ' + maxFiles + ' files.');
                    input.value = '';
                    return;
                }
                const file = filesArr[0];
                const reader = new FileReader();
                reader.onload = () => {
                    savedFiles[key].push({
                        name: file.name,
                        type: file.type,
                        data: reader.result.split(',')[1]
                    });
                    saveProgress();
                    renderFileListForInput(input);
                    input.value = '';
                };
                reader.readAsDataURL(file);
            }

            function renderFileListForInput(input) {
                const key = input.name || input.id;
                let fileListDiv = input.parentElement.querySelector('.file-list');
                if (!fileListDiv) {
                    fileListDiv = document.createElement('div');
                    fileListDiv.className = 'file-list';
                    // Insert file list directly after the file input
                    input.parentElement.insertBefore(fileListDiv, input.nextSibling);
                }
                fileListDiv.innerHTML = '';
                const files = (savedFiles && savedFiles[key]) ? savedFiles[key] : [];
                if (files.length > 0) {
                    files.forEach((file, idx) => {
                        const fileDiv = document.createElement('div');
                        fileDiv.className = 'file-entry';
                        fileDiv.innerHTML = `<span>${file.name}</span><span class="delete-file-x" title="Delete file" data-key="${key}" data-idx="${idx}">×</span>`;
                        fileListDiv.appendChild(fileDiv);
                    });
                }
                // Attach delete handlers
                fileListDiv.querySelectorAll('.delete-file-x').forEach(x => {
                    x.onclick = function() {
                        const key = x.getAttribute('data-key');
                        const idx = parseInt(x.getAttribute('data-idx'), 10);
                        if (savedFiles[key]) {
                            savedFiles[key].splice(idx, 1);
                            if (savedFiles[key].length === 0) delete savedFiles[key];
                            saveProgress();
                            renderFileListForInput(input);
                        }
                    };
                });
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
                // Render file lists for all file inputs
                document.querySelectorAll('input[type="file"]').forEach(input => {
                    renderFileListForInput(input);
                });
            }


            function allStepsValid() {
                try {
                    let formStepsData = JSON.parse(document.getElementById('forms-by-dan-definition').textContent.replace(/&quot;/g, '"'));
                    if (!Array.isArray(formStepsData)) {
                        formStepsData = formStepsData.steps;
                    }
                    const savedData = JSON.parse(localStorage.getItem('multiStepFormData') || '{}');
                    let isValid = true;
                    let groupCheckboxErrors = {};

                    formStepsData.forEach((step, stepIdx) => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(step.html, 'text/html');
                        // Validate all [required] fields
                        const requiredElements = doc.querySelectorAll('[required]');
                        requiredElements.forEach(el => {
                            const name = el.name || el.id;
                            let valid = true;
                            if (el.type === 'checkbox') {
                                valid = !!savedData[name];
                            } else if (el.type === 'file') {
                                valid = !!(savedData.files && savedData.files[name] && savedData.files[name].length > 0);
                            } else {
                                valid = !!(savedData[name] && savedData[name].toString().trim() !== '');
                            }
                            if (!valid) isValid = false;
                        });
                        // Validate all .required-if-visible fields that are visible
                        const requiredIfVisibleElements = doc.querySelectorAll('.required-if-visible');
                        requiredIfVisibleElements.forEach(el => {
                            const name = el.name || el.id;
                            // Find the input in the live DOM by name or id
                            const form = document.getElementById('formsByDanForm');
                            let liveInput = null;
                            if (form) {
                                liveInput = form.querySelector(`[name="${name}"]`) || form.querySelector(`#${name}`);
                            }
                            // Only validate if the input is visible (not hidden by CSS or parent)
                            let isVisible = false;
                            if (liveInput) {
                                let node = liveInput;
                                isVisible = true;
                                while (node) {
                                    if (node.classList && node.classList.contains('hidden')) {
                                        isVisible = false;
                                        break;
                                    }
                                    const style = window.getComputedStyle(node);
                                    if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
                                        isVisible = false;
                                        break;
                                    }
                                    node = node.parentElement;
                                }
                            }
                            if (liveInput && isVisible) {
                                let valid = true;
                                if (liveInput.type === 'file') {
                                    valid = !!(savedData.files && savedData.files[name] && savedData.files[name].length > 0);
                                } else if (liveInput.type === 'checkbox') {
                                    valid = !!savedData[name];
                                } else {
                                    valid = !!(savedData[name] && savedData[name].toString().trim() !== '');
                                }
                                if (!valid) isValid = false;
                            }
                        });

                        // Group checkbox validation: require at least one checked per group
                        const groupCheckboxes = doc.querySelectorAll('input[type="checkbox"][data-group]');
                        const groupMap = {};
                        groupCheckboxes.forEach(cb => {
                            const group = cb.getAttribute('data-group');
                            if (!group) return;
                            if (!groupMap[group]) groupMap[group] = [];
                            groupMap[group].push(cb);
                        });
                        Object.keys(groupMap).forEach(group => {
                            // Find all checkboxes in this group in the live DOM
                            const form = document.getElementById('formsByDanForm');
                            const liveCheckboxes = form ? Array.from(form.querySelectorAll(`input[type="checkbox"][data-group="${group}"]`)) : [];
                            // Only consider visible checkboxes
                            const visibleCheckboxes = liveCheckboxes.filter(cb => {
                                let node = cb;
                                let isVisible = true;
                                while (node) {
                                    if (node.classList && node.classList.contains('hidden')) {
                                        isVisible = false;
                                        break;
                                    }
                                    const style = window.getComputedStyle(node);
                                    if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
                                        isVisible = false;
                                        break;
                                    }
                                    node = node.parentElement;
                                }
                                return isVisible;
                            });
                            // At least one must be checked
                            const anyChecked = visibleCheckboxes.some(cb => cb.checked);
                            if (visibleCheckboxes.length > 0 && !anyChecked) {
                                isValid = false;
                                groupCheckboxErrors[group] = true;
                            }
                        });
                    });

                    // Show/hide group checkbox error messages in the UI
                    const form = document.getElementById('formsByDanForm');
                    if (form) {
                        // Remove old group error messages
                        form.querySelectorAll('.group-checkbox-error-message').forEach(el => el.remove());
                        // For each group with error, show a message after the last checkbox in the group
                        Object.keys(groupCheckboxErrors).forEach(group => {
                            const checkboxes = form.querySelectorAll(`input[type="checkbox"][data-group="${group}"]`);
                            if (checkboxes.length > 0) {
                                const last = checkboxes[checkboxes.length - 1];
                                const msg = document.createElement('div');
                                msg.className = 'error-message group-checkbox-error-message';
                                msg.textContent = 'Please select at least one option.';
                                last.parentElement.appendChild(msg);
                            }
                        });
                    }

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

                // Disable next button on last step
                const nextBtn = document.getElementById('nextBtn');
                if (nextBtn) {
                    nextBtn.disabled = (typeof formSteps !== 'undefined') && (typeof currentStep !== 'undefined') && (currentStep === formSteps.length - 1);
                }
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
                        // If this is a required-if-visible field, only include if visible in the live DOM
                        if (field.classList && field.classList.contains('required-if-visible')) {
                            // Try to find the live input in the DOM
                            const liveForm = document.getElementById('formsByDanForm');
                            let liveInput = null;
                            if (liveForm) {
                                liveInput = liveForm.querySelector(`[name="${key}"]`) || liveForm.querySelector(`#${key}`);
                            }
                            let isVisible = false;
                            if (liveInput) {
                                let node = liveInput;
                                isVisible = true;
                                while (node) {
                                    if (node.classList && node.classList.contains('hidden')) {
                                        isVisible = false;
                                        break;
                                    }
                                    const style = window.getComputedStyle(node);
                                    if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
                                        isVisible = false;
                                        break;
                                    }
                                    node = node.parentElement;
                                }
                            }
                            if (!isVisible) return;
                        }
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
                    // Make lastName read-only if it is a visible input
                    if (name === 'lastName') {
                        input.readOnly = true;
                        input.type = 'text'; // Ensure it's visible if not already
                    }
                });
                // Inject pid as hidden field
                let pid = '';
                const pidEl = document.getElementById('forms-by-dan-pid');
                if (pidEl) pid = pidEl.textContent.trim();
                let pidInput = form.querySelector('input[name="pid"]');
                if (!pidInput) {
                    pidInput = document.createElement('input');
                    pidInput.type = 'hidden';
                    pidInput.name = 'pid';
                    form.appendChild(pidInput);
                }
                pidInput.value = pid;

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
                        // Always allow multiple for flow, but only one at a time
                        el.setAttribute('multiple', 'multiple');
                        el.addEventListener('change', handleFileInputChange);
                        renderFileListForInput(el);
                    }
                });

                if (currentStep === formSteps.length - 1) {
                    const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
                    const anyClaimed = !!(saved['creditMonitoring'] || saved['claimOrdinary'] || saved['claimTime'] || saved['claimExtraordinary']);
                    // console.log('Benefit claim status:', {
                    //     creditMonitoring: saved['creditMonitoring'],
                    //     claimOrdinary: saved['claimOrdinary'],
                    //     claimTime: saved['claimTime'],
                    //     claimExtraordinary: saved['claimExtraordinary'],
                    //     anyClaimed
                    // });
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
                    if (!allStepsValid()) {
                        alert('Please complete all required fields before continuing.');
                        return;
                    }
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
                    if (!allStepsValid()) {
                        alert('Please complete all required fields before submitting.');
                        return;
                    }
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
                            body: JSON.stringify(savedData),
                            redirect: 'manual'
                        }).then(response => {
                            // Only redirect or show a static message; never use response body to re-render the form
                            if (response.status === 302 || response.status === 301) {
                                const location = response.headers.get('Location');
                                if (location) {
                                    window.location.href = location;
                                    return;
                                }
                            }
                            if (redirectUrl) {
                                window.location.href = redirectUrl;
                            } else {
                                // Show a static success message (do not re-render form with response body)
                                formRoot.innerHTML = '<div class="form-instruction" style="color:green;font-size:1.2em;">Thank you! Your submission has been received.</div>';
                                localStorage.removeItem(storageKey);
                            }
                        }).catch(() => {
                            alert('Submission failed.');
                        });
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

// Admin notice for Atticus Form Tool JSONer link
add_action('admin_notices', function() {
    if (!is_admin()) return;
    echo '<div class="notice notice-info" style="margin-top:20px;"><strong><a href="https://chatgpt.com/g/g-683891e9fe208191964005c5e2c0fa4e-atticus-form-tool-jsoner" target="_blank" rel="noopener" style="font-size:1.15em;">Atticus Form Tool JSONer &rarr;</a></strong></div>';
});