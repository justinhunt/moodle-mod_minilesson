define(['jquery', 'core/str', 'core/notification'], function($, str, notification) {
    "use strict"; // jshint ;_;

    /*
    Live notes on the speed reading authoring form: the length of the passage and how long it takes
    at the target speed, and how many questions students need to get right at the pass mark.
    */

    var COMPONENT = 'minilessonitem_speedreading';

    return {

        opts: {},

        init: function(opts) {
            var self = this;
            self.opts = opts;
            self.passagestatus = $('[data-region="passagestatus"]');
            self.passmarkstatus = $('[data-region="passmarkstatus"]');
            self.lastpassage = null;

            var fields = [opts.wordcountfield, opts.targetfield, opts.passmarkfield].concat(opts.questionfields);
            fields.forEach(function(field) {
                $('#id_' + field).on('input change', function() {
                    self.update(true);
                });
            });
            //the passage is in a rich text editor, which does not always update its textarea as you type
            setInterval(function() {
                self.update(false);
            }, 1500);
            self.update(true);
        },

        /**
         * The text of the passage, from the editor when it is running, else from its textarea.
         *
         * @returns {string} the passage HTML
         */
        passage_html: function() {
            var id = this.opts.editorid;
            var editor = window.tinymce ? window.tinymce.get(id) : null;
            //while the editor starts up it reports no content, so use the textarea until the passage is edited
            if (editor && editor.initialized && editor.isDirty()) {
                return editor.getContent();
            }
            return $('#' + id).val() || '';
        },

        /**
         * Count the words, or the characters for languages without spaces between words.
         * Keep in step with itemtype::count_words() in PHP.
         *
         * @param {string} html the passage
         * @returns {number}
         */
        count_words: function(html) {
            var withbreaks = html.replace(/<\/(p|div|li|h[1-6]|tr|td|blockquote)>|<br\s*\/?>/gi, '\n');
            var text = new DOMParser().parseFromString(withbreaks, 'text/html').body.textContent || '';
            if (this.opts.characters) {
                return (text.match(/[\p{L}\p{N}]/gu) || []).length;
            }
            return text.split(/\s+/).filter(function(word) {
                return /[\p{L}\p{N}]/u.test(word);
            }).length;
        },

        int_value: function(field) {
            return parseInt($('#id_' + field).val(), 10) || 0;
        },

        update: function(force) {
            var self = this;
            var html = self.passage_html();
            if (force || html !== self.lastpassage) {
                self.lastpassage = html;
                self.update_passage(html);
            }
            if (force) {
                self.update_passmark();
            }
        },

        update_passage: function(html) {
            var self = this;
            var count = self.int_value(self.opts.wordcountfield);
            if (count <= 0) {
                count = self.count_words(html);
            }
            var target = self.int_value(self.opts.targetfield);
            if (count <= 0 || target <= 0) {
                self.passageseq = (self.passageseq || 0) + 1;
                self.passagestatus.text('');
                return;
            }
            var seconds = Math.round(count * 60 / target);
            //strings load asynchronously, so only the latest update may write the note
            var seq = self.passageseq = (self.passageseq || 0) + 1;
            str.get_string('minsec', COMPONENT, {minutes: Math.floor(seconds / 60), seconds: seconds % 60})
                .then(function(time) {
                    var key = self.opts.characters ? 'passagestatus_cpm' : 'passagestatus';
                    return str.get_string(key, COMPONENT, {count: count, target: target, time: time});
                })
                .then(function(s) {
                    if (seq === self.passageseq) {
                        self.passagestatus.text(s);
                    }
                    return s;
                })
                .catch(notification.exception);
        },

        update_passmark: function() {
            var self = this;
            var questions = self.opts.questionfields.filter(function(field) {
                return ($('#id_' + field).val() || '').trim() !== '';
            }).length;
            var passmark = self.int_value(self.opts.passmarkfield);
            if (questions === 0) {
                self.passmarkstatus.text('');
                return;
            }
            //integer maths, as in itemtype::required_correct() in PHP
            var needed = Math.floor((questions * passmark + 99) / 100);
            str.get_string('passmarkstatus', COMPONENT, {questions: questions, needed: needed})
                .then(function(s) {
                    self.passmarkstatus.text(s);
                    return s;
                })
                .catch(notification.exception);
        },
    };
});
