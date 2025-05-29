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

function forms_by_dan_menu() {
    add_options_page('Forms by Dan Settings', 'Forms by Dan', 'manage_options', 'forms-by-dan-settings', 'forms_by_dan_settings_page');
}

function forms_by_dan_settings() {
    register_setting('acenClaimFormOptions', 'forms_by_dan_webhook_url');
    register_setting('acenClaimFormOptions', 'forms_by_dan_form_json');
}

function forms_by_dan_settings_page() {
    ?>
    <div class="wrap">
        <h1>Forms by Dan Form Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('acenClaimFormOptions'); ?>
            <?php do_settings_sections('acenClaimFormOptions'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Webhook URL</th>
                    <td><input type="text" name="forms_by_dan_webhook_url" value="<?php echo esc_attr(get_option('forms_by_dan_webhook_url')); ?>" size="50" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Form Steps JSON</th>
                    <td><textarea name="forms_by_dan_form_json" rows="15" cols="100"><?php echo esc_textarea(get_option('forms_by_dan_form_json')); ?></textarea></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <h2>Embed This Form</h2>
        <p>Copy and paste the shortcode below into any page or post to display your form:</p>
        <input type="text" value="[forms_by_dan]" readonly onclick="this.select();" style="width: 300px;">
        <button onclick="navigator.clipboard.writeText('[forms_by_dan]'); alert('Shortcode copied!');">Copy to Clipboard</button>
    </div>
    <?php
}

function render_forms_by_dan_form() {
    $webhook_url = esc_url(get_option('forms_by_dan_webhook_url'));
    $form_json = get_option('forms_by_dan_form_json');
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
            padding: 10px;
            margin: 10px 0;
            box-sizing: border-box;
        }
        .hidden { display: none; }
        .error-message { color: red; font-weight: bold; }
        .form-navigation { display: flex; justify-content: space-between; margin-top: 20px; }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const formSteps = JSON.parse(document.getElementById('forms-by-dan-definition').textContent);

        const formRoot = document.getElementById('formsByDanRoot');
        let currentStep = 0;
        const storageKey = 'multiStepFormData';

        function saveProgress() {
            const form = document.getElementById('formsByDanForm');
            const inputs = form.querySelectorAll('input, select, textarea');
            const data = JSON.parse(localStorage.getItem(storageKey) || '{}');
            inputs.forEach(input => {
                if (!input.name) return;
                if (input.type === 'checkbox') {
                    data[input.name] = input.checked;
                } else if (input.type !== 'file') {
                    data[input.name] = input.value;
                }
            });
            localStorage.setItem(storageKey, JSON.stringify(data));
        }

        function loadProgress() {
            const data = JSON.parse(localStorage.getItem(storageKey) || '{}');
            const inputs = document.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                if (!input.name || !(input.name in data)) return;
                if (input.type === 'checkbox') {
                    input.checked = data[input.name];
                } else if (input.type !== 'file') {
                    input.value = data[input.name];
                }
            });
        }

        function renderForm() {
            const isFinalStep = currentStep === formSteps.length - 1;
            formRoot.innerHTML = `
                <form id="formsByDanForm" enctype="multipart/form-data">
                    <h2>${formSteps[currentStep].title}</h2>
                    ${formSteps[currentStep].html}
                    <div class="form-navigation">
                        <button type="button" id="prevBtn">Back</button>
                        <button type="button" id="nextBtn">Next</button>
                        <button type="submit" id="submitBtn">Submit</button>
                    </div>
                </form>`;

            loadProgress();

            if (isFinalStep) {
                const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
                const anyClaimed = !!(saved['creditMonitoring'] || saved['claimOrdinary'] || saved['claimTime'] || saved['claimExtraordinary']);
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
                const formData = new FormData(document.getElementById('formsByDanForm'));
                fetch(document.getElementById('forms-by-dan-webhook-url').textContent.trim(), {
                    method: 'POST',
                    body: formData
                }).then(() => alert('Submitted')).catch(() => alert('Submission failed.'));
            };

            attachConditionalHandlers();
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
    });
    </script>
    <?php
    return ob_get_clean();
}
