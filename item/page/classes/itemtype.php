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

namespace minilessonitem_page;

use mod_minilesson\local\itemtype\item;

use mod_minilesson\constants;

/**
 * Renderable class for a page item in a minilesson activity.
 *
 * @package    mod_minilesson
 * @copyright  2023 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class itemtype extends item
{
    /** @var array Language skills (or "content") this item type focuses on. */
    public static $skills = [constants::SKILL_CONTENT];

    /** @var bool this item type produces no grade/result. */
    public $gradeable = false;

    /**
     * A content page stays stacked vertically however much content it carries.
     *
     * @return bool
     */
    public function autolayout_prefers_vertical() {
        return true;
    }

    /**
     * When and why to choose this item type (agent-facing, used by the aigen web services).
     *
     * @return string
     */
    public static function aigen_fetch_usage() {
        return 'A display-only content page with no question and no grade. Use it for a lesson introduction, '
            . 'to present or explain new language (e.g. a grammar point or key phrases), or as a transition between '
            . 'activity sections. The content can be rich text, TTS audio, a TTS dialog, a TTS reading passage, '
            . 'a YouTube clip, an embed, or an uploaded image/audio/video file.';
    }

    /**
     * The agent-facing import field spec for page.
     *
     * @return array the import spec (usage, fields, fileareas, example)
     */
    public static function aigen_fetch_import_spec() {
        $fields = static::aigen_common_import_field_specs(['type', 'name', 'visible', 'instructions', 'text',
            'tts', 'ttsvoice', 'ttsoption', 'ttsautoplay',
            'ttsdialog', 'ttsdialogvoicea', 'ttsdialogvoiceb', 'ttsdialogvoicec',
            'ttsdialoglabela', 'ttsdialoglabelb', 'ttsdialoglabelc', 'ttsdialogvisible',
            'ttspassage', 'ttspassagevoice', 'ttspassagespeed',
            'audiofname', 'audiostoryzoom', 'nativelangchooser',
            'ytid', 'ytstart', 'ytend', 'iframe', 'timelimit', 'layout']);
        $fields['type']['example'] = 'page';
        $fields['text']['description'] = 'The page content, as HTML or plain text. A page needs at least one piece '
            . 'of content: text, tts, ttsdialog, ttspassage, ytid, iframe, an uploaded media file or an audio story.';

        $fields['filesid'] = [
            'jsonname' => 'filesid',
            'type' => 'int',
            'required' => false,
            'default' => '',
            'description' => 'Links this item to its entry in the top level "files" object of the payload. '
                . 'Only needed when the page has an uploaded media file or audio story files.',
            'example' => '1',
        ];

        return [
            'usage' => 'Compose one item object per page. Give the page some content: rich text in "text", '
                . 'spoken audio via the tts fields or a ttsdialog, a listen-while-reading passage via ttspassage, '
                . 'a YouTube clip via ytid, or an uploaded image/audio/video file in the '
                . constants::MEDIAQUESTION . ' file area. For an audio story (an image slideshow that plays like a '
                . 'video), put numbered images in the ' . constants::AUDIOSTORY . ' file area, list their entry times '
                . 'in "audiofname", and supply narration either as an uploaded audio file in the same area or via the '
                . 'tts fields. Set nativelangchooser to let the learner pick a first language for translations and '
                . 'feedback. Keep pages short; use several pages rather than one long one.',
            'fields' => array_values($fields),
            'fileareas' => [
                [
                    'filearea' => constants::MEDIAQUESTION,
                    'description' => 'An uploaded image, audio or video file shown as the page media prompt.',
                    'filenames' => 'Any filename; the extension decides how it renders: '
                        . 'image (png/jpg/gif/svg), video (mp4/mov/webm) or audio (mp3/m4a/ogg/wav).',
                ],
                [
                    'filearea' => constants::AUDIOSTORY,
                    'description' => 'Audio story files: the numbered images shown in sequence, an optional audio file '
                        . '(mp3/m4a/ogg/wav) that narrates them, and an optional .vtt subtitle file. If no audio file '
                        . 'is supplied the page\'s "tts" audio narrates the story instead. Pair the images with the '
                        . '"audiofname" entry times (one timestamp per image, in number order).',
                    'filenames' => 'Images numbered in display order (1.png, 2.jpg, 3.png, ...); plus at most one '
                        . 'audio file and at most one .vtt subtitle file, each with any filename.',
                ],
            ],
            'example' => [
                'items' => [
                    [
                        'type' => 'page',
                        'name' => 'Welcome',
                        'text' => '<p>In this lesson you will learn some useful classroom phrases. '
                            . 'First read the phrases, then practise saying them.</p>',
                        'tts' => 'Welcome! In this lesson we will practise useful classroom phrases.',
                        'ttsvoice' => 'auto',
                    ],
                ],
            ],
        ];
    }

    /**
     * Validates an import record for this item type: a page must have some content.
     *
     * @param \stdClass $newrecord the db-ready import record
     * @param \stdClass $cm the course module
     * @return false|\stdClass false when valid, or an error object with col and message
     */
    public static function validate_import($newrecord, $cm) {
        $error = new \stdClass();
        $error->col = '';
        $error->message = '';

        // Media files from the payload are attached to the record under the filearea name.
        $hasmedia = !empty($newrecord->{constants::MEDIAQUESTION})
            || !empty($newrecord->{constants::AUDIOSTORY});
        $hascontent = !empty(trim((string) $newrecord->{constants::TEXTQUESTION}))
            || !empty(trim((string) $newrecord->{constants::TTSQUESTION}))
            || !empty(trim((string) $newrecord->{constants::TTSDIALOG}))
            || !empty(trim((string) $newrecord->{constants::TTSPASSAGE}))
            || !empty(trim((string) $newrecord->{constants::YTVIDEOID}))
            || !empty(trim((string) $newrecord->{constants::MEDIAIFRAME}))
            || $hasmedia;

        if (!$hascontent) {
            $error->col = constants::TEXTQUESTION;
            $error->message = get_string('error:pagenocontent', constants::M_COMPONENT);
            return $error;
        }

        // Return false to indicate no error.
        return false;
    }

    //the item type
    /**
     * Export the data for the mustache template.
     *
     * @param \renderer_base $output renderer to be used to render the action bar elements.
     * @return array
     */
    public function export_for_template(\renderer_base $output)
    {

        $testitem = parent::export_for_template($output);
        $testitem = $this->get_polly_options($testitem);
        $testitem = $this->set_layout($testitem);

        return $testitem;
    }
}
