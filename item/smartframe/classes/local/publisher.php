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

namespace minilessonitem_smartframe\local;

/**
 * Puts the files of a widget package where the widget host serves them, and builds their URLs.
 *
 * Widgets run untrusted JavaScript, so they must be served from a site of their own: never from Moodle, and
 * never from a host that shares cookies with Moodle or Poodll. Each revision lives in its own folder named by
 * the ZIP's sha256, so a published revision never changes and the same ZIP is never stored twice.
 *
 * @package    minilessonitem_smartframe
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class publisher {
    /** @var string Config plugin name for the publisher settings. */
    const CONFIG = 'minilessonitem_smartframe';
    /** @var string Publish to a folder in moodledata, served by a separate web server (development only). */
    const LOCAL = 'local';
    /** @var string Publish through Cloud Poodll to S3. */
    const CLOUDPOODLL = 'cloudpoodll';

    /**
     * The security policy every widget page runs under. It is added to each HTML file as a meta tag, because a
     * plain S3 URL can not send it as a header. A CDN should also send it as a header, with frame-ancestors.
     *
     * Inline script is allowed: AI tools often write single file widgets, and the widget host has nothing to
     * protect. Everything that would let a widget reach another site (to load code, or to send away what a
     * learner typed) is blocked.
     */
    const CSP = "default-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data: blob:; media-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; "
        . "form-action 'none'; base-uri 'none'; object-src 'none'; worker-src 'none'; manifest-src 'none'";

    /**
     * The publisher the site is set to use.
     *
     * @return publisher
     */
    public static function instance(): publisher {
        $which = get_config(self::CONFIG, 'publisher');
        if ($which === self::LOCAL) {
            return new publisher_local();
        }
        return new publisher_cloudpoodll();
    }

    /**
     * The base URL revisions are served under, with no trailing slash.
     *
     * @return string the URL, or '' when not set
     */
    abstract protected function base_url(): string;

    /**
     * Is this revision already published?
     *
     * @param string $revision the revision id
     * @return bool
     */
    abstract public function is_published(string $revision): bool;

    /**
     * Publish the files of a package.
     *
     * @param array $files path => ['content' => string, 'contenttype' => string], already prepared
     * @param string $revision the revision id
     */
    abstract protected function put_files(array $files, string $revision): void;

    /**
     * Publish a package, unless that revision is already published.
     *
     * @param package $package the checked package
     */
    public function publish(package $package): void {
        $revision = $package->get_revision();
        if ($this->is_published($revision)) {
            return;
        }
        $files = [];
        foreach ($package->files() as $path => $file) {
            if (strpos($file['contenttype'], 'text/html') === 0) {
                $file['content'] = self::add_csp($file['content']);
            }
            $files[$path] = $file;
        }
        $this->put_files($files, $revision);
    }

    /**
     * Add the security policy meta tag to an HTML page, as early as it can go.
     *
     * @param string $html the page
     * @return string the page with the tag
     */
    public static function add_csp(string $html): string {
        $tag = '<meta http-equiv="Content-Security-Policy" content="' . self::CSP . '">';
        if (preg_match('/<head(\s[^>]*)?>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            $at = $m[0][1] + strlen($m[0][0]);
            return substr($html, 0, $at) . $tag . substr($html, $at);
        }
        return $tag . $html;
    }

    /**
     * Is the publisher set up?
     *
     * @return bool
     */
    public function is_configured(): bool {
        return self::origin_of($this->base_url()) !== '';
    }

    /**
     * The origin (scheme, host and port) widgets are served from. The lesson page only listens to it.
     *
     * @return string the origin, or '' if the base URL is not usable
     */
    public function get_origin(): string {
        return self::origin_of($this->base_url());
    }

    /**
     * The origin of a URL.
     *
     * @param string $url the URL
     * @return string e.g. "https://widgets.example.net", or '' if it is not an http(s) URL
     */
    public static function origin_of(string $url): string {
        $bits = parse_url($url);
        if (!$bits || empty($bits['host']) || !in_array($bits['scheme'] ?? '', ['http', 'https'])) {
            return '';
        }
        $origin = $bits['scheme'] . '://' . strtolower($bits['host']);
        if (!empty($bits['port'])) {
            $origin .= ':' . $bits['port'];
        }
        return $origin;
    }

    /**
     * The URL of a revision's entry page. Built only from the site setting and the server made revision id,
     * never from anything in the package other than the checked entry path.
     *
     * @param string $revision the revision id
     * @param string $entry the entry path from the checked manifest
     * @return string the URL, or '' if the publisher is not set up
     */
    public function get_entry_url(string $revision, string $entry): string {
        if (!self::is_revision($revision) || !$this->is_configured()) {
            return '';
        }
        $path = implode('/', array_map('rawurlencode', explode('/', $entry)));
        return $this->base_url() . '/' . $revision . '/' . $path;
    }

    /**
     * Is this a revision id (a sha256 in hex)?
     *
     * @param string $revision the value
     * @return bool
     */
    public static function is_revision($revision): bool {
        return is_string($revision) && preg_match('/^[0-9a-f]{64}$/', $revision) === 1;
    }
}
