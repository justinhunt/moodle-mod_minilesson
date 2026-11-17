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

namespace mod_minilesson;

/**
 * Turns a score (0-100) into a star rating (0-5), using the score ranges set by the site admin.
 *
 * The admin sets the minimum score for each number of stars (1 to 5). A score below the minimum
 * for one star gets no stars. The same ranges are used on the results page and by the items'
 * own feedback (see quizhelper.starCount() in amd/src/quizhelper.js).
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class starrating {
    /** @var int The number of stars in a full rating. */
    public const MAXSTARS = 5;

    /** @var string The config setting that holds the minimum scores. */
    public const SETTING = 'starthresholds';

    /** @var int[] The minimum score for 1, 2, 3, 4 and 5 stars, unless the admin changes them. */
    public const DEFAULT_THRESHOLDS = [1, 19, 39, 59, 79];

    /**
     * The minimum score for 1 to 5 stars, as set by the admin.
     *
     * @return int[] Five scores, lowest first.
     */
    public static function get_thresholds(): array {
        $thresholds = self::parse(get_config(constants::M_COMPONENT, self::SETTING));
        return $thresholds ?? self::DEFAULT_THRESHOLDS;
    }

    /**
     * Read the stored setting.
     *
     * @param mixed $value The stored value, such as "1,19,39,59,79".
     * @return int[]|null Five scores, or null if the value is missing or not valid.
     */
    public static function parse($value): ?array {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $parts = array_map('trim', explode(',', $value));
        if (self::validate($parts) !== '') {
            return null;
        }
        return array_map('intval', $parts);
    }

    /**
     * Check a set of minimum scores.
     *
     * @param array $values The minimum score for 1 to 5 stars.
     * @return string The lang string key of the problem, or an empty string if the values are fine.
     */
    public static function validate(array $values): string {
        $values = array_values($values);
        if (count($values) !== self::MAXSTARS) {
            return 'starthresholds_errnumber';
        }
        foreach ($values as $value) {
            $value = is_string($value) ? trim($value) : $value;
            if (!is_numeric($value) || (string) (int) $value !== (string) $value || $value < 0 || $value > 100) {
                return 'starthresholds_errnumber';
            }
        }
        for ($i = 1; $i < self::MAXSTARS; $i++) {
            if ((int) $values[$i] <= (int) $values[$i - 1]) {
                return 'starthresholds_errorder';
            }
        }
        return '';
    }

    /**
     * The number of stars for a score.
     *
     * @param float|int|null $score The score, 0-100.
     * @param int[]|null $thresholds The minimum score for 1 to 5 stars, or null for the site setting.
     * @return int 0 to 5.
     */
    public static function count($score, ?array $thresholds = null): int {
        $thresholds = $thresholds ?? self::get_thresholds();
        $score = is_numeric($score) ? (float) $score : 0;
        $stars = 0;
        foreach ($thresholds as $index => $minimum) {
            if ($score >= $minimum) {
                $stars = $index + 1;
            }
        }
        return $stars;
    }

    /**
     * The star arrays the templates loop over: one entry per yellow star and one per gray star.
     *
     * @param float|int|null $score The score, 0-100.
     * @return array With keys 'yellowstars' and 'graystars'.
     */
    public static function for_template($score): array {
        $stars = self::count($score);
        return [
            'yellowstars' => array_fill(0, $stars, true),
            'graystars' => array_fill(0, self::MAXSTARS - $stars, true),
        ];
    }

    /**
     * The score range that gives each number of stars, for showing the admin what their settings mean.
     *
     * @param int[] $thresholds The minimum score for 1 to 5 stars.
     * @return array One entry per number of stars that can be reached, each with 'stars', 'from' and 'to'.
     */
    public static function get_ranges(array $thresholds): array {
        $thresholds = array_values($thresholds);
        $ranges = [];
        if ($thresholds[0] > 0) {
            $ranges[] = ['stars' => 0, 'from' => 0, 'to' => $thresholds[0] - 1];
        }
        foreach ($thresholds as $index => $minimum) {
            $to = isset($thresholds[$index + 1]) ? $thresholds[$index + 1] - 1 : 100;
            $ranges[] = ['stars' => $index + 1, 'from' => $minimum, 'to' => $to];
        }
        return $ranges;
    }
}
