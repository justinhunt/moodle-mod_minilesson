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

    /**
     * @var array the InnerTube clients to try, in order.
     *
     * YouTube throttles automated requests per client, so a server it refuses on
     * one client is often still served on another. Each entry is the client half
     * of the InnerTube context plus the user agent that client would really send.
     */
    const CLIENTS = [
        'ANDROID' => [
            'context' => ['clientName' => 'ANDROID', 'clientVersion' => self::CLIENT_VERSION,
                'androidSdkVersion' => 30],
            'useragent' => self::USERAGENT,
        ],
        'IOS' => [
            'context' => ['clientName' => 'IOS', 'clientVersion' => '20.10.4', 'deviceMake' => 'Apple',
                'deviceModel' => 'iPhone16,2', 'osName' => 'iPhone', 'osVersion' => '18.3.2.22D82'],
            'useragent' => 'com.google.ios.youtube/20.10.4 (iPhone16,2; U; CPU iOS 18_3_2 like Mac OS X;)',
        ],
        'ANDROID_VR' => [
            'context' => ['clientName' => 'ANDROID_VR', 'clientVersion' => '1.60.19', 'deviceMake' => 'Oculus',
                'deviceModel' => 'Quest 3', 'androidSdkVersion' => 32, 'osName' => 'Android',
                'osVersion' => '12L'],
            'useragent' => 'com.google.android.apps.youtube.vr.oculus/1.60.19 (Linux; U; Android 12L; GB) gzip',
        ],
        'MWEB' => [
            'context' => ['clientName' => 'MWEB', 'clientVersion' => '2.20250312.04.00'],
            'useragent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 ' .
                '(KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        ],
    ];

    /** @var string[] default language preference order */
    const DEFAULT_LANGS = ['en', 'en-GB', 'en-US'];

    /** @var string the user agent of the client that was served the caption track list */
    protected $useragent = self::USERAGENT;

    /** @var string[] playabilityStatus reasons that really do mean the video is age restricted */
    const AGERESTRICTED_REASONS = [
        'inappropriate for some users',
        'confirm your age',
        'age-restricted',
    ];

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
     * Ask the InnerTube player API for the video's caption track list, trying each
     * client in turn until one is served.
     *
     * YouTube refuses automated requests per client rather than outright, so a
     * server turned away as a suspected bot on one client is frequently served on
     * the next. Only when every client has refused is the failure reported, using
     * the first refusal - that being the most representative of the video itself.
     *
     * @param string $videoid the video ID
     * @return array the caption tracks (each with baseUrl, languageCode, kind ...)
     * @throws \moodle_exception error:youtubefetchfailed, error:youtubeblocked,
     *                           error:youtubeagerestricted or error:youtubeunplayable
     */
    protected function fetch_caption_tracks(string $videoid): array {
        $firstrefusal = null;

        foreach (self::CLIENTS as $client) {
            try {
                $playerresponse = $this->call_player($videoid, $client);
                self::assert_playable($playerresponse);
            } catch (\moodle_exception $e) {
                $firstrefusal = $firstrefusal ?? $e;
                continue;
            }

            $tracks = $playerresponse['captions']['playerCaptionsTracklistRenderer']['captionTracks'] ?? [];
            if (!empty($tracks)) {
                // Fetch the track content as the same client that was served the list.
                $this->useragent = $client['useragent'];
                return $tracks;
            }
        }

        if ($firstrefusal !== null) {
            throw $firstrefusal;
        }

        // Every client was served but none listed captions, so there genuinely are none.
        return [];
    }

    /**
     * Make one InnerTube player request as the given client.
     *
     * @param string $videoid the video ID
     * @param array $client an entry from self::CLIENTS
     * @return array the decoded player response
     * @throws \moodle_exception error:youtubefetchfailed or error:youtubeblocked
     */
    protected function call_player(string $videoid, array $client): array {
        $body = json_encode([
            'context' => ['client' => array_merge($client['context'], ['hl' => 'en', 'gl' => 'US'])],
            'videoId' => $videoid,
        ]);

        $curl = new \curl();
        $curl->setHeader(['Content-Type: application/json']);
        $response = $curl->post(self::INNERTUBE_URL, $body, self::curl_options($client['useragent']));
        if ($curl->get_errno() !== 0) {
            throw new \moodle_exception('error:youtubefetchfailed', constants::M_COMPONENT);
        }

        // A rate limited or IP blocked request never reaches the player at all.
        $httpcode = (int)($curl->get_info()['http_code'] ?? 0);
        if ($httpcode === 429 || $httpcode === 403) {
            throw new \moodle_exception('error:youtubeblocked', constants::M_COMPONENT, '', 'HTTP ' . $httpcode);
        }

        $playerresponse = json_decode($response, true);
        if (!is_array($playerresponse)) {
            // A challenge page rather than the API response we asked for.
            if (stripos((string)$response, 'g-recaptcha') !== false) {
                throw new \moodle_exception('error:youtubeblocked', constants::M_COMPONENT, '', 'captcha');
            }
            throw new \moodle_exception('error:youtubefetchfailed', constants::M_COMPONENT);
        }

        return $playerresponse;
    }

    /**
     * Check the player response actually describes a playable video, and translate
     * the ways it can refuse into distinct errors.
     *
     * Without this every refusal reaches the caller as an empty caption track list,
     * which is indistinguishable from a video that genuinely has no subtitles - so a
     * server blocked by YouTube reports "no subtitles are available" for every video.
     *
     * @param array $playerresponse the decoded InnerTube player response
     * @return void
     * @throws \moodle_exception error:youtubeblocked, error:youtubeagerestricted or error:youtubeunplayable
     */
    protected static function assert_playable(array $playerresponse): void {
        $status = (string)($playerresponse['playabilityStatus']['status'] ?? '');
        $reason = (string)($playerresponse['playabilityStatus']['reason'] ?? '');

        if ($status === '' || $status === 'OK') {
            return;
        }

        if ($status === 'LOGIN_REQUIRED') {
            // Age restriction and the bot check both arrive as LOGIN_REQUIRED. Only claim
            // age restriction when the reason positively says so - YouTube words the bot
            // check several ways, and from a server a block is by far the likelier cause,
            // so anything unrecognised is reported as a block rather than mislabelled.
            foreach (self::AGERESTRICTED_REASONS as $needle) {
                if (stripos($reason, $needle) !== false) {
                    throw new \moodle_exception('error:youtubeagerestricted', constants::M_COMPONENT);
                }
            }
            throw new \moodle_exception('error:youtubeblocked', constants::M_COMPONENT, '', $reason);
        }
        throw new \moodle_exception('error:youtubeunplayable', constants::M_COMPONENT, '', $reason);
    }

    /**
     * The curl options every YouTube request shares.
     *
     * @param string $useragent the user agent of the client making the request
     * @return array curl options for \curl::get() / \curl::post()
     */
    protected static function curl_options(string $useragent): array {
        return [
            'CURLOPT_USERAGENT' => $useragent,
            'CURLOPT_TIMEOUT' => 30,
        ];
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
        $response = $curl->get($url, null, self::curl_options($this->useragent));
        if ($curl->get_errno() !== 0) {
            throw new \moodle_exception('error:youtubefetchfailed', constants::M_COMPONENT);
        }
        $httpcode = (int)($curl->get_info()['http_code'] ?? 0);
        if ($httpcode === 429 || $httpcode === 403) {
            throw new \moodle_exception('error:youtubeblocked', constants::M_COMPONENT);
        }
        return (string)$response;
    }

    /**
     * Convert a transcript copied out of YouTube's own "Show transcript" panel into WebVTT.
     *
     * This is the fallback for servers YouTube refuses to serve automated requests:
     * the author opens the transcript panel with timestamps switched on, copies it and
     * pastes it in. The panel writes each timestamp on its own line followed by its
     * text, though some browsers put both on one line, so both shapes are accepted.
     *
     * The panel only shows whole seconds, and truncates rather than rounds, so a cue
     * really starts somewhere inside the second it names. The truncated value is used
     * as-is, which keeps every cue start at or before the true one: the item seeks to
     * cue start to play a line, so a start even slightly late lands mid-word and clips
     * it, while a start early only adds a moment of lead-in. Nudging starts towards the
     * middle of their second would lower the average error but make roughly half of
     * them late, which is the trade the wrong way round.
     *
     * Each cue runs until the next begins, so the ends inherit the same truncation and
     * can fall up to a second early. That cannot be helped without overlapping cues,
     * which would break the item's active-cue lookup - it takes the first match.
     *
     * @param string $pasted the text copied from the transcript panel
     * @param float $lastcuelength seconds to allow for the final cue
     * @return string the WebVTT text, with numbered cue identifiers
     * @throws \moodle_exception error:notranscripttimestamps when no timestamps were found
     */
    public static function transcript_to_vtt(string $pasted, float $lastcuelength = 3.0): string {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $pasted));

        // A timestamp is m:ss or h:mm:ss, either alone on its line or leading the text.
        $timestampregex = '/^\s*(?:(\d{1,2}):)?(\d{1,3}):([0-5]\d)\s*(.*)$/u';

        $cues = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            if (preg_match($timestampregex, $line, $m)) {
                $seconds = ((int)$m[1] * 3600) + ((int)$m[2] * 60) + (int)$m[3];
                $last = count($cues) - 1;
                if ($last >= 0 && $cues[$last]['start'] === (float)$seconds) {
                    // Two panel entries inside the same second. Emitting both would give the
                    // first a zero length, which can never be the active cue and would end a
                    // shadow segment the instant it started, so they become one cue.
                    $cues[$last]['text'] = trim($cues[$last]['text'] . ' ' . trim($m[4]));
                    continue;
                }
                $cues[] = ['start' => (float)$seconds, 'text' => trim($m[4])];
            } else if (!empty($cues)) {
                // A continuation of the current cue's text.
                $last = count($cues) - 1;
                $cues[$last]['text'] = trim($cues[$last]['text'] . ' ' . trim($line));
            }
        }

        // Drop any cue the panel left without text (a timestamp on a purely visual row).
        $cues = array_values(array_filter($cues, function ($cue) {
            return $cue['text'] !== '';
        }));

        if (empty($cues)) {
            throw new \moodle_exception('error:notranscripttimestamps', constants::M_COMPONENT);
        }

        $blocks = ['WEBVTT'];
        foreach ($cues as $i => $cue) {
            $end = isset($cues[$i + 1]) ? $cues[$i + 1]['start'] : $cue['start'] + $lastcuelength;
            $blocks[] = self::format_timestamp($cue['start']) . ' --> ' . self::format_timestamp($end) .
                "\n" . $cue['text'];
        }

        return self::add_cue_identifiers(implode("\n\n", $blocks) . "\n");
    }

    /**
     * Format seconds as a WebVTT timestamp.
     *
     * @param float $seconds the offset in seconds
     * @return string the timestamp, hh:mm:ss.mmm
     */
    protected static function format_timestamp(float $seconds): string {
        $hours = (int)floor($seconds / 3600);
        $minutes = (int)floor(($seconds - ($hours * 3600)) / 60);
        $secs = $seconds - ($hours * 3600) - ($minutes * 60);
        return sprintf('%02d:%02d:%06.3f', $hours, $minutes, $secs);
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
