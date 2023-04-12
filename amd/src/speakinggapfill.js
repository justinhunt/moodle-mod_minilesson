define(['jquery',
    'core/log',
    'core/ajax',
    'mod_minilesson/definitions',
    'mod_minilesson/pollyhelper',
    'mod_minilesson/cloudpoodllloader',
    'mod_minilesson/ttrecorder',
    'mod_minilesson/animatecss',
    'mod_minilesson/progresstimer'
], function($, log, ajax, def, polly, cloudpoodll, ttrecorder, anim, progresstimer) {
    "use strict"; // jshint ;_;

    log.debug('MiniLesson speaking gap fill: initialising');

    return {
        // For making multiple instances
        clone: function() {
            return $.extend(true, {}, this);
        },

        init: function(index, itemdata, quizhelper) {
            var self = this;
            var theCallback = function(message) {

                switch (message.type) {
                    case 'recording':
                        break;

                    case 'speech':
                        log.debug("Speech at speaking gap fill");
                        var words = self.items[self.game.pointer].words;
                        var maskedwords = [];

                        Object.keys(words).forEach(function(key) {
                            maskedwords.push(words[key]);
                        });

                        console.log(maskedwords.join(" "));
                        console.log(message.capturedspeech);

                        self.getComparison(
                            maskedwords.join(" "),
                            message.capturedspeech,
                            self.items[self.game.pointer].phonetic,
                            function(comparison) {
                                self.gotComparison(comparison, message);
                            }
                        );
                        break;

                }

            };

            if (quizhelper.use_ttrecorder()) {
                var opts = {};
                opts.uniqueid = itemdata.uniqueid;
                opts.callback = theCallback;
                opts.stt_guided = quizhelper.is_stt_guided();
                opts.wwwroot = quizhelper.is_stt_guided();
                ttrecorder.clone().init(opts);
            } else {
                // Init cloudpoodll push recorder
                cloudpoodll.init('minilesson-recorder-listenrepeat-' + itemdata.id, theCallback);
            }

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
        },

        next_question: function(percent) {
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
            // On next button click
            $("#" + self.itemdata.uniqueid + "_container .minilesson_nextbutton").on('click', function(e) {
                self.next_question();
            });
            // On start button click
            $("#" + self.itemdata.uniqueid + "_container .landr_start_btn").on("click", function() {
                self.start();
            });
            // On listen button click
            $("#" + self.itemdata.uniqueid + "_container .landr_listen_btn").on("click", function() {
                self.items[self.game.pointer].audio.load();
                self.items[self.game.pointer].audio.play();
            });
            // On skip button click
            $("#" + self.itemdata.uniqueid + "_container .landr_skip_btn").on("click", function() {
                // Disable the buttons
                $("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn").prop("disabled", true);
                // Reveal the prompt
                $("#" + self.itemdata.uniqueid + "_container .landr_speech.landr_teacher_left").text(self.items[self.game.pointer].prompt + "");
                // Reveal the answer
                $("#" + self.itemdata.uniqueid + "_container .landr_targetWord").each(function() {
                    var realidx = $(this).data("realidx");
                    var landr_targetWord = self.items[self.game.pointer].landr_targetWords[realidx];
                    $(this).val(landr_targetWord);
                });

                if (self.game.pointer < self.items.length - 1) {
                    // Move on after short time to next prompt
                    setTimeout(function() {
                        $(".landr_reply_" + self.game.pointer).hide();
                        self.items[self.game.pointer].answered = true;
                        self.items[self.game.pointer].correct = false;
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
                    displayprompt: target.displayprompt,
                    definition: target.definition,
                    phonetic: target.phonetic,
                    words: target.words,
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
                        return e.audio === null;
                    }).length === 0) {
                        self.appReady();
                    }
                });
            });


        },

        appReady: function() {
            var self = this;
            $("#" + self.itemdata.uniqueid + "_container .landr_not_loaded").hide();
            $("#" + self.itemdata.uniqueid + "_container .landr_loaded").show();
            $("#" + self.itemdata.uniqueid + "_container .landr_start_btn").prop("disabled", false);
        },

        gotComparison: function(comparison, typed) {
            var self = this;
            var feedback = $("#" + self.itemdata.uniqueid + "_container .landr_reply_" + self.game.pointer + " .dictate_feedback[data-idx='" + self.game.pointer + "']");

            $("#" + self.itemdata.uniqueid + "_container .landr_targetWord").removeClass("landr_correct landr_incorrect");
            $("#" + self.itemdata.uniqueid + "_container .landr_feedback").removeClass("fa fa-check fa-times");

            var allCorrect = comparison.filter(function(e) {
                return !e.matched;
            }).length == 0;

            if (allCorrect && comparison && comparison.length > 0) {
                self.items[self.game.pointer].parsedstring.forEach(function(data, index) {
                    var characterinput = $("#" + self.itemdata.uniqueid + "_container .landr_reply_" + self.game.pointer + ' input.single-character[data-index="' + index + '"]');
                    if (data.type === 'input') {
                        characterinput.val(data.character);
                    }
                });

                feedback.removeClass("fa fa-times");
                feedback.addClass("fa fa-check");

                self.items[self.game.pointer].answered = true;
                self.items[self.game.pointer].correct = true;
                self.items[self.game.pointer].typed = typed;

                $("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn").prop("disabled", true);
                if (self.game.pointer < self.items.length - 1) {
                    setTimeout(function() {
                        $(".landr_reply_" + self.game.pointer).hide();
                        self.game.pointer++;
                        self.nextPrompt();
                    }, 2000);
                } else {
                    self.end();
                }
            } else {
                feedback.removeClass("fa fa-check");
                feedback.addClass("fa fa-times");
                // Mark up the words as correct or not
                comparison.forEach(function(obj) {
                    var words = self.items[self.game.pointer].words;

                    Object.keys(words).forEach(function(key) {
                        if (words[key] == obj.word) {
                            if (!obj.matched) {
                                self.items[self.game.pointer].parsedstring.forEach(function(data, index) {
                                    var characterinput = $("#" + self.itemdata.uniqueid + "_container .landr_reply_" + self.game.pointer + ' input.single-character[data-index="' + index + '"]');
                                    if (data.index == key && data.type === 'input') {
                                        characterinput.val('');
                                    }
                                });
                            } else {
                                self.items[self.game.pointer].parsedstring.forEach(function(data, index) {
                                    var characterinput = $("#" + self.itemdata.uniqueid + "_container .landr_reply_" + self.game.pointer + ' input.single-character[data-index="' + index + '"]');
                                    if (data.index == key && data.type === 'input') {
                                        characterinput.val(data.character);
                                    }
                                });
                            }
                        }
                    });
                });
                var thereply = $("#" + self.itemdata.uniqueid + "_container .landr_reply_" + self.game.pointer);
                anim.do_animate(thereply, 'shakeX animate__faster').then(
                    function() {
                        $("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn").prop("disabled", false);
                    }
                );
            }
            // Show all the correct words
            $("#" + self.itemdata.uniqueid + "_container .landr_targetWord.landr_correct").each(function() {
                var realidx = $(this).data("realidx");
                var landr_targetWord = self.items[self.game.pointer].landr_targetWords[realidx];
                $(this).val(landr_targetWord);
            });

        },

        getComparison: function(passage, transcript, phonetic, callback) {
            var self = this;

            $(".landr_ctrl-btn").prop("disabled", true);
            self.quizhelper.comparePassageToTranscript(passage, transcript, phonetic, self.itemdata.language).then(function(ajaxresult) {
                var payloadobject = JSON.parse(ajaxresult);
                if (payloadobject) {
                    callback(payloadobject);
                } else {
                    callback(false);
                }
            });
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

            $("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn").prop("disabled", true);
            $("#" + self.itemdata.uniqueid + "_container .landr_speakbtncontainer").show();

            self.items.forEach(function(item) {
                item.spoken = "";
                item.answered = false;
                item.correct = false;
            });

            self.game.pointer = 0;

            $("#" + self.itemdata.uniqueid + "_container .question").show();
            $("#" + self.itemdata.uniqueid + "_container .landr_start_btn").hide();
            $("#" + self.itemdata.uniqueid + "_container .landr_mainmenu").hide();
            $("#" + self.itemdata.uniqueid + "_container .landr_controls").show();

            self.nextPrompt();
        },

        nextPrompt: function() {
            var self = this;

            $(".landr_ctrl-btn").prop("disabled", false);

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

            $("#" + self.itemdata.uniqueid + "_container .landr_title").html(progress);
            var newprompt = $(".landr_prompt_" + self.game.pointer);
            anim.do_animate(newprompt, 'zoomIn animate__faster', 'in').then(
                function() {
                }
            );
            self.nextReply();

        },

        nextReply: function() {
            var self = this;

            var code = "<div class='landr_reply landr_reply_" + self.game.pointer + " text-center' style='display:none;'>";

            code += "<div class='form-container'>";
            self.items[self.game.pointer].parsedstring.forEach(function(data, index) {
                if (data.type === 'input') {
                    code += "<input class='single-character' type='text' name='filltext" + index + "' maxlength='1' data-index='" + index + "' readonly>";
                } else if (data.type === 'mtext') {
                    code += "<input class='single-character-mtext' type='text' name='readonly" + index + "' maxlength='1' value='" + data.character + "' readonly>";
                } else {
                    code += data.character;
                }
            });
            code += " <i data-idx='" + self.game.pointer + "' class='dictate_feedback'></i></div>";

            code += "<div class='definition-container'>";
            code += "<div class='definition'>" + self.items[self.game.pointer].definition + "</div>";
            code += "</div>";

            $("#" + self.itemdata.uniqueid + "_container .question").append(code);
            var newreply = $(".landr_reply_" + self.game.pointer);
            anim.do_animate(newreply, 'zoomIn animate__faster', 'in').then(
                function() {
                }
            );
            $("#" + self.itemdata.uniqueid + "_container .landr_ctrl-btn").prop("disabled", false);

            var inputElements = [...document.querySelectorAll(".landr_reply_" + self.game.pointer + " input.single-character")];
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
                    onFinish: function() {
                        $("#" + self.itemdata.uniqueid + "_container .dictate_check_btn").trigger('click');
                    }
                });
            }

            if (!self.quizhelper.mobile_user()) {
                setTimeout(function() {
                    $("#" + self.itemdata.uniqueid + "_container .landr_listen_btn").trigger('click');
                }, 1000);
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