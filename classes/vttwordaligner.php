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
 * Adds word-level timestamps to YouTube subtitles.
 *
 * Combines two sources: the clean, punctuated cue text of a (usually
 * manually created) WebVTT caption track, and the per-word timings of the
 * video's auto-generated (ASR) caption track in timedtext json3 format.
 * The words of each cue are aligned against the ASR words that fall inside
 * the cue's time window, and the cue payload is rewritten with inline
 * WebVTT timestamps, e.g. "Welcome <00:00:01.400>back, <00:00:01.700>to ...",
 * which the shadow item's vttparser/player understand as word timings.
 *
 * Alignment is best effort. Cues that cannot be aligned confidently (for
 * example languages written without spaces, or text that has drifted too
 * far from the ASR transcript) are left untouched, and the shadow player
 * falls back to line-level highlighting for them.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class vttwordaligner {
    /** @var int how far (ms) outside a cue's window an ASR word may sit and still belong to the cue */
    const SLACKMS = 2000;

    /** @var float the minimum share of a cue's words that must match ASR words for the cue to be tagged */
    const MINMATCHRATIO = 0.5;

    /** @var int forced minimum gap (ms) between out-of-order word timestamps in a cue */
    const MONOTONICSTEPMS = 10;

    /** Matches a WebVTT timestamp, e.g. 01:02:03.456 or 02:03.456 */
    const TIMESTAMP = '(?:\d{1,2}:)?\d{1,2}:\d{2}[\.,]\d{1,3}';

    /**
     * Inject word-level timestamps into a line-level WebVTT using the word
     * timings of an ASR json3 transcript of the same video.
     *
     * Header, NOTE/STYLE/REGION blocks, cue identifiers and cue timing lines
     * (including cue settings) are preserved. Cues that already contain
     * inline timestamps are left alone. Multi-line cue payloads are reflowed
     * onto a single line (the shadow player joins lines anyway) and any
     * styling tags in rewritten payloads are dropped.
     *
     * @param string $vtt the line-level WebVTT text
     * @param string $json3 the ASR track's timedtext json3 response body
     * @return string the WebVTT text with inline word timestamps where alignment succeeded
     */
    public static function enhance_manual_vtt(string $vtt, string $json3): string {
        $asrwords = self::flatten_json3($json3);
        if (empty($asrwords)) {
            return $vtt;
        }

        $vtt = str_replace(["\r\n", "\r"], "\n", $vtt);
        $blocks = preg_split('/\n{2,}/', trim($vtt));
        $timingregex = '/^\s*(' . self::TIMESTAMP . ')\s*-->\s*(' . self::TIMESTAMP . ')/';

        $cursor = 0;
        $total = count($asrwords);
        foreach ($blocks as $i => $block) {
            $lines = explode("\n", trim($block));

            // Find the timing line (an optional cue identifier line may precede it).
            $timinglineindex = -1;
            $timingmatch = null;
            foreach ($lines as $j => $line) {
                if (preg_match($timingregex, $line, $timingmatch)) {
                    $timinglineindex = $j;
                    break;
                }
            }
            if ($timinglineindex === -1) {
                // Not a cue (header, NOTE, STYLE...) - leave it alone.
                continue;
            }

            $payload = trim(implode(' ', array_slice($lines, $timinglineindex + 1)));
            if ($payload === '' || preg_match('/<' . self::TIMESTAMP . '>/', $payload)) {
                // Empty, or already carries inline timestamps.
                continue;
            }

            $cuestartms = self::to_ms($timingmatch[1]);
            $cueendms = self::to_ms($timingmatch[2]);
            if ($cueendms <= $cuestartms) {
                continue;
            }

            // ASR words that ended well before this cue belong to no cue - skip them.
            while ($cursor < $total && $asrwords[$cursor]['ms'] < $cuestartms - self::SLACKMS) {
                $cursor++;
            }
            // The cue's candidate ASR words are those from the cursor up to the cue end plus slack.
            $from = $cursor;
            $to = $from;
            while ($to < $total && $asrwords[$to]['ms'] <= $cueendms + self::SLACKMS) {
                $to++;
            }

            // Tokenize the payload into words, dropping styling tags but keeping punctuation.
            $cleantext = preg_replace('/<[^>]*>/u', '', $payload);
            $cuewords = preg_split('/\s+/u', trim($cleantext), -1, PREG_SPLIT_NO_EMPTY);
            if (count($cuewords) < 2 || $from >= $to) {
                // Single-token cues (e.g. scripts without spaces) cannot be word-aligned.
                continue;
            }

            $aligned = self::align_cue($cuewords, $cuestartms, $cueendms, $asrwords, $from, $to);
            if ($aligned === null) {
                // No confident alignment; consume the window and leave the cue untouched.
                while ($cursor < $total && $asrwords[$cursor]['ms'] < $cueendms) {
                    $cursor++;
                }
                continue;
            }
            [$times, $lastmatched] = $aligned;
            $cursor = max($cursor, $lastmatched + 1);

            $newpayload = [];
            foreach ($cuewords as $k => $word) {
                if ($k === 0 && $times[$k] <= $cuestartms) {
                    // The first word starts with the cue, so it needs no explicit tag.
                    $newpayload[] = $word;
                } else {
                    $newpayload[] = '<' . self::format_timestamp($times[$k]) . '>' . $word;
                }
            }
            $blocks[$i] = implode("\n", array_slice($lines, 0, $timinglineindex + 1)) .
                "\n" . implode(' ', $newpayload);
        }
        return implode("\n\n", $blocks) . "\n";
    }

    /**
     * Build a clean word-level WebVTT directly from an ASR json3 transcript.
     *
     * YouTube's native VTT rendering of ASR tracks is the rolled-up live
     * caption format (each line duplicated across cues, <c> tags, position
     * settings). The json3 events carry the same content cleanly: one cue
     * per event with per-word offsets.
     *
     * @param string $json3 the ASR track's timedtext json3 response body
     * @param bool $wordtags whether to include the inline word timestamps
     * @return string a WebVTT document, or '' if the json3 held no usable events
     */
    public static function build_vtt_from_json3(string $json3, bool $wordtags = true): string {
        $data = json_decode($json3, true);
        if (!is_array($data) || empty($data['events'])) {
            return '';
        }

        $events = [];
        foreach ($data['events'] as $event) {
            // Roll-up 'aAppend' events re-emit the previous line for the live display - skip them.
            if (!empty($event['aAppend']) || empty($event['segs'])) {
                continue;
            }
            $startms = (int)($event['tStartMs'] ?? 0);
            $words = [];
            foreach ($event['segs'] as $seg) {
                $text = trim(preg_replace('/\s+/u', ' ', $seg['utf8'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $words[] = ['text' => $text, 'ms' => $startms + (int)($seg['tOffsetMs'] ?? 0)];
            }
            if (empty($words)) {
                continue;
            }
            $events[] = [
                'start' => $startms,
                'end' => $startms + (int)($event['dDurationMs'] ?? 0),
                'words' => $words,
            ];
        }
        if (empty($events)) {
            return '';
        }

        // ASR event durations often overlap the next event (roll-up display); trim them.
        $count = count($events);
        for ($i = 0; $i < $count - 1; $i++) {
            if ($events[$i]['end'] > $events[$i + 1]['start']) {
                $events[$i]['end'] = $events[$i + 1]['start'];
            }
        }

        $out = ['WEBVTT'];
        foreach ($events as $event) {
            if ($event['end'] <= $event['start']) {
                continue;
            }
            $line = [];
            $prevms = $event['start'];
            foreach ($event['words'] as $k => $word) {
                $ms = min(max($word['ms'], $event['start']), $event['end']);
                if ($k > 0 && $ms < $prevms) {
                    $ms = min($prevms + self::MONOTONICSTEPMS, $event['end']);
                }
                if (!$wordtags || ($k === 0 && $ms <= $event['start'])) {
                    $line[] = $word['text'];
                } else {
                    $line[] = '<' . self::format_timestamp($ms) . '>' . $word['text'];
                }
                $prevms = $ms;
            }
            $out[] = self::format_timestamp($event['start']) . ' --> ' . self::format_timestamp($event['end']) .
                "\n" . implode(' ', $line);
        }
        if (count($out) < 2) {
            return '';
        }
        return implode("\n\n", $out) . "\n";
    }

    /**
     * Flatten a json3 transcript into a chronological list of timed words.
     *
     * @param string $json3 the timedtext json3 response body
     * @return array of ['text' => original word, 'token' => normalized word, 'ms' => absolute start ms]
     */
    protected static function flatten_json3(string $json3): array {
        $data = json_decode($json3, true);
        if (!is_array($data) || empty($data['events'])) {
            return [];
        }
        $words = [];
        foreach ($data['events'] as $event) {
            if (!empty($event['aAppend']) || empty($event['segs'])) {
                continue;
            }
            $startms = (int)($event['tStartMs'] ?? 0);
            foreach ($event['segs'] as $seg) {
                $text = trim($seg['utf8'] ?? '');
                if ($text === '') {
                    continue;
                }
                $ms = $startms + (int)($seg['tOffsetMs'] ?? 0);
                foreach (preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $word) {
                    $words[] = ['text' => $word, 'token' => self::normalize_token($word), 'ms' => $ms];
                }
            }
        }
        // Keep document order: it is the spoken word sequence the alignment relies on,
        // even if an odd seg offset makes the timestamps locally non-monotonic.
        return $words;
    }

    /**
     * Align one cue's words against its candidate ASR words and compute a start time per word.
     *
     * Matched and 1:1-replaced words take the ASR timestamp; unmatched (human-added)
     * words are linearly interpolated between their timed neighbours. All times are
     * clamped to the cue window and forced monotonically non-decreasing.
     *
     * @param array $cuewords the cue's words (original text, punctuation kept)
     * @param int $cuestartms cue start in ms
     * @param int $cueendms cue end in ms
     * @param array $asrwords all timed ASR words (see flatten_json3)
     * @param int $from index of the first candidate ASR word
     * @param int $to index one past the last candidate ASR word
     * @return array|null [times per cue word (ms), index of the last matched ASR word],
     *     or null if the alignment was not confident enough
     */
    protected static function align_cue(
        array $cuewords,
        int $cuestartms,
        int $cueendms,
        array $asrwords,
        int $from,
        int $to
    ): ?array {
        $manualtokens = array_map([self::class, 'normalize_token'], $cuewords);
        $asrtokens = [];
        for ($i = $from; $i < $to; $i++) {
            $asrtokens[] = $asrwords[$i]['token'];
        }

        $wordcount = count($cuewords);
        $times = array_fill(0, $wordcount, null);
        $matched = 0;
        $lastmatched = $from - 1;
        foreach (self::lcs_opcodes($asrtokens, $manualtokens) as $op) {
            [$tag, $i1, $i2, $j1, $j2] = $op;
            if ($tag === 'equal') {
                for ($k = 0; $k < $i2 - $i1; $k++) {
                    $times[$j1 + $k] = $asrwords[$from + $i1 + $k]['ms'];
                    $matched++;
                }
                $lastmatched = $from + $i2 - 1;
            } else if ($tag === 'replace') {
                // A rewritten stretch - pair the words up positionally and keep the ASR
                // timing, but as a guess only: pairs do not count towards the match ratio.
                $pairs = min($i2 - $i1, $j2 - $j1);
                for ($k = 0; $k < $pairs; $k++) {
                    $times[$j1 + $k] = $asrwords[$from + $i1 + $k]['ms'];
                }
                $lastmatched = max($lastmatched, $from + $i1 + $pairs - 1);
            }
            // Words in 'delete' ops (ASR-only words) contribute nothing; words in
            // 'insert' ops (human-added words) are interpolated below.
        }
        if ($matched < 2 || $matched / $wordcount < self::MINMATCHRATIO) {
            return null;
        }

        // Clamp the matched times to the cue window before interpolating between them.
        foreach ($times as $k => $time) {
            if ($time !== null) {
                $times[$k] = min(max($time, $cuestartms), $cueendms);
            }
        }

        // Interpolate human-added words evenly between their timed neighbours.
        $k = 0;
        while ($k < $wordcount) {
            if ($times[$k] !== null) {
                $k++;
                continue;
            }
            $gapstart = $k;
            while ($k < $wordcount && $times[$k] === null) {
                $k++;
            }
            $prev = $gapstart > 0 ? $times[$gapstart - 1] : $cuestartms;
            $next = $k < $wordcount ? $times[$k] : $cueendms;
            $gaplen = $k - $gapstart;
            for ($g = 0; $g < $gaplen; $g++) {
                $times[$gapstart + $g] = (int)round($prev + ($next - $prev) * ($g + 1) / ($gaplen + 1));
            }
        }

        // Timestamps inside a cue must never decrease.
        for ($k = 1; $k < $wordcount; $k++) {
            if ($times[$k] < $times[$k - 1]) {
                $times[$k] = min($times[$k - 1] + self::MONOTONICSTEPMS, $cueendms);
            }
        }

        return [$times, $lastmatched];
    }

    /**
     * Compute difflib-style opcodes between two token sequences using a
     * longest-common-subsequence table.
     *
     * Token equality is fuzzy (see tokens_match) so small ASR errors still
     * count as matches. Empty tokens (e.g. words that were pure punctuation)
     * never match. Adjacent delete+insert runs are merged into a single
     * 'replace' op.
     *
     * @param array $a the first token sequence (the timing source)
     * @param array $b the second token sequence (the text source)
     * @return array of [tag, i1, i2, j1, j2] with tag one of equal/replace/delete/insert
     */
    protected static function lcs_opcodes(array $a, array $b): array {
        $n = count($a);
        $m = count($b);

        // The table cell at [i][j] holds the LCS length of the suffixes starting at i and j.
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                if (self::tokens_match($a[$i], $b[$j])) {
                    $lcs[$i][$j] = $lcs[$i + 1][$j + 1] + 1;
                } else {
                    $lcs[$i][$j] = max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
                }
            }
        }

        // Walk the table, emitting one raw op per token.
        $raw = [];
        $i = 0;
        $j = 0;
        while ($i < $n || $j < $m) {
            if ($i < $n && $j < $m && self::tokens_match($a[$i], $b[$j])) {
                $raw[] = 'equal';
                $i++;
                $j++;
            } else if ($j < $m && ($i === $n || $lcs[$i][$j + 1] >= $lcs[$i + 1][$j])) {
                $raw[] = 'insert';
                $j++;
            } else {
                $raw[] = 'delete';
                $i++;
            }
        }

        // Group the raw ops into ranged opcodes, merging mixed delete/insert runs into 'replace'.
        $ops = [];
        $i = 0;
        $j = 0;
        $idx = 0;
        $rawcount = count($raw);
        while ($idx < $rawcount) {
            $i1 = $i;
            $j1 = $j;
            if ($raw[$idx] === 'equal') {
                while ($idx < $rawcount && $raw[$idx] === 'equal') {
                    $i++;
                    $j++;
                    $idx++;
                }
                $ops[] = ['equal', $i1, $i, $j1, $j];
            } else {
                while ($idx < $rawcount && $raw[$idx] !== 'equal') {
                    if ($raw[$idx] === 'delete') {
                        $i++;
                    } else {
                        $j++;
                    }
                    $idx++;
                }
                if ($i > $i1 && $j > $j1) {
                    $tag = 'replace';
                } else if ($i > $i1) {
                    $tag = 'delete';
                } else {
                    $tag = 'insert';
                }
                $ops[] = [$tag, $i1, $i, $j1, $j];
            }
        }
        return $ops;
    }

    /**
     * Decide whether two comparison tokens refer to the same spoken word.
     *
     * Exact matches always count. For tokens of three or more characters a
     * small Levenshtein distance also counts, so typical ASR misspellings
     * ("bac"/"back", "chanel"/"channel") align as matches rather than
     * replacements. levenshtein() is byte-based, so fuzzy matching is
     * restricted to single-byte (ASCII) tokens.
     *
     * @param string $a the first token
     * @param string $b the second token
     * @return bool whether the tokens match
     */
    protected static function tokens_match(string $a, string $b): bool {
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        if (strlen($a) !== \core_text::strlen($a) || strlen($b) !== \core_text::strlen($b)) {
            return false;
        }
        $minlen = min(strlen($a), strlen($b));
        if ($minlen < 3) {
            return false;
        }
        $maxdistance = $minlen <= 5 ? 1 : 2;
        return levenshtein($a, $b) <= $maxdistance;
    }

    /**
     * Normalize a word into a comparison token: strip punctuation, symbols
     * and invisible characters, and lowercase.
     *
     * @param string $text the word
     * @return string the token; may be '' if the word was pure punctuation
     */
    protected static function normalize_token(string $text): string {
        $text = preg_replace('/[\p{P}\p{S}\p{C}]+/u', '', $text);
        return trim(\core_text::strtolower($text));
    }

    /**
     * Convert a WebVTT timestamp to milliseconds.
     *
     * @param string $timestamp e.g. "01:02:03.456" or "02:03.456"
     * @return int milliseconds
     */
    protected static function to_ms(string $timestamp): int {
        $timestamp = str_replace(',', '.', trim($timestamp));
        $seconds = 0.0;
        foreach (explode(':', $timestamp) as $bit) {
            $seconds = $seconds * 60 + (float)$bit;
        }
        return (int)round($seconds * 1000);
    }

    /**
     * Format milliseconds as a WebVTT timestamp, e.g. "00:01:02.345".
     *
     * @param int $ms milliseconds
     * @return string the timestamp
     */
    protected static function format_timestamp(int $ms): string {
        $ms = max(0, $ms);
        $hours = intdiv($ms, 3600000);
        $minutes = intdiv($ms % 3600000, 60000);
        $seconds = intdiv($ms % 60000, 1000);
        return sprintf('%02d:%02d:%02d.%03d', $hours, $minutes, $seconds, $ms % 1000);
    }
}
