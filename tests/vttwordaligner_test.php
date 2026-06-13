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
 * Tests for the word-level subtitle alignment engine.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_minilesson\vttwordaligner
 */
final class vttwordaligner_test extends \basic_testcase {
    /**
     * Build a json3 body from a compact event description.
     *
     * @param array $events each ['start' => ms, 'dur' => ms, 'segs' => [[text, offsetms?]...], 'append' => bool?]
     * @return string the json3 body
     */
    protected function make_json3(array $events): string {
        $out = ['events' => []];
        foreach ($events as $event) {
            $segs = [];
            foreach ($event['segs'] as $seg) {
                $segdata = ['utf8' => $seg[0]];
                if (isset($seg[1])) {
                    $segdata['tOffsetMs'] = $seg[1];
                }
                $segs[] = $segdata;
            }
            $eventdata = [
                'tStartMs' => $event['start'],
                'dDurationMs' => $event['dur'],
                'segs' => $segs,
            ];
            if (!empty($event['append'])) {
                $eventdata['aAppend'] = 1;
            }
            $out['events'][] = $eventdata;
        }
        return json_encode($out);
    }

    /**
     * The json3 of the spec's worked example: ASR words with typos.
     *
     * @return string the json3 body
     */
    protected function example_json3(): string {
        return $this->make_json3([
            ['start' => 1000, 'dur' => 2000, 'segs' => [
                ['welcome '], ['bac ', 400], ['to ', 700], ['the ', 900], ['chanel', 1100],
            ]],
        ]);
    }

    /**
     * Matched and replaced (ASR typo) words take the ASR timestamps; the first
     * word starts with the cue so it gets no tag.
     */
    public function test_enhance_basic_match_and_replace(): void {
        $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nWelcome back, to the channel!\n";
        $enhanced = vttwordaligner::enhance_manual_vtt($vtt, $this->example_json3());

        $expected = "00:00:01.000 --> 00:00:03.000\n" .
            'Welcome <00:00:01.400>back, <00:00:01.700>to <00:00:01.900>the <00:00:02.100>channel!';
        $this->assertStringContainsString($expected, $enhanced);
        $this->assertStringStartsWith('WEBVTT', $enhanced);
    }

    /**
     * A human-added word the ASR missed is interpolated between its timed neighbours.
     */
    public function test_enhance_insert_interpolation(): void {
        $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nWelcome back, to the great channel!\n";
        $enhanced = vttwordaligner::enhance_manual_vtt($vtt, $this->example_json3());

        // The word "great" sits between the (1900ms) and channel (2100ms): midpoint 2000ms.
        $this->assertStringContainsString('<00:00:01.900>the <00:00:02.000>great <00:00:02.100>channel!', $enhanced);
    }

    /**
     * ASR words with no equivalent in the clean text (hallucinations) are skipped.
     */
    public function test_enhance_delete_asr_extra_word(): void {
        $json3 = $this->make_json3([
            ['start' => 1000, 'dur' => 2000, 'segs' => [
                ['welcome '], ['um ', 200], ['back ', 400], ['to ', 700], ['the ', 900], ['channel', 1100],
            ]],
        ]);
        $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nWelcome back, to the channel!\n";
        $enhanced = vttwordaligner::enhance_manual_vtt($vtt, $json3);

        $this->assertStringNotContainsString('um', $enhanced);
        $this->assertStringContainsString('Welcome <00:00:01.400>back,', $enhanced);
    }

    /**
     * Word timestamps are clamped to the cue window and never decrease within a cue.
     */
    public function test_enhance_clamping_and_monotonic(): void {
        // ASR start (800ms) is before the cue start; "the" (900ms) regresses behind "to" (950ms).
        $json3 = $this->make_json3([
            ['start' => 800, 'dur' => 2200, 'segs' => [
                ['welcome '], ['back ', 300], ['to ', 150], ['the ', 100], ['channel', 1300],
            ]],
        ]);
        $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nWelcome back, to the channel!\n";
        $enhanced = vttwordaligner::enhance_manual_vtt($vtt, $json3);

        // Welcome at 800ms clamps to the cue start 1000ms, so it carries no tag.
        $this->assertStringContainsString("00:00:03.000\nWelcome <", $enhanced);
        // Then back: 1100ms; to: 950ms regresses, forced to 1110ms; the: 900ms regresses, forced to 1120ms.
        $expected = '<00:00:01.100>back, <00:00:01.110>to <00:00:01.120>the <00:00:02.100>channel!';
        $this->assertStringContainsString($expected, $enhanced);
    }

    /**
     * Cues whose text cannot be matched confidently are left untouched.
     */
    public function test_enhance_quality_gate_leaves_unmatched_cue_alone(): void {
        $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nCompletely different words here altogether\n";
        $enhanced = vttwordaligner::enhance_manual_vtt($vtt, $this->example_json3());

        $expected = "00:00:01.000 --> 00:00:03.000\nCompletely different words here altogether";
        $this->assertStringContainsString($expected, $enhanced);
    }

    /**
     * Single-token cues (e.g. scripts written without spaces) are left untouched.
     */
    public function test_enhance_spaceless_script_falls_back(): void {
        $json3 = $this->make_json3([
            ['start' => 1000, 'dur' => 2000, 'segs' => [['こんにちは'], ['世界', 800]]],
        ]);
        $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nこんにちは、世界\n";
        $enhanced = vttwordaligner::enhance_manual_vtt($vtt, $json3);

        $this->assertStringContainsString("00:00:01.000 --> 00:00:03.000\nこんにちは、世界", $enhanced);
    }

