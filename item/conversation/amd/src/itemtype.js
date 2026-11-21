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
 * Conversation item: the student speaks one part of a set dialog, and a TTS voice speaks the other.
 *
 * The partner's lines are played and shown as chat messages. The student's next line waits as a dimmed
 * "ghost" message. With auto check, speech recognition must match it; with self check it is only recorded.
 * At the end the student can play back the whole conversation, with their own recordings for their part.
 *
 * @module     minilessonitem_conversation/itemtype
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/log', 'core/str', 'core/notification', 'mod_minilesson/ttrecorder', 'mod_minilesson/animatecss'],
    function($, log, str, notification, ttrecorder, anim) {
        "use strict";

        /** After this many unmatched tries at a line, the student may move on. */
        var MAXTRIES = 3;

        log.debug('MiniLesson Conversation: initialising');

        return {

            /**
             * Make a fresh copy of this module, so a lesson can have more than one conversation.
             *
             * @returns {Object}
             */
            clone: function() {
                return $.extend(true, {}, this);
            },

            /**
             * Set up the item.
             *
             * @param {number} index the item's place in the lesson
             * @param {Object} itemdata the item data from export_for_template
             * @param {Object} quizhelper the lesson's quiz helper
             */
            init: function(index, itemdata, quizhelper) {
                var self = this;
                self.index = index;
                self.itemdata = itemdata;
                self.quizhelper = quizhelper;
                self.selfcheck = !!itemdata.selfcheck;
                self.role = itemdata.studentrole;
                self.run = 0;
                self.playtoken = 0;
                self.player = new Audio();
                self.strings = {};
                self.state = {pointer: 0, phase: 'idle', redo: null, playing: false, started: false};
                self.lines = [];

                self.container = $('#' + itemdata.uniqueid + '_container');
                self.controls = {
                    messages: self.container.find('[data-region="messages"]'),
                    stage: self.container.find('[data-region="stage"]'),
                    status: self.container.find('[data-region="status"]'),
                    rolechip: self.container.find('[data-region="rolechip"]'),
                    recorder: self.container.find('[data-region="recorder"]'),
                    tries: self.container.find('[data-region="tries"]'),
                    moveon: self.container.find('[data-action="moveon"]'),
                    nextbutton: self.container.find('.minilesson_nextbutton'),
                };

                if (itemdata.sceneurl) {
                    self.controls.stage.addClass('ml_conv_hasscene')
                        .css('background-image', 'url("' + itemdata.sceneurl.replace(/"/g, '%22') + '")');
                }
                self.controls.messages.toggleClass('ml_conv_look_grey', !!itemdata.promptgrey);
                self.controls.tries.prop('hidden', self.selfcheck);

                anim.init({useanimatecss: quizhelper.useanimatecss});
                self.reset_lines();
                self.init_recorder();
                self.strings_ready = self.init_strings();
                self.register_events();
            },

            /**
             * Fetch the strings the JS shows. Strings with values get placeholders, filled in by fill().
             *
             * @returns {Promise}
             */
            init_strings: function() {
                var self = this;
                var c = 'minilessonitem_conversation';
                var keys = [
                    {key: 'listen', component: c},
                    {key: 'yourturn', component: c},
                    {key: 'yourline', component: c},
                    {key: 'hearit', component: c},
                    {key: 'replay', component: c},
                    {key: 'myrecording', component: c},
                    {key: 'redo', component: c},
                    {key: 'redohere', component: c},
                    {key: 'redowait', component: c},
                    {key: 'recording', component: c},
                    {key: 'recordingreplaced', component: c},
                    {key: 'recorded', component: c},
                    {key: 'checking', component: c},
                    {key: 'great', component: c},
                    {key: 'tryagain', component: c},
                    {key: 'movedon', component: c},
                    {key: 'saidfirsttry', component: c},
                    {key: 'saidintries', component: c, param: '%A%'},
                    {key: 'complete', component: c},
                    {key: 'summaryauto', component: c, param: {correct: '%C%', total: '%T%'}},
                    {key: 'summaryself', component: c, param: '%A%'},
                    {key: 'playall', component: c},
                    {key: 'stopplayback', component: c},
                    {key: 'playingback', component: c, param: {num: '%N%', total: '%T%'}},
                    {key: 'startagain', component: c},
                    {key: 'youare', component: c, param: '%A%'},
                    {key: 'nextlessonitem', component: 'mod_minilesson'},
                    {key: 'confirm_desc', component: 'mod_minilesson'},
                    {key: 'yes', component: 'moodle'},
                    {key: 'no', component: 'moodle'},
                ];
                return str.get_strings(keys).then(function(s) {
                    keys.forEach(function(k, i) {
                        self.strings[k.key] = s[i];
                    });
                    return true;
                }).catch(notification.exception);
            },

            /**
             * Put values into a string fetched with placeholders.
             *
             * @param {string} key the string key
             * @param {Object} values placeholder => value
             * @returns {string}
             */
            fill: function(key, values) {
                var text = this.strings[key] || '';
                Object.keys(values).forEach(function(placeholder) {
                    text = text.split(placeholder).join(values[placeholder]);
                });
                return text;
            },

            /**
             * Start the recorder: speech recognition for auto check, record only for self check.
             */
            init_recorder: function() {
                var self = this;
                self.ttrec = ttrecorder.clone();
                self.ttrec.init({
                    uniqueid: self.itemdata.uniqueid,
                    callback: function(message) {
                        self.recorder_message(message);
                    },
                    // eslint-disable-next-line camelcase
                    stt_guided: self.quizhelper.is_stt_guided(),
                    recordonly: self.selfcheck,
                });
                self.set_recorder(false);
            },

            /**
             * Let the student use the microphone, or not.
             *
             * @param {boolean} on
             */
            set_recorder: function(on) {
                this.controls.recorder.toggleClass('ml_conv_recorder_off', !on);
            },

            /**
             * A message from the recorder.
             *
             * @param {Object} message
             */
            recorder_message: function(message) {
                var self = this;
                switch (message.type) {
                    case 'recording':
                        self.recstart = Date.now();
                        self.stop_audio();
                        self.set_status(self.strings.recording);
                        break;
                    case 'speech':
                        if (!self.selfcheck) {
                            self.check_speech(message.capturedspeech);
                        }
                        break;
                    case 'recorded':
                        if (self.selfcheck) {
                            self.got_recording(message.bloburl);
                        }
                        break;
                }
            },

            /**
             * Set up the buttons.
             */
            register_events: function() {
                var self = this;

                // Choosing a part on the start screen. This runs before the lesson's own start handler.
                self.container.find('[data-action="startmodule"][data-role]').on('click', function() {
                    self.role = $(this).data('role');
                });
                self.container.on('showElement', function() {
                    if (!self.state.started) {
                        self.state.started = true;
                        self.strings_ready.then(function() {
                            self.start();
                            return true;
                        }).catch(notification.exception);
                    }
                });

                self.controls.moveon.on('click', function() {
                    var line = self.lines[self.state.pointer];
                    if (self.state.phase === 'turn' && line) {
                        line.correct = false;
                        self.accept_line(line);
                    }
                });

                // Buttons on the chat messages.
                self.controls.messages.on('click', 'button[data-action]', function() {
                    var button = $(this);
                    var line = self.lines[button.closest('[data-line]').data('line')];
                    switch (button.data('action')) {
                        case 'listen':
                            if (line && self.can_listen()) {
                                var url = (button.data('own') && line.bloburl) ? line.bloburl : line.audiourl;
                                self.play_line(line, url);
                            }
                            break;
                        case 'redo':
                            self.start_redo(line);
                            break;
                        case 'playall':
                            self.play_all(button);
                            break;
                        case 'startagain':
                            self.start();
                            break;
                    }
                });

                self.controls.nextbutton.on('click', function() {
                    if (self.state.phase !== 'finished') {
                        notification.confirm(
                            self.strings.nextlessonitem,
                            self.strings.confirm_desc,
                            self.strings.yes,
                            self.strings.no,
                            function() {
                                self.next_question();
                            }
                        );
                    } else {
                        self.next_question();
                    }
                });
            },

            /**
             * Copy the lines from the item data, with fresh progress.
             */
            reset_lines: function() {
                this.lines = this.itemdata.lines.map(function(line) {
                    return $.extend({}, line, {correct: false, said: false, tries: 0, bloburl: null, row: null});
                });
            },

            /**
             * Start (or restart) the conversation.
             */
            start: function() {
                var self = this;
                self.run++;
                self.stop_audio();
                self.state.playing = false;
                self.state.redo = null;
                self.state.pointer = 0;
                self.reset_lines();
                self.controls.messages.empty();
                self.controls.moveon.prop('hidden', true);
                self.controls.rolechip.text(self.fill('youare', {'%A%': self.label(self.role)}));
                self.quizhelper.set_finish_label(self.index, false);
                self.later(function() {
                    self.next_turn();
                }, 400);
            },

            /**
             * Run a function later, unless the conversation has been restarted since.
             *
             * @param {Function} fn
             * @param {number} ms
             */
            later: function(fn, ms) {
                var self = this;
                var run = self.run;
                setTimeout(function() {
                    if (run === self.run) {
                        fn();
                    }
                }, ms);
            },

            /**
             * The display name of a part.
             *
             * @param {string} speaker 'A' or 'B'
             * @returns {string}
             */
            label: function(speaker) {
                return speaker === 'A' ? this.itemdata.labela : this.itemdata.labelb;
            },

            /**
             * Show a message under the chat.
             *
             * @param {string} text
             * @param {string} kind '', 'ok' or 'warn'
             */
            set_status: function(text, kind) {
                this.controls.status.text(text || '')
                    .removeClass('ml_conv_ok ml_conv_warn')
                    .addClass(kind ? 'ml_conv_' + kind : '');
            },

            /**
             * Play the next turn: the partner speaks, or the student's line waits for them.
             */
            next_turn: function() {
                var self = this;
                if (self.state.pointer >= self.lines.length) {
                    self.finish();
                    return;
                }
                var line = self.lines[self.state.pointer];
                self.controls.moveon.prop('hidden', true);
                if (line.speaker !== self.role) {
                    self.state.phase = 'partner';
                    self.set_recorder(false);
                    self.set_status(self.strings.listen);
                    var typing = self.add_typing(line);
                    self.later(function() {
                        typing.remove();
                        line.row = self.add_row(line, false);
                        line.row.addClass('ml_conv_playing');
                        var run = self.run;
                        self.play_audio(line.audiourl).then(function() {
                            if (run !== self.run) {
                                return;
                            }
                            line.row.removeClass('ml_conv_playing');
                            self.state.pointer++;
                            self.later(function() {
                                self.next_turn();
                            }, 300);
                            return true;
                        }).catch(notification.exception);
                    }, 700);
                } else {
                    self.state.phase = 'turn';
                    line.tries = 0;
                    self.update_tries(line);
                    line.row = self.add_row(line, true);
                    if (self.ttrec.update_currentprompt) {
                        self.ttrec.update_currentprompt(line.target);
                    } else {
                        self.ttrec.currentPrompt = line.target;
                    }
                    self.set_recorder(true);
                    self.set_status(self.strings.yourturn);
                }
            },

            /**
             * Add the "typing" dots for the partner, while their audio loads.
             *
             * @param {Object} line
             * @returns {jQuery}
             */
            add_typing: function(line) {
                var row = $('<div class="ml_conv_row ml_conv_partner"></div>');
                row.append(this.avatar(line.speaker));
                row.append('<div class="ml_conv_typing" aria-hidden="true"><i></i><i></i><i></i></div>');
                this.controls.messages.append(row);
                this.scroll_down();
                return row;
            },

            /**
             * The round badge with the first letter of the speaker's name.
             *
             * @param {string} speaker
             * @returns {jQuery}
             */
            avatar: function(speaker) {
                var name = this.label(speaker);
                return $('<div class="ml_conv_avatar" aria-hidden="true"></div>')
                    .attr('title', name)
                    .text(Array.from(name)[0].toUpperCase());
            },

            /**
             * Add a chat message for a line.
             *
             * @param {Object} line
             * @param {boolean} ghost true for the student's line while it waits to be said
             * @returns {jQuery}
             */
            add_row: function(line, ghost) {
                var mine = line.speaker === this.role;
                var row = $('<div class="ml_conv_row"></div>')
                    .addClass(mine ? 'ml_conv_me' : 'ml_conv_partner')
                    .attr('data-line', line.index);
                var bubble = $('<div class="ml_conv_bubble"></div>');
                row.append(this.avatar(line.speaker)).append(bubble);
                this.controls.messages.append(row);
                if (ghost) {
                    this.show_ghost(line, row);
                } else {
                    this.show_said(line, row);
                }
                row.addClass('ml_conv_enter');
                this.scroll_down();
                return row;
            },

            /**
             * Show a line as a ghost prompt, with its words in spans so recognised words can light up.
             *
             * @param {Object} line
             * @param {jQuery} row
             * @param {string} hint optional text in place of "Your line"
             */
            show_ghost: function(line, row, hint) {
                var self = this;
                var regexp = self.quizhelper.spliton_regexp;
                var help = self.itemdata.prompthelp;
                var words = $('<span class="ml_conv_words"></span>');
                var idx = 0;
                line.target.trim().split(regexp).filter(function(token) {
                    return token !== '';
                }).forEach(function(token) {
                    if (token.match(regexp)) {
                        words.append(document.createTextNode(token));
                        return;
                    }
                    idx++;
                    var chars = Array.from(token);
                    var shown = token;
                    if (help === 'initials') {
                        shown = chars[0] + chars.slice(1).map(function() {
                            return '_';
                        }).join('');
                    } else if (help === 'hidden') {
                        shown = chars.map(function() {
                            return '•';
                        }).join('');
                    }
                    words.append($('<span class="ml_conv_word"></span>')
                        .attr('data-idx', idx).attr('data-full', token).text(shown));
                });
                var hintbox = $('<div class="ml_conv_hint"></div>')
                    .append($('<span></span>').text(hint || self.strings.yourline))
                    .append($('<button type="button" class="ml_conv_linkbtn" data-action="listen"></button>')
                        .append('<i class="fa fa-volume-up" aria-hidden="true"></i> ')
                        .append(document.createTextNode(self.strings.hearit)));
                row.addClass('ml_conv_ghostrow');
                row.find('.ml_conv_bubble').addClass('ml_conv_ghost').empty().append(words).append(hintbox);
            },

            /**
             * Show a line as a said chat message.
             *
             * @param {Object} line
             * @param {jQuery} row
             */
            show_said: function(line, row) {
                var self = this;
                var mine = line.speaker === self.role;
                var bubble = row.find('.ml_conv_bubble');
                var meta = $('<div class="ml_conv_meta"></div>');
                if (!mine) {
                    meta.append($('<button type="button" class="ml_conv_linkbtn" data-action="listen"></button>')
                        .append('<span class="ml_conv_wave" aria-hidden="true"><i></i><i></i><i></i><i></i></span> ')
                        .append(document.createTextNode(self.strings.replay)));
                } else {
                    var note;
                    if (self.selfcheck) {
                        note = self.strings.recorded;
                    } else if (line.correct) {
                        note = line.tries <= 1 ? self.strings.saidfirsttry : self.fill('saidintries', {'%A%': line.tries});
                    } else {
                        note = self.strings.movedon;
                    }
                    meta.append($('<span></span>').text((line.correct || self.selfcheck ? '✓ ' : '') + note));
                    if (line.bloburl) {
                        meta.append($('<button type="button" class="ml_conv_linkbtn" data-action="listen" data-own="1"></button>')
                            .append('<i class="fa fa-play" aria-hidden="true"></i> ')
                            .append(document.createTextNode(self.strings.myrecording)));
                    }
                    if (self.selfcheck) {
                        meta.append($('<button type="button" class="ml_conv_linkbtn" data-action="redo"></button>')
                            .text(self.strings.redo));
                    }
                }
                row.removeClass('ml_conv_ghostrow');
                bubble.removeClass('ml_conv_ghost').empty().append($('<span></span>').text(line.text)).append(meta);
            },

            /**
             * Show the student's tries at the current line.
             *
             * @param {Object} line
             */
            update_tries: function(line) {
                this.controls.tries.find('i').each(function(i) {
                    $(this).toggleClass('ml_conv_used', i < line.tries);
                });
            },

            /**
             * Auto check: compare what speech recognition heard with the line.
             *
             * @param {string} transcript
             */
            check_speech: function(transcript) {
                var self = this;
                var line = self.lines[self.state.pointer];
                if (self.state.phase !== 'turn' || !line) {
                    return;
                }
                var run = self.run;
                self.set_status(self.strings.checking);
                self.set_recorder(false);
                self.quizhelper.comparePassageToTranscript(line.target, transcript, line.phonetic,
                    self.itemdata.language, self.itemdata.alternates).then(function(ajaxresult) {
                    if (run !== self.run || self.state.phase !== 'turn') {
                        return;
                    }
                    var comparison = JSON.parse(ajaxresult) || [];
                    var allmatched = comparison.length > 0 && comparison.every(function(word) {
                        return word.matched;
                    });
                    line.tries++;
                    self.update_tries(line);
                    var words = line.row.find('.ml_conv_word');
                    words.removeClass('ml_conv_heard ml_conv_missed');
                    comparison.forEach(function(word) {
                        var span = words.filter('[data-idx="' + word.wordnumber + '"]');
                        span.text(span.data('full')).addClass(word.matched ? 'ml_conv_heard' : 'ml_conv_missed');
                    });
                    if (allmatched) {
                        line.correct = true;
                        self.accept_line(line);
                    } else {
                        self.set_status(self.strings.tryagain, 'warn');
                        anim.do_animate(line.row.find('.ml_conv_bubble'), 'shakeX animate__faster');
                        self.controls.moveon.prop('hidden', line.tries < MAXTRIES);
                        self.set_recorder(true);
                    }
                    return true;
                }).catch(notification.exception);
            },

            /**
             * Self check: a recording finished.
             *
             * @param {string} bloburl
             */
            got_recording: function(bloburl) {
                var self = this;
                var redo = self.state.redo;
                if (redo) {
                    self.state.redo = null;
                    redo.bloburl = bloburl;
                    self.show_said(redo, redo.row);
                    self.set_recorder(self.state.phase === 'turn');
                    self.set_status(self.strings.recordingreplaced, 'ok');
                    return;
                }
                var line = self.lines[self.state.pointer];
                if (self.state.phase !== 'turn' || !line) {
                    return;
                }
                line.bloburl = bloburl;
                self.accept_line(line);
            },

            /**
             * Auto check: the recorder keeps the last recording. Fetch it for "My recording" and playback,
             * once it is ready. Speech recognition in the browser can finish before the recording does.
             *
             * @param {Object} line
             */
            keep_recording: function(line) {
                var self = this;
                var since = self.recstart || 0;
                var tries = 0;
                var check = function() {
                    var audio = self.ttrec.audio || {};
                    if (audio.dataURI && audio.end && audio.end.getTime() >= since) {
                        line.bloburl = audio.dataURI;
                        if (line.row) {
                            self.show_said(line, line.row);
                        }
                    } else if (++tries < 10) {
                        setTimeout(check, 200);
                    }
                };
                check();
            },

            /**
             * The student's line is done (said, recorded, or moved on): show it as a message and go on.
             *
             * @param {Object} line
             */
            accept_line: function(line) {
                var self = this;
                self.state.phase = 'accepted';
                self.set_recorder(false);
                self.controls.moveon.prop('hidden', true);
                line.said = true;
                self.show_said(line, line.row);
                line.row.addClass('ml_conv_solidify');
                if (!self.selfcheck && line.correct) {
                    self.keep_recording(line);
                }
                self.set_status(self.selfcheck ? self.strings.recorded : (line.correct ? self.strings.great : ''),
                    'ok');
                self.later(function() {
                    self.state.pointer++;
                    self.next_turn();
                }, 900);
            },

            /**
             * Self check: record one of the student's lines again.
             *
             * @param {Object} line
             */
            start_redo: function(line) {
                var self = this;
                if (!line || !self.selfcheck || self.state.redo || self.state.playing) {
                    return;
                }
                if (self.state.phase !== 'turn' && self.state.phase !== 'finished') {
                    self.set_status(self.strings.redowait);
                    return;
                }
                self.stop_audio();
                self.state.redo = line;
                self.show_ghost(line, line.row, self.strings.redohere);
                self.set_recorder(true);
                self.set_status(self.strings.redohere);
            },

            /**
             * Can the student play a line now? Not while the partner is speaking or the playback is on.
             *
             * @returns {boolean}
             */
            can_listen: function() {
                return !this.state.playing && (this.state.phase === 'turn' || this.state.phase === 'finished');
            },

            /**
             * Play one line, with its message highlighted.
             *
             * @param {Object} line
             * @param {string} url
             * @returns {Promise}
             */
            play_line: function(line, url) {
                var self = this;
                var row = line.row;
                self.controls.messages.find('.ml_conv_nowplaying').removeClass('ml_conv_nowplaying ml_conv_playing');
                if (row) {
                    row.addClass('ml_conv_nowplaying ml_conv_playing');
                }
                return self.play_audio(url).then(function() {
                    if (row) {
                        row.removeClass('ml_conv_nowplaying ml_conv_playing');
                    }
                    return true;
                });
            },

            /**
             * Play an audio file. The promise resolves when it ends or fails. If it is stopped, or another
             * file starts, the promise never resolves, so whatever was waiting for it does not go on.
             *
             * @param {string} url
             * @returns {Promise}
             */
            play_audio: function(url) {
                var self = this;
                var token = ++self.playtoken;
                var player = self.player;
                return new Promise(function(resolve) {
                    var done = false;
                    var finish = function() {
                        if (!done && token === self.playtoken) {
                            done = true;
                            resolve();
                        }
                    };
                    player.onended = finish;
                    player.onerror = function() {
                        setTimeout(finish, 1200);
                    };
                    if (!url) {
                        setTimeout(finish, 1200);
                        return;
                    }
                    player.src = url;
                    var playing = player.play();
                    if (playing && playing.catch) {
                        playing.catch(function() {
                            setTimeout(finish, 1200);
                        });
                    }
                });
            },

            /**
             * Stop any audio that is playing.
             */
            stop_audio: function() {
                this.playtoken++;
                if (this.player && !this.player.paused) {
                    this.player.pause();
                }
                this.controls.messages.find('.ml_conv_nowplaying').removeClass('ml_conv_nowplaying ml_conv_playing');
            },

            /**
             * The conversation is over: show the summary, with playback and start again.
             */
            finish: function() {
                var self = this;
                self.state.phase = 'finished';
                self.set_recorder(false);
                self.set_status('');
                var mine = self.lines.filter(function(line) {
                    return line.speaker === self.role;
                });
                var summary = self.selfcheck
                    ? self.fill('summaryself', {'%A%': mine.length})
                    : self.fill('summaryauto', {'%C%': self.correct_count(), '%T%': mine.length});
                var card = $('<div class="ml_conv_donecard ml_conv_enter"></div>')
                    .append($('<div class="ml_conv_donetitle"></div>').text(self.strings.complete))
                    .append($('<div class="ml_conv_donesummary"></div>').text(summary))
                    .append($('<div class="ml_conv_donebuttons"></div>')
                        .append($('<button type="button" class="btn btn-primary" data-action="playall"></button>')
                            .text(self.strings.playall))
                        .append($('<button type="button" class="btn btn-secondary" data-action="startagain"></button>')
                            .text(self.strings.startagain)));
                self.controls.messages.append(card);
                self.scroll_down();
                self.quizhelper.set_finish_label(self.index, true);
            },

            /**
             * How many of the student's lines were matched by speech recognition (or recorded, for self check).
             * This is only feedback for the summary, not the grade.
             *
             * @returns {number}
             */
            correct_count: function() {
                var self = this;
                return self.lines.filter(function(line) {
                    return line.speaker === self.role && (self.selfcheck ? line.said : line.correct);
                }).length;
            },

            /**
             * How many of the student's lines are done: said, recorded or moved on. This is the grade, in both
             * check modes, because speech recognition alone is not reliable enough to grade on.
             *
             * @returns {number}
             */
            done_count: function() {
                var self = this;
                return self.lines.filter(function(line) {
                    return line.speaker === self.role && line.said;
                }).length;
            },

            /**
             * Play the whole conversation: the partner's TTS, and the student's own recordings for their part
             * (or the model TTS for a line with no recording).
             *
             * @param {jQuery} button the play button, which becomes the stop button
             */
            play_all: function(button) {
                var self = this;
                if (self.state.playing) {
                    self.stop_playback(button);
                    return;
                }
                if (self.state.redo) {
                    return;
                }
                var lines = self.lines.filter(function(line) {
                    return line.row;
                });
                self.state.playing = true;
                button.text(self.strings.stopplayback);
                var i = 0;
                var step = function() {
                    if (!self.state.playing) {
                        return;
                    }
                    if (i >= lines.length) {
                        self.stop_playback(button);
                        return;
                    }
                    var line = lines[i++];
                    var messages = self.controls.messages[0];
                    var row = line.row[0];
                    messages.scrollTop = row.offsetTop - messages.clientHeight / 2 + row.offsetHeight / 2;
                    self.set_status(self.fill('playingback', {'%N%': i, '%T%': lines.length}));
                    var url = line.speaker === self.role && line.bloburl ? line.bloburl : line.audiourl;
                    self.play_line(line, url).then(function() {
                        setTimeout(step, 250);
                        return true;
                    }).catch(notification.exception);
                };
                step();
            },

            /**
             * Stop the playback of the whole conversation.
             *
             * @param {jQuery} button
             */
            stop_playback: function(button) {
                this.state.playing = false;
                this.stop_audio();
                button.text(this.strings.playall);
                this.set_status('');
            },

            /**
             * Keep the newest message in view.
             */
            scroll_down: function() {
                var messages = this.controls.messages[0];
                messages.scrollTop = messages.scrollHeight;
            },

            /**
             * Report the grade and go to the next item.
             */
            next_question: function() {
                var self = this;
                self.run++;
                self.stop_audio();
                var total = self.lines.filter(function(line) {
                    return line.speaker === self.role;
                }).length;
                var correct = self.done_count();
                self.quizhelper.do_next({
                    index: self.index,
                    hasgrade: true,
                    totalitems: total,
                    correctitems: correct,
                    grade: total > 0 ? Math.round((correct / total) * 100) : 0,
                });
            },
        };
    });
