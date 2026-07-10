define(['jquery',
    'core/log',
    'mod_minilesson/definitions',
    'mod_minilesson/animatecss',
    'mod_minilesson/progresstimer',
    'core/templates',
    'core/str',
    'core/notification',
    'minilessonitem_wordcards/keyboard'
], function ($, log, def, anim, progresstimer, templates, str, notification, keyboard) {
    "use strict"; // jshint ;_;

    log.debug('MiniLesson wordcards: initialising');

    return {

        // For making multiple instances
        clone: function () {
            return $.extend(true, {}, this);
        },

        init: function (index, itemdata, quizhelper) {
            var self = this;
            self.strings = {};
            self.itemdata = itemdata;
            self.quizhelper = quizhelper;
            self.index = index;
            self.started = false;

            // Anim
            var animopts = {};
            animopts.useanimatecss = quizhelper.useanimatecss;
            anim.init(animopts);

            self.init_controls();
            self.init_strings();
            self.getItems();
            self.register_events();
        },

        init_controls: function () {
            var self = this;
            var container = $("#" + self.itemdata.uniqueid + "_container");
            self.controls = {
                container: container,
                startscreen: container.find(".wordcards_startscreen"),
                nextbutton: container.find(".minilesson_nextbutton"),
                gamebox: container.find(".wordcards_gamebox"),
                progress: container.find(".wordcards_progress"),
                question: container.find(".wordcards_question"),
                listen_cont: container.find(".wordcards_listen_cont"),
                listen_btn: container.find(".wordcards_listen_btn"),
                definition_prompt: container.find(".wordcards_definition_prompt"),
                image_hint: container.find(".wordcards_image_hint"),
                submitted: container.find(".wordcards_submitted"),
                options: container.find(".wordcards_options"),
                keyboardbox: container.find(".wordcards_keyboard"),
                resultscontainer: container.find(".wordcards_resultscontainer"),
                progress_container: container.find(".progress-container"),
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

        getItems: function () {
            var self = this;
            self.items = self.itemdata.words.map(function (word) {
                return {
                    term: word.term,
                    definition: word.definition,
                    audiourl: word.audiourl ? word.audiourl : "",
                    imageurl: word.imageurl,
                    audio: null,
                    typed: "",
                    timer: [],
                    answered: false,
                    correct: false,
                };
            }).filter(function (e) {
                return e.term !== "";
            });

            //Prepare audio
            $.each(self.items, function (index, item) {
                if (item.audiourl !== "") {
                    item.audio = new Audio();
                    item.audio.src = item.audiourl;
                }
            });

            // The distractor letter pool for the onscreen keyboard: the letters of all the terms.
            self.distractorletters = [];
            self.items.forEach(function (item) {
                Array.from(item.term).forEach(function (letter) {
                    if (self.distractorletters.indexOf(letter) === -1) {
                        self.distractorletters.push(letter);
                    }
                });
            });

            self.game = { pointer: 0 };
        },

        register_events: function () {
            var self = this;

            // Flip cards on the start screen.
            self.controls.container.on('click keydown', '.wordcards_flipcard', function (e) {
                if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
                    return;
                }
                e.preventDefault();
                var card = $(this);
                card.toggleClass('flipped');
                card.attr('aria-pressed', card.hasClass('flipped') ? 'true' : 'false');
            });

            // The quizhelper triggers showElement both when the start button on the splash
            // screen is clicked and, if there is no splash screen, when the item is shown.
            self.controls.container.on("showElement", function () {
                if (!self.started) {
                    self.started = true;
                    self.start();
                }
            });

            self.controls.nextbutton.on('click', function () {
                if (self.items.some(function (item) { return !item.answered; })) {
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

            //AUDIO PLAYER events
            var audioplayerbtn = self.controls.listen_btn;
            audioplayerbtn.on("click", function () {
                var theaudio = self.items[self.game.pointer].audio;
                if (theaudio === null) {
                    return;
                }

                //if we are already playing stop playing
                if (!theaudio.paused) {
                    theaudio.pause();
                    theaudio.currentTime = 0;
                    $(audioplayerbtn).removeClass('activeanimation');
                    return;
                }

                //change icon to indicate playing state
                theaudio.addEventListener('ended', function () {
                    $(audioplayerbtn).removeClass('activeanimation');
                });
                theaudio.addEventListener('play', function () {
                    $(audioplayerbtn).addClass('activeanimation');
                });

                theaudio.load();
                theaudio.play();
            });

            // Option clicks (choose modes).
            self.controls.options.on('click', '.wordcards_option', function () {
                var option = $(this);
                if (self.items[self.game.pointer].answered || self.controls.options.hasClass('wordcards_options_locked')) {
                    return;
                }
                self.controls.options.addClass('wordcards_options_locked');
                self.check(option.attr('data-term'), option);
            });
        },

        start: function () {
            var self = this;

            self.items.forEach(function (item) {
                item.typed = "";
                item.answered = false;
                item.correct = false;
            });

            // Re-shuffle so the tasks come in a different order than the flip card grid.
            self.shuffle(self.items);

            self.game.pointer = 0;
            self.controls.gamebox.show();
            self.next();
        },

        next: function () {
            var self = this;
            var item = self.items[self.game.pointer];

            self.updateProgressDots();
            self.controls.submitted.hide().text("").removeClass('wordcards_submitted_correct wordcards_submitted_incorrect');

            // Image hint.
            if (item.imageurl) {
                self.controls.image_hint.find('img').attr('src', item.imageurl).attr('alt', item.term);
                self.controls.image_hint.show();
            } else {
                self.controls.image_hint.hide();
            }

            // The prompt: audio in the listen modes, the definition in the definition modes.
            if (self.itemdata.islistenmode) {
                self.controls.listen_cont.show();
                if (item.audio !== null && !self.quizhelper.mobile_user()) {
                    setTimeout(function () {
                        if (self.game.pointer < self.items.length && self.items[self.game.pointer] === item && !item.answered) {
                            self.controls.listen_btn.trigger('click');
                        }
                    }, 1000);
                }
            }
            if (self.itemdata.isdefmode) {
                self.controls.definition_prompt.text(item.definition).show();
            }

            // The answer input: option buttons in the choose modes, the keyboard in the type modes.
            if (self.itemdata.ischoosemode) {
                self.showOptions(item);
            }
            if (self.itemdata.istypemode) {
                self.showKeyboard(item);
            }

            self.startTimer();
        },

        showOptions: function (item) {
            var self = this;
            var others = self.items.filter(function (e) {
                return e !== item;
            });
            self.shuffle(others);
            var options = others.slice(0, 4);
            options.push(item);
            self.shuffle(options);

            var code = "";
            options.forEach(function (option) {
                code += "<button type='button' class='btn wordcards_option' data-term='"
                    + self.escapeHtml(option.term) + "' data-correct='" + (option === item ? "true" : "false") + "'>"
                    + "<span class='wordcards_option_label'>" + self.escapeHtml(option.term) + "</span>"
                    + "<i class='fa wordcards_option_icon' aria-hidden='true'></i>"
                    + "</button>";
            });
            self.controls.options.removeClass('wordcards_options_locked').html(code).show();
            anim.do_animate(self.controls.options, 'zoomIn animate__faster', 'in');
        },

        showKeyboard: function (item) {
            var self = this;
            self.keyboard = keyboard.clone();
            self.keyboard.create(
                self.controls.keyboardbox,
                item.term,
                self.distractorletters.slice(),
                function (typed) {
                    if (!item.answered) {
                        self.keyboard.disable();
                        self.check(typed);
                    }
                }
            );
            self.controls.keyboardbox.show();
        },

        check: function (selected, optionbutton) {
            var self = this;
            var item = self.items[self.game.pointer];
            var correct = String(selected).toLowerCase().trim() === item.term.toLowerCase().trim();

            item.answered = true;
            item.correct = correct;
            item.typed = String(selected);
            self.stopTimer(item.timer);
            self.stop_audio();

            // Feedback.
            if (self.itemdata.ischoosemode) {
                var stateclass = correct ? 'wordcards_option_correct' : 'wordcards_option_incorrect';
                var stateicon = correct ? 'fa-check' : 'fa-times';
                if (optionbutton) {
                    optionbutton.addClass(stateclass);
                    optionbutton.find('.wordcards_option_icon').addClass(stateicon);
                }
                if (!correct) {
                    // Also reveal the correct option.
                    self.controls.options.find(".wordcards_option[data-correct='true']").addClass('wordcards_option_correct');
                }
            } else {
                // Type modes: show the correct term, colored by the result.
                self.controls.submitted
                    .text(item.term)
                    .addClass(correct ? 'wordcards_submitted_correct' : 'wordcards_submitted_incorrect')
                    .show();
            }

            self.updateProgressDots();

            if (self.game.pointer < self.items.length - 1) {
                setTimeout(function () {
                    self.game.pointer++;
                    self.next();
                }, correct ? 1200 : 1800);
            } else {
                self.end();
            }
        },

        end: function () {
            var self = this;
            self.controls.nextbutton.prop("disabled", true);

            //disable the buttons and go to next question or review
            setTimeout(function () {
                self.controls.nextbutton.prop("disabled", false);
                if (self.quizhelper.showitemreview) {
                    self.controls.progress_container.removeClass('d-flex');
                    self.controls.progress_container.hide();
                    self.show_item_review();
                } else {
                    self.next_question();
                }
            }, 2000);
        },

        show_item_review: function () {
            var self = this;
            var review_data = {};
            review_data.items = self.items.map(function (item) {
                return {
                    target: "<b>" + self.escapeHtml(item.term) + "</b>"
                        + (item.definition ? " — " + self.escapeHtml(item.definition) : ""),
                    correct: item.correct,
                    audio: item.audiourl !== "" ? { src: item.audiourl } : false,
                };
            });
            review_data.totalitems = self.items.length;
            review_data.correctitems = self.items.filter(function (e) {
                return e.correct;
            }).length;

            //display results
            templates.render('mod_minilesson/listitemresults', review_data).then(
                function (html, js) {
                    self.controls.resultscontainer.html(html);
                    //show and hide
                    self.controls.resultscontainer.show();
                    self.controls.progress.hide();
                    self.controls.question.hide();
                    self.controls.submitted.hide();
                    self.controls.options.hide();
                    self.controls.keyboardbox.hide();
                    // Run js for audio player events
                    templates.runTemplateJS(js);
                }
            );// End of templates
        },

        next_question: function () {
            var self = this;
            var stepdata = {};
            stepdata.index = self.index;
            stepdata.hasgrade = true;
            stepdata.totalitems = self.items.length;
            stepdata.correctitems = self.items.filter(function (e) {
                return e.correct;
            }).length;
            stepdata.grade = stepdata.totalitems > 0 ? Math.round((stepdata.correctitems / stepdata.totalitems) * 100) : 0;

            //stop audio
            self.stop_audio();

            self.quizhelper.do_next(stepdata);
        },

        updateProgressDots: function () {
            var self = this;
            var color;
            var icon;
            var progress = self.items.map(function (item, idx) {
                color = "#E6E9FD";
                icon = "fa fa-square";
                if (self.items[idx].answered && self.items[idx].correct) {
                    color = "#74DC72";
                    icon = "fa fa-check-square";
                } else if (self.items[idx].answered && !self.items[idx].correct) {
                    color = "#FB6363";
                    icon = "fa fa-window-close";
                }
                return "<i style='color:" + color + "' class='" + icon + " pl-1'></i>";
            }).join(" ");
            self.controls.progress.html(progress);
        },

        startTimer: function () {
            var self = this;
            // If we have a time limit, set up the timer, otherwise return
            if (self.itemdata.timelimit > 0) {
                self.controls.progress_container.show();
                self.controls.progress_container.addClass('d-flex align-items-center');
                self.controls.progress_container.find('i').show();
                var progresbar = self.controls.progress_container.find('#progresstimer').progressTimer({
                    height: '5px',
                    timeLimit: self.itemdata.timelimit,
                    onFinish: function () {
                        // Time is up: the current word is marked wrong and we move on.
                        var item = self.items[self.game.pointer];
                        if (!item.answered) {
                            if (self.keyboard) {
                                self.keyboard.disable();
                            }
                            self.check("", null);
                        }
                    }
                });
                progresbar.each(function () {
                    self.items[self.game.pointer].timer.push($(this).attr('timer'));
                });
            }
        },

        stopTimer: function (timers) {
            if (timers.length) {
                timers.forEach(function (timer) {
                    clearInterval(timer);
                });
            }
        },

        // Stop audio .. usually when leaving the item or word
        stop_audio: function () {
            var self = this;
            //pause audio if its playing
            var theaudio = self.items[self.game.pointer].audio;
            if (theaudio && !theaudio.paused) {
                theaudio.pause();
            }
        },

        shuffle: function (a) {
            var j, x, i;
            for (i = a.length; i; i -= 1) {
                j = Math.floor(Math.random() * i);
                x = a[i - 1];
                a[i - 1] = a[j];
                a[j] = x;
            }
        },

        escapeHtml: function (text) {
            return $('<div>').text(text).html().replace(/'/g, '&#39;').replace(/"/g, '&quot;');
        },
    };
});
