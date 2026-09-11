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

/**
 * The file manager the chat agent composer attaches files with.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_minilesson;

use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * A form holding nothing but a file manager.
 *
 * It is never submitted. The chat panel reads the draft item id out of it and passes that to
 * chatagent_send, which syncs the draft area into the conversation's own file area. Using the
 * standard element rather than a bare upload button is what gives teachers the repositories
 * they already use, drag and drop, and - the part a plain upload button cannot do - a way to
 * take a file back off again.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chatagent_attachform extends moodleform {
    /** @var int Files one conversation may work with at a time. */
    const MAX_FILES = 5;

    /**
     * The file manager, and nothing else.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        if (isset($this->_customdata['id'])) {
            $mform->setConstant('id', $this->_customdata['id']);
        }

        $mform->addElement(
            'filemanager',
            'chatagent_filemanager',
            get_string('chatagent_files', constants::M_COMPONENT),
            null,
            self::filemanager_options()
        );
        $mform->addElement('static', 'chatagent_fileshelp', '', get_string('chatagent_fileshelp', constants::M_COMPONENT));
    }

    /**
     * The options the draft area is prepared with and saved back with.
     *
     * The same array has to be used in both places, or a file the teacher was allowed to add
     * would be silently dropped when it was saved.
     *
     * @return array
     */
    public static function filemanager_options(): array {
        return [
            'subdirs' => 0,
            'maxfiles' => self::MAX_FILES,
            // The site's own upload limit, not the agent's. One file manager cannot vary its limit
            // by file type, and the agent's limit does not apply to JSON at all - so the per-type
            // rule is enforced server side in attachments::sync_draft() instead. The cost is that
            // an oversized PDF is refused on send rather than on upload.
            'maxbytes' => 0,
            // Only what the model can actually read. Anything else would be uploaded, sent,
            // charged for and ignored. JSON is here for exported lessons, which a teacher reuses
            // as the shape for a new topic.
            'accepted_types' => ['.pdf', '.json', 'web_image'],
        ];
    }
}
