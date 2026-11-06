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

use mod_minilesson\constants;

/**
 * Stores the unpacked files of an item's widget in Moodle, and serves them.
 *
 * Widgets run untrusted JavaScript, yet they are served from Moodle's own site. That is safe because every
 * response carries a Content-Security-Policy with the "sandbox" directive (without allow-same-origin). The
 * browser then runs the widget in an opaque origin of its own, even when its page is opened directly in a
 * tab rather than in the lesson's frame. It can not read Moodle's cookies, storage or pages, and the rest of
 * the policy only lets it load files from its own folder, so it can not send what a learner typed to any
 * other site either.
 *
 * The files are kept per item, under a folder named by the ZIP's sha256 (the revision). Only the current
 * revision is kept. The URL holds the revision, so a file never changes at its URL and can be cached for ever.
 * The files are public to anyone who has the URL: they need no login (the sandbox stops the browser from
 * sending Moodle's cookies with the widget's own requests anyway), and the revision makes the URL hard to
 * guess.
 *
 * @package    minilessonitem_smartframe
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class widgetstore {
    /** @var string Component of the stored widget files. */
    const COMPONENT = 'minilessonitem_smartframe';
    /** @var string File area of the stored widget files. Its item id is the lesson item's id. */
    const FILEAREA = 'widget';
    /** @var string The script that serves the files, from the site root. */
    const SCRIPT = '/mod/minilesson/item/smartframe/widget.php';

    /**
     * Is this a revision id (a sha256 in hex)?
     *
     * @param mixed $revision the value
     * @return bool
     */
    public static function is_revision($revision): bool {
        return is_string($revision) && preg_match('/^[0-9a-f]{64}$/', $revision) === 1;
    }

    /**
     * The URL of the folder an item's widget revision is served from, with a trailing slash.
     *
     * @param int $itemid the lesson item id
     * @param string $revision the revision id
     * @return string
     */
    public static function base_url(int $itemid, string $revision): string {
        global $CFG;
        return $CFG->wwwroot . self::SCRIPT . '/' . $itemid . '/' . $revision . '/';
    }

    /**
     * The URL of a widget's entry page.
     *
     * @param int $itemid the lesson item id
     * @param string $revision the revision id
     * @param string $entry the entry path from the checked manifest
     * @return string
     */
    public static function entry_url(int $itemid, string $revision, string $entry): string {
        $path = implode('/', array_map('rawurlencode', explode('/', $entry)));
        return self::base_url($itemid, $revision) . $path;
    }

    /**
     * The security policy a widget revision is served with.
     *
     * The sandbox gives the widget an opaque origin. Scripts are allowed (inline too: AI tools often write
     * single file widgets) and forms are allowed (a submit in a sandbox without allow-forms is dropped before
     * the submit event, which breaks widgets that handle submit in JS), but form-action 'none' stops a form
     * from going anywhere. Every fetch is limited to the revision's own folder, not to all of Moodle.
     * There is no frame-ancestors: a lesson may itself be embedded in another site, and a widget framed by
     * some other page can reach nothing.
     *
     * @param int $itemid the lesson item id
     * @param string $revision the revision id
     * @return string
     */
    public static function csp(int $itemid, string $revision): string {
        $own = self::base_url($itemid, $revision);
        return "sandbox allow-scripts allow-forms; default-src 'none'; "
            . "script-src $own 'unsafe-inline'; style-src $own 'unsafe-inline'; "
            . "img-src $own data: blob:; media-src $own data: blob:; font-src $own data:; connect-src $own; "
            . "form-action 'none'; base-uri 'none'; object-src 'none'; worker-src 'none'; manifest-src 'none'";
    }

    /**
     * Where a widget file is stored: its file path and file name.
     *
     * @param string $revision the revision id
     * @param string $path the file's path in the package, e.g. "locales/ja.json"
     * @return array [filepath, filename]
     */
    protected static function location(string $revision, string $path): array {
        $dir = dirname($path);
        $filepath = '/' . $revision . '/' . ($dir === '.' || $dir === '' ? '' : $dir . '/');
        return [$filepath, basename($path)];
    }

    /**
     * Is this revision of an item's widget stored?
     *
     * The entry page is stored last, so a revision whose entry page is there is complete.
     *
     * @param \context $context the lesson's module context
     * @param int $itemid the lesson item id
     * @param string $revision the revision id
     * @param string $entry the entry path from the checked manifest
     * @return bool
     */
    public static function is_published(\context $context, int $itemid, string $revision, string $entry): bool {
        if (!self::is_revision($revision)) {
            return false;
        }
        [$filepath, $filename] = self::location($revision, $entry);
        return get_file_storage()->file_exists($context->id, self::COMPONENT, self::FILEAREA, $itemid, $filepath, $filename);
    }

    /**
     * Store a checked package as an item's widget, replacing any other revision.
     *
     * @param package $package the checked package
     * @param \context $context the lesson's module context
     * @param int $itemid the lesson item id
     */
    public static function publish(package $package, \context $context, int $itemid): void {
        $revision = $package->get_revision();
        $entry = $package->get_manifest()->entry;
        if (self::is_published($context, $itemid, $revision, $entry)) {
            return;
        }
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, self::COMPONENT, self::FILEAREA, $itemid);

        $record = [
            'contextid' => $context->id,
            'component' => self::COMPONENT,
            'filearea' => self::FILEAREA,
            'itemid' => $itemid,
        ];
        $entryfile = null;
        try {
            foreach ($package->files() as $path => $file) {
                if ($path === $entry) {
                    $entryfile = $file;
                    continue;
                }
                self::store($fs, $record, $revision, $path, $file);
            }
            if (!$entryfile) {
                throw new package_exception('error:manifestentry', $entry);
            }
            self::store($fs, $record, $revision, $entry, $entryfile);
        } catch (\file_exception | \stored_file_creation_exception $e) {
            $fs->delete_area_files($context->id, self::COMPONENT, self::FILEAREA, $itemid);
            throw new package_exception('error:publishfailed');
        }
    }

    /**
     * Store one widget file, with the content type the package gave it.
     *
     * @param \file_storage $fs file storage
     * @param array $record the file record fields common to all the widget's files
     * @param string $revision the revision id
     * @param string $path the file's path in the package
     * @param array $file ['content' => string, 'contenttype' => string]
     */
    protected static function store(\file_storage $fs, array $record, string $revision, string $path, array $file): void {
        [$filepath, $filename] = self::location($revision, $path);
        $record['filepath'] = $filepath;
        $record['filename'] = $filename;
        // Moodle's own guess from the name is not always what a browser needs (a module script must be JS).
        $record['mimetype'] = explode(';', $file['contenttype'])[0];
        $fs->create_file_from_string($record, $file['content']);
    }

    /**
     * Send a plain "not found", then stop. Moodle's own send_file_not_found() throws, so a learner's browser
     * would get a server error page.
     */
    public static function not_found(): void {
        send_header_404();
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo 'Not found';
        die;
    }

    /**
     * Send a widget file, then stop. Sends "not found" for anything that is not a file of an item's current
     * widget revision.
     *
     * @param int $itemid the lesson item id
     * @param string $revision the revision id
     * @param string $path the file's path in the package
     */
    public static function serve(int $itemid, string $revision, string $path): void {
        global $DB;
        $item = $DB->get_record(constants::M_QTABLE, ['id' => $itemid], 'id, minilesson, type, customtext1');
        if (!$item || $item->type !== constants::TYPE_SMARTFRAME || $item->customtext1 !== $revision) {
            self::not_found();
        }
        $cm = get_coursemodule_from_instance(constants::M_MODNAME, $item->minilesson);
        if (!$cm) {
            self::not_found();
        }
        $context = \context_module::instance($cm->id);
        [$filepath, $filename] = self::location($revision, $path);
        $file = get_file_storage()->get_file(
            $context->id,
            self::COMPONENT,
            self::FILEAREA,
            $itemid,
            $filepath,
            $filename
        );
        if (!$file || $file->is_directory()) {
            self::not_found();
        }

        header('Content-Security-Policy: ' . self::csp($itemid, $revision));
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        // The widget's origin is opaque, so its fetch() of its own files is a cross-origin request.
        header('Access-Control-Allow-Origin: *');
        send_stored_file($file, YEARSECS, 0, false, [
            'cacheability' => 'public',
            'immutable' => true,
            // SVG is safe to show: the policy above sandboxes it like any other widget page.
            'dontforcesvgdownload' => true,
        ]);
    }
}
