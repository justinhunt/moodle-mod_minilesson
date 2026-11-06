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
 * Publishes widgets through Cloud Poodll to S3, served from the widget CDN (or a plain S3 URL).
 *
 * TODO: the Cloud Poodll upload service does not exist yet. Until it does, publishing fails with a message.
 *
 * @package    minilessonitem_smartframe
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class publisher_cloudpoodll extends publisher {
    /**
     * The base URL revisions are served under.
     *
     * @return string
     */
    protected function base_url(): string {
        return rtrim(trim((string) get_config(self::CONFIG, 'cdnbaseurl')), '/');
    }

    /**
     * Is this revision already published? Not known until the upload service exists.
     *
     * @param string $revision the revision id
     * @return bool
     */
    public function is_published(string $revision): bool {
        return false;
    }

    /**
     * Send the files to Cloud Poodll.
     *
     * @param array $files path => ['content' => string, 'contenttype' => string]
     * @param string $revision the revision id
     */
    protected function put_files(array $files, string $revision): void {
        throw new package_exception('error:publishernotready');
    }
}
