/**
 * Onscreen keyboard for the wordcards item type.
 *
 * Ported from mod_wordcards (based on Paul Raine's APPs 4 EFL). This version is
 * instance-scoped (class based selectors inside a container, so several keyboards
 * can live on one page) and code-point safe (multibyte characters are handled with
 * Array.from and literal characters, not charcodes).
 *
 * @author  Justin Hunt - poodll.com
 */

define(['jquery', 'core/templates'], function($, templates) {

    var keyboard = {

        container: null,
        targetword: '',
        targetlength: 0,
        onsubmit: null,
        enabled: false,
        evns: '',

        clone: function() {
            return $.extend(true, {}, this);
        },

        /**
         * Render the keyboard for a target word into a container and wire its events.
         *
         * @param {jQuery} container the element to render the keyboard into
         * @param {string} targetword the word the user must type
         * @param {Array} distractorpool pool of distractor letters (union of letters in all terms)
         * @param {Function} onsubmit called with the typed string when the user submits
         * @return {Promise} resolves when the keyboard is rendered
         */
        create: function(container, targetword, distractorpool, onsubmit) {
            var self = this;
            self.clear();
            self.container = container;
            self.targetword = targetword;
            self.targetlength = Array.from(targetword).length;
            self.onsubmit = onsubmit;
            self.enabled = true;
            self.evns = 'wckb' + Date.now() + Math.floor(Math.random() * 10000);

            // The keys are the unique letters of the target word plus a few distractors.
            var qwerty = 'abcdefghijklmnopqrstuvwxyz'.split('');
            var pool = (Array.isArray(distractorpool) && distractorpool.length > 3) ? distractorpool.slice() : qwerty;
            var letters = [];
            Array.from(targetword).forEach(function(letter) {
                if (letters.indexOf(letter) === -1) {
                    letters.push(letter);
                    var poolindex = pool.indexOf(letter);
                    if (poolindex !== -1) {
                        pool.splice(poolindex, 1);
                    }
                }
            });
            self.shuffle(pool);
            if (pool.length > 2) {
                letters = letters.concat(pool.slice(0, 3));
            }
            letters.sort();

            var tdata = {
                letters: letters.map(function(letter) {
                    return {letter: letter, isspace: letter === ' '};
                })
            };
            return templates.render('minilessonitem_wordcards/keyboard', tdata).then(function(html) {
                self.container.html(html);
                self.registerEvents();
                return true;
            });
        },

        registerEvents: function() {
            var self = this;

            self.container.on('click', '.wordcards_kb_key', function(e) {
                e.preventDefault();
                self.press($(this));
            });
            self.container.on('click', '.wordcards_kb_del', function(e) {
                e.preventDefault();
                self.deleteLast();
            });
            self.container.on('click', '.wordcards_kb_submit', function(e) {
                e.preventDefault();
                self.submit();
            });

            // Physical keyboard support.
            $(document).on('keydown.' + self.evns, function(e) {
                if (!self.enabled || !self.container.is(':visible')) {
                    return;
                }
                // When an onscreen key or control button has focus (e.g. the user tabbed
                // to it), let the browser's native Space/Enter button activation click it.
                if ((e.key === ' ' || e.key === 'Enter') && $(e.target).closest('.wordcards_kb button').length) {
                    return;
                }
                if (e.key === 'Backspace' || e.key === 'Delete') {
                    self.deleteLast();
                    e.preventDefault();
                } else if (e.key === 'Enter') {
                    self.submit();
                    e.preventDefault();
                } else if (typeof e.key === 'string' && Array.from(e.key).length === 1 && !e.ctrlKey && !e.metaKey) {
                    var button = self.container.find('.wordcards_kb_key').filter(function() {
                        return $(this).attr('data-val') === e.key;
                    }).first();
                    if (button.length) {
                        self.press(button);
                    } else {
                        self.flash(self.typedInner(), 'wordcards_kb_flash');
                    }
                    e.preventDefault();
                }
            });
        },

        typedInner: function() {
            return this.container.find('.wordcards_kb_typed_inner');
        },

        press: function(button) {
            var self = this;
            if (!self.enabled) {
                return;
            }
            var value = String(button.attr('data-val'));
            var typed = self.typedInner().text();
            if (Array.from(typed + value).length <= self.targetlength) {
                self.flash(button, 'wordcards_kb_pressed');
                self.typedInner().text(typed + value);
            } else {
                self.flash(self.typedInner(), 'wordcards_kb_flash');
            }
        },

        deleteLast: function() {
            if (!this.enabled) {
                return;
            }
            var typed = this.typedInner().text();
            this.typedInner().text(Array.from(typed).slice(0, -1).join(''));
        },

        submit: function() {
            if (!this.enabled) {
                return;
            }
            var typed = this.typedInner().text();
            if (typeof this.onsubmit === 'function') {
                this.onsubmit(typed);
            }
        },

        off: function() {
            if (this.container) {
                this.container.off('click', '.wordcards_kb_key');
                this.container.off('click', '.wordcards_kb_del');
                this.container.off('click', '.wordcards_kb_submit');
            }
            if (this.evns) {
                $(document).off('keydown.' + this.evns);
            }
        },

        disable: function() {
            this.enabled = false;
            this.off();
            if (this.container) {
                this.typedInner().text('');
                this.container.find('.wordcards_kb').addClass('wordcards_kb_disabled');
            }
        },

        clear: function() {
            this.enabled = false;
            this.off();
            if (this.container) {
                this.container.empty();
            }
        },

        shuffle: function(a) {
            var j, x, i;
            for (i = a.length; i; i -= 1) {
                j = Math.floor(Math.random() * i);
                x = a[i - 1];
                a[i - 1] = a[j];
                a[j] = x;
            }
        },

        flash: function(target, classname) {
            $(target).addClass(classname);
            setTimeout(function() {
                $(target).removeClass(classname);
            }, 100);
        }
    };

    return keyboard;
});
