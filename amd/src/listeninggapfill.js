define(['jquery',
    'core/log',
    'core/ajax',
    'mod_minilesson/definitions',
    'mod_minilesson/pollyhelper',
    'mod_minilesson/animatecss',
    'mod_minilesson/progresstimer',
], function($, log, ajax, def, polly, anim, progresstimer) {
    "use strict"; // jshint ;_;

    log.debug('MiniLesson listening gap fill: initialising');

    return {

        // For making multiple instances
        clone: function() {
            return $.extend(true, {}, this);
        },

        usevoice: 'Amy',

        init: function(index, itemdata, quizhelper) {
            var self = this;
            self.itemdata = itemdata;
            self.quizhelper = quizhelper;
            self.index = index;

            // Anim
            var animopts = {};
            animopts.useanimatecss = quizhelper.useanimatecss;
            anim.init(animopts);

            self.register_events();
            self.setvoice();
            self.getItems();
            self.appReady();
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
            self.quizhelper.do_next(stepdata);
        },

        register_events: function() {

            var self = this;

            $("#" + self.itemdata.uniqueid + "_container .minilesson_nextbutton").on('click', function(e) {
                self.next_question();
            });

            $("#" + self.itemdata.uniqueid + "_container .dictate_start_btn").on("click", function() {
                self.start();
            });

            $("#" + self.itemdata.uniqueid + "_container .dictate_listen_btn").on("click", function() {
                self.items[self.game.pointer].audio.load();
                self.items[self.game.pointer].audio.play();
            });

            // On skip button click
            $("#" + self.itemdata.uniqueid + "_container .dictate_skip_btn").on("click", function() {
                // Disable buttons
                $("#" + self.itemdata.uniqueid + "_container .dictate_ctrl-btn").prop("disabled", true);
                // Reveal prompt
                $("#" + self.itemdata.uniqueid + "_container .dictate_speech.dictate_teacher_left").text(self.items[self.game.pointer].prompt + "");
                // Reveal answers
                // reveal the answer
                $("#" + self.itemdata.uniqueid + "_container .dictate_targetWord").each(function() {
                    var realidx = $(this).data("realidx");
                    var dictate_targetWord = self.items[self.game.pointer].dictate_targetWords[realidx];
                    $(this).val(dictate_targetWord);
                });


                // Move on after short time, to next prompt, or next question
                if (self.game.pointer < self.items.length - 1) {
                    setTimeout(function() {
                        self.items[self.game.pointer].answered = true;
                        self.items[self.game.pointer].correct = false;
                        self.game.pointer++;
                        self.nextPrompt();
                    }, 3000);
                } else {
                    self.end();
                }
            });


            $("#" + self.itemdata.uniqueid + "_container .dictate_check_btn").on("click", function() {
                self.check_answer();
            });

            // Listen for enter key
            $("#" + self.itemdata.uniqueid + "_container").on("keydown", ".dictate_targetWord", function(e) {
                if (e.which == 13) {
                    self.check_answer();
                }
            });

            // Auto nav between inputs
            $("#" + self.itemdata.uniqueid + "_container").on("keyup", ".dictate_targetWord", function(e) {

                // Move focus between textboxes
                // log.debug(e);
                var target = e.srcElement || e.target;
                var maxLength = parseInt(target.attributes.maxlength.value, 10);
                var myLength = target.value.length;
                var key = e.which;
                if (myLength >= maxLength) {
                    var nextIdx = $(this).data('idx') + 1;
                    var next = $("#" + self.itemdata.uniqueid + "_container input.dictate_targetWord[data-idx=\"" + nextIdx + "\"");
                    if (next.length === 1) {
                        next.focus();
                    }

                    // Move to previous field if empty (user pressed backspace or delete)
                } else if ((key == 8 || key == 46) && myLength === 0) {
                    var previousIdx = $(this).data('idx') - 1;
                    var previous = $("#" + self.itemdata.uniqueid + "_container input.dictate_targetWord[data-idx=\"" + previousIdx + "\"");
                    if (previous.length === 1) {
                        previous.focus();
                    }
                }
            });

        },

        game: {
            pointer: 0
        },

        check_answer: function() {
            var self = this;
            var passage = self.items[self.game.pointer].parsedstring;
            var characterunputs = $("#" + self.itemdata.uniqueid + "_container .dictate_reply_" + self.game.pointer + ' input.single-character');
            var transcript = [];

            characterunputs.each(function() {
                var index = $(this).data('index');
                var value = $(this).val();
                transcript.push = ({
                    index: index,
                    value: value
                });
            });

            self.getComparison(passage, transcript, function(comparison) {
                self.gotComparison(comparison);
            });
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
                    target: target.sentence,
                    prompt: target.prompt,
                    parsedstring: target.parsedstring,
                    typed: "",
                    answered: false,
                    correct: false,
                    audio: null
                };
            }).filter(function(e) {
                return e.target !== "";
            });

            $.each(self.items, function(index, item) {
                polly.fetch_polly_url(item.prompt, self.voiceoption, self.usevoice).then(function(audiourl) {
                    item.audio = new Audio();
                    item.audio.src = audiourl;
                    if (self.items.filter(function(e) {
                        return e.audio == null;
                    }).length == 0) {
                        self.appReady();
                    }
                });
            });
        },

        appReady: function() {
            var self = this;
            $("#" + self.itemdata.uniqueid + "_container .dictate_not_loaded").hide();
            $("#" + self.itemdata.uniqueid + "_container .dictate_loaded").show();
            $("#" + self.itemdata.uniqueid + "_container .dictate_start_btn").prop("disabled", false);
        },

        gotComparison: function(comparison) {
            var self = this;
            if (comparison) {
                $("#" + self.itemdata.uniqueid + "_container .dictate_reply_" + self.game.pointer + " .dictate_feedback[data-idx='" + self.game.pointer + "']").addClass("fa fa-check");
                self.items[self.game.pointer].answered = true;
                self.items[self.game.pointer].correct = true;
                self.items[self.game.pointer].typed = false;
            } else {
                $("#" + self.itemdata.uniqueid + "_container .dictate_reply_" + self.game.pointer + " .dictate_feedback[data-idx='" + self.game.pointer + "']").addClass("fa fa-times");
                self.items[self.game.pointer].answered = true;
                self.items[self.game.pointer].correct = false;
                self.items[self.game.pointer].typed = false;
            }

            if (self.game.pointer < self.items.length - 1) {
                setTimeout(function() {
                    $(".dictate_reply_" + self.game.pointer).hide();
                    self.game.pointer++;
                    self.nextPrompt();
                }, 2000);
            } else {
                self.end();
            }
        },

        getWords: function(thetext) {
            var self = this;
            var checkcase = false;
            if (checkcase == 'false') {
                thetext = thetext.toLowerCase();
            }
            var chunks = thetext.split(self.quizhelper.spliton_regexp).filter(function(e) {
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

        getComparison: function(passage, transcript, callback) {
            var self = this;

            $(".dictate_ctrl-btn").prop("disabled", true);

            var correctanswer = true;

            passage.forEach(function(data, index) {
                var char = '';

                if (data.type === 'input') {
                    if (correctanswer === true) {
                        char = $("#" + self.itemdata.uniqueid + "_container .dictate_reply_" + self.game.pointer + ' input.single-character[data-index="' + index + '"]').val();
                        if (char == '') {
                            correctanswer = false;
                        } else if (char != data.character) {
                            correctanswer = false;
                        }
                    }
                }
            });

            callback(correctanswer);
        },

        end: function() {
            var self = this;
            $(".minilesson_nextbutton").prop("disabled", true);
            setTimeout(function() {
                $(".minilesson_nextbutton").prop("disabled", false);
                self.next_question();
            }, 2200);
        },

        start: function() {
            var self = this;

            $("#" + self.itemdata.uniqueid + "_container .dictate_ctrl-btn").prop("disabled", true);

            self.items.forEach(function(item) {
                item.spoken = "";
                item.answered = false;
                item.correct = false;
            });

            self.game.pointer = 0;

            $("#" + self.itemdata.uniqueid + "_container .question").show();
            $("#" + self.itemdata.uniqueid + "_container .dictate_game").show();
            $("#" + self.itemdata.uniqueid + "_container .dictate_start_btn").hide();
            $("#" + self.itemdata.uniqueid + "_container .dictate_mainmenu").hide();
            $("#" + self.itemdata.uniqueid + "_container .dictate_controls").show();

            self.nextPrompt();

        },

        nextPrompt: function() {

            var self = this;

            $(".dictate_ctrl-btn").prop("disabled", false);

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

            $("#" + self.itemdata.uniqueid + "_container .dictate_title").html(progress);

            self.nextReply();

        },

        nextReply: function() {
            var self = this;
            var code = "<div class='dictate_reply dictate_reply_" + self.game.pointer + " text-center' style='display:none;'>";

            code += "<div class='form-container'>";
            self.items[self.game.pointer].parsedstring.forEach(function(data, index) {
                if (data.type === 'input') {
                    code += "<input class='single-character' type='text' name='filltext" + index + "' maxlength='1' data-index='" + index + "'>";
                } else if (data.type === 'mtext') {
                    code += "<input class='single-character-mtext' type='text' name='readonly" + index + "' maxlength='1' value='" + data.character + "' readonly>";
                } else {
                    code += data.character;
                }
            });
            code += " <i data-idx='" + self.game.pointer + "' class='dictate_feedback'></i></div>";

            code += "</div>";
            $("#" + self.itemdata.uniqueid + "_container .question").append(code);
            var newreply = $(".dictate_reply_" + self.game.pointer);

            anim.do_animate(newreply, 'zoomIn animate__faster', 'in').then(
                function() {
                }
            );

            $("#" + self.itemdata.uniqueid + "_container .dictate_ctrl-btn").prop("disabled", false);

            var inputElements = [...document.querySelectorAll(".dictate_reply_" + self.game.pointer + " input.single-character")];
            self.formReady(inputElements);

            if (self.itemdata.timelimit > 0) {
                $("#" + self.itemdata.uniqueid + "_container .progress-container").show();
                $("#" + self.itemdata.uniqueid + "_container .progress-container #progresstimer").progressTimer({
                    height: '5px',
                    timeLimit: self.itemdata.timelimit,
                    warningThreshold: 10,
                    baseStyle: 'bg-danger progress-bar progress-bar-animated',
                    warningStyle: 'bg-danger progress-bar progress-bar-animated',
                    completeStyle: 'bg-danger progress-bar progress-bar-animated',
                    onFinish: function () {
                        $("#" + self.itemdata.uniqueid + "_container .dictate_check_btn").trigger('click');
                    }
                });
            }
        },

        formReady: function(inputElements) {
            inputElements.forEach(function(ele, index) {
                ele.addEventListener("keydown", function(e) {
                    // If the keycode is backspace & the current field is empty
                    // focus the input before the current. Then the event happens
                    // which will clear the "before" input box.
                    if (e.keyCode === 8 && e.target.value === "") {
                        inputElements[Math.max(0, index - 1)].focus();
                    }
                });
                ele.addEventListener("input", function(e) {
                    // Take the first character of the input
                    // this actually breaks if you input an emoji like 👨‍👩‍👧‍👦....
                    // but I'm willing to overlook insane security code practices.
                    const [first, ...rest] = e.target.value;
                    e.target.value = first ?? ""; // First will be undefined when backspace was entered, so set the input to ""
                    const lastInputBox = index === inputElements.length - 1;
                    const didInsertContent = first !== undefined;
                    if (didInsertContent && !lastInputBox) {
                        // Continue to input the rest of the string
                        inputElements[index + 1].focus();
                        inputElements[index + 1].value = rest.join("");
                        inputElements[index + 1].dispatchEvent(new Event("input"));
                    }
                });
            });
        },
    };
});