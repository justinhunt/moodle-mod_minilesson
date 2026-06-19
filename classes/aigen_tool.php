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

use mod_minilesson\youtubetranscript;

defined('MOODLE_INTERNAL') || die();

/**
 * Class for AI generation tools.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aigen_tool {
    /** @var array The tool options */
    protected $tooloptions;

    /** @var array The context data */
    protected $contextdata;

    /**
     * Constructor for aigen_tool.
     *
     * @param array $tooloptions The tool options (e.g., ['fetch_vtt', 'user_customdata1']).
     * @param array $contextdata The context data available.
     */
    public function __construct(array $tooloptions, array $contextdata) {
        // Clean the tool options by trimming any whitespace from each option
        $this->tooloptions = array_map('trim', $tooloptions);
        $this->contextdata = $contextdata;
    }

    /**
     * Run the tool based on the provided options.
     *
     * @return string The result of the tool execution.
     */
    public function run() {
        if (empty($this->tooloptions)) {
            return '';
        }

        // The first option is the method name to call.
        $method = array_shift($this->tooloptions);

        // Ensure the method exists in this class and is callable.
        if (method_exists($this, $method) && is_callable([$this, $method])) {
            return (string)$this->$method();
        }

        return '';
    }

    /**
     * Fetch a WebVTT transcript for a YouTube video.
     *
     * @return string The WebVTT subtitle content, or empty string on failure.
     */
    protected function fetch_vtt() {
        if (empty($this->tooloptions)) {
            return '';
        }

        // The remaining option is the field name that contains the video URL.
        $urlfield = $this->tooloptions[0];
        if (empty($this->contextdata[$urlfield])) {
            return '';
        }

        $url = $this->contextdata[$urlfield];

        // Extract the video ID.
        $videoid = youtubetranscript::get_video_id($url);
        if ($videoid === null) {
            return '';
        }

        try {
            $fetcher = new youtubetranscript();
            // Fetch the transcript as VTT.
            $result = $fetcher->fetch($videoid, youtubetranscript::FORMAT_VTT, youtubetranscript::DEFAULT_LANGS, true);
            return $result['vtt'] ?? '';
        } catch (\Exception $e) {
            return '';
        }
    }

     /**
      * Fetch a transcript for a YouTube video.
      *
      * @return string The video transcript, or empty string on failure.
      */
    protected function fetch_transcript() {
        if (empty($this->tooloptions)) {
            return '';
        }

        // The remaining option is the field name that contains the video URL.
        $urlfield = $this->tooloptions[0];
        if (empty($this->contextdata[$urlfield])) {
            return '';
        }

        $url = $this->contextdata[$urlfield];

        // Extract the video ID.
        $videoid = youtubetranscript::get_video_id($url);
        if ($videoid === null) {
            return '';
        }

        try {
            $fetcher = new youtubetranscript();
            // Fetch the transcript as VTT.
            $result = $fetcher->fetch($videoid, youtubetranscript::FORMAT_TRANSCRIPT, youtubetranscript::DEFAULT_LANGS, true);
            return $result['transcript'] ?? '';
        } catch (\Exception $e) {
            return '';
        }
    }
}