    /**
     * Header, NOTE blocks, cue identifiers and cue settings survive; cues that
     * already carry inline timestamps are not re-processed.
     */
    public function test_enhance_preserves_structure(): void {
        $vtt = "WEBVTT\n\nNOTE hands off\n\nintro\n00:00:01.000 --> 00:00:03.000 align:start\n" .
            "Welcome back, to the channel!\n\n00:00:04.000 --> 00:00:05.000\nAlready <00:00:04.500>tagged\n";
        $enhanced = vttwordaligner::enhance_manual_vtt($vtt, $this->example_json3());

        $this->assertStringContainsString("NOTE hands off", $enhanced);
        $this->assertStringContainsString("intro\n00:00:01.000 --> 00:00:03.000 align:start\n", $enhanced);
        $this->assertStringContainsString('Welcome <00:00:01.400>back,', $enhanced);
        $this->assertStringContainsString("Already <00:00:04.500>tagged", $enhanced);
    }

    /**
     * Empty and whitespace-only segs must not crash the parser or pollute the output.
     */
    public function test_enhance_resilient_to_empty_segs(): void {
        $json3 = $this->make_json3([
            ['start' => 1000, 'dur' => 2000, 'segs' => [
                [''], ['welcome '], ["\n", 100], ['back ', 400], ['to ', 700], ['the ', 900], ['channel', 1100],
            ]],
        ]);
        $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nWelcome back, to the channel!\n";
        $enhanced = vttwordaligner::enhance_manual_vtt($vtt, $json3);

        $this->assertStringContainsString('Welcome <00:00:01.400>back,', $enhanced);
    }

    /**
     * Unusable json3 leaves the VTT unchanged.
     */
    public function test_enhance_with_bad_json3_returns_input(): void {
        $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nWelcome back, to the channel!\n";
        $this->assertSame($vtt, vttwordaligner::enhance_manual_vtt($vtt, 'not json'));
        $this->assertSame($vtt, vttwordaligner::enhance_manual_vtt($vtt, '{"events":[]}'));
    }

    /**
     * Building a VTT from ASR json3: one cue per event with word tags, roll-up
     * append events skipped, overlapping event durations trimmed.
     */
    public function test_build_vtt_from_json3(): void {
        $json3 = $this->make_json3([
            // Overlaps the next event by 500ms - its end must be trimmed to 3000ms.
            ['start' => 1000, 'dur' => 2500, 'segs' => [['welcome '], ['back', 400]]],
            ['start' => 3000, 'dur' => 1000, 'segs' => [["\n"]], 'append' => true],
            ['start' => 3000, 'dur' => 2000, 'segs' => [['to '], ['the ', 300], ['channel', 600]]],
        ]);
        $built = vttwordaligner::build_vtt_from_json3($json3);

        $this->assertStringStartsWith("WEBVTT\n", $built);
        $this->assertStringContainsString("00:00:01.000 --> 00:00:03.000\nwelcome <00:00:01.400>back", $built);
        $expected = "00:00:03.000 --> 00:00:05.000\nto <00:00:03.300>the <00:00:03.600>channel";
        $this->assertStringContainsString($expected, $built);
    }

    /**
     * With word tags disabled the built VTT is still clean but holds plain cue text.
     */
    public function test_build_vtt_from_json3_without_word_tags(): void {
        $json3 = $this->make_json3([
            ['start' => 1000, 'dur' => 2000, 'segs' => [['welcome '], ['back', 400]]],
        ]);
        $built = vttwordaligner::build_vtt_from_json3($json3, false);

        $this->assertStringContainsString("00:00:01.000 --> 00:00:03.000\nwelcome back", $built);
        $this->assertStringNotContainsString('<00:', $built);
    }

    /**
     * Unusable json3 yields an empty string so callers can fall back.
     */
    public function test_build_vtt_from_bad_json3(): void {
        $this->assertSame('', vttwordaligner::build_vtt_from_json3('not json'));
        $this->assertSame('', vttwordaligner::build_vtt_from_json3('{"events":[]}'));
    }

    /**
     * The enhanced output must parse as word timings in the shadow item's parser.
     */
    public function test_output_parses_in_shadow_vttparser(): void {
        $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nWelcome back, to the channel!\n";
        $enhanced = vttwordaligner::enhance_manual_vtt($vtt, $this->example_json3());

        $cues = \minilessonitem_shadow\vttparser::parse($enhanced);
        $this->assertCount(1, $cues);
        $this->assertTrue($cues[0]['haswordtimings']);
        $this->assertSame(['Welcome', 'back,', 'to', 'the', 'channel!'], array_column($cues[0]['words'], 'text'));
        $this->assertSame(1.0, $cues[0]['words'][0]['start']);
        $this->assertSame(1.4, $cues[0]['words'][1]['start']);
        $this->assertSame('Welcome back, to the channel!', $cues[0]['text']);
    }

    /**
     * The enhanced output must survive the cue-numbering pass of the fetcher.
     */
    public function test_output_survives_add_cue_identifiers(): void {
        $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nWelcome back, to the channel!\n";
        $enhanced = vttwordaligner::enhance_manual_vtt($vtt, $this->example_json3());
        $numbered = youtubetranscript::add_cue_identifiers($enhanced);

        $expected = "line-number: 01\n00:00:01.000 --> 00:00:03.000\nWelcome <00:00:01.400>back,";
        $this->assertStringContainsString($expected, $numbered);
    }
}
