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

use mod_minilesson\local\admin_setting_starthresholds;

/**
 * Tests for the star rating score ranges.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_minilesson\starrating
 * @covers     \mod_minilesson\local\admin_setting_starthresholds
 */
#[\PHPUnit\Framework\Attributes\CoversClass(starrating::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(admin_setting_starthresholds::class)]
final class starrating_test extends \advanced_testcase {
    /**
     * The default ranges give the same stars as the old fixed thresholds on the results page.
     *
     * @dataProvider default_count_provider
     * @param float $score The score.
     * @param int $expected The expected number of stars.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('default_count_provider')]
    public function test_count_with_defaults(float $score, int $expected): void {
        $this->resetAfterTest();
        $this->assertSame($expected, starrating::count($score));
    }

    /**
     * Scores around each default boundary.
     *
     * @return array
     */
    public static function default_count_provider(): array {
        return [
            [0, 0], [1, 1], [18, 1], [19, 2], [38, 2], [39, 3],
            [58, 3], [59, 4], [78, 4], [79, 5], [80, 5], [100, 5],
        ];
    }

    /**
     * The admin's ranges are used once saved.
     */
    public function test_count_uses_setting(): void {
        $this->resetAfterTest();
        set_config(starrating::SETTING, '10,30,50,70,90', constants::M_COMPONENT);
        $this->assertSame(0, starrating::count(9));
        $this->assertSame(1, starrating::count(10));
        $this->assertSame(4, starrating::count(89));
        $this->assertSame(5, starrating::count(90));
        $this->assertSame(0, starrating::count(null));
    }

    /**
     * A broken setting falls back to the defaults rather than giving odd stars.
     */
    public function test_invalid_setting_uses_defaults(): void {
        $this->resetAfterTest();
        set_config(starrating::SETTING, '50,40,30,20,10', constants::M_COMPONENT);
        $this->assertSame(starrating::DEFAULT_THRESHOLDS, starrating::get_thresholds());
        set_config(starrating::SETTING, 'rubbish', constants::M_COMPONENT);
        $this->assertSame(starrating::DEFAULT_THRESHOLDS, starrating::get_thresholds());
    }

    /**
     * Checking a set of scores.
     */
    public function test_validate(): void {
        $this->assertSame('', starrating::validate([0, 20, 40, 60, 100]));
        $this->assertSame('', starrating::validate(['1', ' 19', '39 ', '59', '79']));
        $this->assertSame('starthresholds_errnumber', starrating::validate([1, 19, 39, 59]));
        $this->assertSame('starthresholds_errnumber', starrating::validate([1, 19, 39, 59, 101]));
        $this->assertSame('starthresholds_errnumber', starrating::validate([-1, 19, 39, 59, 79]));
        $this->assertSame('starthresholds_errnumber', starrating::validate([1, 19.5, 39, 59, 79]));
        $this->assertSame('starthresholds_errnumber', starrating::validate([1, '', 39, 59, 79]));
        $this->assertSame('starthresholds_errorder', starrating::validate([1, 19, 19, 59, 79]));
        $this->assertSame('starthresholds_errorder', starrating::validate([1, 39, 19, 59, 79]));
    }

    /**
     * The template arrays always add up to five stars.
     */
    public function test_for_template(): void {
        $this->resetAfterTest();
        $stars = starrating::for_template(60);
        $this->assertCount(4, $stars['yellowstars']);
        $this->assertCount(1, $stars['graystars']);
        $stars = starrating::for_template(0);
        $this->assertCount(0, $stars['yellowstars']);
        $this->assertCount(5, $stars['graystars']);
    }

    /**
     * The ranges shown to the admin.
     */
    public function test_get_ranges(): void {
        $ranges = starrating::get_ranges([1, 19, 39, 59, 79]);
        $this->assertCount(6, $ranges);
        $this->assertSame(['stars' => 0, 'from' => 0, 'to' => 0], $ranges[0]);
        $this->assertSame(['stars' => 1, 'from' => 1, 'to' => 18], $ranges[1]);
        $this->assertSame(['stars' => 5, 'from' => 79, 'to' => 100], $ranges[5]);

        // With 0 as the 1 star score, no score gets 0 stars.
        $ranges = starrating::get_ranges([0, 20, 40, 60, 80]);
        $this->assertCount(5, $ranges);
        $this->assertSame(1, $ranges[0]['stars']);
    }

    /**
     * Saving the admin setting: good scores are stored, bad ones are refused, the default can be applied.
     */
    public function test_admin_setting_write(): void {
        $this->resetAfterTest();
        $setting = new admin_setting_starthresholds('mod_minilesson/starthresholds', 'Stars', '');

        $this->assertSame('', $setting->write_setting([1 => '5', 2 => '25', 3 => '45', 4 => '65', 5 => '85']));
        $this->assertSame('5,25,45,65,85', get_config(constants::M_COMPONENT, 'starthresholds'));
        $this->assertSame([5, 25, 45, 65, 85], $setting->get_setting());

        $error = $setting->write_setting([1 => '5', 2 => '25', 3 => '25', 4 => '65', 5 => '85']);
        $this->assertSame(get_string('starthresholds_errorder', constants::M_COMPONENT), $error);
        $this->assertSame('5,25,45,65,85', get_config(constants::M_COMPONENT, 'starthresholds'));

        // Upgrade applies the default as a string.
        $this->assertSame('', $setting->write_setting($setting->get_defaultsetting()));
        $this->assertSame('1,19,39,59,79', get_config(constants::M_COMPONENT, 'starthresholds'));

        // The setting renders.
        $html = $setting->output_html($setting->get_setting());
        $this->assertStringContainsString('s_mod_minilesson_starthresholds[5]', $html);
    }
}
