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
 * A Poodll Widget Package v1: either a ZIP of static HTML, CSS, JS and media with a manifest.json, or a single
 * HTML file with its manifest in a <script type="application/json" id="poodll-widget-manifest"> block. The single
 * file is what AI tools make most easily (it is what their previews run), and it is published as index.html.
 *
 * Opening a package checks it. Nothing is unpacked to disk here: the files are read into memory one at a
 * time, with size limits that do not trust the sizes the ZIP claims, and handed to the widget store.
 *
 * @package    minilessonitem_smartframe
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class package {
    /** @var int Largest ZIP accepted, in bytes. */
    const MAX_ZIP_BYTES = 20 * 1024 * 1024;
    /** @var int Largest total unpacked size, in bytes. */
    const MAX_TOTAL_BYTES = 80 * 1024 * 1024;
    /** @var int Largest single file, in bytes. */
    const MAX_FILE_BYTES = 15 * 1024 * 1024;
    /** @var int Most files in a package. */
    const MAX_FILES = 200;
    /** @var int Deepest folder nesting. */
    const MAX_DEPTH = 8;
    /** @var int Largest manifest.json, in bytes. */
    const MAX_MANIFEST_BYTES = 64 * 1024;
    /** @var int The only manifest schema version this host understands. */
    const SCHEMA_VERSION = 1;
    /** @var int The host message protocol version this host speaks. */
    const HOST_PROTOCOL = 1;
    /** @var string The id of the script element holding a single HTML file's manifest. */
    const MANIFEST_ELEMENT = 'poodll-widget-manifest';
    /** @var string The name a single HTML file is published under. */
    const SINGLE_ENTRY = 'index.html';

    /** @var array File extensions a package may contain, with the Content-Type each is served with. */
    const ALLOWED_TYPES = [
        'html' => 'text/html; charset=utf-8',
        'htm' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'mjs' => 'text/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'txt' => 'text/plain; charset=utf-8',
        'md' => 'text/markdown; charset=utf-8',
        'vtt' => 'text/vtt; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'mp3' => 'audio/mpeg',
        'm4a' => 'audio/mp4',
        'ogg' => 'audio/ogg',
        'oga' => 'audio/ogg',
        'wav' => 'audio/wav',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
    ];

    /** @var \ZipArchive|null the open archive, or null for a single HTML file */
    protected $zip = null;
    /** @var string|null the content of a single HTML file package */
    protected $html = null;
    /** @var string folder all entries sit in (a zipped folder), '' when the files are at the root */
    protected $prefix = '';
    /** @var array ZIP index => path in the package, for the files that will be published */
    protected $entries = [];
    /** @var \stdClass the checked manifest */
    protected $manifest;
    /** @var string the sha256 of the uploaded file, which names the published revision */
    protected $revision;

    /**
     * Open and check a package.
     *
     * @param string $path path to the ZIP or HTML file on disk
     * @param string $filename the uploaded file's name, which says which kind it is
     * @throws package_exception if the package is not acceptable
     */
    public function __construct(string $path, string $filename = 'widget.zip') {
        if (!is_readable($path)) {
            throw new package_exception('error:zipunreadable');
        }
        if (self::is_html_name($filename)) {
            $this->open_html($path, $filename);
            return;
        }
        $size = filesize($path);
        if ($size === false || $size > self::MAX_ZIP_BYTES) {
            throw new package_exception('error:ziptoobig', display_size(self::MAX_ZIP_BYTES));
        }
        $this->revision = hash_file('sha256', $path);

        $this->zip = new \ZipArchive();
        if ($this->zip->open($path, \ZipArchive::RDONLY) !== true) {
            throw new package_exception('error:notazip');
        }
        $this->check_entries();
        $index = array_search('manifest.json', $this->entries, true);
        if ($index === false) {
            throw new package_exception('error:nomanifest');
        }
        $this->manifest = $this->check_manifest($this->read_index($index, self::MAX_MANIFEST_BYTES));
    }

    /**
     * Is this the name of a single HTML file package?
     *
     * @param string $filename the file name
     * @return bool
     */
    public static function is_html_name(string $filename): bool {
        return (bool) preg_match('/\.html?$/i', $filename);
    }

    /**
     * Is this the name of a file that can be a widget package (a ZIP or an HTML file)?
     *
     * @param string $filename the file name
     * @return bool
     */
    public static function is_package_name(string $filename): bool {
        return self::is_html_name($filename) || (bool) preg_match('/\.zip$/i', $filename);
    }

    /**
     * Open a single HTML file package: read it, and the manifest in it.
     *
     * @param string $path path to the file on disk
     * @param string $filename the uploaded file's name, for messages
     */
    protected function open_html(string $path, string $filename): void {
        $size = filesize($path);
        if ($size === false || $size > self::MAX_FILE_BYTES) {
            throw new package_exception('error:zipfiletoobig', (object) ['file' => s($filename),
                'size' => display_size(self::MAX_FILE_BYTES)]);
        }
        $html = file_get_contents($path);
        if ($html === false) {
            throw new package_exception('error:zipunreadable');
        }
        if (strpos($html, "\xEF\xBB\xBF") === 0) {
            $html = substr($html, 3);
        }
        if (!mb_check_encoding($html, 'UTF-8')) {
            throw new package_exception('error:htmlencoding');
        }
        $this->revision = hash('sha256', $html);
        $this->html = $html;
        $this->entries = [0 => self::SINGLE_ENTRY];

        // The first <script id="poodll-widget-manifest"> element, with the id quoted or not.
        $pattern = '~<script\b[^>]*?\bid\s*=\s*(["\']?)' . self::MANIFEST_ELEMENT . '\1(?=[\s/>])[^>]*>(.*?)</script\s*>~is';
        if (!preg_match($pattern, $html, $matches)) {
            throw new package_exception('error:nomanifesthtml');
        }
        $json = $matches[2];
        if (strlen($json) > self::MAX_MANIFEST_BYTES) {
            throw new package_exception('error:manifestjson');
        }
        // A single file is always published as index.html, whatever its manifest says.
        $this->manifest = $this->check_manifest($json, true);
    }

    /**
     * Open a package from a Moodle stored file.
     *
     * @param \stored_file $file the ZIP or HTML file
     * @return package
     * @throws package_exception if the package is not acceptable
     */
    public static function from_stored_file(\stored_file $file): package {
        $filename = $file->get_filename();
        if (self::is_html_name($filename) && $file->get_filesize() > self::MAX_FILE_BYTES) {
            throw new package_exception('error:zipfiletoobig', (object) ['file' => s($filename),
                'size' => display_size(self::MAX_FILE_BYTES)]);
        }
        if ($file->get_filesize() > self::MAX_ZIP_BYTES) {
            throw new package_exception('error:ziptoobig', display_size(self::MAX_ZIP_BYTES));
        }
        $tempdir = make_request_directory();
        $temppath = $tempdir . '/' . (self::is_html_name($filename) ? 'widget.html' : 'widget.zip');
        $file->copy_content_to($temppath);
        return new package($temppath, $filename);
    }

    /**
     * Close the archive.
     */
    public function __destruct() {
        if ($this->zip instanceof \ZipArchive) {
            @$this->zip->close();
        }
    }

    /**
     * Files that zip tools add on their own, which are skipped rather than rejected.
     *
     * @param string $name entry name
     * @return bool
     */
    protected static function is_junk(string $name): bool {
        $base = basename($name);
        return strpos($name, '__MACOSX/') === 0 || $base === '.DS_Store' || $base === 'Thumbs.db'
            || $base === 'desktop.ini';
    }

    /**
     * Check every entry in the archive: names, types, sizes, links and encryption.
     */
    protected function check_entries(): void {
        $count = $this->zip->numFiles;
        $names = [];
        for ($i = 0; $i < $count; $i++) {
            $stat = $this->zip->statIndex($i, \ZipArchive::FL_UNCHANGED);
            if ($stat === false) {
                throw new package_exception('error:notazip');
            }
            $names[$i] = $stat['name'];
        }

        // A zipped folder (every entry inside one top folder, with the manifest in it) is accepted as if it had
        // been zipped from inside. Zipping the folder is the most common way to make a ZIP.
        $hasrootmanifest = in_array('manifest.json', $names, true);
        if (!$hasrootmanifest) {
            $tops = [];
            foreach ($names as $name) {
                if (!self::is_junk($name)) {
                    $tops[explode('/', $name)[0]] = true;
                }
            }
            if (count($tops) === 1) {
                $top = array_key_first($tops);
                if (in_array($top . '/manifest.json', $names, true)) {
                    $this->prefix = $top . '/';
                }
            }
        }

        $total = 0;
        $seen = [];
        foreach ($names as $i => $rawname) {
            if (self::is_junk($rawname)) {
                continue;
            }
            $stat = $this->zip->statIndex($i, \ZipArchive::FL_UNCHANGED);
            $isdir = substr($rawname, -1) === '/';

            // Links could point anywhere once unpacked. The top 4 bits of the unix mode say what the entry is.
            if (
                $this->zip->getExternalAttributesIndex($i, $opsys, $attr, \ZipArchive::FL_UNCHANGED)
                    && $opsys === \ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0xF000) === 0xA000
            ) {
                throw new package_exception('error:zipsymlink', s($rawname));
            }
            if (!empty($stat['encryption_method'])) {
                throw new package_exception('error:zipencrypted');
            }

            $name = $rawname;
            if ($this->prefix !== '') {
                if (strpos($name, $this->prefix) !== 0) {
                    throw new package_exception('error:zipbadpath', s($rawname));
                }
                $name = substr($name, strlen($this->prefix));
                if ($name === '') {
                    continue;
                }
            }
            self::check_path($isdir ? rtrim($name, '/') : $name, $rawname);
            if ($isdir) {
                continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!array_key_exists($ext, self::ALLOWED_TYPES)) {
                throw new package_exception('error:zipfiletype', s($name));
            }
            // Two names that differ only in case are the same file on many systems.
            $folded = \core_text::strtolower($name);
            if (isset($seen[$folded])) {
                throw new package_exception('error:zipduplicate', s($name));
            }
            $seen[$folded] = true;

            if ($stat['size'] > self::MAX_FILE_BYTES) {
                throw new package_exception('error:zipfiletoobig', (object) ['file' => s($name),
                    'size' => display_size(self::MAX_FILE_BYTES)]);
            }
            $total += $stat['size'];
            if ($total > self::MAX_TOTAL_BYTES) {
                throw new package_exception('error:ziptotaltoobig', display_size(self::MAX_TOTAL_BYTES));
            }
            $this->entries[$i] = $name;
            if (count($this->entries) > self::MAX_FILES) {
                throw new package_exception('error:ziptoomanyfiles', self::MAX_FILES);
            }
        }
    }

    /**
     * Check one path is a plain relative path that stays inside the package.
     *
     * @param string $name the path, without the zipped folder prefix
     * @param string $rawname the name as it is in the ZIP, for the error message
     */
    protected static function check_path(string $name, string $rawname): void {
        $bad = $name === ''
            || !mb_check_encoding($name, 'UTF-8')
            || preg_match('/[\x00-\x1f\x7f\\\\:*?"<>|]/', $name)
            || $name[0] === '/';
        if (!$bad) {
            $parts = explode('/', $name);
            foreach ($parts as $part) {
                // Hidden files and folders (.htaccess, .git) have no place in a static widget.
                if ($part === '' || $part === '.' || $part === '..' || $part[0] === '.') {
                    $bad = true;
                    break;
                }
            }
            if (count($parts) > self::MAX_DEPTH) {
                $bad = true;
            }
        }
        if ($bad) {
            throw new package_exception('error:zipbadpath', s($rawname));
        }
    }

    /**
     * Check a manifest.
     *
     * @param string $json the manifest JSON
     * @param bool $single true for a single HTML file, whose entry is always index.html
     * @return \stdClass the manifest, with the language tags tidied
     */
    protected function check_manifest(string $json, bool $single = false): \stdClass {
        $json = trim($json);
        // A byte order mark is invisible in editors and breaks json_decode.
        if (strpos($json, "\xEF\xBB\xBF") === 0) {
            $json = substr($json, 3);
        }
        $manifest = json_decode($json);
        if (!($manifest instanceof \stdClass)) {
            throw new package_exception('error:manifestjson');
        }
        $fail = function (string $field) {
            throw new package_exception('error:manifestfield', $field);
        };

        if (($manifest->schemaVersion ?? null) !== self::SCHEMA_VERSION) {
            throw new package_exception('error:manifestschema', self::SCHEMA_VERSION);
        }
        if (
            isset($manifest->minimumHostProtocol)
                && (!is_int($manifest->minimumHostProtocol) || $manifest->minimumHostProtocol > self::HOST_PROTOCOL)
        ) {
            throw new package_exception('error:manifestprotocol', self::HOST_PROTOCOL);
        }
        if (!isset($manifest->title) || !is_string($manifest->title) || trim($manifest->title) === '') {
            $fail('title');
        }
        if (isset($manifest->id) && !is_string($manifest->id)) {
            $fail('id');
        }

        $entry = $single ? self::SINGLE_ENTRY : ($manifest->entry ?? 'index.html');
        if (!is_string($entry) || !preg_match('/\.html?$/i', $entry) || !in_array($entry, $this->entries, true)) {
            throw new package_exception('error:manifestentry', s(is_string($entry) ? $entry : ''));
        }
        $manifest->entry = $entry;

        $langs = $manifest->uiLanguages ?? null;
        if (!is_array($langs) || !$langs) {
            $fail('uiLanguages');
        }
        $tidy = [];
        foreach ($langs as $lang) {
            $tag = is_string($lang) ? self::canonical_lang($lang) : false;
            if (!$tag) {
                $fail('uiLanguages');
            }
            $tidy[$tag] = $tag;
        }
        $manifest->uiLanguages = array_values($tidy);
        $default = isset($manifest->defaultUiLanguage) && is_string($manifest->defaultUiLanguage)
            ? self::canonical_lang($manifest->defaultUiLanguage) : $manifest->uiLanguages[0];
        if (!in_array($default, $manifest->uiLanguages, true)) {
            $fail('defaultUiLanguage');
        }
        $manifest->defaultUiLanguage = $default;
        if (isset($manifest->targetLanguage)) {
            if (!is_string($manifest->targetLanguage) || !self::canonical_lang($manifest->targetLanguage)) {
                $fail('targetLanguage');
            }
            $manifest->targetLanguage = self::canonical_lang($manifest->targetLanguage);
        }

        $caps = $manifest->capabilities ?? new \stdClass();
        if (!($caps instanceof \stdClass)) {
            $fail('capabilities');
        }
        foreach (['resize', 'grade'] as $cap) {
            if (isset($caps->{$cap}) && !is_bool($caps->{$cap})) {
                $fail('capabilities.' . $cap);
            }
            $caps->{$cap} = !empty($caps->{$cap});
        }
        $manifest->capabilities = (object) ['resize' => $caps->resize, 'grade' => $caps->grade];
        $manifest->title = \core_text::substr(trim($manifest->title), 0, 255);

        // Keep only the fields this host knows, so the stored copy is small and predictable.
        $keep = ['schemaVersion', 'id', 'title', 'entry', 'defaultUiLanguage', 'uiLanguages', 'targetLanguage',
            'capabilities', 'minimumHostProtocol'];
        $clean = new \stdClass();
        foreach ($keep as $field) {
            if (isset($manifest->{$field})) {
                $clean->{$field} = $manifest->{$field};
            }
        }
        return $clean;
    }

    /**
     * Tidy a BCP 47 language tag: "pt_br" becomes "pt-BR", "ZH-hant" becomes "zh-Hant".
     *
     * @param string $tag the tag
     * @return string|false the tidied tag, or false if it is not a language tag
     */
    public static function canonical_lang(string $tag) {
        $tag = str_replace('_', '-', trim($tag));
        if (!preg_match('/^[A-Za-z]{2,3}(-[A-Za-z0-9]{1,8})*$/', $tag)) {
            return false;
        }
        $parts = explode('-', $tag);
        $out = [strtolower(array_shift($parts))];
        foreach ($parts as $part) {
            if (strlen($part) === 4 && ctype_alpha($part)) {
                $out[] = ucfirst(strtolower($part));
            } else if (strlen($part) === 2 && ctype_alpha($part)) {
                $out[] = strtoupper($part);
            } else {
                $out[] = strtolower($part);
            }
        }
        return implode('-', $out);
    }

    /**
     * Read one entry, never more than $limit bytes, whatever size the ZIP claims for it.
     *
     * @param int $index ZIP index
     * @param int $limit most bytes to accept
     * @return string the content
     */
    protected function read_index(int $index, int $limit): string {
        $stream = $this->zip->getStreamIndex($index, \ZipArchive::FL_UNCHANGED);
        if (!$stream) {
            throw new package_exception('error:zipread', s($this->entries[$index] ?? ''));
        }
        $data = '';
        while (!feof($stream)) {
            $chunk = fread($stream, 65536);
            if ($chunk === false) {
                fclose($stream);
                throw new package_exception('error:zipread', s($this->entries[$index] ?? ''));
            }
            $data .= $chunk;
            if (strlen($data) > $limit) {
                fclose($stream);
                throw new package_exception('error:zipfiletoobig', (object) ['file' => s($this->entries[$index] ?? ''),
                    'size' => display_size($limit)]);
            }
        }
        fclose($stream);
        return $data;
    }

    /**
     * Go through the files that will be published, with their content and Content-Type.
     *
     * The total is counted again as the files are read, because the sizes in a ZIP's directory can lie.
     *
     * @return \Generator yields path => ['content' => string, 'contenttype' => string]
     */
    public function files(): \Generator {
        if ($this->html !== null) {
            yield self::SINGLE_ENTRY => ['content' => $this->html, 'contenttype' => self::ALLOWED_TYPES['html']];
            return;
        }
        $total = 0;
        foreach ($this->entries as $index => $path) {
            $content = $this->read_index($index, self::MAX_FILE_BYTES);
            $total += strlen($content);
            if ($total > self::MAX_TOTAL_BYTES) {
                throw new package_exception('error:ziptotaltoobig', display_size(self::MAX_TOTAL_BYTES));
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            yield $path => ['content' => $content, 'contenttype' => self::ALLOWED_TYPES[$ext]];
        }
    }

    /**
     * Paths of the HTML, CSS and JS files that load something from another site.
     *
     * Widgets must bundle everything: the widget host's security policy blocks other sites, so such a widget
     * would break when it runs. This is a warning for the teacher, not a check, because a URL in a string or a
     * comment is harmless.
     *
     * @return array paths
     */
    public function files_with_remote_references(): array {
        $found = [];
        foreach ($this->entries as $index => $path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($ext, ['html', 'htm', 'css', 'js', 'mjs'])) {
                continue;
            }
            $content = $this->html ?? $this->read_index($index, self::MAX_FILE_BYTES);
            if (
                preg_match('~(src|href)\s*=\s*["\']?\s*(https?:)?//|@import\s+(url\()?\s*["\']?(https?:)?//|' .
                    'url\(\s*["\']?(https?:)?//|\bimport\s*(\(|[^;]*from)\s*["\']https?://~i', $content)
            ) {
                $found[] = $path;
            }
        }
        return $found;
    }

    /**
     * The checked manifest.
     *
     * @return \stdClass
     */
    public function get_manifest(): \stdClass {
        return $this->manifest;
    }

    /**
     * The revision id: the sha256 of the uploaded file. The same file always gives the same revision.
     *
     * @return string 64 hex characters
     */
    public function get_revision(): string {
        return $this->revision;
    }
}
