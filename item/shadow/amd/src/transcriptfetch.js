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

/**
 * Wires the "Fetch subtitles" button on the shadow item form, which pulls the
 * WebVTT of the configured YouTube video over ajax and writes it into the
 * code editor.
 *
 * The code editor itself is set up by a separate setupCodeEditor call (the same
 * way fiction and slides do it). This module reads the current content from the
 * underlying textarea and writes fetched content back via the editor's
 * ml_codeeditor_set_content custom event, so it never needs the editor view.
 *
 * @module     minilessonitem_shadow/transcriptfetch
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/str', 'core/log'],
    function($, Ajax, Notification, Str, log) {

        "use strict"; // jshint ;_;

        log.debug('MiniLesson Shadow transcript fetch: initialising');

        // Same shapes the server accepts: a full YouTube URL or a bare 11 character video ID.
        var URLPATTERN = new RegExp('(?:https?:\\/\\/)?(?:www\\.)?(?:youtube\\.com\\/(?:[^\\/\\n\\s]+\\/\\S+\\/|' +
            '(?:v|e(?:mbed)?)\\/|\\S*?[?&]v=)|youtu\\.be\\/)([a-zA-Z0-9_-]{11})');
        var IDPATTERN = /^[a-zA-Z0-9_-]{11}$/;

        return {

            opts: null,

            init: function(opts) {
                var self = this;
                self.opts = opts;
                $('#' + opts.buttonid).on('click', function(e) {
                    e.preventDefault();
                    self.handle_click();
                });
            },

            // The editor keeps the underlying textarea in sync, so its value is the
            // current editor content.
            get_editor_content: function() {
                return $.trim($('#' + this.opts.editorid).val() || '');
            },

            set_editor_content: function(content) {
                var element = document.getElementById(this.opts.editorid);
                if (element) {
                    element.dispatchEvent(new CustomEvent('ml_codeeditor_set_content', {detail: {content: content}}));
                }
            },

            handle_click: function() {
                var self = this;
                var url = $.trim($('#' + self.opts.ytfieldid).val());

                if (!IDPATTERN.test(url) && !URLPATTERN.test(url)) {
                    Str.get_strings([
                        {key: 'warning', component: 'moodle'},
                        {key: 'fetchvtt_invalidurl', component: 'minilessonitem_shadow'},
                    ]).then(function(s) {
                        Notification.alert(s[0], s[1]);
                        return s;
                    }).catch(Notification.exception);
                    return;
                }

                if (self.get_editor_content() !== '') {
                    Str.get_strings([
                        {key: 'fetchvtt_overwrite_title', component: 'minilessonitem_shadow'},
                        {key: 'fetchvtt_overwrite', component: 'minilessonitem_shadow'},
                        {key: 'yes', component: 'moodle'},
                        {key: 'no', component: 'moodle'},
                    ]).then(function(s) {
                        Notification.confirm(s[0], s[1], s[2], s[3], function() {
                            self.do_fetch(url);
                        });
                        return s;
                    }).catch(Notification.exception);
                } else {
                    self.do_fetch(url);
                }
            },

            set_button_busy: function(busy) {
                var button = $('#' + this.opts.buttonid);
                if (busy) {
                    button.data('idlecaption', button.html());
                    button.prop('disabled', true);
                    Str.get_string('fetchvtt_fetching', 'minilessonitem_shadow').then(function(fetching) {
                        button.html('<i class="fa fa-spinner fa-spin" aria-hidden="true"></i> ' + fetching);
                        return fetching;
                    }).catch(Notification.exception);
                } else {
                    button.html(button.data('idlecaption'));
                    button.prop('disabled', false);
                }
            },

            do_fetch: function(url) {
                var self = this;
                self.set_button_busy(true);

                // With per-word highlighting off there is no point fetching word timestamps.
                var wordtimestamps = true;
                var highlightbox = $('#' + self.opts.wordhighlightid);
                if (highlightbox.length) {
                    wordtimestamps = highlightbox.prop('checked');
                }

                Ajax.call([{
                    methodname: 'mod_minilesson_fetch_youtube_transcript',
                    args: {
                        contextid: self.opts.contextid,
                        url: url,
                        lang: self.opts.lang,
                        wordtimestamps: wordtimestamps,
                    },
                }])[0].then(function(response) {
                    self.set_button_busy(false);
                    if (response.success) {
                        self.set_editor_content(response.vtt);
                    } else {
                        Str.get_string('fetchvtt_failed', 'minilessonitem_shadow').then(function(title) {
                            Notification.alert(title, response.message);
                            return title;
                        }).catch(Notification.exception);
                    }
                    return response;
                }).catch(function(err) {
                    self.set_button_busy(false);
                    Notification.exception(err);
                });
            },

        }; //end of return
    }
);
