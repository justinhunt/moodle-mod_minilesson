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
], function ($, log, ajax, def, polly, anim, progresstimer, templates, notification, str) {
    "use strict"; // jshint ;_;

    log.debug('MiniLesson wordshuffle: initialising');

    return {

        strings: {},

        // For making multiple instances
        clone: function () {
            return $.extend(true, {}, this);
        },

        usevoice: 'Amy',

        pointerdiv: null,

        init: function (index, itemdata, quizhelper) {
            var self = this;
            self.itemdata = itemdata;
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
        },

        init_controls: function () {
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
                retry_btn: container.find(".wordshuffle_retry_btn"),
                description: container.find(".wordshuffle_description"),
                image: container.find(".wordshuffle_image_container"),
                maintitle: container.find(".wordshuffle_maintitle"),
                itemquestion: container.find(".wordshuffle_itemtext"),
            };
        },

        init_strings: function () {
            var self = this;
            str.get_strings([
                { "key": "nextlessonitem", "component": 'mod_minilesson' },
                { "key": "confirm_desc", "component": 'mod_minilesson' },
                { "key": "yes", "component": 'moodle' },
                { "key": "no", "component": 'moodle' },
                { "key": "wordshuffle_wordbank_label", "component": 'mod_minilesson' },
                { "key": "wordshuffle_drop_slot_label", "component": 'mod_minilesson' },
                { "key": "wordshuffle_a11y_returned_to_bank", "component": 'mod_minilesson' },
                { "key": "wordshuffle_a11y_placed_in_slot", "component": 'mod_minilesson' },
            ]).done(function (s) {
                var i = 0;
                self.strings.nextlessonitem = s[i++];
                self.strings.confirm_desc = s[i++];
                self.strings.yes = s[i++];
                self.strings.no = s[i++];
                self.strings.wordbank_label = s[i++];
                self.strings.drop_slot_label = s[i++];
                self.strings.a11y_returned_to_bank = s[i++];
                self.strings.a11y_placed_in_slot = s[i++];
            });
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
            stepdata.grade = Math.round((stepdata.correctitems / stepdata.totalitems) * 100);

            //stop audio
            self.stop_audio();

            self.quizhelper.do_next(stepdata);
        },

        show_item_review: function () {
            var self = this;
            var review_data = {};
            review_data.items = self.items;
            review_data.totalitems = self.items.length;
            review_data.correctitems = self.items.filter(function (e) {
                return e.correct; }).length;

            //Get controls
            var listencont = self.controls.listen_cont;
            var qbox = self.controls.question;
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
                    //recorderbox.hide();
                    // Run js for audio player events
                    templates.runTemplateJS(js);
                }
            );// End of templates
        },

        register_events: function () {

            var self = this;

            self.controls.nextbutton.on('click', function (e) {
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

            self.controls.start_btn.on("click", function () {
                self.start();
            });

            //AUDIO PLAYER events
            var audioplayerbtn = self.controls.listen_btn;
            //audio button click event
            audioplayerbtn.on("click", function () {
                var theaudio = self.items[self.game.pointer].audio;

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

            // On skip button click
            self.controls.skip_btn.on("click", function () {
                // Disable buttons
                self.controls.ctrl_btn.prop("disabled", true);
                // Reveal prompt
                self.controls.container.find('.wordshuffle_speech.wordshuffle_teacher_left').text(self.items[self.game.pointer].prompt + "");
                // Reveal answers
                self.controls.container.find('.wordshuffle_targetWord').each(function () {
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
                    // Prevent any more interactions during the transition delay
                    self.interactionLocked = true;
                    setTimeout(function () {
                        self.controls.container.find('.wordshuffle_reply_' + self.game.pointer).hide();
                        self.game.pointer++;
                        self.nextPrompt();
                    }, 2000);
                } else {
                    self.end();
                }
            });


            self.controls.check_btn.on("click", function () {
                self.pointerdiv.find(".drop-slot .word").each(function () {
                    self.placeInBank($(this));
                });
                self.clearPerSlotFeedback();
                self.controls.retry_btn.hide();
                self.items[self.game.pointer].answered = false;
                self.items[self.game.pointer].correct = false;
            });

            self.controls.retry_btn.on("click", function () {
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

        check_answer: function () {
            var self = this;

            // self.evaluateIfComplete();
            if (self.itemdata.allowretry && !self.items[self.game.pointer].correct) {
                self.controls.retry_btn.show();
                self.controls.retry_btn.prop("disabled", false);
                self.controls.skip_btn.prop("disabled", false);
                self.controls.check_btn.hide();
            } else {
                // Prevent any more interactions during the pre-comparison delay
                self.interactionLocked = true;
                setTimeout(() => self.gotComparison(true), 2000);
            }
        },

        setvoice: function () {
            var self = this;
            self.usevoice = self.itemdata.usevoice;
            self.voiceoption = self.itemdata.voiceoption;
            return;
        },

        getItems: function () {
            var self = this;
            var text_items = self.itemdata.sentences;

            self.items = text_items.map(function (target) {
                return {
                    target: target.sentenceclean,
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

            if (self.itemdata.audiocontent) {
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
            self.controls.container.find('.wordshuffle_not_loaded').hide();
            self.controls.container.find('.wordshuffle_loaded').show();
            if (self.itemdata.hidestartpage) {
                self.start();
            } else {
                self.controls.start_btn.prop("disabled", false);
            }
        },

        gotComparison: function (comparison) {
            var self = this;
            log.debug("gotComparison", comparison);
            var timelimit_progressbar = self.controls.progress_bar;
            if (comparison) {
                log.debug("correct!!");


                //if they cant retry OR the time limit is up, move on
            } else if (!self.itemdata.allowretry || timelimit_progressbar.hasClass('progress-bar-complete')) {
                log.debug("incorrect");
            } else {
                //it was wrong but they can retry
                log.debug("incorrect!! retry");
                return;
            }

            self.stopTimer(self.items[self.game.pointer].timer);

            if (self.game.pointer < self.items.length - 1) {
                // Prevent interactions while we wait to advance to the next prompt
                self.interactionLocked = true;
                setTimeout(function () {
                    self.controls.container.find('.wordshuffle_reply_' + self.game.pointer).hide();
                    self.game.pointer++;
                    self.nextPrompt();
                }, 2000);
            } else {
                self.end();
            }
        },

        end: function () {
            var self = this;
            self.controls.nextbutton.prop("disabled", true);

            //progress dots are updated on next_item. The last item has no next item, so we update from here
            self.updateProgressDots();

            //disable the buttons and go to next question or review
            // Also lock interactions during this final transition
            self.interactionLocked = true;
            setTimeout(function () {
                self.controls.nextbutton.prop("disabled", false);
                if (self.quizhelper.showitemreview) {
                    self.show_item_review();
                } else {
                    self.next_question();
                }
            }, 2000);
        },

        start: function () {
            var self = this;

            self.controls.ctrl_btn.prop("disabled", true);

            self.items.forEach(function (item) {
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
            self.controls.maintitle.show();
            self.controls.itemquestion.show();
            self.controls.description.hide();
            self.controls.image.hide();
            self.controls.controlsbox.show();

            self.nextPrompt();
        },

        nextPrompt: function () {

            var self = this;
            self.pointerdiv = self.controls.question.find(`.wordshuffle_wordset_container[data-index="${self.game.pointer}"]`);
            // Unlock interactions for the new prompt
            self.interactionLocked = false;
            self.controls.retry_btn.hide();
            self.controls.check_btn.show();

            self.controls.ctrl_btn.prop("disabled", false);

            self.updateProgressDots();

            self.showNextWordSet();

            // We autoplay the audio on item entry, if its not a mobile user.
            // If we do not have a start page and its the first item, we play on the item show event
            if (self.items[self.game.pointer].audio !== null && !self.quizhelper.mobile_user()) {
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
        },

        updateProgressDots: function () {
            var self = this;
            var color, icon;
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
            self.controls.title.html(progress);
        },

        showNextWordSet: function () {
            var self = this;


            // Hide previous wordset
            self.controls.container.find('.wordshuffle_wordset_container').hide();
            // Show new one
            var newwordset = self.controls.container.find('.wordshuffle_wordset_' + self.game.pointer);
            anim.do_animate(newwordset, 'zoomIn animate__faster', 'in').then(
                function () {
                }
            );

            self.controls.ctrl_btn.prop("disabled", false);

            self.startTimer();
            self.makeDragZones();
        },

        getSlotWords: function () {
            var self = this;
            return self.pointerdiv.find(".drop-slot").map(function () {
                const w = $(this).find(".word").first().text().trim();
                return w || "";
            }).get();
        },

        allFilled: function () {
            var self = this;
            return self.getSlotWords().every(Boolean);
        },

        clearPerSlotFeedback: function () {
            var self = this;
            self.pointerdiv.find(".drop-slot").removeClass("border-success border-danger")
                .addClass("border-secondary-subtle");
            self.pointerdiv.find("[id^='fb-']").each(function () {
                $(this).removeClass("text-success text-danger").addClass("text-muted").html("&nbsp;");
            });
        },

        setPerSlotFeedback: function () {
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

        evaluateIfComplete: function () {
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

        moveToSlot: function ($word, $slot) {
            var self = this;

            // Ensure only one word per slot: if target slot already has a word, move it back to bank first.
            var $existing = $slot.find('.word').first();
            if ($existing.length && !$existing.is($word)) {
                self.placeInBank($existing);
            }

            $word.detach()
                .css({ top: 0, left: 0, position: "relative" })
                .appendTo($slot);
            self.evaluateIfComplete();
        },

        placeInBank: function ($word) {
            var self = this;
            $word.detach()
                .css({ top: 0, left: 0, position: "relative" })
                .appendTo(self.pointerdiv.find(".word-bank"));
            self.evaluateIfComplete();
        },

        gapWords: function () {
            var self = this;
            return self.itemdata.sentences[self.game.pointer].gapwords;
        },

        fixedWords: function () {
            var self = this;
            return self.gapWords().filter(w => w.isgap === false).map(w => w.word);
        },

        expectedAnswers: function () {
            var self = this;
            return self.gapWords().filter(w => w.isgap === true).map(w => w.word);
        },

        selectedWord: null,
        // Interaction lock to block user actions during short transitions
        interactionLocked: false,

        makeDragZones: function () {
            var self = this;

            if (!self.pointerdiv.attr('data-initialized')) {
                // Accessibility: roles and live region
                // Create a polite live region to announce moves for screen readers.
                if (!self.pointerdiv.find('.ml-ws-live').length) {
                    self.pointerdiv.append('<div class="ml-ws-live sr-only" aria-live="polite" aria-atomic="true"></div>');
                }
                self.a11yAnnounce = function (msg) {
                    var $live = self.pointerdiv.find('.ml-ws-live');
                    // Clear then set to force announcement across ATs.
                    $live.text('');
                    setTimeout(function () {
                        $live.text(msg); }, 10);
                };

                // Add roles/labels to containers if present.
                var $bank = self.pointerdiv.find('.word-bank');
                var bankLabel = (self.strings && self.strings.wordbank_label) ? self.strings.wordbank_label : 'Word bank';
                $bank.attr({ role: 'list', 'aria-label': bankLabel });
                self.pointerdiv.find('.drop-slot').each(function (i) {
                    var slotLabelTmpl = (self.strings && self.strings.drop_slot_label) ? self.strings.drop_slot_label : 'Drop slot {$a}';
                    var slotLabel = slotLabelTmpl.replace('{$a}', (i + 1));
                    $(this).attr({ role: 'button', 'aria-label': slotLabel });
                });

                // Click-to-select and click-to-drop support
                self.pointerdiv.on('click', e => {
                    if (self.interactionLocked) {
                        return; }
                    const $target = $(e.target);
                    if ($target.is('.word')) {
                        if (!self.selectedWord || !self.selectedWord.is($target)) {
                            self.selectedWord = $target;
                            self.selectedWord.attr('aria-grabbed', 'true');
                        } else {
                            self.selectedWord.attr('aria-grabbed', 'false');
                            self.selectedWord = null;
                        }
                    } else if ($target.is('.word-bank')) {
                        if (self.selectedWord) {
                            if (self.selectedWord.parent('.drop-slot')) {
                                self.placeInBank(self.selectedWord);
                                var tmpl = (self.strings && self.strings.a11y_returned_to_bank) ? self.strings.a11y_returned_to_bank : 'Returned "{$a}" to word bank';
                                self.a11yAnnounce(tmpl.replace('{$a}', self.selectedWord.text().trim()));
                            }
                            if (self.selectedWord) {
                                self.selectedWord.attr('aria-grabbed', 'false'); }
                            self.selectedWord = null;
                        }
                    } else if ($target.is('.drop-slot')) {
                        if (self.selectedWord) {
                            self.moveToSlot(self.selectedWord, $target);
                            var tmpl2 = (self.strings && self.strings.a11y_placed_in_slot) ? self.strings.a11y_placed_in_slot : 'Placed "{$a}" in drop slot';
                            self.a11yAnnounce(tmpl2.replace('{$a}', self.selectedWord.text().trim()));
                            if (self.selectedWord) {
                                self.selectedWord.attr('aria-grabbed', 'false'); }
                            self.selectedWord = null;
                        }
                    }
                    self.highlightDropZones();
                });

                // Keyboard support via Spacebar
                self.pointerdiv.on('keydown', function (e) {
                    if (self.interactionLocked) {
                        e.preventDefault(); return; }
                    if (e.key === ' ' || e.key === 'Spacebar') {
                        const $focused = $(document.activeElement);
                        if ($focused.is('.word')) {
                            if (!self.selectedWord || !self.selectedWord.is($focused)) {
                                self.selectedWord = $focused;
                                self.selectedWord.attr('aria-grabbed', 'true');
                            } else {
                                self.selectedWord.attr('aria-grabbed', 'false');
                                self.selectedWord = null;
                            }
                        } else if ($focused.is('.word-bank')) {
                            if (self.selectedWord) {
                                if (self.selectedWord.parent('.drop-slot')) {
                                    self.placeInBank(self.selectedWord);
                                    var tmpl = (self.strings && self.strings.a11y_returned_to_bank) ? self.strings.a11y_returned_to_bank : 'Returned "{$a}" to word bank';
                                    self.a11yAnnounce(tmpl.replace('{$a}', self.selectedWord.text().trim()));
                                }
                                if (self.selectedWord) {
                                    self.selectedWord.attr('aria-grabbed', 'false'); }
                                self.selectedWord = null;
                            }
                        } else if ($focused.is('.drop-slot')) {
                            if (self.selectedWord) {
                                self.moveToSlot(self.selectedWord, $focused);
                                var tmpl2 = (self.strings && self.strings.a11y_placed_in_slot) ? self.strings.a11y_placed_in_slot : 'Placed "{$a}" in drop slot';
                                self.a11yAnnounce(tmpl2.replace('{$a}', self.selectedWord.text().trim()));
                                if (self.selectedWord) {
                                    self.selectedWord.attr('aria-grabbed', 'false'); }
                                self.selectedWord = null;
                            }
                        }
                        self.highlightDropZones();
                        e.preventDefault();
                    }
                });

                // ========== HTML5 Drag & Drop support ==========
                // Mark words as draggable
                self.pointerdiv.find('.word').attr({ draggable: true, role: 'button', tabindex: 0, 'aria-grabbed': 'false' });

                // Re-mark words as draggable if DOM changes (e.g., after moves)
                // Using a delegated handler when a word is added to DOM under pointerdiv
                self.pointerdiv.on('DOMNodeInserted', function (e) {
                    var $t = $(e.target);
                    if ($t.hasClass('word')) {
                        $t.attr({ draggable: true, role: 'button', tabindex: 0, 'aria-grabbed': 'false' });
                    } else {
                        $t.find('.word').attr({ draggable: true, role: 'button', tabindex: 0, 'aria-grabbed': 'false' });
                    }
                });

                // Track the word being dragged
                self.draggedWord = null;

                // dragstart on a word
                self.pointerdiv.on('dragstart', '.word', function (ev) {
                    if (self.interactionLocked) {
                        ev.preventDefault(); return false; }
                    var e = ev.originalEvent || ev;
                    self.draggedWord = $(this);
                    self.selectedWord = self.draggedWord; // reuse existing highlight logic
                    self.draggedWord.attr('aria-grabbed', 'true');
                    try {
                        // Set dummy data to make Firefox happy
                        e.dataTransfer.setData('text/plain', 'move');
                        e.dataTransfer.effectAllowed = 'move';
                    } catch (ex) {
                    }
                    self.highlightDropZones();
                    $(this).addClass('ml_ws_dragging');
                });

                // dragend cleanup
                self.pointerdiv.on('dragend', '.word', function () {
                    $(this).removeClass('ml_ws_dragging');
                    $(this).attr('aria-grabbed', 'false');
                    self.draggedWord = null;
                    self.selectedWord = null;
                    self.highlightDropZones();
                });

                // Allow drop on slots and bank
                self.pointerdiv.on('dragover', '.drop-slot, .word-bank', function (ev) {
                    if (self.interactionLocked) {
                        return; }
                    var e = ev.originalEvent || ev;
                    e.preventDefault();
                    if (e.dataTransfer) {
                        e.dataTransfer.dropEffect = 'move'; }
                });

                // Visual cue on enter/leave
                self.pointerdiv.on('dragenter', '.drop-slot, .word-bank', function () {
                    $(this).addClass('ml_ws_highlight');
                });
                self.pointerdiv.on('dragleave', '.drop-slot, .word-bank', function () {
                    $(this).removeClass('ml_ws_highlight');
                });

                // Drop into a slot
                self.pointerdiv.on('drop', '.drop-slot', function (ev) {
                    if (self.interactionLocked) {
                        return; }
                    var e = ev.originalEvent || ev;
                    e.preventDefault();
                    $(this).removeClass('ml_ws_highlight');
                    if (self.draggedWord) {
                        self.moveToSlot(self.draggedWord, $(this));
                        var tmpl = (self.strings && self.strings.a11y_placed_in_slot) ? self.strings.a11y_placed_in_slot : 'Placed "{$a}" in drop slot';
                        self.a11yAnnounce(tmpl.replace('{$a}', self.draggedWord.text().trim()));
                        self.draggedWord.attr('aria-grabbed', 'false');
                        self.draggedWord = null;
                        self.selectedWord = null;
                        self.highlightDropZones();
                    }
                });

                // Drop back into the bank
                self.pointerdiv.on('drop', '.word-bank', function (ev) {
                    if (self.interactionLocked) {
                        return; }
                    var e = ev.originalEvent || ev;
                    e.preventDefault();
                    $(this).removeClass('ml_ws_highlight');
                    if (self.draggedWord) {
                        self.placeInBank(self.draggedWord);
                        var tmpl = (self.strings && self.strings.a11y_returned_to_bank) ? self.strings.a11y_returned_to_bank : 'Returned "{$a}" to word bank';
                        self.a11yAnnounce(tmpl.replace('{$a}', self.draggedWord.text().trim()));
                        self.draggedWord.attr('aria-grabbed', 'false');
                        self.draggedWord = null;
                        self.selectedWord = null;
                        self.highlightDropZones();
                    }
                });

                self.pointerdiv.attr('data-initialized', 1);
            }
        },

        highlightDropZones: function () {
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
                dropZones.filter(function (_, slot) {
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

        startTimer: function () {
            var self = this;
            // If we have a time limit, set up the timer, otherwise return
            if (self.itemdata.timelimit > 0) {
                // This is a function to start the timer (we call it conditionally below)
                var doStartTimer = function () {
                    // This shows progress bar
                    self.controls.progress_container.show();
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

        // Stop audio .. usually when leaving the item or sentence
        stop_audio: function () {
            var self = this;
            //pause audio if its playing
            var theaudio = self.items[self.game.pointer].audio;
            if (theaudio && !theaudio.paused) {
                theaudio.pause();
            }
        },
    };
});