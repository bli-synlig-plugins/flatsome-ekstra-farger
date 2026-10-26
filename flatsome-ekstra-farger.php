<?php
/**
 * Plugin Name: Flatsome ekstra farger
 * Plugin URI: https://wordpress-plugins-docs.vercel.app/plugins/flatsome-ekstra-farger
 * Description: Legg til egne farger og knappestiler i Flatsome. Farger som CSS-variabler og fargeprøver, knapper som valg i UX Builder.
 * Version: 1.0.0
 * Author: Benjamin Pedersen
 * Text Domain: flatsome-ekstra-farger
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FEF_VERSION', '1.0.0');
define('FEF_FILE', __FILE__);
define('FEF_DIR', plugin_dir_path(__FILE__));
define('FEF_URL', plugin_dir_url(__FILE__));

const FEF_OPTION = 'fef_colors';
const FEF_BUTTON_OPTION = 'fef_button_styles';

add_action('ux_builder_enqueue_scripts', 'fef_enqueue_builder_editor_styles');
function fef_enqueue_builder_editor_styles($context) {
    if ($context !== 'editor') {
        return;
    }

    $css = file_get_contents(FEF_DIR . 'assets/editor.css');
    if (!$css) {
        return;
    }

    wp_add_inline_style('ux-builder-core', $css);
}

function fef_reserved_color_ids() {
    return array('primary', 'secondary', 'success', 'alert', 'white');
}

function fef_reserved_button_ids() {
    return array('primary', 'secondary', 'success', 'alert', 'white');
}

function fef_get_definitions() {
    $colors = get_option(FEF_OPTION, array());
    return is_array($colors) ? $colors : array();
}

function fef_get_button_definitions() {
    $styles = get_option(FEF_BUTTON_OPTION, array());
    return is_array($styles) ? $styles : array();
}

function fef_theme_mod_key($id) {
    return 'fef_color_' . $id;
}

function fef_variable_name($id) {
    return '--fs-color-' . $id;
}

function fef_button_class($id) {
    return 'fef-btn-' . $id;
}

function fef_button_bg_var($id) {
    return '--fef-btn-' . $id . '-bg';
}

function fef_button_fg_var($id) {
    return '--fef-btn-' . $id . '-fg';
}

function fef_normalize_color($value) {
    if (!is_string($value)) {
        return '';
    }

    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $hex = sanitize_hex_color($value);
    if ($hex) {
        return $hex;
    }

    if (preg_match('/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value, $matches)) {
        $raw = strtolower($matches[1]);
        if (strlen($raw) === 3) {
            $raw = $raw[0] . $raw[0] . $raw[1] . $raw[1] . $raw[2] . $raw[2];
        }
        return '#' . $raw;
    }

    if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*[\d.]+\s*)?\)$/i', $value, $matches)) {
        $r = max(0, min(255, (int) $matches[1]));
        $g = max(0, min(255, (int) $matches[2]));
        $b = max(0, min(255, (int) $matches[3]));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    return '';
}

function fef_color_value($id) {
    return fef_normalize_color(get_theme_mod(fef_theme_mod_key($id), ''));
}

function fef_unique_id($id, $label, array &$used, array $reserved, $label_for_errors, array &$errors) {
    $id = sanitize_title($id);
    if ($id === '') {
        $id = sanitize_title($label);
    }

    if ($id === '') {
        $errors[] = sprintf(
            __('«%s» må ha et navn som kan brukes i en CSS-klasse.', 'flatsome-ekstra-farger'),
            $label_for_errors
        );
        return '';
    }

    if (in_array($id, $reserved, true)) {
        $errors[] = sprintf(
            __('«%s» er allerede en Flatsome-farge.', 'flatsome-ekstra-farger'),
            $label_for_errors
        );
        return '';
    }

    $base = $id;
    $suffix = 2;
    while (in_array($id, $used, true)) {
        $id = $base . '-' . $suffix;
        $suffix++;
    }
    $used[] = $id;

    return $id;
}

function fef_active_colors() {
    $active = array();

    foreach (fef_get_definitions() as $color) {
        if (empty($color['id']) || empty($color['label'])) {
            continue;
        }

        $value = fef_color_value($color['id']);
        if ($value === '' && !empty($color['color'])) {
            $value = fef_normalize_color($color['color']);
        }
        if ($value === '') {
            continue;
        }

        $active[] = array(
            'id' => $color['id'],
            'label' => $color['label'],
            'color' => $value,
        );
    }

    return $active;
}

function fef_active_button_styles() {
    $active = array();

    foreach (fef_get_button_definitions() as $style) {
        if (empty($style['id']) || empty($style['label'])) {
            continue;
        }

        $background = !empty($style['background']) ? fef_normalize_color($style['background']) : '';
        $foreground = !empty($style['foreground']) ? fef_normalize_color($style['foreground']) : '';
        if ($background === '' || $foreground === '') {
            continue;
        }

        $active[] = array(
            'id' => $style['id'],
            'label' => $style['label'],
            'background' => $background,
            'foreground' => $foreground,
            'class' => fef_button_class($style['id']),
        );
    }

    return $active;
}

add_action('admin_menu', 'fef_register_admin_page');
function fef_register_admin_page() {
    add_theme_page(
        __('Ekstra farger', 'flatsome-ekstra-farger'),
        __('Ekstra farger', 'flatsome-ekstra-farger'),
        'edit_theme_options',
        'flatsome-ekstra-farger',
        'fef_render_admin_page'
    );
}

add_action('admin_init', 'fef_handle_admin_save');
function fef_handle_admin_save() {
    if (!isset($_POST['fef_save_colors'])) {
        return;
    }

    if (!current_user_can('edit_theme_options')) {
        return;
    }

    check_admin_referer('fef_save_colors');

    $posted_colors = isset($_POST['fef_colors']) && is_array($_POST['fef_colors']) ? wp_unslash($_POST['fef_colors']) : array();
    $posted_buttons = isset($_POST['fef_buttons']) && is_array($_POST['fef_buttons']) ? wp_unslash($_POST['fef_buttons']) : array();

    $color_result = fef_sanitize_posted_colors($posted_colors);
    $button_result = fef_sanitize_posted_buttons($posted_buttons);
    $previous_colors = fef_get_definitions();

    update_option(FEF_OPTION, $color_result['colors']);
    update_option(FEF_BUTTON_OPTION, $button_result['styles']);

    $kept_ids = array();
    foreach ($color_result['colors'] as $color) {
        $kept_ids[] = $color['id'];
        set_theme_mod(fef_theme_mod_key($color['id']), $color['color']);
    }

    foreach ($previous_colors as $color) {
        if (!empty($color['id']) && !in_array($color['id'], $kept_ids, true)) {
            remove_theme_mod(fef_theme_mod_key($color['id']));
        }
    }

    $errors = array_merge($color_result['errors'], $button_result['errors']);
    if (!empty($errors)) {
        set_transient('fef_admin_errors_' . get_current_user_id(), $errors, 30);
    }

    wp_safe_redirect(add_query_arg(
        array(
            'page' => 'flatsome-ekstra-farger',
            'updated' => '1',
        ),
        admin_url('themes.php')
    ));
    exit;
}

function fef_sanitize_posted_colors($posted) {
    $colors = array();
    $errors = array();
    $used = array();

    foreach ($posted as $row) {
        if (!is_array($row)) {
            continue;
        }

        $label = isset($row['label']) ? sanitize_text_field($row['label']) : '';
        if ($label === '') {
            continue;
        }

        $id = fef_unique_id(
            isset($row['id']) ? $row['id'] : '',
            $label,
            $used,
            fef_reserved_color_ids(),
            $label,
            $errors
        );
        if ($id === '') {
            continue;
        }

        $color = isset($row['color']) ? fef_normalize_color($row['color']) : '';
        if ($color === '') {
            $errors[] = sprintf(__('«%s» har en ugyldig fargekode. Bruk f.eks. #f8b334 eller rgb(248,179,52).', 'flatsome-ekstra-farger'), $label);
            continue;
        }

        $colors[] = array(
            'id' => $id,
            'label' => $label,
            'color' => $color,
        );
    }

    return array(
        'colors' => $colors,
        'errors' => $errors,
    );
}

function fef_sanitize_posted_buttons($posted) {
    $styles = array();
    $errors = array();
    $used = array();

    foreach ($posted as $row) {
        if (!is_array($row)) {
            continue;
        }

        $label = isset($row['label']) ? sanitize_text_field($row['label']) : '';
        if ($label === '') {
            continue;
        }

        $id = fef_unique_id(
            isset($row['id']) ? $row['id'] : '',
            $label,
            $used,
            fef_reserved_button_ids(),
            $label,
            $errors
        );
        if ($id === '') {
            continue;
        }

        $background = isset($row['background']) ? fef_normalize_color($row['background']) : '';
        $foreground = isset($row['foreground']) ? fef_normalize_color($row['foreground']) : '';

        if ($background === '' || $foreground === '') {
            $errors[] = sprintf(
                __('«%s» må ha gyldig bakgrunns- og tekstfarge.', 'flatsome-ekstra-farger'),
                $label
            );
            continue;
        }

        $styles[] = array(
            'id' => $id,
            'label' => $label,
            'background' => $background,
            'foreground' => $foreground,
        );
    }

    return array(
        'styles' => $styles,
        'errors' => $errors,
    );
}

function fef_render_admin_page() {
    if (!current_user_can('edit_theme_options')) {
        return;
    }

    $colors = fef_get_definitions();
    $buttons = fef_get_button_definitions();
    $errors = get_transient('fef_admin_errors_' . get_current_user_id());
    if ($errors) {
        delete_transient('fef_admin_errors_' . get_current_user_id());
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Ekstra farger', 'flatsome-ekstra-farger'); ?></h1>

        <?php if (!empty($_GET['updated'])) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Endringene er lagret.', 'flatsome-ekstra-farger'); ?></p></div>
        <?php endif; ?>

        <?php if (!empty($errors) && is_array($errors)) : ?>
            <div class="notice notice-error"><p><?php echo esc_html(implode(' ', $errors)); ?></p></div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('fef_save_colors'); ?>

            <h2><?php esc_html_e('Farger', 'flatsome-ekstra-farger'); ?></h2>
            <p><?php esc_html_e('Viser under Style → Colors i Customizer, som CSS-variabler og som fargeprøver i UX Builder.', 'flatsome-ekstra-farger'); ?></p>
            <table class="widefat striped fef-repeater-table" id="fef-colors" data-fef-next="<?php echo esc_attr(count($colors)); ?>">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Navn', 'flatsome-ekstra-farger'); ?></th>
                        <th><?php esc_html_e('Variabel', 'flatsome-ekstra-farger'); ?></th>
                        <th><?php esc_html_e('Farge', 'flatsome-ekstra-farger'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($colors as $index => $color) : ?>
                        <?php
                        $value = fef_color_value($color['id']);
                        if ($value === '' && !empty($color['color'])) {
                            $value = fef_normalize_color($color['color']);
                        }
                        if ($value === '') {
                            $value = '#000000';
                        }
                        fef_render_color_row($index, $color['id'], $color['label'], $value);
                        ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p>
                <button type="button" class="button" data-fef-add="fef-colors" data-fef-template="fef-color-template"><?php esc_html_e('Legg til farge', 'flatsome-ekstra-farger'); ?></button>
            </p>

            <hr style="margin: 32px 0;" />

            <h2><?php esc_html_e('Knappestiler', 'flatsome-ekstra-farger'); ?></h2>
            <p><?php esc_html_e('Legges til under Color i UX Builder for knapper. Hver stil får bakgrunn og tekstfarge, og fungerer også med outline.', 'flatsome-ekstra-farger'); ?></p>
            <table class="widefat striped fef-repeater-table" id="fef-buttons" data-fef-next="<?php echo esc_attr(count($buttons)); ?>">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Navn', 'flatsome-ekstra-farger'); ?></th>
                        <th><?php esc_html_e('Klasse', 'flatsome-ekstra-farger'); ?></th>
                        <th><?php esc_html_e('Bakgrunn', 'flatsome-ekstra-farger'); ?></th>
                        <th><?php esc_html_e('Tekst', 'flatsome-ekstra-farger'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($buttons as $index => $style) : ?>
                        <?php
                        $background = !empty($style['background']) ? fef_normalize_color($style['background']) : '#000000';
                        $foreground = !empty($style['foreground']) ? fef_normalize_color($style['foreground']) : '#ffffff';
                        if ($background === '') {
                            $background = '#000000';
                        }
                        if ($foreground === '') {
                            $foreground = '#ffffff';
                        }
                        fef_render_button_row($index, $style['id'], $style['label'], $background, $foreground);
                        ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p>
                <button type="button" class="button" data-fef-add="fef-buttons" data-fef-template="fef-button-template"><?php esc_html_e('Legg til knappestil', 'flatsome-ekstra-farger'); ?></button>
            </p>

            <?php submit_button(__('Lagre', 'flatsome-ekstra-farger'), 'primary', 'fef_save_colors'); ?>
        </form>
    </div>

    <template id="fef-color-template">
        <?php fef_render_color_row('__INDEX__', '', '', '#000000'); ?>
    </template>
    <template id="fef-button-template">
        <?php fef_render_button_row('__INDEX__', '', '', '#000000', '#ffffff'); ?>
    </template>

    <style>
        .fef-color-fields {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .fef-color-fields input[type="color"] {
            width: 40px;
            height: 32px;
            padding: 0;
            border: 1px solid #8c8f94;
            background: none;
            cursor: pointer;
        }
        .fef-color-fields .fef-color-text {
            width: 120px;
            font-family: Consolas, Monaco, monospace;
        }
        .fef-preview {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 72px;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            line-height: 1;
        }
    </style>
    <script>
    (function () {
        function normalizeColor(value) {
            if (!value) {
                return '';
            }

            value = String(value).trim();

            var hexMatch = value.match(/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/);
            if (hexMatch) {
                var raw = hexMatch[1].toLowerCase();
                if (raw.length === 3) {
                    raw = raw[0] + raw[0] + raw[1] + raw[1] + raw[2] + raw[2];
                }
                return '#' + raw;
            }

            var rgbMatch = value.match(/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*[\d.]+\s*)?\)$/i);
            if (rgbMatch) {
                var toHex = function (part) {
                    var number = Math.max(0, Math.min(255, parseInt(part, 10)));
                    return ('0' + number.toString(16)).slice(-2);
                };
                return '#' + toHex(rgbMatch[1]) + toHex(rgbMatch[2]) + toHex(rgbMatch[3]);
            }

            return '';
        }

        function syncFromPicker(picker) {
            var text = picker.parentElement.querySelector('.fef-color-text');
            if (text) {
                text.value = picker.value;
            }
            updatePreview(picker.closest('tr'));
        }

        function syncFromText(text) {
            var picker = text.parentElement.querySelector('.fef-color-picker');
            var normalized = normalizeColor(text.value);
            if (!normalized) {
                return;
            }

            text.value = normalized;
            if (picker) {
                picker.value = normalized;
            }
            updatePreview(text.closest('tr'));
        }

        function updatePreview(row) {
            if (!row) {
                return;
            }
            var preview = row.querySelector('.fef-preview');
            if (!preview) {
                return;
            }
            var bg = row.querySelector('[data-fef-role="background"] .fef-color-text');
            var fg = row.querySelector('[data-fef-role="foreground"] .fef-color-text');
            if (!bg || !fg) {
                return;
            }
            preview.style.backgroundColor = bg.value;
            preview.style.color = fg.value;
        }

        document.querySelectorAll('[data-fef-add]').forEach(function (button) {
            button.addEventListener('click', function () {
                var table = document.getElementById(button.getAttribute('data-fef-add'));
                var template = document.getElementById(button.getAttribute('data-fef-template'));
                if (!table || !template) {
                    return;
                }
                var nextIndex = parseInt(table.getAttribute('data-fef-next') || '0', 10);
                var html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
                table.querySelector('tbody').insertAdjacentHTML('beforeend', html);
                table.setAttribute('data-fef-next', String(nextIndex + 1));
            });
        });

        document.addEventListener('click', function (event) {
            var button = event.target.closest('.fef-remove');
            if (!button) {
                return;
            }
            var row = button.closest('tr');
            if (row) {
                row.remove();
            }
        });

        document.addEventListener('input', function (event) {
            if (event.target.classList.contains('fef-color-picker')) {
                syncFromPicker(event.target);
            }
            if (event.target.classList.contains('fef-color-text')) {
                syncFromText(event.target);
            }
        });

        document.addEventListener('change', function (event) {
            if (event.target.classList.contains('fef-color-text')) {
                syncFromText(event.target);
            }
        });

        document.addEventListener('paste', function (event) {
            if (!event.target.classList.contains('fef-color-text')) {
                return;
            }

            var pasted = (event.clipboardData || window.clipboardData).getData('text');
            var normalized = normalizeColor(pasted);
            if (!normalized) {
                return;
            }

            event.preventDefault();
            event.target.value = normalized;
            syncFromText(event.target);
        });
    }());
    </script>
    <?php
}

function fef_render_color_input($name, $value, $role = '') {
    ?>
    <div class="fef-color-fields"<?php echo $role !== '' ? ' data-fef-role="' . esc_attr($role) . '"' : ''; ?>>
        <input type="color" class="fef-color-picker" value="<?php echo esc_attr($value); ?>" aria-label="<?php esc_attr_e('Velg farge', 'flatsome-ekstra-farger'); ?>" />
        <input
            type="text"
            class="fef-color-text"
            name="<?php echo esc_attr($name); ?>"
            value="<?php echo esc_attr($value); ?>"
            placeholder="#f8b334"
            spellcheck="false"
            autocomplete="off"
        />
    </div>
    <?php
}

function fef_render_color_row($index, $id, $label, $color) {
    $variable = $id !== '' ? 'var(' . fef_variable_name($id) . ')' : __('Lages fra navnet når du lagrer', 'flatsome-ekstra-farger');
    ?>
    <tr>
        <td>
            <input type="hidden" name="fef_colors[<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($id); ?>" />
            <input type="text" class="regular-text" name="fef_colors[<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($label); ?>" />
        </td>
        <td><code><?php echo esc_html($variable); ?></code></td>
        <td><?php fef_render_color_input('fef_colors[' . $index . '][color]', $color); ?></td>
        <td><button type="button" class="button-link-delete fef-remove"><?php esc_html_e('Fjern', 'flatsome-ekstra-farger'); ?></button></td>
    </tr>
    <?php
}

function fef_render_button_row($index, $id, $label, $background, $foreground) {
    $class = $id !== '' ? fef_button_class($id) : __('Lages fra navnet når du lagrer', 'flatsome-ekstra-farger');
    ?>
    <tr>
        <td>
            <input type="hidden" name="fef_buttons[<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($id); ?>" />
            <input type="text" class="regular-text" name="fef_buttons[<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($label); ?>" />
        </td>
        <td><code><?php echo esc_html($class); ?></code></td>
        <td><?php fef_render_color_input('fef_buttons[' . $index . '][background]', $background, 'background'); ?></td>
        <td><?php fef_render_color_input('fef_buttons[' . $index . '][foreground]', $foreground, 'foreground'); ?></td>
        <td>
            <span class="fef-preview" style="background-color: <?php echo esc_attr($background); ?>; color: <?php echo esc_attr($foreground); ?>;">Aa</span>
            <button type="button" class="button-link-delete fef-remove" style="margin-left: 8px;"><?php esc_html_e('Fjern', 'flatsome-ekstra-farger'); ?></button>
        </td>
    </tr>
    <?php
}

add_action('init', 'fef_register_customizer_colors');
function fef_register_customizer_colors() {
    if (!is_customize_preview() || !class_exists('Flatsome_Option')) {
        return;
    }

    $colors = fef_active_colors();
    if (empty($colors)) {
        return;
    }

    Flatsome_Option::add_field('', array(
        'type' => 'custom',
        'settings' => 'fef_colors_divider',
        'label' => '',
        'section' => 'colors',
        'priority' => 180,
        'default' => '<div class="options-title-divider">Ekstra farger</div>',
    ));

    foreach ($colors as $index => $color) {
        Flatsome_Option::add_field('option', array(
            'type' => 'color',
            'settings' => fef_theme_mod_key($color['id']),
            'label' => $color['label'],
            'description' => 'Variabel: var(' . fef_variable_name($color['id']) . ')',
            'section' => 'colors',
            'default' => $color['color'],
            'priority' => 181 + $index,
            'transport' => 'refresh',
        ));
    }
}

add_action('wp_head', 'fef_print_styles', 20);
function fef_print_styles() {
    $colors = fef_active_colors();
    $buttons = fef_active_button_styles();
    if (empty($colors) && empty($buttons)) {
        return;
    }

    echo "<style id=\"fef-styles\">\n";

    if (!empty($colors) || !empty($buttons)) {
        echo ":root {\n";
        foreach ($colors as $color) {
            printf(
                "\t%s: %s;\n",
                esc_html(fef_variable_name($color['id'])),
                esc_html($color['color'])
            );
        }
        foreach ($buttons as $button) {
            printf(
                "\t%s: %s;\n\t%s: %s;\n",
                esc_html(fef_button_bg_var($button['id'])),
                esc_html($button['background']),
                esc_html(fef_button_fg_var($button['id'])),
                esc_html($button['foreground'])
            );
        }
        echo "}\n";
    }

    foreach ($buttons as $button) {
        $class = '.' . $button['class'];
        $bg = 'var(' . fef_button_bg_var($button['id']) . ')';
        $fg = 'var(' . fef_button_fg_var($button['id']) . ')';

        printf(
            "%1\$s{background-color:%2\$s;color:%3\$s;}\n" .
            "%1\$s.is-link,%1\$s.is-outline,%1\$s.is-underline{background-color:transparent;color:%2\$s;}\n" .
            "%1\$s.is-outline:hover{background-color:%2\$s;border-color:%2\$s;color:%3\$s;}\n",
            esc_html($class),
            esc_html($bg),
            esc_html($fg)
        );
    }

    echo "</style>\n";
}

add_filter('ux_builder_shortcode_data', 'fef_extend_ux_builder');
function fef_extend_ux_builder($data) {
    if (empty($data['options']) || !is_array($data['options'])) {
        return $data;
    }

    $data['options'] = fef_walk_options($data['options']);
    return $data;
}

function fef_walk_options($options) {
    foreach ($options as $key => $option) {
        if (!is_array($option)) {
            continue;
        }

        if (isset($option['type'], $option['helpers']) && $option['type'] === 'colorpicker' && fef_is_color_helper_list($option['helpers'])) {
            $option['helpers'] = fef_merge_color_helpers($option['helpers']);
        }

        if (isset($option['type'], $option['options']) && $option['type'] === 'select' && fef_is_button_color_options($option['options'])) {
            $option['options'] = fef_merge_button_color_options($option['options']);
        }

        foreach ($option as $child_key => $child) {
            if (is_array($child)) {
                $option[$child_key] = fef_walk_options($child);
            }
        }

        $options[$key] = $option;
    }

    return $options;
}

function fef_is_color_helper_list($helpers) {
    if (!is_array($helpers) || empty($helpers)) {
        return false;
    }

    $titles = array();
    foreach ($helpers as $helper) {
        if (!is_array($helper) || !isset($helper['title'], $helper['value'])) {
            return false;
        }
        $titles[] = $helper['title'];
    }

    return (bool) array_intersect($titles, array('Dark', 'White', 'Primary', 'Secondary', 'Success'));
}

function fef_merge_color_helpers($helpers) {
    $existing = array();
    foreach ($helpers as $helper) {
        if (isset($helper['title'])) {
            $existing[] = $helper['title'];
        }
    }

    foreach (fef_active_colors() as $color) {
        if (in_array($color['label'], $existing, true)) {
            continue;
        }

        $helpers[] = array(
            'title' => $color['label'],
            'value' => $color['color'],
        );
    }

    return $helpers;
}

function fef_is_button_color_options($options) {
    if (!is_array($options)) {
        return false;
    }

    $keys = array_keys($options);
    return in_array('primary', $keys, true)
        && in_array('secondary', $keys, true)
        && in_array('success', $keys, true)
        && in_array('alert', $keys, true);
}

function fef_merge_button_color_options($options) {
    foreach (fef_active_button_styles() as $button) {
        if (isset($options[$button['class']])) {
            continue;
        }
        $options[$button['class']] = $button['label'];
    }

    return $options;
}
