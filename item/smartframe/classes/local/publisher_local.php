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

namespace minilessonitem_smartframe\local;

/**
 * Publishes widgets to a folder in moodledata, for development.
 *
 * Moodle does not serve this folder. A separate web server on another host name or port must serve it, so
 * that widgets run on an origin of their own. Not for production.
 *
 * @package    minilessonitem_smartframe
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class publisher_local extends publisher {
    /**
     * The folder revisions are published into.
     *
     * @return string the path
     */
    public static function get_root(): string {
        global $CFG;
        return $CFG->dataroot . '/minilessonitem_smartframe/widgets';
    }

    /**
     * The base URL revisions are served under.
     *
     * @return string
     */
    protected function base_url(): string {
        return rtrim(trim((string) get_config(self::CONFIG, 'localbaseurl')), '/');
    }

    /**
     * Is this revision already published?
     *
     * @param string $revision the revision id
     * @return bool
     */
    public function is_published(string $revision): bool {
        return self::is_revision($revision) && is_dir(self::get_root() . '/' . $revision);
    }

    /**
     * Write the files to a temporary folder, then move it into place, so a revision is never half there.
     *
     * @param array $files path => ['content' => string, 'contenttype' => string]
     * @param string $revision the revision id
     */
    protected function put_files(array $files, string $revision): void {
        global $CFG;
        $root = self::get_root();
        if (!check_dir_exists($root, true, true)) {
            throw new package_exception('error:publishfailed');
        }
        $temp = $root . '/.tmp-' . $revision . '-' . random_string(8);
        try {
            foreach ($files as $path => $file) {
                $target = $temp . '/' . $path;
                if (
                    !check_dir_exists(dirname($target), true, true)
                        || file_put_contents($target, $file['content']) === false
                ) {
                    throw new package_exception('error:publishfailed');
                }
                @chmod($target, $CFG->filepermissions);
            }
            if (!@rename($temp, $root . '/' . $revision) && !$this->is_published($revision)) {
                throw new package_exception('error:publishfailed');
            }
        } finally {
            if (is_dir($temp)) {
                remove_dir($temp);
            }
        }
    }
}
