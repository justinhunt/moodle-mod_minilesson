define(['jquery',
    'core/log',
    'core/ajax',
    'mod_minilesson/definitions',
    'mod_minilesson/pollyhelper',
    'mod_minilesson/ttrecorder',
    'mod_minilesson/animatecss',
    'mod_minilesson/progresstimer',
    'core/templates',
    'core/str',
    'core/notification'
], function ($, log, ajax, def, polly, ttrecorder, anim, progresstimer, templates, str, notification) {
    "use strict"; // jshint ;_;

    log.debug('MiniLesson speaking gap fill: initialising');

    return {

        //a handle on the tt recorder
        ttrec: null,
        controls: {},

        // For making multiple instances
        clone: function () {
            return $.extend(true, {}, this);
        },

        init: function (index, itemdata, quizhelper) {
            var self = this;
            self.strings = {};
            var theCallback = function (message) {

                switch (message.type) {
                    case 'recording':
                        break;

                    case 'speech':
                        log.debug("Speech at speaking gap fill -");
                        var words = self.items[self.game.pointer].words;
                        var maskedwords = [];

                        Object.keys(words).forEach(function (key) {
                            maskedwords.push(words[key]);
                        });

                        self.getComparison(
                            maskedwords.join(" "),
                            message.capturedspeech,
                            self.items[self.game.pointer].phonetic,
                            function (comparison) {
                                self.gotComparison(comparison, message);
                            }
                        );
                        break;

                    case 'mediasaved':
                        self.mediaurl = message.mediaurl;
                        log.debug('Media saved at speaking gap fill: ' + self.mediaurl);
                        break;
                }

            };

            // Init the TT Recorder
            var opts = {};
            opts.uniqueid = itemdata.uniqueid;
            opts.callback = theCallback;
            opts.stt_guided = quizhelper.is_stt_guided();
            opts.wwwroot = quizhelper.is_stt_guided();
            self.ttrec = ttrecorder.clone();
            self.ttrec.init(opts);

            self.itemdata = itemdata;
            log.debug("itemdata");
            log.debug(itemdata);
            self.quizhelper = quizhelper;
            self.index = index;

            // Anim
            var animopts = {};
            animopts.useanimatecss = quizhelper.useanimatecss;
            anim.init(animopts);
            self.init_controls();
            self.init_strings();
            self.register_events();
            self.setvoice();
            self.getItems();
        },

        init_controls: function () {
            var self = this;
            self.controls = {
                container: $("#" + self.itemdata.uniqueid + "_container"),
                listen_cont: $("#" + self.itemdata.uniqueid + "_container .sgapfill_listen_cont"),
                nextbutton: $("#" + self.itemdata.uniqueid + "_container .minilesson_nextbutton"),
                start_btn: $("#" + self.itemdata.uniqueid + "_container .sgapfill_start_btn"),
                skip_btn: $("#" + self.itemdata.uniqueid + "_container .sgapfill_skip_btn"),
                ctrl_btn: $("#" + self.itemdata.uniqueid + "_container .sgapfill_ctrl-btn"),
                speakbtncontainer: $("#" + self.itemdata.uniqueid + "_container .sgapfill_speakbtncontainer"),
                game: $("#" + self.itemdata.uniqueid + "_container .sgapfill_game"),
                controlsbox: $("#" + self.itemdata.uniqueid + "_container .sgapfill_controls"),
                resultscontainer: $("#" + self.itemdata.uniqueid + "_container .sgapfill_resultscontainer"),
                mainmenu: $("#" + self.itemdata.uniqueid + "_container .sgapfill_mainmenu"),
                title: $("#" + self.itemdata.uniqueid + "_container .sgapfill_title"),
                progress_container: $("#" + self.itemdata.uniqueid + "_container .progress-container"),
                progress_bar: $("#" + self.itemdata.uniqueid + "_container .progress-container .progress-bar"),
                question: $("#" + self.itemdata.uniqueid + "_container .question"),
                listen_btn: $("#" + self.itemdata.uniqueid + "_container .sgapfill_listen_btn"),
                description: $("#" + self.itemdata.uniqueid + "_container .sgapfill_description"),
                image: $("#" + self.itemdata.uniqueid + "_container .sgapfill_image_container"),
                maintitle: $("#" + self.itemdata.uniqueid + "_container .sgapfill_maintitle"),
                itemquestion: $("#" + self.itemdata.uniqueid + "_container .sgapfill_itemtext"),
            };
        },

        init_strings: function () {
            var self = this;
            str.get_strings([
                { "key": "nextlessonitem", "component": 'mod_minilesson' },
                { "key": "confirm_desc", "component": 'mod_minilesson' },
                { "key": "yes", "component": 'moodle' },
                { "key": "no", "component": 'moodle' },
            ]).done(function (s) {
                var i = 0;
                self.strings.nextlessonitem = s[i++];
                self.strings.confirm_desc = s[i++];
                self.strings.yes = s[i++];
                self.strings.no = s[i++];
            });
        },

        next_question: function (percent) {
            var self = this;
            var stepdata = {};
            stepdata.index = self.index;
            stepdata.hasgrade = true;
            stepdata.totalitems = self.items.length;
            stepdata.correctitems = self.items.filter(function (e) {
                return e.correct;
            }).length;
            stepdata.grade = Math.round((stepdata.correctitems / stepdata.totalitems) * 100);

            //stop audio
            self.stop_audio();

            self.quizhelper.do_next(stepdata);
        },

        show_item_review: function () {
            var self = this;
            var review_data = {};

            self.items.forEach(function (item) {
                var itemwordlist = [];
                item.parsedstring.forEach(function (data) {
                    if (data.type === 'input' || data.type === 'mtext') {
                        itemwordlist.push(data.character);
                    }
                });
                var wordmatch = itemwordlist.join("");
                var regex = new RegExp(wordmatch, "gi");
                var answerclass = item.correct ? 'correctitem' : 'wrongitem';
                var result = item.target.replace(regex, `<span class="${answerclass}">${wordmatch}</span>`);
                item.target = result;
            });

            review_data.items = self.items;
            review_data.totalitems = self.items.length;
            review_data.correctitems = self.items.filter(function (e) {
                return e.correct; }).length;

            //Get controls
            var listencont = self.controls.listen_cont;
            var qbox = self.controls.question;
            var recorderbox = self.controls.speakbtncontainer;
            var gamebox = self.controls.game;
            var controlsbox = self.controls.controlsbox;
            var resultsbox = self.controls.resultscontainer;

            //display results
            templates.render('mod_minilesson/listitemresults', review_data).then(
                function (html, js) {
                    resultsbox.html(html);
                    //show and hide
                    resultsbox.show();
                    gamebox.hide();
                    controlsbox.hide();
                    listencont.hide();
                    qbox.hide();
                    recorderbox.hide();
                    // Run js for audio player events
                    templates.runTemplateJS(js);
                }
            );// End of templates
        },

        register_events: function () {

            var self = this;
            // On next button click
            self.controls.container.find('.minilesson_nextbutton').on('click', function (e) {
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
            // On start button click
            self.controls.start_btn.on("click", function () {
                self.start();
            });

            //AUDIO PLAYER Button events
            var audioplayerbtn = self.controls.listen_btn;
            // On listen button click
            if (self.itemdata.readsentence) {
                audioplayerbtn.on("click", function () {
                    var theaudio = self.items[self.game.pointer].audio;

                    //if we are already playing stop playing
                    if (!theaudio.paused) {
                        theaudio.pause();
                        theaudio.currentTime = 0;
                        $(audioplayerbtn).children('.fa').removeClass('fa-stop');
                        $(audioplayerbtn).children('.fa').addClass('fa-volume-up');
                        return;
                    }

                    //change icon to indicate playing state
                    theaudio.addEventListener('ended', function () {
                        $(audioplayerbtn).children('.fa').removeClass('fa-stop');
                        $(audioplayerbtn).children('.fa').addClass('fa-volume-up');
                    });

                    theaudio.addEventListener('play', function () {
                        $(audioplayerbtn).children('.fa').removeClass('fa-volume-up');
                        $(audioplayerbtn).children('.fa').addClass('fa-stop');
                    });
                    theaudio.load();
                    theaudio.play();
                });
            }

            // On skip button click
            self.controls.skip_btn.on("click", function () {
                // Disable the buttons
                self.controls.ctrl_btn.prop("disabled", true);
                // Reveal the prompt
                self.controls.container.find('.sgapfill_speech.sgapfill_teacher_left').text(self.items[self.game.pointer].prompt + "");
                // Reveal the answer
                self.controls.container.find('.sgapfill_targetWord').each(function () {
                    var realidx = $(this).data("realidx");
                    var sgapfill_targetWord = self.items[self.game.pointer].sgapfill_targetWords[realidx];
                    $(this).val(sgapfill_targetWord);
                });

                self.stopTimer(self.items[self.game.pointer].timer);

                //mark as answered and incorrect
                self.items[self.game.pointer].answered = true;
                self.items[self.game.pointer].correct = false;

                if (self.game.pointer < self.items.length - 1) {
                    self.controls.skip_btn.prop("disabled", true);
                    self.controls.skip_btn.children('.fa').removeClass('fa-arrow-right');
                    self.controls.skip_btn.children('.fa').addClass('fa-spinner fa-spin');
                    // Move on after short time to next prompt
                    setTimeout(function () {
                        self.controls.skip_btn.children('.fa').removeClass('fa-spinner fa-spin');
                        self.controls.skip_btn.children('.fa').addClass('fa-arrow-right');
                        self.controls.skip_btn.prop("disabled", false);
                        self.controls.container.find('.sgapfill_reply_' + self.game.pointer).hide();
                        self.game.pointer++;
                        self.nextPrompt();
                    }, 2000);
                    // End question
                } else {
                    self.end();
                }

            });

        },

        game: {
            pointer: 0
        },

        usevoice: '',

        setvoice: function () {
            var self = this;
            self.usevoice = self.itemdata.usevoice;
            self.voiceoption = self.itemdata.voiceoption;
        },

        getItems: function () {
            var self = this;
            var text_items = self.itemdata.sentences;

            self.items = text_items.map(function (target) {
                return {
                    target: target.sentence,
                    segmentedsentence: target.segmentedsentence,
                    prompt: target.prompt,
                    parsedstring: target.parsedstring,
                    displayprompt: target.displayprompt,
                    definition: target.definition,
                    phonetic: target.phonetic,
                    words: target.words,
                    typed: "",
                    timer: [],
                    answered: false,
                    correct: false,
                    audio: null,
                    audiourl: target.audiourl ? target.audiourl : "",
                    imageurl: target.imageurl,
                };
            }).filter(function (e) {
                return e.target !== "";
            });

            if (self.itemdata.readsentence) {
                $.each(self.items, function (index, item) {
                    item.audio = new Audio();
                    item.audio.src = item.audiourl;
                });
                self.appReady();
            } else {
                self.appReady();
            }
        },

        appReady: function () {
            var self = this;
            self.controls.container.find('.sgapfill_not_loaded').hide();
            self.controls.container.find('.sgapfill_loaded').show();
            if (self.itemdata.hidestartpage) {
                self.start();
            } else {
                self.controls.start_btn.prop("disabled", false);
            }
        },

        gotComparison: function (comparison, typed) {
            log.debug("sgapfill comparison");
            var self = this;
            var countdownStarted = false;
            var feedback = self.controls.container.find('.sgapfill_reply_' + self.game.pointer + " .dictate_feedback[data-idx='" + self.game.pointer + "']");

            self.controls.container.find('.sgapfill_targetWord').removeClass("sgapfill_correct sgapfill_incorrect");
            self.controls.container.find('.sgapfill_feedback').removeClass("fa fa-check fa-times");

            var allCorrect = comparison.filter(function (e) {
                return !e.matched;
            }).length == 0;
            log.debug('allcorrect=' + allCorrect);

            if (allCorrect && comparison && comparison.length > 0) {
                // Fill in all the correct words
                self.items[self.game.pointer].parsedstring.forEach(function (data, index) {
                    var characterinput = self.controls.container.find('.sgapfill_reply_' + self.game.pointer + ' .sgapfill_missing_input[data-index="' + index + '"]');
                    if (characterinput && data.type === 'input') {
                        characterinput.text(data.character);
                    }
                });

                feedback.removeClass("fa fa-times");
                feedback.addClass("fa fa-check");
                //make the input boxes green and move forward
                log.debug('applying correct class to input boxes');
                self.controls.container.find('.sgapfill_reply_' + self.game.pointer + " .sgapfill_missing_input").addClass("ml_gapfill_char_correct");

                self.items[self.game.pointer].answered = true;
                self.items[self.game.pointer].correct = true;
                self.items[self.game.pointer].typed = typed;

                self.controls.ctrl_btn.prop("disabled", true);

                self.stopTimer(self.items[self.game.pointer].timer);

                if ((self.game.pointer < self.items.length - 1) && !countdownStarted) {
                    countdownStarted = true;
                    log.debug('moving to next prompt B');
                    setTimeout(function () {
                        self.controls.container.find('.sgapfill_reply_' + self.game.pointer).hide();
                        self.game.pointer++;
                        self.nextPrompt();
                    }, 2000);
                } else {
                    self.updateProgressDots();
                    self.end();
                }
            } else {
                feedback.removeClass("fa fa-check");
                feedback.addClass("fa fa-times");
                // Mark up the words as correct or not
                comparison.forEach(function (obj) {
                    var words = self.items[self.game.pointer].words;

                    Object.keys(words).forEach(function (key) {
                        if (words[key] == obj.word) {
                            if (!obj.matched) {
                                self.items[self.game.pointer].parsedstring.forEach(function (data, index) {
                                    var characterinput = self.controls.container.find('.sgapfill_reply_' + self.game.pointer + ' input.single-character[data-index="' + index + '"]');
                                    if (data.index == key && data.type === 'input') {
                                        characterinput.val('');
                                    }
                                });
                            } else {
                                self.items[self.game.pointer].parsedstring.forEach(function (data, index) {
                                    var characterinput = self.controls.container.find('.sgapfill_reply_' + self.game.pointer + ' input.single-character[data-index="' + index + '"]');
                                    if (data.index == key && data.type === 'input') {
                                        characterinput.val(data.character);
                                        characterinput.addClass("ml_gapfill_char_correct");
                                    }
                                });
                            }
                        }
                    });
                });
                var thereply = self.controls.container.find('.sgapfill_reply_' + self.game.pointer);
                anim.do_animate(thereply, 'shakeX animate__faster').then(
                    function () {
                        self.controls.ctrl_btn.prop("disabled", false);
                    }
                );

                // Show all the correct words
                self.controls.container.find('.sgapfill_targetWord.sgapfill_correct').each(function () {
                    var realidx = $(this).data("realidx");
                    var targetWord = self.items[self.game.pointer].sgapfill_targetWords[realidx];
                    $(this).val(targetWord);
                });

                //if they cant retry OR the time limit is up, move on
                var timelimit_progressbar = self.controls.progress_bar;
                if (!self.itemdata.allowretry || timelimit_progressbar.hasClass('progress-bar-complete')) {
                    self.items[self.game.pointer].answered = true;
                    self.items[self.game.pointer].correct = false;
                    self.items[self.game.pointer].typed = typed;

                    self.controls.ctrl_btn.prop("disabled", true);

                    self.stopTimer(self.items[self.game.pointer].timer);

                    if (!countdownStarted) {
                        if (self.game.pointer < self.items.length - 1) {
                            countdownStarted = true;
                            setTimeout(function () {
                                self.controls.container.find('.sgapfill_reply_' + self.game.pointer).hide();
                                self.game.pointer++;
                                self.nextPrompt();
                            }, 2000);
                        } else {
                            self.updateProgressDots();
                            self.end();
                        }
                    }//end of if countdown not started
                } //end of if can't retry or time limit up
            }//end of if -all -correct or not
        },

        getComparison: function (passage, transcript, phonetic, callback) {
            var self = this;

            self.controls.ctrl_btn.prop("disabled", true);
            self.quizhelper.comparePassageToTranscript(passage, transcript, phonetic, self.itemdata.language, self.itemdata.alternates).then(function (ajaxresult) {
                var payloadobject = JSON.parse(ajaxresult);
                if (payloadobject) {
                    callback(payloadobject);
                } else {
                    callback(false);
                }
            });
        },

        end: function () {
            var self = this;
            $(".minilesson_nextbutton").prop("disabled", true);

            //progress dots are updated on next_item. The last item has no next item, so we update from here
            self.updateProgressDots();

            setTimeout(function () {
                $(".minilesson_nextbutton").prop("disabled",false);
                if (self.quizhelper.showitemreview) {
                    self.controls.progress_container.removeClass('d-flex');
                    self.controls.progress_container.hide();
                    self.controls.title.hide();
                    self.show_item_review();
                } else {
                    self.next_question();
                }
            }, 2000);

        },

        start: function () {
            var self = this;

            self.controls.ctrl_btn.prop("disabled", true);
            self.controls.speakbtncontainer.show();

            self.items.forEach(function (item) {
                item.spoken = "";
                item.answered = false;
                item.correct = false;
            });

            self.game.pointer = 0;

            self.controls.question.show();
            if (self.itemdata.readsentence) {
                self.controls.listen_cont.show();
            }
            self.controls.start_btn.hide();
            self.controls.mainmenu.hide();
            self.controls.controlsbox.show();
            self.controls.maintitle.show();
            self.controls.itemquestion.show();
            self.controls.description.hide();
            self.controls.image.hide();

            self.nextPrompt();
        },

        updateProgressDots: function () {
            var self = this;
            var color,icon;
            var progress = self.items.map(function (item, idx) {
                color = "#E6E9FD";
                icon = 'fa fa-square';
                if (self.items[idx].answered && self.items[idx].correct) {
                    color = "#74DC72";
                    icon = 'fa fa-check-square';
                } else if (self.items[idx].answered && !self.items[idx].correct) {
                    color = "#FB6363";
                    icon = 'fa fa-window-close';
                }
                return "<i style='color:" + color + "' class='" + icon + " pl-1'></i>";
            }).join(" ");
            self.controls.title.html(progress);
        },

        nextPrompt: function () {
            var self = this;
            self.controls.ctrl_btn.prop("disabled", false);
            self.updateProgressDots();
            var newprompt = self.controls.container.find('.sgapfill_prompt_' + self.game.pointer);
            anim.do_animate(newprompt, 'zoomIn animate__faster', 'in').then(
                function () {
                }
            );
            self.nextReply();
        },


        nextReply: function () {
            var self = this;

            var code = "<div class='sgapfill_reply sgapfill_reply_" + self.game.pointer + " text-center' style='display:none;'>";
            var brackets = { started: false, ended: false, index: null };

            code += "<div class='form-container'>";
            self.items[self.game.pointer].parsedstring.forEach(function (data, index) {
                if (brackets.started && !brackets.ended && brackets.index !== data.index) {
                    brackets.started = brackets.ended = false;
                    code += '</span>';
                }
                if ((data.type === 'input' || data.type === 'mtext') && !brackets.started) {
                    code += '<span class="form-input-phrase-online" data-mindex="' + data.index + '">';
                    brackets.started = true;
                }
                brackets.index = data.index;
                if (data.type === 'input') {
                    code += "<span class='sgapfill_missing_input' data-index='" + index + "'></span>";
                } else if (data.type === 'mtext') {
                    code += "<span class='sgapfill_missing_inputmtext'>" + data.character + "</span>";
                } else {
                    code += data.character;
                }
            });
            if (brackets.started && !brackets.ended) {
                code += '</span>';
            }
            //correct or not
            code += " <i data-idx='" + self.game.pointer + "' class='dictate_feedback'></i></div>";

            //hints
            // We need to set a higher margin for the image if there is no hint, to stop it being overlapped by the recorder
            var hasdefinition = self.items[self.game.pointer].definition && self.items[self.game.pointer].definition !== "";
            var imagebottommargin = hasdefinition ? "margin-bottom: 5px" : "margin-bottom: 50px";
            if (self.items[self.game.pointer].imageurl) {
                code += "<div class='minilesson_sentence_image' style='" + imagebottommargin + "'><div class='minilesson_padded_image'><img src='"
                    + self.items[self.game.pointer].imageurl + "' alt='Image for gap fill' /></div></div>";
            }
            //hint - definition
            if (hasdefinition) {
                code += "<div class='definition-container'><div class='definition'>"
                    + "<div class='hinticon-container'><img class='icon' src='" + M.util.image_url('lightbulb-icon', 'mod_minilesson') + "' alt='hint'></div>"
                    + "<h4 class='hint-title'>Hint</h4>"
                    + self.items[self.game.pointer].definition + "</div>";
            }


            self.controls.question.append(code);


            var newreply = self.controls.container.find('.sgapfill_reply_' + self.game.pointer);
            anim.do_animate(newreply, 'zoomIn animate__faster', 'in').then(
                function () {
                }
            );
            self.controls.ctrl_btn.prop("disabled", false);

            // Start the timer (if we have one)
            self.startTimer();

            // We autoplay the audio on item entry, if its not a mobile user.
            // If we do not have a start page and its the first item, we play on the item show event
            if (!self.quizhelper.mobile_user()) {
                if (self.itemdata.hidestartpage && self.game.pointer === 0) {
                    self.controls.container.on("showElement", () => {
                        setTimeout(function () {
                            self.controls.listen_btn.trigger('click');
                        }, 1000);
                    });
                } else {
                    setTimeout(function () {
                        self.controls.listen_btn.trigger('click');
                    }, 1000);
                }
            }

            //target is the speech we expect
            var target = self.items[self.game.pointer].target;
            //in some cases ttrecorder wants to know the target
            if (self.quizhelper.use_ttrecorder()) {
                self.ttrec.currentPrompt = target;
            }
        },

        // Stop audio .. usually when leaving the item or sentence
        stop_audio: function () {
            var self = this;
            //pause audio if its playing
            var theaudio = self.items[self.game.pointer].audio;
            if (theaudio && !theaudio.paused) {
                theaudio.pause();
            }
        },

        startTimer: function () {
            var self = this;
            // If we have a time limit, set up the timer, otherwise return
            if (self.itemdata.timelimit > 0) {
                // This is a function to start the timer (we call it conditionally below)
                var doStartTimer = function () {
                    // This shows progress bar
                    self.controls.progress_container.show();
                    self.controls.progress_container.addClass('d-flex align-items-center');
                    self.controls.progress_container.find('i').show();
                    var progresbar = self.controls.progress_container.find('#progresstimer').progressTimer({
                        height: '5px',
                        timeLimit: self.itemdata.timelimit,
                        onFinish: function () {
                            self.controls.skip_btn.trigger('click');
                        }
                    });
                    progresbar.each(function () {
                        self.items[self.game.pointer].timer.push($(this).attr('timer'));
                    });
                }

                // This adds the timer and starts it. But if we dont have a start page and its the first item
                // we need to defer the timer start until the item is shown
                if (self.itemdata.hidestartpage && self.game.pointer === 0) {
                    self.controls.container.on("showElement", () => {
                        doStartTimer();
                    });
                } else {
                    doStartTimer();
                }
            }
        },

        stopTimer: function (timers) {
            if (timers.length) {
                timers.forEach(function (timer) {
                    clearInterval(timer);
                });
            }
        },
    };
});