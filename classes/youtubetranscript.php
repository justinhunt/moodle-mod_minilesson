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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Fetches subtitles/transcripts of YouTube videos.
 *
 * PHP port of forclaude/extract_transcript.py (youtube_transcript_api):
 * the caption track list is requested from YouTube's InnerTube player API,
 * a track is chosen by language preference (manually created tracks first,
 * then auto-generated), and its content is downloaded as WebVTT and/or
 * plain text.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class youtubetranscript {
    /** @var string return WebVTT subtitle text */
    const FORMAT_VTT = 'vtt';

    /** @var string return the plain text transcript */
    const FORMAT_TRANSCRIPT = 'transcript';

    /** @var string return both WebVTT and plain text */
    const FORMAT_BOTH = 'both';

    /** @var string the InnerTube player endpoint */
    const INNERTUBE_URL = 'https://www.youtube.com/youtubei/v1/player?prettyPrint=false';

    /** @var string the InnerTube client version we present as */
    const CLIENT_VERSION = '20.10.38';

    /** @var string the user agent matching the InnerTube client */
    const USERAGENT = 'com.google.android.youtube/20.10.38 (Linux; U; Android 11) gzip';

    /** @var string[] default language preference order */
    const DEFAULT_LANGS = ['en', 'en-GB', 'en-US'];

    /**
     * Extract the video ID from a YouTube URL, or accept a bare video ID.
     * Handles standard urls, shortened youtu.be urls, and embed urls.
     *
     * @param string $urlorid a YouTube URL or an 11 character video ID
     * @return string|null the video ID, or null if none could be extracted
     */
    public static function get_video_id(string $urlorid): ?string {
        $urlorid = trim($urlorid);
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $urlorid)) {
            return $urlorid;
        }
        $pattern = '~(?:https?://)?(?:www\.)?(?:youtube\.com/(?:[^/\n\s]+/\S+/|(?:v|e(?:mbed)?)/|\S*?[?&]v=)|youtu\.be/)' .
            '([a-zA-Z0-9_-]{11})~';
        if (preg_match($pattern, $urlorid, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Fetch the subtitles of a YouTube video.
     *
     * @param string $urlorid a YouTube URL or video ID
     * @param string $format one of the FORMAT_* constants
     * @param array $preflangs language codes in preference order
     * @param bool $wordtimestamps whether to add word-level timestamps to the VTT (best effort)
     * @return array with 'vtt' and/or 'transcript' keys depending on $format
     * @throws \moodle_exception error:invalidyoutubeurl, error:noyoutubetranscript or error:youtubefetchfailed
     */
    public function fetch(
        string $urlorid,
        string $format = self::FORMAT_VTT,
        array $preflangs = self::DEFAULT_LANGS,
        bool $wordtimestamps = true
    ): array {
        $videoid = self::get_video_id($urlorid);
        if ($videoid === null) {
            throw new \moodle_exception('error:invalidyoutubeurl', constants::M_COMPONENT);
        }

        $tracks = $this->fetch_caption_tracks($videoid);
        if (empty($tracks)) {
            throw new \moodle_exception('error:noyoutubetranscript', constants::M_COMPONENT);
        }
        $track = self::pick_track($tracks, $preflangs);
        $baseurl = $track['baseUrl'] ?? '';

        $result = [];
        if ($format === self::FORMAT_VTT || $format === self::FORMAT_BOTH) {
            $vtt = $this->fetch_track($baseurl, 'vtt');
            if (trim($vtt) === '' || strpos($vtt, 'WEBVTT') === false) {
                throw new \moodle_exception('error:noyoutubetranscript', constants::M_COMPONENT);
            }
            $vtt = $this->add_word_timestamps($vtt, $track, $tracks, $wordtimestamps);
            $result['vtt'] = self::add_cue_identifiers($vtt);
        }
        if ($format === self::FORMAT_TRANSCRIPT || $format === self::FORMAT_BOTH) {
            $json3 = $this->fetch_track($baseurl, 'json3');
            $text = self::json3_to_text($json3);
            if (trim($text) === '') {
                throw new \moodle_exception('error:noyoutubetranscript', constants::M_COMPONENT);
            }
            $result['transcript'] = $text;
        }
        return $result;
    }

    /**
     * Ask the InnerTube player API for the video's caption track list.
     *
     * @param string $videoid the video ID
     * @return array the caption tracks (each with baseUrl, languageCode, kind ...)
     * @throws \moodle_exception error:youtubefetchfailed
     */
    protected function fetch_caption_tracks(string $videoid): array {
        $body = json_encode([
            'context' => [
                'client' => [
                    'clientName' => 'ANDROID',
                    'clientVersion' => self::CLIENT_VERSION,
                    'androidSdkVersion' => 30,
                    'hl' => 'en',
                ],
            ],
            'videoId' => $videoid,
        ]);

        $curl = new \curl();
        $curl->setHeader(['Content-Type: application/json']);
        $response = $curl->post(self::INNERTUBE_URL, $body, [
            'CURLOPT_USERAGENT' => self::USERAGENT,
            'CURLOPT_TIMEOUT' => 30,
        ]);
        if ($curl->get_errno() !== 0) {
            throw new \moodle_exception('error:youtubefetchfailed', constants::M_COMPONENT);
        }

        $playerresponse = json_decode($response, true);
        if (!is_array($playerresponse)) {
            throw new \moodle_exception('error:youtubefetchfailed', constants::M_COMPONENT);
        }
        return $playerresponse['captions']['playerCaptionsTracklistRenderer']['captionTracks'] ?? [];
    }

    /**
     * Choose a caption track: manually created tracks in a preferred language win,
     * then auto-generated tracks in a preferred language, then the first track.
     *
     * @param array $tracks the caption tracks
     * @param array $preflangs language codes in preference order
     * @return array the chosen track
     */
    protected static function pick_track(array $tracks, array $preflangs): array {
        foreach ($preflangs as $lang) {
            foreach ($tracks as $track) {
                if (($track['languageCode'] ?? '') === $lang && ($track['kind'] ?? '') !== 'asr') {
                    return $track;
                }
            }
        }
        foreach ($preflangs as $lang) {
            foreach ($tracks as $track) {
                if (($track['languageCode'] ?? '') === $lang) {
                    return $track;
                }
            }
        }
        return $tracks[0];
    }

    /**
     * Add word-level timestamps to a fetched VTT, best effort.
     *
     * Word timings only exist on auto-generated (ASR) tracks. If the chosen
     * track is itself ASR, a clean word-level VTT is rebuilt from its json3
     * events (its native VTT is the rolled-up live-caption format). If the
     * chosen track is manually created, the ASR track in the same language
     * supplies the timings and vttwordaligner merges them into the manual
     * text. On any failure the plain VTT is returned unchanged.
     *
     * With $wordtimestamps false no word timings are added, but an ASR
     * track's VTT is still rebuilt from json3 (without the inline tags),
     * since its native VTT is the rolled-up format.
     *
     * @param string $vtt the fetched WebVTT text
     * @param array $track the chosen caption track
     * @param array $tracks all caption tracks of the video
     * @param bool $wordtimestamps whether to add word-level timestamps
     * @return string the WebVTT text, with inline word timestamps where possible
     */
    protected function add_word_timestamps(string $vtt, array $track, array $tracks, bool $wordtimestamps = true): string {
        try {
            if (($track['kind'] ?? '') === 'asr') {
                $json3 = $this->fetch_track($track['baseUrl'] ?? '', 'json3');
                $enhanced = vttwordaligner::build_vtt_from_json3($json3, $wordtimestamps);
            } else {
                if (!$wordtimestamps) {
                    return $vtt;
                }
                $asrtrack = self::pick_asr_track($tracks, $track['languageCode'] ?? '');
                if ($asrtrack === null) {
                    return $vtt;
                }
                $json3 = $this->fetch_track($asrtrack['baseUrl'] ?? '', 'json3');
                $enhanced = vttwordaligner::enhance_manual_vtt($vtt, $json3);
            }
        } catch (\moodle_exception $e) {
            return $vtt;
        }
        if (trim($enhanced) === '' || strpos($enhanced, 'WEBVTT') === false) {
            return $vtt;
        }
        return $enhanced;
    }

    /**
     * Find the auto-generated (ASR) caption track matching a language.
     *
     * @param array $tracks the caption tracks
     * @param string $lang the language code to match, e.g. 'en-GB'
     * @return array|null the ASR track, or null if there is no usable match
     */
    protected static function pick_asr_track(array $tracks, string $lang): ?array {
        $asrtracks = array_values(array_filter($tracks, function ($track) {
            return ($track['kind'] ?? '') === 'asr' && !empty($track['baseUrl']);
        }));
        foreach ($asrtracks as $track) {
            if (($track['languageCode'] ?? '') === $lang) {
                return $track;
            }
        }
        // Fall back to a base-language match, e.g. a manual 'en-GB' track with an 'en' ASR track.
        $base = strtolower(explode('-', $lang)[0]);
        if ($base !== '') {
            foreach ($asrtracks as $track) {
                if (strtolower(explode('-', $track['languageCode'] ?? '')[0]) === $base) {
                    return $track;
                }
            }
        }
        return null;
    }

    /**
     * Download a caption track in the given format.
     *
     * @param string $baseurl the track's timedtext URL from the player response
     * @param string $fmt the timedtext format, e.g. 'vtt' or 'json3'
     * @return string the response body
     * @throws \moodle_exception error:youtubefetchfailed
     */
    protected function fetch_track(string $baseurl, string $fmt): string {
        // Only ever fetch from YouTube itself.
        $host = parse_url($baseurl, PHP_URL_HOST);
        if (!in_array($host, ['www.youtube.com', 'youtube.com'])) {
            throw new \moodle_exception('error:youtubefetchfailed', constants::M_COMPONENT);
        }

        // The baseUrl carries its own fmt param (the first occurrence wins), so replace it.
        if (preg_match('/[?&]fmt=/', $baseurl)) {
            $url = preg_replace('/([?&])fmt=[^&]*/', '$1fmt=' . $fmt, $baseurl);
        } else {
            $url = $baseurl . (strpos($baseurl, '?') === false ? '?' : '&') . 'fmt=' . $fmt;
        }

        $curl = new \curl();
        $response = $curl->get($url, null, [
            'CURLOPT_USERAGENT' => self::USERAGENT,
            'CURLOPT_TIMEOUT' => 30,
        ]);
        if ($curl->get_errno() !== 0) {
            throw new \moodle_exception('error:youtubefetchfailed', constants::M_COMPONENT);
        }
        return (string)$response;
    }

    /**
     * Number every cue with a "line-number: NN" cue identifier, so activity
     * authors can see which line number to refer to in line-based settings.
     * Any existing cue identifiers are replaced.
     *
     * @param string $vtt the WebVTT text
     * @return string the WebVTT text with numbered cue identifiers
     */
    public static function add_cue_identifiers(string $vtt): string {
        $vtt = str_replace(["\r\n", "\r"], "\n", $vtt);
        $blocks = preg_split('/\n{2,}/', trim($vtt));
        $timingregex = '/^\s*(?:\d{1,2}:)?\d{1,2}:\d{2}[\.,]\d{1,3}\s*-->/';

        $lineno = 0;
        foreach ($blocks as $i => $block) {
            $lines = explode("\n", trim($block));
            $timingline = -1;
            foreach ($lines as $j => $line) {
                if (preg_match($timingregex, $line)) {
                    $timingline = $j;
                    break;
                }
            }
            if ($timingline === -1) {
                // Not a cue (header, NOTE, STYLE...) - leave it alone.
                continue;
            }
            $lineno++;
            $identifier = sprintf('line-number: %02d', $lineno);
            $blocks[$i] = $identifier . "\n" . implode("\n", array_slice($lines, $timingline));
        }
        return implode("\n\n", $blocks) . "\n";
    }

    /**
     * Flatten a timedtext json3 response into plain text, one line per event.
     *
     * @param string $json3 the json3 response body
     * @return string the plain text transcript
     */
    protected static function json3_to_text(string $json3): string {
        $data = json_decode($json3, true);
        if (!is_array($data) || empty($data['events'])) {
            return '';
        }
        $lines = [];
        foreach ($data['events'] as $event) {
            if (empty($event['segs'])) {
                continue;
            }
            $line = '';
            foreach ($event['segs'] as $seg) {
                $line .= $seg['utf8'] ?? '';
            }
            $line = trim(preg_replace('/\s+/u', ' ', $line));
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        return implode("\n", $lines);
    }
}
