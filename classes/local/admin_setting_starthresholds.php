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

namespace mod_minilesson\local;

use mod_minilesson\constants;
use mod_minilesson\starrating;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Admin setting for the minimum score that gives each number of stars.
 *
 * The five minimum scores are one setting, stored as "1,19,39,59,79", so they can be checked
 * together when saved: each must be higher than the one before.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_starthresholds extends \admin_setting {
    /**
     * Constructor.
     *
     * @param string $name The setting name, such as 'mod_minilesson/starthresholds'.
     * @param string $visiblename The setting label.
     * @param string $description The setting description.
     */
    public function __construct($name, $visiblename, $description) {
        parent::__construct($name, $visiblename, $description, implode(',', starrating::DEFAULT_THRESHOLDS));
    }

    /**
     * The saved minimum scores.
     *
     * @return array|null The minimum score for 1 to 5 stars, or null if not saved yet.
     */
    public function get_setting() {
        $value = $this->config_read($this->name);
        if ($value === null) {
            return null;
        }
        return starrating::parse($value) ?? starrating::DEFAULT_THRESHOLDS;
    }

    /**
     * Check and save the minimum scores.
     *
     * @param array|string $data The five scores from the form, or the default "1,19,39,59,79" string.
     * @return string An error message, or an empty string on success.
     */
    public function write_setting($data) {
        if (is_string($data)) {
            $data = explode(',', $data);
        }
        if (!is_array($data)) {
            return '';
        }
        $values = array_map(fn($value) => is_string($value) ? trim($value) : $value, array_values($data));
        $error = starrating::validate($values);
        if ($error !== '') {
            return get_string($error, constants::M_COMPONENT);
        }
        $result = $this->config_write($this->name, implode(',', array_map('intval', $values)));
        return $result ? '' : get_string('errorsetting', 'admin');
    }

    /**
     * Show a table with a minimum score box for each number of stars, and the score ranges they give.
     *
     * @param array|null $data The saved scores, or the scores just entered if they could not be saved.
     * @param string $query The admin search query.
     * @return string The setting HTML.
     */
    public function output_html($data, $query = '') {
        global $OUTPUT;

        $values = is_array($data) ? array_values($data) : starrating::DEFAULT_THRESHOLDS;
        $rows = [];
        for ($stars = 1; $stars <= starrating::MAXSTARS; $stars++) {
            $rows[] = [
                'id' => $this->get_id() . '_' . $stars,
                'name' => $this->get_full_name() . '[' . $stars . ']',
                'value' => $values[$stars - 1] ?? '',
                'label' => get_string('starthresholds_minfor', constants::M_COMPONENT, $stars),
                'stars' => self::star_icons($stars),
                'starslabel' => get_string('starthresholds_numstars', constants::M_COMPONENT, $stars),
            ];
        }

        // Show the ranges only for scores that could be saved, not for a rejected set.
        $ranges = [];
        if (starrating::validate($values) === '') {
            foreach (starrating::get_ranges($values) as $range) {
                $ranges[] = [
                    'stars' => self::star_icons($range['stars']),
                    'starslabel' => get_string('starthresholds_numstars', constants::M_COMPONENT, $range['stars']),
                    'text' => get_string(
                        $range['from'] === $range['to'] ? 'starthresholds_rangeone' : 'starthresholds_range',
                        constants::M_COMPONENT,
                        (object) $range
                    ),
                ];
            }
        }

        $context = [
            'rows' => $rows,
            'ranges' => $ranges,
            'hasranges' => !empty($ranges),
            'readonly' => $this->is_readonly(),
        ];
        $element = $OUTPUT->render_from_template('mod_minilesson/setting_starthresholds', $context);

        $defaultinfo = implode(', ', starrating::DEFAULT_THRESHOLDS);
        return format_admin_setting(
            $this,
            $this->visiblename,
            $element,
            $this->description,
            $this->get_id() . '_1',
            '',
            $defaultinfo,
            $query
        );
    }

    /**
     * Yellow and gray star flags for a star count, for the template.
     *
     * @param int $count The number of yellow stars.
     * @return array One entry per star, each with 'filled'.
     */
    private static function star_icons(int $count): array {
        $icons = [];
        for ($i = 1; $i <= starrating::MAXSTARS; $i++) {
            $icons[] = ['filled' => $i <= $count];
        }
        return $icons;
    }
}
