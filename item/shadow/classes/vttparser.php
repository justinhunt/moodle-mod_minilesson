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

namespace minilessonitem_shadow;

/**
 * WebVTT parser for the Video Shadowing item type.
 *
 * Parses standard WebVTT cues, plus enhanced cues containing inline
 * word-level timestamps, e.g. "<00:00:10.100>word". Cues without inline
 * timestamps get haswordtimings=false and an empty words array (the
 * player then highlights the whole line).
 *
 * @package    minilessonitem_shadow
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vttparser {

    /** Matches a WebVTT timestamp, e.g. 01:02:03.456 or 02:03.456 */
    const TIMESTAMP = '(?:\d{1,2}:)?\d{1,2}:\d{2}[\.,]\d{1,3}';

    /**
     * Parse WebVTT text into an array of cues.
     *
     * @param string $vtt the raw WebVTT text
     * @return array of cue arrays: [index, start, end, text, haswordtimings, words[[start, end, text]]]
     */
    public static function parse(string $vtt): array {
        $cues = [];
        if (trim($vtt) === '') {
            return $cues;
        }

        // Normalize line endings and strip the BOM.
        $vtt = str_replace(["\r\n", "\r"], "\n", $vtt);
        $vtt = preg_replace('/^\xEF\xBB\xBF/', '', $vtt);

        // Split into blocks on blank lines.
        $blocks = preg_split('/\n{2,}/', trim($vtt));
        $timingregex = '/^\s*(' . self::TIMESTAMP . ')\s*-->\s*(' . self::TIMESTAMP . ')/';

        foreach ($blocks as $block) {
            $lines = explode("\n", trim($block));
            if (empty($lines)) {
                continue;
            }
            // Skip the header and metadata blocks.
            $firstword = strtoupper(trim(explode(' ', trim($lines[0]))[0]));
            if (in_array($firstword, ['WEBVTT', 'NOTE', 'STYLE', 'REGION'])) {
                continue;
            }

            // Find the timing line (an optional cue identifier line may precede it).
            $timingmatch = false;
            $timinglineindex = -1;
            foreach ($lines as $i => $line) {
                if (preg_match($timingregex, $line, $timingmatch)) {
                    $timinglineindex = $i;
                    break;
                }
            }
            if ($timinglineindex === -1) {
                continue;
            }

            $cuestart = self::to_seconds($timingmatch[1]);
            $cueend = self::to_seconds($timingmatch[2]);
            $payload = trim(implode(' ', array_slice($lines, $timinglineindex + 1)));
            if ($payload === '' || $cueend <= $cuestart) {
                continue;
            }

            $cues[] = self::parse_cue_payload($payload, $cuestart, $cueend);
        }

        // Reindex and sort chronologically.
        usort($cues, function ($a, $b) {
            return $a['start'] <=> $b['start'];
        });
        foreach ($cues as $i => $unused) {
            $cues[$i]['index'] = $i;
        }
        return $cues;
    }

    /**
     * Parse a single cue payload into text and (optionally) timed words.
     *
     * @param string $payload the cue text, possibly with inline timestamps and markup
     * @param float $cuestart cue start in seconds
     * @param float $cueend cue end in seconds
     * @return array the cue array
     */
    protected static function parse_cue_payload(string $payload, float $cuestart, float $cueend): array {
        // Remove voice/class/styling tags but keep inline timestamps.
        $cleaned = preg_replace('/<(?!\/?(?:\d{1,2}:)?\d)[^>]*>/u', '', $payload);

        $words = [];
        $haswordtimings = strpos($cleaned, '<') !== false;
        if ($haswordtimings) {
            // Split into [text][<timestamp>text][<timestamp>text]... chunks.
            $parts = preg_split('/<(' . self::TIMESTAMP . ')>/u', $cleaned, -1,
                PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            $currentstart = $cuestart;
            foreach ($parts as $part) {
                if (preg_match('/^' . self::TIMESTAMP . '$/', trim($part))) {
                    $currentstart = self::to_seconds(trim($part));
                    continue;
                }
                foreach (preg_split('/\s+/u', trim($part), -1, PREG_SPLIT_NO_EMPTY) as $wordtext) {
                    $words[] = ['start' => $currentstart, 'end' => $cueend, 'text' => $wordtext];
                }
            }
            // Each word ends where the next begins; the last ends at cue end.
            $wordcount = count($words);
            for ($i = 0; $i < $wordcount - 1; $i++) {
                $words[$i]['end'] = $words[$i + 1]['start'];
            }
            if ($wordcount === 0) {
                $haswordtimings = false;
            }
        }

        $text = trim(preg_replace('/\s+/u', ' ', preg_replace('/<[^>]*>/u', '', $cleaned)));

        return [
            'index' => 0,
            'start' => $cuestart,
            'end' => $cueend,
            'text' => $text,
            'haswordtimings' => $haswordtimings,
            'words' => $words,
        ];
    }

    /**
     * Convert a WebVTT timestamp to seconds.
     *
     * @param string $timestamp e.g. "01:02:03.456" or "02:03.456"
     * @return float seconds
     */
    protected static function to_seconds(string $timestamp): float {
        $timestamp = str_replace(',', '.', $timestamp);
        $bits = explode(':', $timestamp);
        $seconds = 0.0;
        foreach ($bits as $bit) {
            $seconds = $seconds * 60 + (float)$bit;
        }
        return round($seconds, 3);
    }
}
