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
 * Serves the files of a widget item: widget.php/<itemid>/<revision>/<path in the package>.
 *
 * There is no login and no session here, on purpose. Widget files are public to anyone with the URL, which
 * holds the revision (a sha256), and the widget runs sandboxed in an opaque origin, so the browser does not
 * send Moodle's cookies with its requests anyway. See \minilessonitem_smartframe\local\widgetstore.
 *
 * @package    minilessonitem_smartframe
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing
define('NO_MOODLE_COOKIES', true);
define('NO_DEBUG_DISPLAY', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/filelib.php');

$relativepath = get_file_argument();
if (!$relativepath || !preg_match('#^/([1-9][0-9]{0,17})/([0-9a-f]{64})/(.+)$#', $relativepath, $matches)) {
    \minilessonitem_smartframe\local\widgetstore::not_found();
}
\minilessonitem_smartframe\local\widgetstore::serve((int) $matches[1], $matches[2], $matches[3]);
