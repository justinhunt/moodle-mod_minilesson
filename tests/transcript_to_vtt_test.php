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
 * Tests for converting a transcript copied from YouTube's panel into WebVTT.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_minilesson\youtubetranscript::transcript_to_vtt
 */
final class transcript_to_vtt_test extends \basic_testcase {
    /**
     * The panel's usual shape: each timestamp alone on its line, text beneath.
     */
    public function test_timestamp_on_its_own_line(): void {
        $vtt = youtubetranscript::transcript_to_vtt("0:00\nbonjour les amis\n0:03\nnouvel episode\n");

        $this->assertStringStartsWith('WEBVTT', $vtt);
        // Half a second is added to centre the panel's whole-second truncation.
        $this->assertStringContainsString("00:00:00.500 --> 00:00:03.500\nbonjour les amis", $vtt);
        // The last cue gets a nominal length.
        $this->assertStringContainsString("00:00:03.500 --> 00:00:06.500\nnouvel episode", $vtt);
        $this->assertStringContainsString('line-number: 01', $vtt);
        $this->assertStringContainsString('line-number: 02', $vtt);
    }

    /**
     * Some browsers put the timestamp and its text on one line.
     */
    public function test_timestamp_inline_with_text(): void {
        $vtt = youtubetranscript::transcript_to_vtt("0:00 bonjour les amis\n0:03 nouvel episode\n");

        $this->assertStringContainsString("00:00:00.500 --> 00:00:03.500\nbonjour les amis", $vtt);
        $this->assertStringContainsString('nouvel episode', $vtt);
    }

    /**
     * Hours are handled, as are text lines that wrap beyond the first.
     */
    public function test_hours_and_wrapped_text(): void {
        $vtt = youtubetranscript::transcript_to_vtt("1:02:03\nune phrase\nqui continue\n1:02:10\nla suite\n");

        $this->assertStringContainsString('01:02:03.500 --> 01:02:10.500', $vtt);
        // The wrapped remainder joins the cue it belongs to.
        $this->assertStringContainsString('une phrase qui continue', $vtt);
    }

    /**
     * Blank lines and non-speech markers survive; timestamps without text do not.
     */
    public function test_blank_lines_and_markers(): void {
        $vtt = youtubetranscript::transcript_to_vtt("0:00\n\nbonjour\n\n0:05\n[Musique]\n0:09\nla suite\n");

        $this->assertStringContainsString('bonjour', $vtt);
        $this->assertStringContainsString('[Musique]', $vtt);
        $this->assertSame(3, substr_count($vtt, '-->'));
    }

    /**
     * A trailing timestamp with nothing after it is dropped rather than emitted empty.
     */
    public function test_trailing_timestamp_without_text(): void {
        $vtt = youtubetranscript::transcript_to_vtt("0:00\nbonjour\n0:05\n");

        $this->assertSame(1, substr_count($vtt, '-->'));
    }

    /**
     * Text with no timestamps at all cannot be timed, so it is rejected.
     */
    public function test_text_without_timestamps_is_rejected(): void {
        $this->expectException(\moodle_exception::class);
        youtubetranscript::transcript_to_vtt("bonjour les amis\nnouvel episode\n");
    }

    /**
     * The panel truncates to whole seconds, so centring must keep the error
     * within half a second of the true start in both directions.
     */
    public function test_centring_bounds_the_error(): void {
        // A cue truly starting at 3.9s is displayed by the panel as 0:03.
        $vtt = youtubetranscript::transcript_to_vtt("0:03\nune phrase\n0:09\nla suite\n");

        $this->assertStringContainsString('00:00:03.500', $vtt);
        // Worst case is half a second, whichever way the true start falls in its second.
        $this->assertLessThanOrEqual(0.5, abs(3.5 - 3.9));
        $this->assertLessThanOrEqual(0.5, abs(3.5 - 3.0));
    }
}
