define(['jquery',
    'core/log',
    'core/ajax',
    'mod_minilesson/definitions',
    'mod_minilesson/pollyhelper',
    'mod_minilesson/animatecss',
    'mod_minilesson/progresstimer',
    'core/templates',
    'core/notification',
    'core/str',
], function($, log, ajax, def, polly, anim, progresstimer, templates, notification, str) {
    "use strict"; // jshint ;_;

    log.debug('MiniLesson wordshuffle: initialising');

    return {

        strings: {},

        // For making multiple instances
        clone: function() {
            return $.extend(true, {}, this);
        },

        usevoice: 'Amy',

        pointerdiv: null,

        init: function(index, itemdata, quizhelper) {
            var self = this;
            self.itemdata = itemdata;
            // Default to true, we might implement a start page later.
            self.itemdata.hidestartpage = true;
            self.quizhelper = quizhelper;
            self.index = index;

            // Anim
            var animopts = {};
            animopts.useanimatecss = quizhelper.useanimatecss;
            anim.init(animopts);
            self.init_strings();
            self.init_controls();
            self.register_events();
            self.setvoice();
            self.getItems();
            self.start();
        },

        init_controls: function() {
            var self = this;
            var container = $("#" + self.itemdata.uniqueid + "_container");
            self.controls = {
                container: container,
                listen_cont: container.find(".wordshuffle_listen_cont"),
                nextbutton: container.find(".minilesson_nextbutton"),
                start_btn: container.find(".wordshuffle_start_btn"),
                skip_btn: container.find(".wordshuffle_skip_btn"),
                ctrl_btn: container.find(".wordshuffle_ctrl-btn"),
                check_btn: container.find(".wordshuffle_check_btn"),
                game: container.find(".wordshuffle_game"),
                controlsbox: container.find(".wordshuffle_controls"),
                resultscontainer: container.find(".wordshuffle_resultscontainer"),
                mainmenu: container.find(".wordshuffle_mainmenu"),
                title: container.find(".wordshuffle_title"),
                progress_container: container.find(".progress-container"),
                progress_bar: container.find(".progress-container .progress-bar"),
                question: container.find(".question"),
                listen_btn: container.find(".wordshuffle_listen_btn"),
                retry_btn: container.find(".wordshuffle_retry_btn")
            };
        },

        init_strings: function() {
            var self = this;
            str.get_strings([
                { "key": "nextlessonitem", "component": 'mod_minilesson'},
                { "key": "confirm_desc", "component": 'mod_minilesson'},
                { "key": "yes", "component": 'moodle'},
                { "key": "no", "component": 'moodle'},
            ]).done(function (s) {
                var i = 0;
                self.strings.nextlessonitem = s[i++];
                self.strings.confirm_desc = s[i++];
                self.strings.yes = s[i++];
                self.strings.no = s[i++];
            });
        },

        next_question: function() {
            var self = this;
            var stepdata = {};
            stepdata.index = self.index;
            stepdata.hasgrade = true;
            stepdata.totalitems = self.items.length;
            stepdata.correctitems = self.items.filter(function(e) {
                return e.correct;
            }).length;
            stepdata.grade = Math.round((stepdata.correctitems / stepdata.totalitems) * 100);

            //stop audio
            self.stop_audio();

            self.quizhelper.do_next(stepdata);
        },

        show_item_review:function(){
            var self=this;
            var review_data = {};
            review_data.items = self.items;
            review_data.totalitems=self.items.length;
            review_data.correctitems=self.items.filter(function(e) {return e.correct;}).length;

            //Get controls
            var listencont = self.controls.listen_cont;
            var qbox = self.controls.question;
            var gamebox = self.controls.game;
            var controlsbox = self.controls.controlsbox;
            var resultsbox = self.controls.resultscontainer;

            //display results
            templates.render('mod_minilesson/listitemresults',review_data).then(
              function(html,js){
                  resultsbox.html(html);
                  //show and hide
                  resultsbox.show();
                  gamebox.hide();
                  controlsbox.hide();
                  listencont.hide();
                  qbox.hide();
                  //recorderbox.hide();
                  // Run js for audio player events
                  templates.runTemplateJS(js);
              }
            );// End of templates
        },

        register_events: function() {

            var self = this;

            self.controls.nextbutton.on('click', function(e) {
                if (self.items.some(item => !item.answered)) {
                    notification.confirm(self.strings.nextlessonitem,
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

            self.controls.start_btn.on("click", function() {
                self.start();
            });

            //AUDIO PLAYER events
            var audioplayerbtn = self.controls.listen_btn;
            //audio button click event
            audioplayerbtn.on("click", function() {
                var theaudio =self.items[self.game.pointer].audio;

                //if we are already playing stop playing
                if(!theaudio.paused){
                    theaudio.pause();
                    theaudio.currentTime=0;
                    $(audioplayerbtn).children('.fa').removeClass('fa-stop');
                    $(audioplayerbtn).children('.fa').addClass('fa-volume-up');
                    return;
                }

                //change icon to indicate playing state
                theaudio.addEventListener('ended', function(){
                    $(audioplayerbtn).children('.fa').removeClass('fa-stop');
                    $(audioplayerbtn).children('.fa').addClass('fa-volume-up');
                });

                theaudio.addEventListener('play', function(){
                    $(audioplayerbtn).children('.fa').removeClass('fa-volume-up');
                    $(audioplayerbtn).children('.fa').addClass('fa-stop');
                });

                theaudio.load();
                theaudio.play();
            });

            // On skip button click
            self.controls.skip_btn.on("click", function() {
                // Disable buttons
                self.controls.ctrl_btn.prop("disabled", true);
                // Reveal prompt
                self.controls.container.find('.wordshuffle_speech.wordshuffle_teacher_left').text(self.items[self.game.pointer].prompt + "");
                // Reveal answers
                self.controls.container.find('.wordshuffle_targetWord').each(function() {
                    var realidx = $(this).data("realidx");
                    var wordshuffle_targetWord = self.items[self.game.pointer].wordshuffle_targetWords[realidx];
                    $(this).val(wordshuffle_targetWord);
                });

                self.stopTimer(self.items[self.game.pointer].timer);

                //mark as answered and incorrect
                self.items[self.game.pointer].answered = true;
                self.items[self.game.pointer].correct = false;

                // Move on after short time, to next prompt, or next question
                if (self.game.pointer < self.items.length - 1) {
                    setTimeout(function() {
                        self.controls.container.find('.wordshuffle_reply_' + self.game.pointer).hide();
                        self.game.pointer++;
                        self.nextPrompt();
                    }, 2000);
                } else {
                    self.end();
                }
            });


            self.controls.check_btn.on("click", function() {
                self.pointerdiv.find(".drop-slot .word").each(function () {
                    self.placeInBank($(this));
                });
                self.clearPerSlotFeedback();
                self.controls.retry_btn.hide();
                self.items[self.game.pointer].answered = false;
                self.items[self.game.pointer].correct = false;
            });

            self.controls.retry_btn.on("click", function() {
                self.pointerdiv.find(".drop-slot .word").each(function () {
                    self.placeInBank($(this));
                });
                self.clearPerSlotFeedback();
                self.controls.retry_btn.hide();
                self.controls.check_btn.show();
                self.items[self.game.pointer].answered = false;
                self.items[self.game.pointer].correct = false;
            });
        },

        game: {
            pointer: 0
        },

        check_answer: function() {
            var self = this;

            // self.evaluateIfComplete();
            if (self.itemdata.allowretry && !self.items[self.game.pointer].correct) {
                self.controls.retry_btn.show();
                self.controls.check_btn.hide();
            } else {
                setTimeout(() => self.gotComparison(true), 2000);
            }
        },

        setvoice: function() {
            var self = this;
            self.usevoice = self.itemdata.usevoice;
            self.voiceoption = self.itemdata.voiceoption;
            return;
        },

        getItems: function() {
            var self = this;
            var text_items = self.itemdata.sentences;

            self.items = text_items.map(function(target) {
                return {
                    target: target.sentenceclean,
                    timer: [],
                    answered: false,
                    correct: false,
                    audio: null,
                    audiourl: target.audiourl ? target.audiourl : "",
                    imageurl: target.imageurl,
                };
            }).filter(function(e) {
                return e.target !== "";
            });

            if(self.itemdata.audiocontent) {
                $.each(self.items, function (index, item) {
                    item.audio = new Audio();
                    item.audio.src = item.audiourl;
                });
                self.appReady();
            }else{
                self.appReady();
            }

        },

        appReady: function() {
            var self = this;
            self.controls.container.find('.wordshuffle_not_loaded').hide();
            self.controls.container.find('.wordshuffle_loaded').show();
            if(self.itemdata.hidestartpage){
                self.start();
            }else{
                self.controls.start_btn.prop("disabled", false);
            }
        },

        gotComparison: function(comparison) {
            var self = this;
            log.debug("gotComparison", comparison);
            var timelimit_progressbar = self.controls.progress_bar;
            if (comparison) {
                log.debug("correct!!");


                //if they cant retry OR the time limit is up, move on
            } else if(!self.itemdata.allowretry || timelimit_progressbar.hasClass('progress-bar-complete')) {
                log.debug("incorrect");

            } else {
                //it was wrong but they can retry
                log.debug("incorrect!! retry");
                return;
            }

            self.stopTimer(self.items[self.game.pointer].timer);

            if (self.game.pointer < self.items.length - 1) {
                setTimeout(function() {
                    self.controls.container.find('.wordshuffle_reply_' + self.game.pointer).hide();
                    self.game.pointer++;
                    self.nextPrompt();
                }, 2000);
            } else {
                self.end();
            }
        },

        end: function() {
            var self = this;
            self.controls.nextbutton.prop("disabled", true);

            //progress dots are updated on next_item. The last item has no next item, so we update from here
            self.updateProgressDots();

            //disable the buttons and go to next question or review
            setTimeout(function() {
                self.controls.nextbutton.prop("disabled",false);
                if(self.quizhelper.showitemreview){
                    self.show_item_review();
                }else{
                    self.next_question();
                }
            }, 2000);
        },

        start: function() {
            var self = this;

            self.controls.ctrl_btn.prop("disabled", true);

            self.items.forEach(function(item) {
                item.spoken = "";
                item.answered = false;
                item.correct = false;
            });

            self.game.pointer = 0;
            self.controls.listen_cont.show();
            self.controls.question.show();
            self.controls.game.show();
            self.controls.start_btn.hide();
            self.controls.mainmenu.hide();
            self.controls.controlsbox.show();

            self.nextPrompt();
        },

        nextPrompt: function() {

            var self = this;
            self.pointerdiv = self.controls.question.find(`.wordshuffle_wordset_container[data-index="${self.game.pointer}"]`);
            self.controls.retry_btn.hide();
            self.controls.check_btn.show();

            self.controls.ctrl_btn.prop("disabled", false);

            self.updateProgressDots();

            self.showNextWordSet();

            // We autoplay the audio on item entry, if its not a mobile user.
            // If we do not have a start page and its the first item, we play on the item show event
            if (self.items[self.game.pointer].audio !==null && !self.quizhelper.mobile_user()){
                if(self.itemdata.hidestartpage && self.game.pointer === 0){
                    self.controls.container.on("showElement", () => {
                        setTimeout(function() {
                            self.controls.listen_btn.trigger('click');
                        }, 1000);
                    });
                }else{
                    setTimeout(function() {
                        self.controls.listen_btn.trigger('click');
                    }, 1000);
                }
            }
        },

        updateProgressDots: function() {
            var self = this;
            var color;
            var progress = self.items.map(function(item, idx) {
              color = "gray";
              if (self.items[idx].answered && self.items[idx].correct) {
                color = "green";
              } else if (self.items[idx].answered && !self.items[idx].correct) {
                color = "red";
              }
              return "<i style='color:" + color + "' class='fa fa-circle'></i>";
            }).join(" ");
            self.controls.title.html(progress);
        },

        showNextWordSet: function() {
            var self = this;


            // Hide previous wordset
            self.controls.container.find('.wordshuffle_wordset_container').hide();
            // Show new one
            var newwordset = self.controls.container.find('.wordshuffle_wordset_' + self.game.pointer);
            anim.do_animate(newwordset, 'zoomIn animate__faster', 'in').then(
                function() {
                }
            );

            self.controls.ctrl_btn.prop("disabled", false);

            self.startTimer();
            self.makeDragZones();
        },

        getSlotWords: function() {
            var self = this;
            return self.pointerdiv.find(".drop-slot").map(function () {
                const w = $(this).find(".word").first().text().trim();
                return w || "";
            }).get();
        },

        allFilled: function() {
            var self = this;
            return self.getSlotWords().every(Boolean);
        },

        clearPerSlotFeedback: function() {
            var self = this;
            self.pointerdiv.find(".drop-slot").removeClass("border-success border-danger")
                .addClass("border-secondary-subtle");
            self.pointerdiv.find("[id^='fb-']").each(function () {
                $(this).removeClass("text-success text-danger").addClass("text-muted").html("&nbsp;");
            });
        },

        setPerSlotFeedback: function() {
            var self = this;
            const words = self.getSlotWords();
            const expectedAnswers = self.expectedAnswers();
            const fullExpected = [...self.fixedWords(), ...expectedAnswers];
            words.forEach((w, i) => {
                const ok = w === expectedAnswers[i];
                const $slot = self.pointerdiv.find(".drop-slot[data-index='" + i + "']");
                const $fb = self.pointerdiv.find("#fb-" + i);

                $slot.removeClass("border-secondary-subtle border-success border-danger")
                    .addClass(ok ? "border-success" : "border-danger");

                $fb.removeClass("text-muted text-success text-danger")
                .addClass(ok ? "text-success" : "text-danger")
                .text(ok ? "Correct" : "Wrong");
            });
            const attempt = [...self.fixedWords(), ...self.getSlotWords()];
            self.items[self.game.pointer].answered = words.some(Boolean);
            self.items[self.game.pointer].correct = attempt.join(" ") === fullExpected.join(" ");
        },

        evaluateIfComplete: function() {
            var self = this;
            if (self.allFilled()) {
                self.controls.check_btn.hide();
                self.controls.skip_btn.prop("disabled", true);
                self.controls.retry_btn.prop("disabled", true);
                self.setPerSlotFeedback();
                self.check_answer();
                self.items[self.game.pointer].answered = true;
            } else {
                self.controls.check_btn.show();
                self.clearPerSlotFeedback();
            }
        },

        moveToSlot: function($word, $slot) {
            var self = this;
            $word.detach()
                .css({ top: 0, left: 0, position: "relative" })
                .appendTo($slot);
            self.evaluateIfComplete();
        },

        placeInBank: function($word) {
            var self = this;
            $word.detach()
                .css({ top: 0, left: 0, position: "relative" })
                .appendTo(self.pointerdiv.find(".word-bank"));
            self.evaluateIfComplete();
        },

        gapWords: function() {
            var self = this;
            return self.itemdata.sentences[self.game.pointer].gapwords;
        },

        fixedWords: function() {
            var self = this;
            return self.gapWords().filter(w => w.isgap === false).map(w => w.word);
        },

        expectedAnswers: function() {
            var self = this;
            return self.gapWords().filter(w => w.isgap === true).map(w => w.word);
        },

        selectedWord: null,

        makeDragZones: function() {
            var self = this;

            if (!self.pointerdiv.attr('data-initialized')) {
                // Click event
                self.pointerdiv.on('click', e => {
                    const $target = $(e.target);
                    if ($target.is('.word')) {
                        if (!self.selectedWord || !self.selectedWord.is($target)) {
                            self.selectedWord = $target;
                        } else {
                            self.selectedWord = null;
                        }
                    } else if ($target.is('.word-bank')) {
                        if (self.selectedWord) {
                            if (self.selectedWord.parent('.drop-slot')) {
                                self.placeInBank(self.selectedWord);
                            }
                            self.selectedWord = null;
                        }
                    } else if ($target.is('.drop-slot')) {
                        if (self.selectedWord) {
                            self.moveToSlot(self.selectedWord, $target);
                            self.selectedWord = null;
                        }
                    }
                    self.highlightDropZones();
                });

                // Spacebar keydown event
                self.pointerdiv.on('keydown', function(e) {
                    if (e.key === ' ' || e.key === 'Spacebar') {
                        const $focused = $(document.activeElement);
                        if ($focused.is('.word')) {
                            if (!self.selectedWord || !self.selectedWord.is($focused)) {
                                self.selectedWord = $focused;
                            } else {
                                self.selectedWord = null;
                            }
                        } else if ($focused.is('.word-bank')) {
                            if (self.selectedWord) {
                                if (self.selectedWord.parent('.drop-slot')) {
                                    self.placeInBank(self.selectedWord);
                                }
                                self.selectedWord = null;
                            }
                        } else if ($focused.is('.drop-slot')) {
                            if (self.selectedWord) {
                                self.moveToSlot(self.selectedWord, $focused);
                                self.selectedWord = null;
                            }
                        }
                        self.highlightDropZones();
                        e.preventDefault();
                    }
                });

                self.pointerdiv.attr('data-initialized', 1);
            }
        },

        highlightDropZones: function() {
            var self = this;

            // Remove highlights and tabindex from drop slots, word bank, and words
            var dropZones = self.pointerdiv.find('.drop-slot')
                .removeClass('ml_ws_highlight')
                .removeAttr('tabindex');
            self.pointerdiv.find('.word').removeClass('ml_ws_highlight');
            self.pointerdiv.find('.word-bank')
                .removeClass('ml_ws_highlight')
                .removeAttr('tabindex');

            if (self.selectedWord) {
                // If the selected word is not in the word bank,
                // It has been selected from a drop slot.
                // Highlight the word bank and set tabindex to 0.
                if (!self.selectedWord.parent('.word-bank').length) {
                    self.pointerdiv.find('.word-bank')
                        .addClass('ml_ws_highlight')
                        .attr('tabindex', 0);
                }

                // Highlight the drop zones that do not contain a word
                dropZones.filter(function(_, slot) {
                    // If the slot does not contain a .word element
                    if (!slot.querySelector('.word')) {
                        // Add highlight and set tabindex to 0
                        $(slot).addClass('ml_ws_highlight');
                        $(slot).attr('tabindex', 0);
                        return true;
                    }
                    return false;
                });

                // Highlight the selected word
                self.selectedWord.addClass('ml_ws_highlight');
            }
        },

         startTimer: function(){
            var self = this;
            // If we have a time limit, set up the timer, otherwise return
            if (self.itemdata.timelimit > 0) {
               // This is a function to start the timer (we call it conditionally below)
                var doStartTimer = function() {
                     // This shows progress bar
                    self.controls.progress_container.show();
                    self.controls.progress_container.find('i').show();
                    var progresbar = self.controls.progress_container.find('#progresstimer').progressTimer({
                        height: '5px',
                        timeLimit: self.itemdata.timelimit,
                        onFinish: function() {
                            self.controls.skip_btn.trigger('click');
                        }
                    });
                    progresbar.each(function() {
                        self.items[self.game.pointer].timer.push($(this).attr('timer'));
                    });
                }

                // This adds the timer and starts it. But if we dont have a start page and its the first item
                // we need to defer the timer start until the item is shown
                if(self.itemdata.hidestartpage && self.game.pointer === 0){
                    self.controls.container.on("showElement", () => {
                        doStartTimer();
                    });
                }else{
                    doStartTimer();
                }
            }
        },

        stopTimer: function(timers) {
            if (timers.length) {
                timers.forEach(function(timer) {
                    clearInterval(timer);
                });
            }
        },

        // Stop audio .. usually when leaving the item or sentence
        stop_audio: function(){
            var self =this;
            //pause audio if its playing
            var theaudio = self.items[self.game.pointer].audio;
            if(theaudio && !theaudio.paused) {
                theaudio.pause();
            }
        },
    };
});