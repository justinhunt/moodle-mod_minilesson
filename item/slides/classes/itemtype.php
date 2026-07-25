<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace minilessonitem_slides;

use mod_minilesson\local\itemtype\item;

use mod_minilesson\constants;
use moodle_url;

/**
 * Renderable class for a slides item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item {
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_CONTENT];

    /** @var bool this item type produces no grade/result. */
    public $gradeable = false;

    /**
     * Load the (locally shipped) reveal.js CSS. The theme CSS is loaded lazily by this item's JS
     * (amd/src/reveal.js) from the same local css directory.
     *
     * @param \moodle_page $page The page to add requirements to.
     * @return void
     */
    public static function page_requirements(\moodle_page $page) {
        $page->requires->css(new \moodle_url('/mod/minilesson/item/slides/css/reveal.min.css'));
    }

    /**
     * Render a preview of the slides from unsaved authoring form data. Images are still in the
     * draft file area at this point, so their filenames are rewritten to draft file URLs.
     *
     * @param array $formdata The parsed authoring form data.
     * @return string HTML fragment
     */
    public static function render_preview($formdata) {
        global $OUTPUT;

        $imageserveurl = moodle_url::make_draftfile_url(
            $formdata[constants::FILEANSWER . '1'],
            '/',
            '{filename}'
        );

        // Rewrite a relative filename in the matched markup to its draft file URL, leaving
        // anything that is already an absolute URL alone.
        $rewritefilename = function ($matches) use ($imageserveurl) {
            $filename = trim($matches['filename']);

            // Skip if it's already a full URL (http/https).
            if (preg_match('/^https?:\/\//', $filename)) {
                return $matches[0];
            }

            // Add base path (and escape spaces if needed).
            $newsrc = str_replace('{filename}', rawurlencode($filename), urldecode($imageserveurl));

            // Replace only the filename part.
            return str_replace($filename, $newsrc, $matches[0]);
        };

        $testitem = new \stdClass();
        $testitem->inajax = AJAX_SCRIPT;
        $slidescontenttype = $formdata[self::CONTENTTYPE] ?? self::CONTENTTYPE_MARKDOWN;
        $slidescontent = $formdata[self::MARKDOWN];

        if ($slidescontenttype == self::CONTENTTYPE_MARKDOWN) {
            $testitem->slidesmarkdown = preg_replace_callback(
                '/!\[[^\]]*\]\((?<filename>.*?)(?=\"|\))(?<optionalpart>\".*\")?\)/',
                $rewritefilename,
                $slidescontent
            );

            // Standardize markdown output, applying layout formatting, before rendering the preview template.
            $testitem->slidesmarkdown = self::sanitize_markdown($testitem->slidesmarkdown);
            $testitem->slidesmarkdown = self::process_layout_markdown($testitem->slidesmarkdown);
        } else {
            // HTML mode.
            $testitem->slidesmarkdown = preg_replace_callback(
                '/(src|data-background-image)=\"(?<filename>.*?)\"/',
                $rewritefilename,
                $slidescontent
            );
        }

        $testitem->slidescontenttype = $slidescontenttype;
        $testitem->ishtml = $slidescontenttype == self::CONTENTTYPE_HTML;
        $testitem->selectedtheme = $formdata[self::SLIDETHEME];
        $testitem->selectedfontsize = $formdata[self::SLIDEFONTSIZE];

        return $OUTPUT->render_from_template(self::get_component() . '/slidesinner', $testitem);
    }

    public const MARKDOWN = 'customtext1';
    public const FULLSCREEN = 'customint1';
    public const MARKDOWN_DEFAULT = "# Slide 1 Title\n\nYour content here. Use markdown syntax to format text and add images.\n\n---\n\n# Slide 2 Title\n\nMore content here. You can add as many slides as you need.\n";
    public const SLIDETHEME = 'customtext2';
    public const SLIDEFONTSIZE = 'customtext3';
    public const FILES = 'customfile1';
    public const CONTENTTYPE = 'customint2';
    public const CONTENTTYPE_MARKDOWN = 0;
    public const CONTENTTYPE_HTML = 1;

    // the item type
    public function from_record($itemrecord, $moduleinstance = false, $context = false) {
        parent::from_record($itemrecord, $moduleinstance, $context);
        $this->filemanageroptions['maxfiles'] = -1;
    }

    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output) {

        $testitem = parent::export_for_template($output);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);
        $testitem->region = $this->region;

        $imageserveurl = moodle_url::make_pluginfile_url(
            $this->context->id,
            constants::M_COMPONENT,
            self::FILES,
            $this->itemrecord->id,
            '/',
            '{filename}'
        );

        // Fetch all filenames in file area.
        $fs = get_file_storage();

        // Get all files in that file area.
        $files = $fs->get_area_files(
            $this->context->id,
            constants::M_COMPONENT,
            self::FILES,
            $this->itemrecord->id,
            'filepath, filename',
            false
        );

        // Extract the filenames into an array.
        $filenames = [];
        foreach ($files as $file) {
            $filenames[] = $file->get_filename();
        }

        // Process images in slides area.
        $slidescontent = $this->itemrecord->{self::MARKDOWN};
        $slidescontenttype = $this->itemrecord->{self::CONTENTTYPE} ?? self::CONTENTTYPE_MARKDOWN;

        if ($slidescontenttype == self::CONTENTTYPE_MARKDOWN) {
            $slidescontent = preg_replace_callback(
                '/!\[[^\]]*\]\((?<filename>.*?)(?=\"|\))(?<optionalpart>\".*\")?\)/',
                function ($matches) use ($imageserveurl, $filenames) {
                    $filename = trim($matches['filename']);

                    // Skip if it's already a full URL (http/https).
                    if (preg_match('/^https?:\/\//', $filename)) {
                        return $matches[0];
                    }

                    // Skip if the file does not exist in the file area.
                    if (!in_array($filename, $filenames)) {
                        return $matches[0];
                    }

                    // Add base path (and escape spaces if needed)
                    $newsrc = str_replace('{filename}', rawurlencode($filename), urldecode($imageserveurl));

                    // Replace only the filename part
                    return str_replace($filename, $newsrc, $matches[0]);
                },
                $slidescontent
            );

            // Weird characters can break things like tables, so clean it a bit.
            $slidescontent = self::sanitize_markdown($slidescontent);

            // Process markdown layouts (e.g. ::: 2cols -> <div class="ml_slides_2cols">)
            $slidescontent = self::process_layout_markdown($slidescontent);
        } else {
            // HTML mode.
            $slidescontent = preg_replace_callback(
                '/(src|data-background-image)=\"(?<filename>.*?)\"/',
                function ($matches) use ($imageserveurl, $filenames) {
                    $filename = trim($matches['filename']);

                    // Skip if it's already a full URL (http/https).
                    if (preg_match('/^https?:\/\//', $filename)) {
                        return $matches[0];
                    }

                    // Skip if the file does not exist in the file area.
                    if (!in_array($filename, $filenames)) {
                        return $matches[0];
                    }

                    // Add base path (and escape spaces if needed)
                    $newsrc = str_replace('{filename}', rawurlencode($filename), urldecode($imageserveurl));

                    // Replace only the filename part
                    return str_replace($filename, $newsrc, $matches[0]);
                },
                $slidescontent
            );
        }

        // Set it to output.
        $testitem->slidesmarkdown = $slidescontent;
        $testitem->slidescontenttype = $slidescontenttype;
        $testitem->ishtml = $slidescontenttype == self::CONTENTTYPE_HTML;

        $testitem->selectedtheme = $this->itemrecord->{self::SLIDETHEME};
        $testitem->selectedfontsize = $this->itemrecord->{self::SLIDEFONTSIZE};
        $testitem->fullscreen = $this->itemrecord->{self::FULLSCREEN};

        return $testitem;
    }

    public static function sanitize_markdown($md) {

        // Remove zero-width chars.
        $md = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $md);

        // Replace NBSP with normal space.
        $md = str_replace(["\xC2\xA0", "\xE2\x80\xAF"], " ", $md);

        // Trim trailing spaces and tabs but preserve newlines.
        $md = preg_replace('/[ \t]+$/m', '', $md);

        return $md;
    }

    /**
     * Replaces ::: class syntax with divs to create grid layouts
     */
    public static function process_layout_markdown($md) {
        // Replace opening tags. Use [ \t]* so we don't accidentally consume newlines and merge previous slides together!
        // Tolerate \r before end-of-line in case of Windows CRLF line endings.
        // Inject \n\n around the block so that Marked.js isolates the HTML elements and resumes standard markdown-parsing inside them.
        $md = preg_replace('/^:::[ \t]*([a-zA-Z0-9_\-]+)[ \t]*\r?$/m', "\n\n<div class=\"ml_slides_$1\">\n\n", $md);

        // Replace closing tags
        $md = preg_replace('/^:::[ \t]*\r?$/m', "\n\n</div>\n\n", $md);

        return $md;
    }

    public static function validate_import($newrecord, $cm) {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

        if (trim((string) $newrecord->{self::MARKDOWN}) == '') {
            $error->col = self::MARKDOWN;
            $error->message = get_string('error:emptyfield', constants::M_COMPONENT);
            return $error;
        }

        $allowedcontenttypes = [self::CONTENTTYPE_MARKDOWN, self::CONTENTTYPE_HTML];
        if (isset($newrecord->{self::CONTENTTYPE}) && !in_array((int) $newrecord->{self::CONTENTTYPE}, $allowedcontenttypes)) {
            $error->col = self::CONTENTTYPE;
            $error->message = get_string(
                'error:invalidoptionvalue',
                constants::M_COMPONENT,
                ['value' => $newrecord->{self::CONTENTTYPE}, 'allowed' => implode(',', $allowedcontenttypes)]
            );
            return $error;
        }

        // Return false to indicate no error.
        return false;
    }

    /**
     * When and why to choose this item type (agent-facing, used by the aigen web services).
     *
     * @return string
     */
    public static function aigen_fetch_usage() {
        return 'A slide presentation (rendered with Reveal.js) the learner clicks through, with no question and '
            . 'no grade. Use it to present structured content - an explanation, a story, a picture sequence or '
            . 'a summary - when one page is not enough. For a single simple content screen use page instead.';
    }

    /**
     * The agent-facing import field spec for slides. Option meanings mirror the authoring form
     * (see custom_definition in itemform.php); keep the two in sync when changing form options.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions',
            'timelimit', 'layout']);
        $fields['type']['example'] = 'slides';

        $ownfields = [
            'slidesmarkdown' => [
                'description' => 'The slide content. In markdown mode (default): start a new horizontal slide '
                    . 'with "---" and a vertical sub-slide with "--", each alone on a LEFT-ALIGNED line with blank '
                    . 'lines around it (the markdown is sensitive to indentation and newlines). Use # / ## / ### '
                    . 'headings, **bold**, _italic_, - bullets, 1. numbered lists, pipe tables, "***" or "-----" '
                    . 'for a horizontal rule, and images as ![alt](filename.png) where the file is uploaded to '
                    . 'the ' . self::FILES . ' file area under that exact filename. Multi-column layouts: '
                    . '"::: 2cols", "::: 3cols", "::: 4cols", "::: 2x2grid". '
                    . 'In HTML mode (slidescontenttype=1): supply Reveal.js HTML with one <section> per slide.',
                'example' => "# Slide 1 Title\n\nSome content.\n\n---\n\n# Slide 2 Title\n\n![A picture](1.png)",
            ],
            'slidescontenttype' => [
                'description' => 'The format of the slidesmarkdown content.',
                'options' => [
                    ['value' => (string) self::CONTENTTYPE_MARKDOWN, 'meaning' => 'Markdown (default)'],
                    ['value' => (string) self::CONTENTTYPE_HTML, 'meaning' => 'Reveal.js HTML, one <section> element per slide'],
                ],
            ],
            'slidestheme' => [
                'description' => 'The Reveal.js display theme.',
                'options' => [
                    ['value' => 'beige', 'meaning' => 'Light beige background'],
                    ['value' => 'black', 'meaning' => 'Dark background (default)'],
                    ['value' => 'black-contrast', 'meaning' => 'Dark, high contrast'],
                    ['value' => 'blood', 'meaning' => 'Dark with red accents'],
                    ['value' => 'dracula', 'meaning' => 'Dark purple palette'],
                    ['value' => 'league', 'meaning' => 'Dark grey'],
                    ['value' => 'moon', 'meaning' => 'Dark blue'],
                    ['value' => 'night', 'meaning' => 'Black with bright text'],
                    ['value' => 'serif', 'meaning' => 'Light, serif fonts'],
                    ['value' => 'simple', 'meaning' => 'Plain white, minimal'],
                    ['value' => 'sky', 'meaning' => 'Light blue'],
                    ['value' => 'solarized', 'meaning' => 'Cream/solarized palette'],
                    ['value' => 'white', 'meaning' => 'White background'],
                    ['value' => 'white_contrast_compact_verbatim_headers', 'meaning' => 'White, compact headers'],
                    ['value' => 'white-contrast', 'meaning' => 'White, high contrast'],
                ],
            ],
            'slidesfontsize' => [
                'description' => 'The base font size of the slide text, in pixels.',
                'options' => [
                    ['value' => '16', 'meaning' => 'Smallest'],
                    ['value' => '24', 'meaning' => 'Smaller'],
                    ['value' => '32', 'meaning' => 'Small (default)'],
                    ['value' => '36', 'meaning' => 'Standard'],
                    ['value' => '40', 'meaning' => 'Large'],
                    ['value' => '44', 'meaning' => 'Larger'],
                    ['value' => '48', 'meaning' => 'Largest'],
                ],
            ],
            'slidesfullscreen' => [
                'description' => 'Whether the learner can view the slides full screen.',
                'options' => [
                    ['value' => '0', 'meaning' => 'No full screen button (default)'],
                    ['value' => '1', 'meaning' => 'Show a full screen button'],
                ],
            ],
        ];
        foreach ($ownfields as $jsonname => $overlay) {
            $fields[$jsonname] = static::aigen_seed_field_spec($jsonname, $overlay);
        }

        $fields['filesid'] = [
            'jsonname' => 'filesid',
            'type' => 'int',
            'required' => false,
            'default' => '',
            'description' => 'Links this item to its entry in the top level "files" object of the payload. '
                . 'Only needed when the slides reference uploaded images/audio/video.',
            'example' => '1',
        ];

        return [
            'usage' => 'Compose one item object per presentation. Keep each slide short - a heading and a few '
                . 'bullets or one image; use more slides rather than crowded ones. Reference uploaded images '
                . 'by their exact filename, e.g. ![Koala](2.png) with 2.png in the ' . self::FILES . ' file area. '
                . 'Prefer markdown mode; use HTML mode only for layouts markdown cannot express.',
            'fields' => array_values($fields),
            'fileareas' => [
                [
                    'filearea' => self::FILES,
                    'description' => 'Images (or audio/video) referenced from the slide content by filename.',
                    'filenames' => 'Any filename; it must exactly match the name used in the slide content, '
                        . 'e.g. ![Koala](2.png) needs a file named "2.png".',
                ],
            ],
            'example' => [
                'items' => [
                    [
                        'type' => 'slides',
                        'name' => 'Native animals of Australia',
                        'instructions' => 'Click > or < to go forward or backward through the slides.',
                        'slidesmarkdown' => "# Native Animals of Australia\n\nA short picture tour\n\n---\n\n"
                            . "# Koala in a Tree\n\n![Koala](1.png)\n\n---\n\n"
                            . "# Facts\n\n- Koalas sleep up to 20 hours a day\n- They eat eucalyptus leaves",
                        'slidestheme' => 'beige',
                        'slidesfontsize' => '36',
                        'slidesfullscreen' => 1,
                        'filesid' => 1,
                    ],
                ],
                'files' => [
                    '1' => [
                        self::FILES => [
                            '1.png' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJ'
                                . 'AAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
                        ],
                    ],
                ],
            ],
        ];
    }
    /*
     * This is for use with importing, telling import class each column's is, db col name, minilesson specific data type
     */
    public static function get_keycolumns() {
        // Get the basic key columns and customize a little for instances of this item type.
        $keycols = parent::get_keycolumns();
        $keycols['text1'] = ['jsonname' => 'slidesmarkdown', 'type' => 'string', 'optional' => false, 'default' => [], 'dbname' => self::MARKDOWN];
        $keycols['text2'] = ['jsonname' => 'slidestheme', 'type' => 'string', 'optional' => false, 'default' => 'black', 'dbname' => self::SLIDETHEME];
        $keycols['text3'] = ['jsonname' => 'slidesfontsize', 'type' => 'string', 'optional' => false, 'default' => '32', 'dbname' => self::SLIDEFONTSIZE];
        $keycols['int1'] = ['jsonname' => 'slidesfullscreen', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => self::FULLSCREEN];
        $keycols['int2'] = ['jsonname' => 'slidescontenttype', 'type' => 'int', 'optional' => true, 'default' => 0, 'dbname' => self::CONTENTTYPE];
        $keycols[self::FILES] = ['jsonname' => self::FILES, 'type' => 'anonymousfile', 'optional' => true, 'default' => null, 'dbname' => false];

        return $keycols;
    }

    /*
    This function return the prompt that the generate method requires.
    */
    public static function aigen_fetch_prompt($itemtemplate, $generatemethod) {
        switch ($generatemethod) {
            case 'extract':
                $prompt = "Create a reveal.js presentation in markdown format to summarize and explain the following topic: [{text}]";
                break;

            case 'reuse':
                // This is a special case where we reuse the existing data, so we do not need a prompt.
                // We don't call AI. So will just return an empty string.
                $prompt = "";
                break;

            case 'generate':
            default:
                $prompt = "Create a reveal.js presentation in markdown format to summarize and explain the following topic: [{text}]";
                break;
        }
        return $prompt;
    }

    /**
     * Builds the prompt for the AI helper in the code editor.
     *
     * @param string $language The language of the code (e.g., 'markdown', 'html').
     * @param string $prompt The user's prompt.
     * @param string $currentcode The current code in the editor.
     * @return string The full prompt for the AI.
     */
    public static function codeeditor_build_prompt($language, $prompt, $currentcode) {
        $fullprompt = "You are an assistant helping a teacher create or edit educational slides for Reveal.js." . PHP_EOL;
        $fullprompt .= "The format is: " . strtoupper($language) . PHP_EOL . PHP_EOL;

        if ($language == 'html') {
            $fullprompt .= "### HTML SLIDES CHEAT SHEET ###" . PHP_EOL;
            $fullprompt .= "- Slide Separator: Use <section> tags to wrap each slide." . PHP_EOL;
            $fullprompt .= "- Headings: Use <h1>, <h2>, etc. (NEVER use Markdown #)." . PHP_EOL;
            $fullprompt .= "- Backgrounds: <section data-background-color=\"#ff0000\">" . PHP_EOL;
            $fullprompt .= "- Fragments: <p class=\"fragment\">This appears on click</p>" . PHP_EOL;
            $fullprompt .= "- Layouts: Use <div class=\"ml_slides_2cols\"> for columns." . PHP_EOL;
            $fullprompt .= "- Images: <img src=\"filename.jpg\" alt=\"description\"> (No path needed, just filename)." . PHP_EOL;
            $fullprompt .= "- Content: Use standard tags like <ul>, <li>, <p>, <strong>." . PHP_EOL . PHP_EOL;
        } else {
            $fullprompt .= "### MARKDOWN SLIDES CHEAT SHEET ###" . PHP_EOL;
            $fullprompt .= "- Slide Separator (Horizontal): '---' on a new line." . PHP_EOL;
            $fullprompt .= "- Slide Separator (Vertical): '--' on a new line." . PHP_EOL;
            $fullprompt .= "- Headings: # Title, ## Subtitle." . PHP_EOL;
            $fullprompt .= "- Layouts: ::: 2cols, ::: 3cols, ::: 4cols, ::: 2x2grid. Wrap columns in ::: col." . PHP_EOL;
            $fullprompt .= "- Images: ![alt](filename.jpg) (No path needed, just filename)." . PHP_EOL;
            $fullprompt .= "- Slide Attributes: <!-- .slide: data-background=\"#ff0000\" -->" . PHP_EOL;
            $fullprompt .= "- Element Attributes: <!-- .element: class=\"fragment\" --> (for reveal-on-click)." . PHP_EOL;
            $fullprompt .= "- Horizontal Line: '***' or '-----'." . PHP_EOL . PHP_EOL;
        }

        if (!empty($currentcode)) {
            $fullprompt .= "The existing slide code is:" .  PHP_EOL . PHP_EOL . $currentcode . PHP_EOL .  PHP_EOL;
            $fullprompt .= "Please modify the existing code based on this instruction: " . PHP_EOL . $prompt . PHP_EOL;
        } else {
            $fullprompt .= "Please create new slides based on this instruction: " . PHP_EOL . $prompt . PHP_EOL;
        }
        $fullprompt .= "Only return the code itself, without any explanations or markdown code blocks (like ```html) unless they are part of the content.";
        return $fullprompt;
    }
}
