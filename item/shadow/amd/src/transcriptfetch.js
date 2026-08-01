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

define(['jquery', 'core/ajax', 'core/notification', 'core/str', 'core/log', 'core/templates',
    'core/modal_save_cancel', 'core/modal_events'],
    function($, Ajax, Notification, Str, log, Templates, ModalSaveCancel, ModalEvents) {

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
                $('#' + opts.pastebuttonid).on('click', function(e) {
                    e.preventDefault();
                    self.handle_paste_click();
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

            // The watch page of whatever video is currently in the form, so the modal can
            // link straight to it. Empty when no usable video has been entered yet.
            get_video_url: function() {
                var value = $.trim($('#' + this.opts.ytfieldid).val() || '');
                if (IDPATTERN.test(value)) {
                    return 'https://www.youtube.com/watch?v=' + value;
                }
                var match = URLPATTERN.exec(value);
                return match ? 'https://www.youtube.com/watch?v=' + match[1] : '';
            },

            handle_paste_click: function() {
                var self = this;
                var textareaid = 'ml_shadow_pastebox';

                ModalSaveCancel.create({
                    title: Str.get_string('pastevtt_title', 'minilessonitem_shadow'),
                    body: Templates.render('minilessonitem_shadow/pastetranscript', {
                        videourl: self.get_video_url(),
                        textareaid: textareaid,
                    }),
                    buttons: {save: Str.get_string('pastevtt_convert', 'minilessonitem_shadow')},
                    large: true,
                    removeOnClose: true,
                }).then(function(modal) {
                    modal.getRoot().on(ModalEvents.save, function(e) {
                        // Keep the modal open so a parse failure can be corrected in place.
                        e.preventDefault();
                        var pasted = $.trim($('#' + textareaid).val() || '');
                        if (pasted === '') {
                            Str.get_string('paste_empty', 'minilessonitem_shadow').then(function(msg) {
                                Notification.alert('', msg);
                                return msg;
                            }).catch(Notification.exception);
                            return;
                        }
                        self.do_convert(pasted, modal);
                    });
                    modal.show();
                    return modal;
                }).catch(Notification.exception);
            },

            do_convert: function(pasted, modal) {
                var self = this;

                Ajax.call([{
                    methodname: 'mod_minilesson_convert_transcript',
                    args: {
                        contextid: self.opts.contextid,
                        transcript: pasted,
                    },
                }])[0].then(function(response) {
                    if (!response.success) {
                        Str.get_string('paste_failed', 'minilessonitem_shadow').then(function(title) {
                            Notification.alert(title, response.message);
                            return title;
                        }).catch(Notification.exception);
                        return response;
                    }
                    // Same overwrite guard the fetch route uses.
                    if (self.get_editor_content() !== '') {
                        Str.get_strings([
                            {key: 'fetchvtt_overwrite_title', component: 'minilessonitem_shadow'},
                            {key: 'fetchvtt_overwrite', component: 'minilessonitem_shadow'},
                            {key: 'yes', component: 'moodle'},
                            {key: 'no', component: 'moodle'},
                        ]).then(function(s) {
                            Notification.confirm(s[0], s[1], s[2], s[3], function() {
                                self.apply_converted(response, modal);
                            });
                            return s;
                        }).catch(Notification.exception);
                    } else {
                        self.apply_converted(response, modal);
                    }
                    return response;
                }).catch(function(err) {
                    Notification.exception(err);
                });
            },

            // Write the converted subtitles in, and stand down per-word highlighting: a pasted
            // transcript only carries whole-line timings, so leaving it on would quietly do nothing.
            apply_converted: function(response, modal) {
                var self = this;
                self.set_editor_content(response.vtt);
                modal.destroy();

                var highlightbox = $('#' + self.opts.wordhighlightid);
                var turnedoff = highlightbox.length && highlightbox.prop('checked');
                if (turnedoff) {
                    highlightbox.prop('checked', false).trigger('change');
                }

                Str.get_string('paste_success', 'minilessonitem_shadow', response.cuecount).then(function(msg) {
                    if (turnedoff) {
                        return Str.get_string('paste_wordhighlightoff', 'minilessonitem_shadow')
                            .then(function(note) {
                                Notification.alert('', msg + ' ' + note);
                                return note;
                            });
                    }
                    Notification.alert('', msg);
                    return msg;
                }).catch(Notification.exception);
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
