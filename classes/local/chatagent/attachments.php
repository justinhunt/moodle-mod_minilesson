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

namespace mod_minilesson\local\chatagent;

use mod_minilesson\constants;

/**
 * The documents and images a teacher attaches to a chat agent conversation.
 *
 * Files live in the module's file area, keyed by conversation, and the transcript records only
 * enough to identify them. The bytes are read back out when they are needed - which, thanks to
 * server-side history, is once when the file is first sent, and again only if the conversation
 * has to be replayed because the provider forgot it.
 *
 * The file manager in the composer edits a draft copy of that same area, so adding and removing
 * files is core's job rather than this class's.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attachments {
    /** @var string File area holding chat agent attachments, one itemid per conversation. */
    const FILEAREA = 'chatagent';

    /** @var int Attachment size allowed when the site has not said otherwise, in megabytes. */
    const DEFAULT_MAX_MB = 4;

    /**
     * Sync the composer's draft area into the conversation's own file area.
     *
     * Deliberately the standard sync rather than an append: a file the teacher removed from the
     * file manager is removed here too, which is how taking an attachment back off works. The
     * draft area is left alone afterwards - core does not purge it - so the file manager goes on
     * showing what the conversation is working with.
     *
     * @param \context $context the module context
     * @param int $conversationid
     * @param int $draftitemid the draft area behind the file manager
     * @return void
     */
    public static function sync_draft(\context $context, int $conversationid, int $draftitemid): void {
        global $CFG, $USER;
        require_once($CFG->libdir . '/filelib.php');

        if (empty($draftitemid)) {
            return;
        }

        // The limit is about what gets sent to the AI service, so it is applied to the thing that
        // is actually sent. For a PDF or an image that is the file itself. For JSON it is not:
        // the media is stripped before it leaves (see controller::describe_json_attachment), so a
        // seven megabyte export and a small one cost the same, and capping the file would refuse a
        // perfectly workable upload for no benefit. Moodle's own upload limits still apply to it.
        //
        // Checked here rather than trusting the file manager because the draft area id comes from
        // the client, and a draft area can be filled by other means.
        $maxbytes = self::max_bytes();
        $usercontext = \context_user::instance($USER->id);
        foreach (get_file_storage()->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false) as $file) {
            if ($file->get_mimetype() === 'application/json') {
                continue;
            }
            if ($file->get_filesize() > $maxbytes) {
                $name = $file->get_filename();
                $size = $file->get_filesize();
                $file->delete();
                throw new \moodle_exception(
                    'chatagent_error_attachmenttoobig',
                    constants::M_COMPONENT,
                    '',
                    (object) [
                        'name' => $name,
                        'size' => round($size / 1048576, 1),
                        'max' => round($maxbytes / 1048576, 1),
                    ]
                );
            }
        }

        file_save_draft_area_files(
            $draftitemid,
            $context->id,
            constants::M_COMPONENT,
            self::FILEAREA,
            $conversationid,
            \mod_minilesson\chatagent_attachform::filemanager_options()
        );
    }

    /**
     * The conversation's files that the model has not been given yet.
     *
     * With server-side history a file is sent once and referred to thereafter, so each turn
     * carries only what is new. What counts as already sent is what the transcript records
     * against earlier messages - no separate bookkeeping to fall out of step with the files.
     *
     * @param conversation $conversation
     * @param \context $context
     * @return array descriptors, with bytes, ready to send
     */
    public static function unsent(conversation $conversation, \context $context): array {
        $alreadysent = [];
        foreach ($conversation->all_attachments() as $attachment) {
            if (!empty($attachment['id'])) {
                $alreadysent[$attachment['id']] = true;
            }
        }

        $new = [];
        foreach (self::stored_files($context, $conversation->id) as $file) {
            if (isset($alreadysent[$file->get_pathnamehash()])) {
                continue;
            }
            $new[] = self::describe($file, true);
        }
        return $new;
    }

    /**
     * Fill in the bytes for descriptors that were read back from the database.
     *
     * The transcript stores which files were attached, not the files themselves, so a
     * conversation loaded from storage has descriptors with no data. This puts it back, and
     * leaves alone any descriptor that already carries its own bytes - which is how the CLI
     * harness, holding everything in memory, keeps working unchanged.
     *
     * @param conversation $conversation
     * @return array descriptors with data where the file could be found
     */
    public static function hydrate(conversation $conversation): array {
        $descriptors = $conversation->all_attachments();
        if (!$descriptors || !$conversation->cmid) {
            return $descriptors;
        }

        $files = [];
        try {
            $context = \context_module::instance($conversation->cmid);
            foreach (self::stored_files($context, $conversation->id) as $file) {
                $files[$file->get_pathnamehash()] = $file;
            }
        } catch (\Throwable $e) {
            // The module has gone. Nothing to hydrate with; the descriptors still name the files.
            return $descriptors;
        }

        foreach ($descriptors as $index => $descriptor) {
            if (!empty($descriptor['data']) || empty($files[$descriptor['id'] ?? ''])) {
                continue;
            }
            $descriptors[$index]['data'] = base64_encode($files[$descriptor['id']]->get_content());
        }
        return $descriptors;
    }

    /**
     * Remove every file belonging to a conversation.
     *
     * @param int $cmid
     * @param int $conversationid
     * @return void
     */
    public static function delete_for(int $cmid, int $conversationid): void {
        try {
            $context = \context_module::instance($cmid);
        } catch (\Throwable $e) {
            return;
        }
        get_file_storage()->delete_area_files(
            $context->id,
            constants::M_COMPONENT,
            self::FILEAREA,
            $conversationid
        );
    }

    /**
     * Describe one stored file the way the controller expects.
     *
     * @param \stored_file $file
     * @param bool $withdata whether to include the file's bytes
     * @return array
     */
    protected static function describe(\stored_file $file, bool $withdata): array {
        $descriptor = [
            'id' => $file->get_pathnamehash(),
            'filename' => $file->get_filename(),
            'mimetype' => $file->get_mimetype(),
        ];
        if ($withdata) {
            $descriptor['data'] = base64_encode($file->get_content());
        }
        return $descriptor;
    }

    /**
     * The files stored for one conversation.
     *
     * @param \context $context
     * @param int $conversationid
     * @return \stored_file[]
     */
    protected static function stored_files(\context $context, int $conversationid): array {
        return get_file_storage()->get_area_files(
            $context->id,
            constants::M_COMPONENT,
            self::FILEAREA,
            $conversationid,
            'filename',
            false
        );
    }

    /**
     * The largest attachment the site accepts, in bytes.
     *
     * @return int
     */
    public static function max_bytes(): int {
        $mb = (int) get_config(constants::M_COMPONENT, 'chatagentmaxattachmentmb');
        if ($mb <= 0) {
            $mb = self::DEFAULT_MAX_MB;
        }
        return $mb * 1048576;
    }
}
