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
 * Video Shadowing app javascript for Poodll minilesson
 *
 * @module     minilessonitem_shadow/itemtype
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(
    ['jquery', 'core/log', 'core/str', 'mod_minilesson/animatecss', 'minilessonitem_shadow/shadowplayer'],
    function($, log, str, anim, shadowplayer) {

        "use strict"; // jshint ;_;

        /*
        This file manages the Video Shadowing item type.

        The student first watches the clip ("watch" mode), then shadows it line
        by line ("loop" mode). Per line: one plain playback (listen), a turn-change
        pause, then n highlighted playbacks (shadow), then OK/Retry (evaluate).
         */

        log.debug('MiniLesson Shadow: initialising');

        // How close (seconds) the playhead must get to a segment end to count as finished.
        var ENDTOLERANCE = 0.05;
        // Polling interval while the video plays (ms).
        var POLLINTERVAL = 50;
        // Refresh interval of the countdown pie (ms).
        var COUNTDOWNINTERVAL = 50;

        return {

            mode: 'watch', // watch | loop | done
            linestate: 'listen', // listen | turnchange | shadow | evaluate
            cueindex: 0,
            shadowpos: 0,
            loopnum: 0,
            completedlines: 0,
            segmentactive: false,
            playercreated: false,
            pollhandle: null,
            countdownhandle: null,
            controls: {},

            //for making multiple instances
            clone: function() {
                return $.extend(true, {}, this);
            },

            init: function(index, itemdata, quizhelper) {
                var self = this;

                log.debug(itemdata);
                self.itemdata = itemdata;
                self.index = index;
                self.quizhelper = quizhelper;
                self.cues = itemdata.cues;
                self.maxloops = itemdata.loopcount;
                self.shadowpause = itemdata.shadowpause;
                // Only flagged cues are shadowed; the rest still show as watch-mode subtitles.
                self.shadowindices = [];
                self.cues.forEach(function(cue, i) {
                    if (cue.shadow) {
                        self.shadowindices.push(i);
                    }
                });

                //anim
                var animopts = {};
                animopts.useanimatecss = quizhelper.useanimatecss;
                anim.init(animopts);

                this.init_controls();
                this.register_events();
            },  //end of init

            init_controls: function() {
                var self = this;

                self.controls = {
                    container: $("#" + self.itemdata.uniqueid + "_container")
                };
                self.controls.wrapper = self.controls.container.find(".poodll-shadowing-wrapper");
                self.controls.captionbox = self.controls.container.find(".ml_shadow_captionbox");
                self.controls.captions = self.controls.container.find(".ml_shadow_caption");
                self.controls.loopindicator = self.controls.container.find(".ml_shadow_loopindicator");
                self.controls.countdown = self.controls.container.find(".ml_shadow_countdown");
                self.controls.countdownpie = self.controls.container.find(".ml_shadow_countdown_pie");
                self.controls.watchhint = self.controls.container.find(".ml_shadow_watchhint");
                self.controls.startshadowing = self.controls.container.find(".ml_shadow_startshadowing");
                self.controls.oknext = self.controls.container.find(".ml_shadow_oknext");
                self.controls.retry = self.controls.container.find(".ml_shadow_retry");
                self.controls.next_button = self.controls.container.find(".minilesson_nextbutton");
            },

            register_events: function() {
                var self = this;

                // Build the player when the item is shown (after the splash screen),
                // so the iframe is never created hidden.
                self.controls.container.on("showElement", function() {
                    if (!self.playercreated) {
                        self.playercreated = true;
                        self.create_player();
                    }
                });

                self.controls.startshadowing.on('click', function() {
                    self.enter_loop_mode();
                });

                self.controls.oknext.on('click', function() {
                    self.ok_next();
                });

                self.controls.retry.on('click', function() {
                    self.retry_line();
                });

                self.controls.next_button.on('click', function() {
                    self.next_question();
                });
            },

            create_player: function() {
                var self = this;
                self.player = shadowplayer.clone();
                self.player.init(
                    self.itemdata.uniqueid + "_shadowplayer",
                    {
                        videoid: self.itemdata.videoid,
                        start: self.itemdata.videostart,
                        end: self.itemdata.videoend
                    },
                    {
                        onPlaying: function() {
                            self.start_poll();
                        },
                        onPaused: function() {
                            self.stop_poll();
                        },
                        onEnded: function() {
                            self.stop_poll();
                            self.handle_video_ended();
                        }
                    }
                );
            },

            /* ============ polling engine ============ */

            start_poll: function() {
                var self = this;
                if (self.pollhandle !== null) {
                    return;
                }
                self.pollhandle = setInterval(function() {
                    self.poll_tick();
                }, POLLINTERVAL);
            },

            stop_poll: function() {
                if (this.pollhandle !== null) {
                    clearInterval(this.pollhandle);
                    this.pollhandle = null;
                }
            },

            poll_tick: function() {
                var self = this;
                var now = self.player.get_current_time();

                if (self.mode === 'watch') {
                    self.update_watch_subtitle(now);
                    if (self.itemdata.videoend > 0 && now >= self.itemdata.videoend - ENDTOLERANCE) {
                        self.player.pause();
                        self.watch_done();
                    }
                    return;
                }

                if (self.mode === 'loop' && self.segmentactive) {
                    var cue = self.cues[self.cueindex];
                    if (self.linestate === 'shadow') {
                        self.update_word_highlight(now);
                    }
                    if (now >= cue.end - ENDTOLERANCE) {
                        self.player.pause();
                        self.handle_segment_end();
                    }
                }
            },

            handle_video_ended: function() {
                var self = this;
                if (self.mode === 'watch') {
                    self.watch_done();
                } else if (self.mode === 'loop' && self.segmentactive) {
                    // The current line runs to the very end of the video.
                    self.handle_segment_end();
                }
            },

            /* ============ watch mode ============ */

            update_watch_subtitle: function(now) {
                var self = this;
                var activecue = -1;
                for (var i = 0; i < self.cues.length; i++) {
                    if (now >= self.cues[i].start && now < self.cues[i].end) {
                        activecue = i;
                        break;
                    }
                }
                self.controls.captions.removeClass('active');
                if (activecue > -1) {
                    self.controls.watchhint.hide();
                    self.get_caption(activecue).addClass('active');
                }
            },

            watch_done: function() {
                var self = this;
                if (self.mode !== 'watch') {
                    return;
                }
                self.controls.startshadowing.prop('disabled', false);
                anim.do_animate(self.controls.startshadowing, 'pulse');
            },

            /* ============ loop mode ============ */

            enter_loop_mode: function() {
                var self = this;
                if (self.shadowindices.length === 0) {
                    self.end();
                    return;
                }
                self.mode = 'loop';
                self.controls.wrapper.attr('data-mode', 'loop');
                self.controls.watchhint.hide();
                self.controls.startshadowing.hide();
                self.shadowpos = 0;
                self.cueindex = self.shadowindices[0];
                self.start_listen_play();
            },

            // One plain playback of the current line, no highlighting.
            start_listen_play: function() {
                var self = this;
                var cue = self.cues[self.cueindex];
                self.linestate = 'listen';
                self.controls.captionbox.removeClass('ml_shadow_yourturn');
                self.controls.loopindicator.hide();
                self.controls.captions.removeClass('active');
                self.get_caption(self.cueindex).addClass('active');
                self.clear_highlights();
                self.play_segment(cue);
            },

            // One of the n highlighted playbacks the student shadows along with.
            start_shadow_play: function(loopnum) {
                var self = this;
                var cue = self.cues[self.cueindex];
                self.loopnum = loopnum;
                self.linestate = 'shadow';
                self.update_loop_indicator(loopnum);
                self.clear_highlights();
                if (!self.current_cue_has_words()) {
                    self.get_caption(self.cueindex).find('.ml_shadow_wholeline').addClass('active');
                } else {
                    // Light the first word straight away, so it is never missed in the
                    // gap between play() starting and the first poll tick firing.
                    self.get_caption(self.cueindex).find('.ml_shadow_word').first().addClass('active');
                }
                self.play_segment(cue);
            },

            play_segment: function(cue) {
                var self = this;
                self.segmentactive = true;
                self.player.seek_to(cue.start);
                self.player.play();
            },

            handle_segment_end: function() {
                var self = this;
                if (!self.segmentactive) {
                    return;
                }
                self.segmentactive = false;
                self.stop_poll();

                if (self.linestate === 'listen') {
                    // Turn change: flip the caption card over to signal it is the student's turn,
                    // then count down to the first shadow attempt.
                    self.linestate = 'turnchange';
                    self.controls.captionbox.addClass('ml_shadow_yourturn');
                    anim.do_animate(self.controls.captionbox, 'flipInX');
                    self.update_loop_indicator(1);
                    self.run_countdown(self.shadowpause, function() {
                        self.start_shadow_play(1);
                    });
                } else if (self.linestate === 'shadow') {
                    if (self.loopnum < self.maxloops) {
                        self.update_loop_indicator(self.loopnum + 1);
                        self.run_countdown(self.shadowpause, function() {
                            self.start_shadow_play(self.loopnum + 1);
                        });
                    } else {
                        self.linestate = 'evaluate';
                        self.controls.retry.show();
                        if (self.shadowpos + 1 < self.shadowindices.length) {
                            self.controls.oknext.show();
                            anim.do_animate(self.controls.oknext, 'pulse');
                        } else {
                            // Final line: it counts as completed now, and the lesson's own
                            // next button (not an OK button) moves the student on.
                            self.completedlines = self.shadowindices.length;
                            anim.do_animate(self.controls.next_button, 'pulse');
                        }
                    }
                }
            },

            // Advance to the next line. The OK button only shows when there is a next line;
            // on the final line the lesson's own next button submits the result instead.
            ok_next: function() {
                var self = this;
                if (self.linestate !== 'evaluate' || self.shadowpos + 1 >= self.shadowindices.length) {
                    return;
                }
                self.completedlines++;
                self.controls.oknext.hide();
                self.controls.retry.hide();
                self.shadowpos++;
                self.cueindex = self.shadowindices[self.shadowpos];
                self.loopnum = 0;
                self.start_listen_play();
            },

            retry_line: function() {
                var self = this;
                if (self.linestate !== 'evaluate') {
                    return;
                }
                self.controls.oknext.hide();
                self.controls.retry.hide();
                self.update_loop_indicator(1);
                self.run_countdown(self.shadowpause, function() {
                    self.start_shadow_play(1);
                });
            },

            /* ============ display helpers ============ */

            get_caption: function(cueindex) {
                return this.controls.captions.filter('[data-cueindex="' + cueindex + '"]');
            },

            current_cue_has_words: function() {
                return this.cues[this.cueindex].haswordtimings &&
                    this.cues[this.cueindex].words.length > 0;
            },

            update_word_highlight: function(now) {
                var self = this;
                if (!self.current_cue_has_words()) {
                    return;
                }
                var words = self.get_caption(self.cueindex).find('.ml_shadow_word');
                var activeindex = -1;
                words.each(function(i) {
                    if (now >= parseFloat($(this).data('start'))) {
                        activeindex = i;
                    }
                });
                // Before any timed word has started (now is still in the cue's lead-in),
                // keep the first word lit as the word about to be spoken.
                if (activeindex === -1) {
                    activeindex = 0;
                }
                words.removeClass('active');
                words.eq(activeindex).addClass('active');
            },

            clear_highlights: function() {
                this.get_caption(this.cueindex).find('.ml_shadow_word, .ml_shadow_wholeline').removeClass('active');
            },

            update_loop_indicator: function(attemptnum) {
                var self = this;
                self.controls.loopindicator.show();
                str.get_string('loopindicator', 'minilessonitem_shadow',
                    {current: attemptnum, total: self.maxloops}).then(function(s) {
                        self.controls.loopindicator.text(s);
                        return s;
                    }).catch(function() {
                        self.controls.loopindicator.text(attemptnum + ' / ' + self.maxloops);
                    });
            },

            // Pause for the given duration, drawing a pie that fills as the pause elapses,
            // then run the callback.
            run_countdown: function(duration, callback) {
                var self = this;
                self.clear_countdown();
                // The pause is a rest between attempts, so the line shows in its
                // resting (unhighlighted) colour rather than keeping the last
                // attempt's red highlight.
                self.clear_highlights();
                var started = Date.now();
                self.set_countdown_progress(0);
                self.controls.countdown.show();
                self.countdownhandle = setInterval(function() {
                    var progress = (Date.now() - started) / duration;
                    if (progress >= 1) {
                        self.clear_countdown();
                        callback();
                    } else {
                        self.set_countdown_progress(progress);
                    }
                }, COUNTDOWNINTERVAL);
            },

            set_countdown_progress: function(progress) {
                var percent = Math.min(100, Math.round(progress * 100));
                this.controls.countdownpie.css('background',
                    'conic-gradient(#d32f2f 0% ' + percent + '%, #e0e0e0 ' + percent + '% 100%)');
            },

            clear_countdown: function() {
                if (this.countdownhandle !== null) {
                    clearInterval(this.countdownhandle);
                    this.countdownhandle = null;
                }
                this.controls.countdown.hide();
            },

            /* ============ completion ============ */

            end: function() {
                var self = this;
                self.mode = 'done';
                self.teardown();
                self.next_question();
            },

            next_question: function() {
                var self = this;
                self.teardown();

                var stepdata = {};
                stepdata.index = self.index;
                stepdata.hasgrade = true;
                stepdata.totalitems = self.shadowindices.length;
                stepdata.correctitems = self.completedlines;
                stepdata.grade = stepdata.totalitems > 0 ?
                    Math.round((stepdata.correctitems / stepdata.totalitems) * 100) : 0;
                self.quizhelper.do_next(stepdata);
            },

            teardown: function() {
                var self = this;
                self.segmentactive = false;
                self.stop_poll();
                self.clear_countdown();
                if (self.player && self.player.ready) {
                    self.player.pause();
                }
            }

        }; //end of return
    }
);
