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
 * Sets up the VTT code editor on the shadow item form and wires the
 * "Fetch subtitles" button, which pulls the WebVTT of the configured
 * YouTube video over ajax and writes it into the editor.
 *
 * @module     minilessonitem_shadow/transcriptfetch
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/str', 'core/log', 'mod_minilesson/codeeditor-lazy'],
    function($, Ajax, Notification, Str, log, codeEditor) {

        "use strict"; // jshint ;_;

        log.debug('MiniLesson Shadow transcript fetch: initialising');

        // Same shapes the server accepts: a full YouTube URL or a bare 11 character video ID.
        var URLPATTERN = new RegExp('(?:https?:\\/\\/)?(?:www\\.)?(?:youtube\\.com\\/(?:[^\\/\\n\\s]+\\/\\S+\\/|' +
            '(?:v|e(?:mbed)?)\\/|\\S*?[?&]v=)|youtu\\.be\\/)([a-zA-Z0-9_-]{11})');
        var IDPATTERN = /^[a-zA-Z0-9_-]{11}$/;

        return {

            view: null,
            opts: null,

            init: function(editorid, opts) {
                var self = this;
                self.opts = opts;
                self.view = codeEditor.setupCodeEditor(editorid, {language: 'vtt'});
                $('#' + opts.buttonid).on('click', function(e) {
                    e.preventDefault();
                    self.handle_click();
                });
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

                if (self.view !== null && $.trim(self.view.state.doc.toString()) !== '') {
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

            do_fetch: function(url) {
                var self = this;
                var button = $('#' + self.opts.buttonid);
                button.prop('disabled', true);

                Ajax.call([{
                    methodname: 'mod_minilesson_fetch_youtube_transcript',
                    args: {
                        contextid: self.opts.contextid,
                        url: url,
                        lang: self.opts.lang,
                    },
                }])[0].then(function(response) {
                    button.prop('disabled', false);
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
                    button.prop('disabled', false);
                    Notification.exception(err);
                });
            },

            set_editor_content: function(content) {
                var self = this;
                // The editor's update listener syncs the underlying textarea for us.
                self.view.dispatch({
                    changes: {from: 0, to: self.view.state.doc.length, insert: content},
                });
            },

        }; //end of return
    }
);
