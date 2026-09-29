define(
    ['jquery', 'core/log', 'mod_minilesson/ttrecorder', 'mod_minilesson/animatecss',
    'mod_minilesson/progresstimer', 'core/templates', 'core/str', 'core/notification'],
    function ($, log, ttrecorder, anim, progresstimer, templates, str, notification) {
        "use strict"; // jshint ;_;

        /*
        This file is to manage the self check item type.
        The student records a sentence, hears model / own recording / model, and then approves it or tries again.
        There is no speech recognition.
        */

        log.debug('MiniLesson Self Check: initialising');

        var theselfcheckitem = {
            // How long to wait after recording stops before we play the compare sequence.
            comparedelay: 800,
            game: {pointer: 0},
            recordingpointer: -1,
            items: null,
            strings: {},
            // Bumped whenever playback is stopped, so a running compare sequence knows to give up.
            playbackid: 0,

            // For making multiple instances.
            clone: function () {
                return $.extend(true, {}, this);
            },

            init: function (index, itemdata, quizhelper) {
                this.itemdata = itemdata;
                this.quizhelper = quizhelper;
                this.index = index;
                this.init_components(itemdata);
                this.init_strings();

                // Anim.
                var animopts = {};
                animopts.useanimatecss = quizhelper.useanimatecss;
                anim.init(animopts);

                this.register_events();
                this.getItems();
            },

            init_components: function (itemdata) {
                var self = this;
                var prefix = "#" + itemdata.uniqueid + "_container ";

                self.container = $("#" + itemdata.uniqueid + "_container");
                self.resultsbox = $(prefix + "div.selfcheck_resultscontainer");
                self.listenbtns = $(prefix + ".selfcheck_listen_btn");
                self.modelbtn = $(prefix + ".selfcheck_listen_btn.audiomodel");
                self.selfbtn = $(prefix + ".selfcheck_listen_btn.audioself");
                self.comparebtn = $(prefix + ".selfcheck_compare_btn");
                self.approvebtn = $(prefix + ".selfcheck_approve_btn");
                self.retrybtn = $(prefix + ".selfcheck_retry_btn");
                self.skipbtn = $(prefix + ".selfcheck_skip_btn");
                self.startbtn = $(prefix + ".selfcheck_start_btn");
                self.smallnextbtn = $(prefix + ".minilesson_nextbutton");
                self.ctrlbtns = $(prefix + ".selfcheck_ctrl_btn");
                self.speakbtncont = $(prefix + ".selfcheck_speakbtncontainer");
                self.verdict = $(prefix + ".selfcheck_verdict");
                self.questioncont = $(prefix + ".question");
                self.listencont = $(prefix + ".selfcheck_listen_cont");
                self.mainmenu = $(prefix + ".selfcheck_mainmenu");
                self.mainstage = $(prefix + ".selfcheck_mainstage");
                self.controls = $(prefix + ".selfcheck_controls");
                self.progresscont = $(prefix + ".progress-container");
                self.description = $(prefix + ".selfcheck_description");
                self.image = $(prefix + ".selfcheck_image_container");
                self.maintitle = $(prefix + ".selfcheck_maintitle");
                self.itemquestion = $(prefix + ".selfcheck_itemtext");
                self.title = $(prefix + ".selfcheck_title");

                // Callback: Recorder updates.
                var recorderCallback = function (message) {
                    switch (message.type) {
                        case 'recording':
                            self.stop_audio();
                            self.recordingpointer = self.game.pointer;
                            break;
                        case 'recorded':
                            // Ignore a recording that finished after the student skipped to the next sentence.
                            if (self.recordingpointer === self.game.pointer) {
                                self.got_recording(message.bloburl, message.mediaurl);
                            }
                            break;
                    }
                };

                // Init tt recorder, in record only mode (no speech recognition).
                var opts = {};
                opts.uniqueid = itemdata.uniqueid;
                opts.callback = recorderCallback;
                opts.recordonly = true;
                self.ttrec = ttrecorder.clone();
                self.ttrec.init(opts);
            },

            getItems: function () {
                var self = this;
                self.items = self.itemdata.sentences.map(function (target) {
                    return {
                        target: target.sentence,
                        prompt: target.prompt,
                        displayprompt: target.displayprompt,
                        answered: false,
                        correct: false,
                        timer: [],
                        audio: target.audiourl ? new Audio(target.audiourl) : null,
                        audioself: null,
                        mediaurl: false,
                        imageurl: target.imageurl,
                        hintdisplay: target.hintdisplay,
                    };
                }).filter(function (e) {
                    return e.prompt !== "";
                });
                self.appReady();
            },

            appReady: function () {
                var self = this;
                self.container.find(".selfcheck_not_loaded").hide();
                self.container.find(".selfcheck_loaded").show();
                if (self.itemdata.hidestartpage) {
                    self.start();
                } else {
                    self.startbtn.prop("disabled", false);
                }
            },

            init_strings: function () {
                var self = this;
                str.get_strings([
                    {"key": "nextlessonitem", "component": 'mod_minilesson'},
                    {"key": "confirm_desc", "component": 'mod_minilesson'},
                    {"key": "yes", "component": 'moodle'},
                    {"key": "no", "component": 'moodle'},
                ]).done(function (s) {
                    var i = 0;
                    self.strings.nextlessonitem = s[i++];
                    self.strings.confirm_desc = s[i++];
                    self.strings.yes = s[i++];
                    self.strings.no = s[i++];
                });
            },

            next_question: function () {
                var self = this;
                self.stop_audio();

                var stepdata = {};
                stepdata.index = self.index;
                stepdata.hasgrade = true;
                stepdata.totalitems = self.items.length;
                stepdata.correctitems = self.items.filter(function (e) {
                    return e.correct;
                }).length;
                stepdata.grade = stepdata.totalitems > 0
                    ? Math.round((stepdata.correctitems / stepdata.totalitems) * 100) : 0;

                // Prepare results data for detailed review on finished page or by teacher.
                // Only saved (S3) recordings are sent, the in-browser ones will not outlive this page.
                var resultsdata = {};
                resultsdata.correctitems = stepdata.correctitems;
                resultsdata.totalitems = stepdata.totalitems;
                resultsdata.items = self.items_for_results_display(false);
                stepdata.resultsdata = resultsdata;
                self.quizhelper.do_next(stepdata);
            },

            register_events: function () {
                var self = this;

                // On next button click.
                self.smallnextbtn.on('click', function () {
                    if (self.items.some(item => !item.answered)) {
                        notification.confirm(
                            self.strings.nextlessonitem,
                            self.strings.confirm_desc,
                            self.strings.yes,
                            self.strings.no,
                            function () {
                                self.next_question();
                            }
                        );
                    } else {
                        self.next_question();
                    }
                });

                // On start button click.
                self.startbtn.on("click", function () {
                    self.start();
                });

                // Model audio and own recording buttons.
                self.modelbtn.on("click", function () {
                    self.toggle_play($(this), self.items[self.game.pointer].audio);
                });
                self.selfbtn.on("click", function () {
                    self.toggle_play($(this), self.items[self.game.pointer].audioself);
                });

                // Compare button: model, own recording, model.
                self.comparebtn.on("click", function () {
                    if (self.comparebtn.hasClass('selfcheck_playing')) {
                        self.stop_audio();
                    } else {
                        self.play_compare();
                    }
                });

                // The student is happy with their recording.
                self.approvebtn.on("click", function () {
                    self.stop_audio();
                    var item = self.items[self.game.pointer];
                    item.answered = true;
                    item.correct = true;
                    self.move_on();
                });

                // The student wants another go.
                self.retrybtn.on("click", function () {
                    self.stop_audio();
                    self.items[self.game.pointer].audioself = null;
                    self.items[self.game.pointer].mediaurl = false;
                    self.show_recorder();
                });

                // Skip counts as not correct.
                self.skipbtn.on("click", function () {
                    self.stop_audio();
                    var item = self.items[self.game.pointer];
                    item.answered = true;
                    item.correct = false;
                    self.move_on();
                });
            },

            start: function () {
                var self = this;

                self.items.forEach(function (item) {
                    item.answered = false;
                    item.correct = false;
                });
                self.game.pointer = 0;

                self.questioncont.show();
                self.listencont.show();
                self.startbtn.hide();
                self.mainmenu.hide();
                self.description.hide();
                self.image.hide();
                self.maintitle.show();
                self.itemquestion.show();
                self.controls.show();

                self.nextPrompt();
            },

            // Go to the next sentence, or finish if this was the last one.
            move_on: function () {
                var self = this;
                self.stopTimer(self.items[self.game.pointer].timer);
                // If we are skipping mid recording, stop it. The recording is then ignored.
                self.recordingpointer = -1;
                if (self.ttrec.audio.isRecording) {
                    self.ttrec.toggleRecording();
                }
                self.ctrlbtns.prop("disabled", true);
                self.updateProgressDots();

                if (self.game.pointer < self.items.length - 1) {
                    setTimeout(function () {
                        self.container.find(".selfcheck_reply_" + self.game.pointer).hide();
                        self.game.pointer++;
                        self.nextPrompt();
                    }, 600);
                } else {
                    self.end();
                }
            },

            end: function () {
                var self = this;
                self.smallnextbtn.prop("disabled", true);
                self.updateProgressDots();

                setTimeout(function () {
                    self.smallnextbtn.prop("disabled", false);
                    if (self.quizhelper.showitemreview) {
                        self.title.hide();
                        self.show_item_review();
                    } else {
                        self.next_question();
                    }
                }, 1000);
            },

            show_item_review: function () {
                var self = this;
                var reviewdata = {};
                reviewdata.correctitems = self.items.filter(function (e) {
                    return e.correct;
                }).length;
                reviewdata.totalitems = self.items.length;
                reviewdata.items = self.items_for_results_display(true);

                templates.render('mod_minilesson/listitemresults', reviewdata).then(
                    function (html, js) {
                        self.resultsbox.html(html);
                        self.resultsbox.show();
                        self.mainstage.hide();
                        templates.runTemplateJS(js);
                    }
                ).catch(notification.exception);
            },

            updateProgressDots: function () {
                var self = this;
                var progress = self.items.map(function (item) {
                    var color = "#E6E9FD";
                    var icon = "fa fa-square";
                    if (item.answered && item.correct) {
                        color = "#74DC72";
                        icon = 'fa fa-check-square';
                    } else if (item.answered && !item.correct) {
                        color = "#FB6363";
                        icon = "fa fa-window-close";
                    }
                    return "<i style='color:" + color + "' class='" + icon + " pl-1'></i>";
                }).join(" ");
                self.title.html(progress);
            },

            nextPrompt: function () {
                var self = this;
                self.updateProgressDots();
                self.nextReply();
            },

            nextReply: function () {
                var self = this;
                var item = self.items[self.game.pointer];

                var code = "<div class='fluency_reply selfcheck_reply selfcheck_reply_" + self.game.pointer
                    + " text-center' style='display:none;'>";
                code += "<div class='form-container'>";
                code += "<div class='fluency_prompt'>";
                code += item.displayprompt || item.prompt;
                code += "</div>";
                if (item.hintdisplay) {
                    var rtl = self.itemdata.hintrtl ? ' rtl' : '';
                    code += "<div class='fluency_prompt_hint" + rtl + "'>";
                    code += item.target;
                    code += "</div>";
                }
                code += "</div>";

                // Hint - image.
                if (item.imageurl) {
                    code += "<div class='minilesson_sentence_image'><div class='minilesson_padded_image'><img src='"
                        + item.imageurl + "' alt='' /></div></div>";
                }

                self.questioncont.append(code);
                var newreply = self.container.find(".selfcheck_reply_" + self.game.pointer);
                anim.do_animate(newreply, 'zoomIn animate__faster', 'in');

                self.show_recorder();
                self.ctrlbtns.prop("disabled", false);
                self.modelbtn.toggle(item.audio !== null);

                // Start timer if we have one.
                self.startTimer();

                // We autoplay the model audio on item entry, if its not a mobile user.
                // If we do not have a start page and its the first item, we play on the item show event.
                if (!self.quizhelper.mobile_user() && item.audio) {
                    var playmodel = function () {
                        setTimeout(function () {
                            self.play_one(self.modelbtn, item.audio);
                        }, 1000);
                    };
                    if (self.itemdata.hidestartpage && self.game.pointer === 0) {
                        self.container.on("showElement", playmodel);
                    } else {
                        playmodel();
                    }
                }
            },

            // Show the recorder and hide the approve / retry buttons and the recording's players.
            show_recorder: function () {
                var self = this;
                self.verdict.hide();
                self.selfbtn.hide();
                self.comparebtn.hide().removeClass('selfcheck_pulse');
                self.speakbtncont.show();
            },

            // The recorder has finished. Swap it for the approve / retry buttons and play the compare sequence.
            got_recording: function (bloburl, mediaurl) {
                var self = this;
                var item = self.items[self.game.pointer];
                // We play the local recording. The S3 copy may still be uploading, but we keep it for the results.
                item.audioself = new Audio(bloburl);
                item.mediaurl = mediaurl;

                self.speakbtncont.hide();
                self.selfbtn.show();
                self.comparebtn.show();
                self.verdict.show();

                var playbackid = self.playbackid;
                setTimeout(function () {
                    // Only autoplay if nothing has happened since the recording stopped.
                    if (playbackid === self.playbackid && self.items[self.game.pointer] === item && item.audioself) {
                        self.play_compare();
                    }
                }, self.comparedelay);
            },

            // Play the model audio, the student's recording and the model audio again.
            play_compare: function () {
                var self = this;
                var item = self.items[self.game.pointer];
                self.stop_audio();
                var playbackid = self.playbackid;

                var steps = [];
                if (item.audio) {
                    steps.push({button: self.modelbtn, audio: item.audio});
                }
                steps.push({button: self.selfbtn, audio: item.audioself});
                if (item.audio) {
                    steps.push({button: self.modelbtn, audio: item.audio});
                }

                self.comparebtn.removeClass('selfcheck_pulse');
                self.set_playing(self.comparebtn, true);

                var dostep = function (i) {
                    if (playbackid !== self.playbackid) {
                        return;
                    }
                    if (i >= steps.length) {
                        self.set_playing(self.comparebtn, false);
                        return;
                    }
                    self.play_audio(steps[i].button, steps[i].audio).then(function (played) {
                        if (!played) {
                            // The browser blocked playback (usually autoplay on mobile). Ask for a tap instead.
                            self.stop_audio();
                            self.comparebtn.addClass('selfcheck_pulse');
                            return;
                        }
                        dostep(i + 1);
                    });
                };
                dostep(0);
            },

            // Play or stop a single audio from its button.
            toggle_play: function (button, audio) {
                var self = this;
                if (!audio) {
                    return;
                }
                if (button.hasClass('selfcheck_playing')) {
                    self.stop_audio();
                } else {
                    self.play_one(button, audio);
                }
            },

            play_one: function (button, audio) {
                var self = this;
                self.stop_audio();
                self.play_audio(button, audio);
            },

            // Play an audio and resolve when it ends. Resolves false if the browser would not play it.
            play_audio: function (button, audio) {
                var self = this;
                return new Promise(function (resolve) {
                    var finished = function (played) {
                        audio.onended = null;
                        audio.onerror = null;
                        self.set_playing(button, false);
                        resolve(played);
                    };
                    audio.onended = function () {
                        finished(true);
                    };
                    // A broken audio file should not stop the sequence.
                    audio.onerror = function () {
                        finished(true);
                    };
                    self.set_playing(button, true);
                    audio.currentTime = 0;
                    var playing = audio.play();
                    if (playing !== undefined) {
                        playing.catch(function (e) {
                            log.debug('Self Check: could not play audio: ' + e.name);
                            // A pause from stop_audio also rejects play(), that is not a blocked play.
                            finished(e.name !== 'NotAllowedError');
                        });
                    }
                });
            },

            // Swap a player button's icon for a stop icon while it plays.
            set_playing: function (button, playing) {
                var icon = button.children('.fa');
                if (playing) {
                    if (!button.data('icon')) {
                        button.data('icon', icon.attr('class'));
                    }
                    icon.attr('class', 'fa fa-stop');
                    button.addClass('selfcheck_playing');
                } else {
                    if (button.data('icon')) {
                        icon.attr('class', button.data('icon'));
                    }
                    button.removeClass('selfcheck_playing');
                }
            },

            // Stop all audio for the current sentence, including a running compare sequence.
            stop_audio: function () {
                var self = this;
                self.playbackid++;
                var item = self.items ? self.items[self.game.pointer] : null;
                if (item) {
                    [item.audio, item.audioself].forEach(function (audio) {
                        if (audio) {
                            audio.onended = null;
                            audio.onerror = null;
                            if (!audio.paused) {
                                audio.pause();
                            }
                        }
                    });
                }
                self.set_playing(self.modelbtn, false);
                self.set_playing(self.selfbtn, false);
                self.set_playing(self.comparebtn, false);
            },

            startTimer: function () {
                var self = this;
                // If we have a time limit, set up the timer, otherwise return.
                if (self.itemdata.timelimit > 0) {
                    var doStartTimer = function () {
                        self.progresscont.show();
                        self.progresscont.addClass('d-flex align-items-center');
                        self.progresscont.find('i').show();
                        var progresbar = self.progresscont.find('#progresstimer').progressTimer({
                            height: '5px',
                            timeLimit: self.itemdata.timelimit,
                            onFinish: function () {
                                self.skipbtn.trigger('click');
                            }
                        });
                        progresbar.each(function () {
                            self.items[self.game.pointer].timer.push($(this).attr('timer'));
                        });
                    };

                    // If we dont have a start page and its the first item, defer the timer until the item is shown.
                    if (self.itemdata.hidestartpage && self.game.pointer === 0) {
                        self.container.on("showElement", doStartTimer);
                    } else {
                        doStartTimer();
                    }
                }
            },

            stopTimer: function (timers) {
                timers.forEach(function (timer) {
                    clearInterval(timer);
                });
            },

            // Prepare items for display in the listitemresults template (here and later in finished review).
            items_for_results_display: function (inthissession) {
                var self = this;
                return self.items.map(function (item) {
                    var selfsrc = false;
                    if (item.audioself) {
                        selfsrc = inthissession ? item.audioself.src : item.mediaurl;
                    }
                    return {
                        target: item.displayprompt || item.prompt,
                        answered: item.answered,
                        correct: item.correct,
                        audio: item.audio ? {src: item.audio.src} : null,
                        audioself: selfsrc ? {src: selfsrc} : null,
                    };
                });
            },
        };
        return theselfcheckitem;
    }
);
