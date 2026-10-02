<?php

namespace PersianKit\Modules\Forms;

defined('ABSPATH') || exit;

/**
 * Buttons in Contact Form 7's form editor that insert the Iranian field
 * tags, such as [national_id* your-id], through CF7's own tag generator
 * dialog. Needs Contact Form 7 6.0 or newer; older versions get no buttons,
 * and the tags can still be typed.
 */
class Cf7TagGenerator
{
    public function register(): void
    {
        // After CF7 adds its own buttons, at priority 15 to 55.
        add_action('wpcf7_admin_init', [$this, 'addTagGenerators'], 60, 0);
    }

    public function addTagGenerators(): void
    {
        if (!class_exists('WPCF7_TagGenerator') || !class_exists('WPCF7_TagGeneratorGenerator')) {
            return;
        }

        $generator = \WPCF7_TagGenerator::get_instance();

        foreach (IranianFieldTypes::types() as $type) {
            $generator->add($type, IranianFieldTypes::label($type), [$this, 'render'], ['version' => '2']);
        }
    }

    /**
     * The dialog for one tag, laid out as CF7's own text field dialog.
     *
     * @param mixed                $contactForm WPCF7_ContactForm
     * @param array<string, mixed> $options     The panel's id (the tag type), title and content id.
     */
    public function render($contactForm, array $options): void
    {
        $type = (string) ($options['id'] ?? '');
        if (!IranianFieldTypes::exists($type)) {
            return;
        }

        $tgg = new \WPCF7_TagGeneratorGenerator((string) ($options['content'] ?? ''));
        ?>
        <header class="description-box">
            <h3>
                <?php
                echo esc_html(sprintf(
                    /* translators: %s: field type, such as "National ID". */
                    __('%s field form-tag generator', 'persian-kit'),
                    IranianFieldTypes::label($type)
                ));
                ?>
            </h3>
            <p>
                <?php
                echo esc_html(sprintf(
                    /* translators: %s: group name, "Iranian fields". */
                    __('%s are checked when the form is sent, and saved and emailed with English digits.', 'persian-kit'),
                    IranianFieldTypes::groupLabel()
                ));
                ?>
            </p>
        </header>

        <div class="control-box">
            <?php
            $tgg->print('field_type', [
                'with_required'  => true,
                'select_options' => [$type => IranianFieldTypes::label($type)],
            ]);
            $tgg->print('field_name');
            $tgg->print('class_attr');
            $tgg->print('default_value', ['with_placeholder' => true]);
            ?>
        </div>

        <footer class="insert-box">
            <?php
            $tgg->print('insert_box_content');
            $tgg->print('mail_tag_tip');
            ?>
        </footer>
        <?php
    }
}
