define(['jquery',
    'core/log',
    'core/ajax',
    'mod_minilesson/definitions',
    'mod_minilesson/pollyhelper',
    'mod_minilesson/animatecss',
    'mod_minilesson/progresstimer',
    'core/templates',
    'core/str',
    'core/notification',
    'mod_minilesson/external/simplekeyboard',
    'mod_minilesson/external/keyboardlayouts'
], function ($, log, ajax, def, polly, anim, progresstimer, templates, str, notification, SimpleKeyboard, KeyboardLayouts) {
    "use strict"; // jshint ;_;

    log.debug('MiniLesson listening gap fill: initialising');

    return {

        // For making multiple instances
        clone: function () {
            return $.extend(true, {}, this);
        },

        usevoice: 'Amy',

        init: function (index, itemdata, quizhelper) {
            var self = this;
            self.strings = {};
            self.itemdata = itemdata;
            self.quizhelper = quizhelper;
            self.index = index;
            self.activeInputElement = null;
            self.itemdata = itemdata;
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
                listen_cont: $("#" + self.itemdata.uniqueid + "_container .lgapfill_listen_cont"),
                nextbutton: $("#" + self.itemdata.uniqueid + "_container .minilesson_nextbutton"),
                start_btn: $("#" + self.itemdata.uniqueid + "_container .lgapfill_start_btn"),
                skip_btn: $("#" + self.itemdata.uniqueid + "_container .lgapfill_skip_btn"),
                ctrl_btn: $("#" + self.itemdata.uniqueid + "_container .lgapfill_ctrl-btn"),
                check_btn: $("#" + self.itemdata.uniqueid + "_container .lgapfill_check_btn"),
                game: $("#" + self.itemdata.uniqueid + "_container .lgapfill_game"),
                controlsbox: $("#" + self.itemdata.uniqueid + "_container .lgapfill_controls"),
                resultscontainer: $("#" + self.itemdata.uniqueid + "_container .lgapfill_resultscontainer"),
                mainmenu: $("#" + self.itemdata.uniqueid + "_container .lgapfill_mainmenu"),
                title: $("#" + self.itemdata.uniqueid + "_container .lgapfill_title"),
                progress_container: $("#" + self.itemdata.uniqueid + "_container .progress-container"),
                progress_bar: $("#" + self.itemdata.uniqueid + "_container .progress-container .progress-bar"),
                question: $("#" + self.itemdata.uniqueid + "_container .question"),
                listen_btn: $("#" + self.itemdata.uniqueid + "_container .lgapfill_listen_btn"),
                description: $("#" + self.itemdata.uniqueid + "_container .lgapfill_description"),
                image: $("#" + self.itemdata.uniqueid + "_container .lgapfill_image_container"),
                maintitle: $("#" + self.itemdata.uniqueid + "_container .lgapfill_maintitle"),
                itemquestion: $("#" + self.itemdata.uniqueid + "_container .lgapfill_itemtext"),
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

            review_data.totalitems = self.items.length;
            review_data.correctitems = self.items.filter(function (e) {
                return e.correct;
            }).length;

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

            //toggle audio playback on spacekey press in input boxes
            self.controls.container.on("keydown", ".single-character", function (e) {
                if (e.which == 32) {
                    e.preventDefault();
                    audioplayerbtn.trigger("click");
                }
            });

            // On skip button click
            self.controls.skip_btn.on("click", function () {
                // Disable buttons
                self.controls.ctrl_btn.prop("disabled", true);
                // Reveal prompt
                self.controls.container.find('.lgapfill_speech.lgapfill_teacher_left').text(self.items[self.game.pointer].prompt + "");
                // Reveal answers
                self.controls.container.find('.lgapfill_targetWord').each(function () {
                    var realidx = $(this).data("realidx");
                    var lgapfill_targetWord = self.items[self.game.pointer].lgapfill_targetWords[realidx];
                    $(this).val(lgapfill_targetWord);
                });

                self.stopTimer(self.items[self.game.pointer].timer);

                //mark as answered and incorrect
                self.items[self.game.pointer].answered = true;
                self.items[self.game.pointer].correct = false;

                // Move on after short time, to next prompt, or next question
                if (self.game.pointer < self.items.length - 1) {
                    self.controls.skip_btn.prop("disabled", true);
                    self.controls.skip_btn.children('.fa').removeClass('fa-arrow-right');
                    self.controls.skip_btn.children('.fa').addClass('fa-spinner fa-spin');
                    setTimeout(function () {
                        self.controls.skip_btn.children('.fa').removeClass('fa-spinner fa-spin');
                        self.controls.skip_btn.children('.fa').addClass('fa-arrow-right');
                        self.controls.skip_btn.prop("disabled", false);
                        self.controls.container.find('.lgapfill_reply_' + self.game.pointer).hide();
                        self.game.pointer++;
                        self.nextPrompt();
                    }, 1500);
                } else {
                    self.end();
                }
            });


            self.controls.check_btn.on("click", function () {
                self.check_answer();
            });

            // Listen for enter key on input boxes
            self.controls.container.on("keydown", ".single-character", function (e) {
                if (e.which == 13) {
                    self.check_answer();
                }
            });

            // Auto nav between inputs
            self.controls.container.on("keyup", ".lgapfill_targetWord", function (e) {

                // Move focus between textboxes
                // log.debug(e);
                var target = e.srcElement || e.target;
                var maxLength = parseInt(target.attributes.maxlength.value, 10);
                var myLength = target.value.length;
                var key = e.which;
                if (myLength >= maxLength) {
                    var nextIdx = $(this).data('idx') + 1;
                    var next = self.controls.container.find('input.lgapfill_targetWord[data-idx="' + nextIdx + '"');
                    if (next.length === 1) {
                        next.focus();
                    }

                    // Move to previous field if empty (user pressed backspace or delete)
                } else if ((key == 8 || key == 46) && myLength === 0) {
                    var previousIdx = $(this).data('idx') - 1;
                    var previous = self.controls.container.find('input.lgapfill_targetWord[data-idx="' + previousIdx + '"');
                    if (previous.length === 1) {
                        previous.focus();
                    }
                }
            });

            // Virtual Keyboard
            if (self.itemdata.enablevkeyboard && self.itemdata.enablevkeyboard != '0') {
                var KeyboardClass = SimpleKeyboard.default || SimpleKeyboard;

                var keyboardConfig = {
                    onKeyPress: button => self.onKeyPress(button)
                };

                if (self.itemdata.enablevkeyboard == '2') {
                    // Custom Layout
                    var customKeys = self.itemdata.customkeys || "";
                    // 1. Clean up the string (remove extra spaces)
                    var charArray = customKeys.split(' ').filter(c => c.trim() !== "");
                    // If no spaces, and not empty, split by character
                    if (charArray.length === 0 && customKeys.trim().length > 0) {
                        charArray = customKeys.trim().split('');
                    }

                    // 2. Map to uppercase
                    var upperArray = charArray.map(c => c.toUpperCase());

                    // 3. Join back into the format simple-keyboard expects
                    var lowerRow = charArray.join(' ') + ' {shift}';
                    var upperRow = upperArray.join(' ') + ' {shift}';

                    keyboardConfig.layout = {
                        'default': [lowerRow],
                        'shift': [upperRow]
                    };
                    keyboardConfig.display = {
                        '{shift}': '⇧'
                    };
                    keyboardConfig.useStandardCaps = false;
                    keyboardConfig.mergeDisplay = true;
                } else {
                    // Standard Language Layout
                    var LayoutsClass = KeyboardLayouts.default || KeyboardLayouts;
                    var keyboardLayouts = new LayoutsClass();
                    var layoutName = self.get_keyboard_layout(self.itemdata.language);
                    var layout = keyboardLayouts.get(layoutName);
                    $.extend(keyboardConfig, layout);
                }

                self.keyboard = new KeyboardClass(".simple-keyboard-" + self.itemdata.uniqueid, keyboardConfig);

                var keyboardtoggle = self.controls.container.find('.ml_simple_keyboard_toggle');
                keyboardtoggle.on('click', function (e) {
                    var kb = self.controls.container.find(".simple-keyboard-" + self.itemdata.uniqueid);
                    if (kb.is(":visible")) {
                        kb.hide();
                    } else {
                        kb.show();
                    }
                });
            }

        },

        onKeyPress: function (button) {
            var self = this;
            if (button === "{shift}" || button === "{lock}") {
                var currentLayout = self.keyboard.options.layoutName;
                var shiftToggle = currentLayout === "default" ? "shift" : "default";

                self.keyboard.setOptions({
                    layoutName: shiftToggle
                });

                var kbContainers = self.controls.container.find(".simple-keyboard-" + self.itemdata.uniqueid);
                if (shiftToggle === "shift") {
                    kbContainers.addClass("vkeyboard-shifted");
                } else {
                    kbContainers.removeClass("vkeyboard-shifted");
                }
                return;
            }
            if (!self.activeInputElement) {
                return;
            }
            var nativeElement = self.activeInputElement[0];

            // Handle backspace
            if (button === "{bksp}") {
                if (nativeElement.value === "") {
                    // Trigger keydown 8 to move focus previous
                    var event = new KeyboardEvent("keydown", {
                        bubbles: true, cancelable: true,
                        key: "Backspace", code: "Backspace", keyCode: 8, which: 8
                    });
                    nativeElement.dispatchEvent(event);
                } else {
                    nativeElement.value = "";
                    nativeElement.classList.remove("ml_gapfill_char_correct");
                    // Trigger input event
                    var event = new Event('input', { bubbles: true });
                    nativeElement.dispatchEvent(event);
                }
            } else if (button.length === 1) {
                // If it is regular character
                nativeElement.value = button;
                nativeElement.classList.remove("ml_gapfill_char_correct");
                // Trigger input event
                var event = new Event('input', { bubbles: true });
                nativeElement.dispatchEvent(event);
            }
        },

        get_keyboard_layout: function (lang) {
            var langCode = lang.substring(0, 2).toLowerCase();
            switch (langCode) {
                case 'en': return 'english';
                case 'de': return 'german';
                case 'es': return 'spanish';
                case 'fr': return 'french';
                case 'it': return 'italian';
                case 'ja': return 'japanese';
                case 'ko': return 'korean';
                case 'pt': return 'portuguese';
                case 'ru': return 'russian';
                case 'tr': return 'turkish';
                case 'uk': return 'ukrainian';
                case 'zh': return 'chinese';
                case 'ar': return 'arabic';
                case 'el': return 'greek';
                case 'he': return 'hebrew';
                case 'hi': return 'hindi';
                case 'th': return 'thai';
                case 'ur': return 'urdu';
                default: return 'english';
            }
        },

        game: {
            pointer: 0
        },

        check_answer: function () {
            var self = this;
            var passage = self.items[self.game.pointer].parsedstring;
            var characterunputs = self.controls.container.find('.lgapfill_reply_' + self.game.pointer + ' input.single-character');
            var transcript = [];

            characterunputs.each(function () {
                var index = $(this).data('index');
                var value = $(this).val();
                transcript.push = ({
                    index: index,
                    value: value
                });
            });

            self.getComparison(passage, transcript, function (comparison) {
                self.gotComparison(comparison);
            });
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
                    target: target.sentence,
                    prompt: target.prompt,
                    parsedstring: target.parsedstring,
                    definition: target.definition,
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

            //Prepare audio
            $.each(self.items, function (index, item) {
                item.audio = new Audio();
                item.audio.src = item.audiourl;
            });
            self.appReady();

        },

        appReady: function () {
            var self = this;
            self.controls.container.find('.lgapfill_not_loaded').hide();
            self.controls.container.find('.lgapfill_loaded').show();
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
            self.controls.container.find('.lgapfill_reply_' + self.game.pointer + ' input').removeClass("ml_gapfill_char_correct");
            if (comparison.allcorrect) {
                self.controls.container.find('.lgapfill_reply_' + self.game.pointer + ' .lgapfill_feedback[data-idx="' + self.game.pointer + '"]').addClass("fa fa-check");
                self.items[self.game.pointer].answered = true;
                self.items[self.game.pointer].correct = true;
                self.items[self.game.pointer].typed = false;
                //if they got it correct, make the input boxes green and move forward
                log.debug("correct!!");
                self.controls.container.find('.lgapfill_reply_' + self.game.pointer + ' input').addClass("ml_gapfill_char_correct");


                //if they cant retry OR the time limit is up, move on
            } else if (!self.itemdata.allowretry || timelimit_progressbar.hasClass('progress-bar-complete')) {
                self.controls.container.find('.lgapfill_reply_' + self.game.pointer + ' .lgapfill_feedback[data-idx="' + self.game.pointer + '"]').addClass("fa fa-times");
                self.items[self.game.pointer].answered = true;
                self.items[self.game.pointer].correct = false;
                self.items[self.game.pointer].typed = false;
            } else {
                //it was wrong but they can retry
                //mark the correct characters
                comparison.charscorrect.forEach(function (iscorrect, index) {
                    if (iscorrect) {
                        self.controls.container.find('.lgapfill_reply_' + self.game.pointer + ' input.single-character[data-index="' + index + '"]').addClass("ml_gapfill_char_correct");
                    }
                });

                var thereply = self.controls.container.find('.lgapfill_reply_' + self.game.pointer);
                anim.do_animate(thereply, 'shakeX animate__faster').then(
                    function () {
                        self.controls.ctrl_btn.prop("disabled", false);
                    }
                );
                return;
            }

            self.stopTimer(self.items[self.game.pointer].timer);

            if (self.game.pointer < self.items.length - 1) {
                setTimeout(function () {
                    self.controls.container.find('.lgapfill_reply_' + self.game.pointer).hide();
                    self.game.pointer++;
                    self.nextPrompt();
                }, 2000);
            } else {
                self.end();
            }
        },

        getWords: function (thetext) {
            var self = this;
            var checkcase = false;
            if (checkcase == 'false') {
                thetext = thetext.toLowerCase();
            }
            var chunks = thetext.split(self.quizhelper.spliton_regexp).filter(function (e) {
                return e !== "";
            });
            var words = [];
            for (var i = 0; i < chunks.length; i++) {
                if (!chunks[i].match(self.quizhelper.spliton_regexp)) {
                    words.push(chunks[i]);
                }
            }
            return words;
        },

        getComparison: function (passage, transcript, callback) {
            var self = this;
            self.controls.ctrl_btn.prop("disabled", true);
            var correctanswer = true;
            var charscorrect = [];

            passage.forEach(function (data, index) {
                var char = '';

                if (data.type === 'input') {
                    char = self.controls.container.find('.lgapfill_reply_' + self.game.pointer + ' input.single-character[data-index="' + index + '"]').val();
                    if (char == '') {
                        correctanswer = false;
                        charscorrect[index] = false;
                    } else if (char != data.character) {
                        correctanswer = false;
                        charscorrect[index] = false;
                    } else {
                        charscorrect[index] = true;
                    }
                }
            });

            var comparison = {
                allcorrect: correctanswer,
                charscorrect: charscorrect
            };

            callback(comparison);
        },

        end: function () {
            var self = this;
            self.controls.nextbutton.prop("disabled", true);

            //progress dots are updated on next_item. The last item has no next item, so we update from here
            self.updateProgressDots();

            //disable the buttons and go to next question or review
            setTimeout(function () {
                self.controls.nextbutton.prop("disabled", false);
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
            self.controls.description.hide();
            self.controls.image.hide();
            self.controls.maintitle.show();
            self.controls.itemquestion.show();
            self.controls.mainmenu.hide();
            self.controls.controlsbox.show();

            self.nextPrompt();
        },

        nextPrompt: function () {

            var self = this;

            self.controls.ctrl_btn.prop("disabled", false);

            self.updateProgressDots();

            self.nextReply();

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
            self.controls.title.html(progress);
        },

        nextReply: function () {
            var self = this;
            var code = "<div class='lgapfill_reply lgapfill_reply_" + self.game.pointer + " text-center' style='display:none;'>";
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
                    code += "<input class='single-character' type='text' autocomplete='off' autocapitalize='off' name='filltext" + index + "' maxlength='1' data-index='" + index + "'>";
                } else if (data.type === 'mtext') {
                    code += "<input class='single-character-mtext' autocomplete='off' autocapitalize='off' type='text' name='readonly" + index + "' maxlength='1' value='" + data.character + "' readonly>";
                } else {
                    code += data.character;
                }
            });
            if (brackets.started && !brackets.ended) {
                code += '</span>';
            }
            code += " <i data-idx='" + self.game.pointer + "' class='lgapfill_feedback'></i></div>";

            //hint - image
            if (self.items[self.game.pointer].imageurl) {
                code += "<div class='minilesson_sentence_image'><div class='minilesson_padded_image'><img src='"
                    + self.items[self.game.pointer].imageurl + "' alt='Image for gap fill' /></div></div>";
            }
            //hint - definition
            if (self.items[self.game.pointer].definition) {
                code += "<div class='definition-container'><div class='definition'>"
                    + "<div class='hinticon-container'><img class='icon' src='" + M.util.image_url('lightbulb-icon', 'mod_minilesson') + "' alt='hint'></div>"
                    + "<h4 class='hint-title'>Hint</h4>"
                    + self.items[self.game.pointer].definition + "</div>";
            }

            code += "</div>";
            self.controls.question.append(code);
            var newreply = self.controls.container.find('.lgapfill_reply_' + self.game.pointer);

            anim.do_animate(newreply, 'zoomIn animate__faster', 'in').then(
                function () {
                }
            );

            self.controls.ctrl_btn.prop("disabled", false);

            var inputElements = Array.from(self.controls.container.find('.lgapfill_reply_' + self.game.pointer + ' input.single-character'));
            self.formReady(inputElements);

            self.controls.container.find('.lgapfill_reply_' + self.game.pointer + ' input.single-character:first').focus();

            self.startTimer();
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

        // Stop audio .. usually when leaving the item or sentence
        stop_audio: function () {
            var self = this;
            //pause audio if its playing
            var theaudio = self.items[self.game.pointer].audio;
            if (theaudio && !theaudio.paused) {
                theaudio.pause();
            }
        },

        formReady: function (inputElements) {
            var self = this;
            inputElements.forEach(function (ele, index) {
                ele.addEventListener("focus", function (e) {
                    self.activeInputElement = $(e.target);
                });
                ele.addEventListener("keydown", function (e) {
                    switch (e.keyCode) {
                        case 8:
                            // If the keycode is backspace & the current field is empty
                            // focus the input before the current. Then the event happens
                            // which will clear the "before" input box.
                            if (e.target.value === "") {
                                inputElements[Math.max(0, index - 1)].focus();
                            } else {
                                // Remove class "ml_gapfill_char_correct" from the current element
                                e.target.classList.remove("ml_gapfill_char_correct");
                            }
                            break;
                        case 39:
                            // If the keycode is right arrow & the current field is not empty
                            // focus the input after the current.
                            //if (e.target.value !== "") {
                            if (true) {
                                e.preventDefault();
                                inputElements[Math.min(inputElements.length - 1, index + 1)].focus();
                            }
                            break;
                        case 37:
                            // If the keycode is left arrow & the current field is not empty
                            // focus the input before the current.
                            //if (e.target.value !== "") {
                            if (true) {
                                e.preventDefault();
                                inputElements[Math.max(0, index - 1)].focus();
                            }
                            break;
                        default:
                            // If the current field is not empty AND new value is not shift/enter/control etc
                            // replace the current value with the newly typed value
                            if (e.target.value !== "" && e.key.length === 1) {
                                e.target.value = e.target.value.replace(e.target.value, e.key);
                                // Remove class "ml_gapfill_char_correct" from the current element
                                e.target.classList.remove("ml_gapfill_char_correct");
                                e.target.dispatchEvent(new Event("input"));
                            }
                            break;
                    }
                });
                ele.addEventListener("input", function (e) {
                    // Take the first character of the input
                    const [first, ...rest] = e.target.value;
                    e.target.value = first ?? ""; // First will be undefined when backspace was entered, so set the input to ""
                    const lastInputBox = index === inputElements.length - 1;
                    const didInsertContent = first !== undefined;
                    if (didInsertContent && !lastInputBox) {
                        // Continue to input the rest of the string
                        inputElements[index + 1].focus();
                        if (rest.length > 0) {
                            inputElements[index + 1].value = rest.join("");
                            inputElements[index + 1].dispatchEvent(new Event("input"));
                        }
                    }
                });
            });
        },
    };
});