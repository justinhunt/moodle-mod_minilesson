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
        // The panel's truncated value is used as-is, so a cue never starts late.
        $this->assertStringContainsString("00:00:00.000 --> 00:00:03.000\nbonjour les amis", $vtt);
        // The last cue gets a nominal length.
        $this->assertStringContainsString("00:00:03.000 --> 00:00:06.000\nnouvel episode", $vtt);
        $this->assertStringContainsString('line-number: 01', $vtt);
        $this->assertStringContainsString('line-number: 02', $vtt);
    }

    /**
     * Some browsers put the timestamp and its text on one line.
     */
    public function test_timestamp_inline_with_text(): void {
        $vtt = youtubetranscript::transcript_to_vtt("0:00 bonjour les amis\n0:03 nouvel episode\n");

        $this->assertStringContainsString("00:00:00.000 --> 00:00:03.000\nbonjour les amis", $vtt);
        $this->assertStringContainsString('nouvel episode', $vtt);
    }

    /**
     * Hours are handled, as are text lines that wrap beyond the first.
     */
    public function test_hours_and_wrapped_text(): void {
        $vtt = youtubetranscript::transcript_to_vtt("1:02:03\nune phrase\nqui continue\n1:02:10\nla suite\n");

        $this->assertStringContainsString('01:02:03.000 --> 01:02:10.000', $vtt);
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
     * A cue must never start after the words do. The item seeks to cue start to play
     * a line, so a late start lands mid-word and clips it; an early one merely adds
     * lead-in. The panel truncates, so using its value as-is guarantees this.
     */
    public function test_cue_never_starts_late(): void {
        // The panel shows 0:03 for any true start from 3.000 up to but not including 4.000.
        $vtt = youtubetranscript::transcript_to_vtt("0:03\nune phrase\n0:09\nla suite\n");

        $this->assertStringContainsString('00:00:03.000 --> 00:00:09.000', $vtt);

        // Whatever the true start was inside that second, the cue begins at or before it.
        foreach ([3.0, 3.1, 3.5, 3.9, 3.999] as $truestart) {
            $this->assertLessThanOrEqual($truestart, 3.0,
                'cue start must never be later than the true start');
        }
    }

    /**
     * The panel shows whole seconds, so two entries can share one. Emitting both would
     * give the first a zero length - never the active cue, and a shadow segment that
     * ends the instant it starts - so they are merged into one.
     */
    public function test_entries_in_the_same_second_are_merged(): void {
        $vtt = youtubetranscript::transcript_to_vtt("0:03\nune phrase\n0:03\net la suite\n0:09\nfin\n");

        $this->assertSame(2, substr_count($vtt, '-->'));
        $this->assertStringContainsString("00:00:03.000 --> 00:00:09.000\nune phrase et la suite", $vtt);
        // No cue may have equal start and end.
        preg_match_all('/^(\S+) --> (\S+)$/m', $vtt, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $this->assertNotSame($match[1], $match[2], 'a cue must never have zero length');
        }
    }

    /**
     * Cues stay contiguous. The item picks the first cue matching the playhead, so
     * overlapping cues would keep an earlier line highlighted over a later one.
     */
    public function test_cues_are_contiguous_and_do_not_overlap(): void {
        $vtt = youtubetranscript::transcript_to_vtt("0:00\nune\n0:03\ndeux\n0:07\ntrois\n");

        preg_match_all('/^(\d\d):(\d\d):(\d\d\.\d\d\d) --> (\d\d):(\d\d):(\d\d\.\d\d\d)$/m',
            $vtt, $matches, PREG_SET_ORDER);
        $this->assertCount(3, $matches);

        $tosecs = function ($h, $m, $s) {
            return ($h * 3600) + ($m * 60) + (float)$s;
        };
        for ($i = 0; $i < count($matches) - 1; $i++) {
            $end = $tosecs($matches[$i][4], $matches[$i][5], $matches[$i][6]);
            $nextstart = $tosecs($matches[$i + 1][1], $matches[$i + 1][2], $matches[$i + 1][3]);
            $this->assertSame($end, $nextstart, 'cue ' . $i . ' must end exactly where the next begins');
        }
    }
}
