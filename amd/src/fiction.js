define([
    'jquery',
    'core/log',
    'core/str',
    'core/notification',
    'mod_minilesson/definitions',
    'mod_minilesson/yarn-bound',
    'core/modal_factory',
    'core/fragment',
    'core/templates'
], function ($, log, str, notification, def, YarnBound, ModalFactory, Fragment, Templates) {
    "use strict"; // jshint ;_;

    /**
     * This file is to manage the fiction item type
     */

    log.debug('MiniLesson Fiction: initialising');

    return {
        runner: null,
        controls: {},
        itemdata: {},
        chatdata: {},
        storycomplete: false,
        strings: {},
        presentationmode: 'plain',
        index: 0,
        quizhelper: null,

        // For making multiple instances
        clone: function () {
            return $.extend(true, {}, this);
        },

        /**
         * Initialize the module
         *
         * @param {int} index
         * @param {object} itemdata
         * @param {object} quizhelper
         */
        init: function (index, itemdata, quizhelper) {
            this.index = index;
            this.itemdata = itemdata;
            this.quizhelper = quizhelper;
            this.init_strings();
            this.prepare_html(itemdata);
            this.register_events(this.index, itemdata, quizhelper);
            this.presentationmode = itemdata.presention_mobilechat ? 'mobilechat' : 'plain';
            var yarnopts = {
                "dialogue": itemdata.fictionyarn,
                "combineTextAndOptionsResults": true,
                "startAt": "Start"
            };
            log.debug('MiniLesson Fiction: initializing yarnbound with options');
            log.debug(yarnopts);
            try {
                this.runner = new YarnBound(yarnopts);
                this.do_render();
            } catch (e) {
                var userFriendlyError = "Yarn Parse Error: " + e.message;
                this.controls.yarncontainer.html(
                    '<div class="alert alert-danger">' + userFriendlyError + '</div>'
                );
                log.error("Full Yarn Error:");
                log.error(e);
            }
        },

        /**
         * Initialize strings
         */
        init_strings: function () {
            var self = this;
            str.get_strings([
                {
                    "key": "nextlessonitem",
                    "component": 'mod_minilesson'
                },
                {
                    "key": "confirm_desc",
                    "component": 'mod_minilesson'
                },
                {
                    "key": "yes",
                    "component": 'moodle'
                },
                {
                    "key": "no",
                    "component": 'moodle'
                },
            ]).done(function (s) {
                var i = 0;
                self.strings.nextlessonitem = s[i++];
                self.strings.confirm_desc = s[i++];
                self.strings.yes = s[i++];
                self.strings.no = s[i++];
            });
        },

        /**
         * Prepare HTML elements
         *
         * @param {object} itemdata
         */
        prepare_html: function (itemdata) {
            this.controls.yarncontainer = $("#" + itemdata.uniqueid + "_container .minilesson_fiction_yarncontainer");
            this.controls.yarntext = this.controls.yarncontainer.find('.minilesson_fiction_yarntext');
            this.controls.yarnmedia = this.controls.yarncontainer.find('.minilesson_fiction_yarnmedia');
            this.controls.yarnoptions = this.controls.yarncontainer.find('.minilesson_fiction_yarnoptions');
            this.controls.yarncontinuebutton = $("#" + itemdata.uniqueid + "_container .minilesson_fiction_continuebutton");
            this.controls.chatwrapper = $("#" + itemdata.uniqueid + "_container .chat-wrapper");
            // To speed up rendering later prefetch some templates
            Templates.prefetchTemplates(['mod_minilesson/fiction_playermessage']);
        },

        /**
         * Render the current content
         *
         * @param {object} currentResult
         */
        do_render: function (currentResult) {
            currentResult = this.runner.currentResult;
            currentResult = this.add_metadata(currentResult);
            log.debug('MiniLesson Fiction: doing render of currentResult');
            log.debug(currentResult);

            var that = this;
            var yarncontent = {
                'yarntext': false,
                'yarnoptions': false
            };

            if (currentResult instanceof YarnBound.TextResult) {
                yarncontent.yarntext = currentResult;
                this.can_continuebutton(false);
                this.chatdata.picturesrc = yarncontent.yarntext.md?.character?.picturesrc;
                this.chatdata.charactername = yarncontent.yarntext.md?.character?.name;
                this.chatdata.charactertext = yarncontent.yarntext.text;

                Templates.render('mod_minilesson/fiction_charactermessage', {
                    charactermedia: '<div class="chat-loader"></div>'
                }).then(function (html, js) {
                    Templates.appendNodeContents(that.controls.chatwrapper, html, js);
                    that.scrolltobottom();
                    setTimeout(() => {
                        Templates.render('mod_minilesson/fiction_charactermessage', that.chatdata).then(
                            function (html, js) {
                                Templates.replaceNode(
                                    that.controls.chatwrapper.find('> .chat-window').last(),
                                    html,
                                    js
                                );
                                that.scrolltobottom();
                                that.reset_chat_data();
                                if (!currentResult.isDialogueEnd) {
                                    that.can_continuebutton(true);
                                }
                            }
                        );
                    }, 2000);
                });
                that.controls.yarnoptions.html('');
            } else if (currentResult instanceof YarnBound.OptionsResult) {
                yarncontent.yarnoptions = currentResult;
                var chatdata = {
                    'yarnoptions': currentResult,
                    'presention_mobilechat': that.itemdata.presention_mobilechat,
                    'presention_plain': that.itemdata.presention_plain,
                };
                that.controls.yarnoptions.html('');
                Templates.render('mod_minilesson/fictionyarnoptions', chatdata).then(
                    function (html, js) {
                        setTimeout(() => {
                            that.controls.yarnoptions.html(html);
                            Templates.runTemplateJS(js);
                        }, 2000);
                    }
                );

                if ('text' in yarncontent.yarnoptions) {
                    that.chatdata.picturesrc = yarncontent.yarnoptions.md?.character?.picturesrc;
                    that.chatdata.charactername = yarncontent.yarnoptions.md?.character?.name;
                    that.chatdata.charactertext = yarncontent.yarnoptions.text;

                    Templates.render('mod_minilesson/fiction_charactermessage', {
                        charactermedia: '<div class="chat-loader"></div>'
                    }).then(function (html, js) {
                        Templates.appendNodeContents(that.controls.chatwrapper, html, js);
                        that.scrolltobottom();
                        setTimeout(() => {
                            Templates.render('mod_minilesson/fiction_charactermessage', that.chatdata).then(
                                function (html, js) {
                                    Templates.replaceNode(
                                        that.controls.chatwrapper.find('> .chat-window').last(),
                                        html,
                                        js
                                    );
                                    that.scrolltobottom();
                                    that.reset_chat_data();
                                }
                            );
                        }, 2000);
                    });
                } else {
                    that.controls.yarntext.html('');
                }
                that.can_continuebutton(false);
            } else if (currentResult instanceof YarnBound.CommandResult) {
                // Process the command string a little. so we have command name and args
                // eg "picture 1.png"
                var rawCommand = currentResult.command;
                var parts = rawCommand.split(' ');
                var commandName = parts[0]; // "picture" "audio etc"
                var args = parts.slice(1); // ["1.png"]
                let promise = null;

                switch (commandName) {
                    case 'picture': {
                        log.debug('got picture command');
                        const imageURL = args[0];
                        promise = Templates.render('mod_minilesson/fictionyarnimage', {
                            "imageurl": imageURL
                        }).then(
                            function (html, js) {
                                that.controls.yarnmedia.html(html);
                                that.chatdata.charactermedia = html;
                                that.chatdata.classname = 'hasmedia';
                                Templates.runTemplateJS(js);
                            }
                        );
                        break;
                    }
                    case 'audio': {
                        log.debug('got audio command');
                        const audioURL = args[0];
                        promise = Templates.render('mod_minilesson/fictionyarnaudio', {
                            "audiourl": audioURL
                        }).then(
                            function (html, js) {
                                that.chatdata.charactermedia = html;
                                that.chatdata.classname = 'hasmedia';
                                that.controls.yarnmedia.html(html);
                                Templates.runTemplateJS(js);
                            }
                        );
                        break;
                    }
                    case 'video': {
                        log.debug('got video command');
                        const videoURL = args[0];
                        promise = Templates.render('mod_minilesson/fictionyarnvideo', {
                            "videourl": videoURL
                        }).then(
                            function (html, js) {
                                that.chatdata.charactermedia = html;
                                that.chatdata.classname = 'hasmedia';
                                that.controls.yarnmedia.html(html);
                                Templates.runTemplateJS(js);
                            }
                        );
                        break;
                    }
                    case 'clearpicture': {
                        that.controls.yarnmedia.html('');
                        break;
                    }
                    case 'blahblah':
                    default:
                }
                // In all cases just do command and then jump to next line
                if (!currentResult.isDialogueEnd) {
                    // Just skip through for now
                    if (promise) {
                        promise.then(() => {
                            that.do_runner_advance();
                            that.do_render();
                        });
                    } else {
                        that.do_runner_advance();
                        that.do_render();
                    }
                }
            } else {
                log.debug('MiniLesson Fiction: unknown yarn result type');
            }

            // In all cases on dialog end there is no continue
            if (currentResult.isDialogueEnd) {
                this.can_continuebutton(false);
                this.storycomplete = true;
            }
        },

        /**
         * Advance the yarn runner
         *
         * @param {int} steps
         */
        do_runner_advance: function (steps) {
            try {
                if (steps !== null) {
                    this.runner.advance(steps);
                } else {
                    this.runner.advance();
                }
            } catch (e) {
                var userFriendlyError = "Yarn Parse Error: " + e.message;
                this.controls.yarncontainer.html(
                    '<div class="alert alert-danger">' + userFriendlyError + '</div>'
                );
                log.error("Full Yarn Error:");
                log.error(e);
            }
        },

        /**
         * Enable/disable continue button
         *
         * @param {bool} cancontinue
         */
        can_continuebutton: function (cancontinue) {
            this.controls.yarncontinuebutton.prop("disabled", !cancontinue);
            if (cancontinue) {
                this.controls.yarncontinuebutton.show();
            } else {
                this.controls.yarncontinuebutton.hide();
            }
        },

        /**
         * Move to next question
         */
        next_question: function () {
            var self = this;
            var stepdata = {};
            stepdata.index = self.index;
            stepdata.hasgrade = false;
            stepdata.totalitems = 0;
            stepdata.correctitems = 0;
            stepdata.grade = 1;
            self.quizhelper.do_next(stepdata);
        },

        /**
         * Register events
         *
         * @param {int} index
         * @param {object} itemdata
         * @param {object} quizhelper
         */
        register_events: function (index, itemdata, quizhelper) {
            var self = this;
            // When click next button, report and leave it up to parent to deal with it.
            $("#" + itemdata.uniqueid + "_container .minilesson_nextbutton").on('click', function () {
                if (!self.storycomplete) {
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
            $("#" + itemdata.uniqueid + "_container").on("showElement", async() => {
                if (!self.instance) {
                    // Maybe init yarn-bound here
                    // this.do_render();
                }

                if (itemdata.timelimit > 0) {
                    $("#" + itemdata.uniqueid + "_container .progress-container").show();
                    $("#" + itemdata.uniqueid + "_container .progress-container i").show();
                    $("#" + itemdata.uniqueid + "_container .progress-container #progresstimer").progressTimer({
                        height: '5px',
                        timeLimit: itemdata.timelimit,
                        onFinish: function () {
                            $("#" + itemdata.uniqueid + "_container .minilesson_nextbutton").trigger('click');
                        }
                    });
                }
            });

            this.controls.yarncontinuebutton.on('click', function (e) {
                log.debug('MiniLesson Fiction: yarn continue button clicked');
                e.preventDefault();
                const playertext = $(this).text().trim();
                Templates.render('mod_minilesson/fiction_playermessage', {
                    playertext: playertext
                }).then(
                    function (html, js) {
                        Templates.appendNodeContents(self.controls.chatwrapper, html, js);
                        self.do_runner_advance();
                        self.do_render();
                    }
                );

            });

            // Add an event listener for option buttons that handles option buttons added at runtime
            this.controls.yarncontainer.on('click', '.minilesson_fiction_optionbutton', function (e) {
                log.debug('MiniLesson Fiction: yarn option button clicked');
                e.preventDefault();

                var buttons = self.controls.yarncontainer.find('.minilesson_fiction_optionbutton');
                var optionindex = buttons.index(this); // 0-based position in the rendered list
                const playertext = $(this).text().trim();
                Templates.render('mod_minilesson/fiction_playermessage', {
                    playertext: playertext
                }).then(
                    function (html, js) {
                        Templates.appendNodeContents(self.controls.chatwrapper, html, js);
                        self.do_runner_advance(optionindex);
                        self.do_render();
                    }
                );
            });

            let scrollbtn = $("#" + itemdata.uniqueid + "_container #scroll-bottom-btn");

            this.controls.chatwrapper.on("scroll", function () {
                const chatwrapper = self.controls.chatwrapper[0];
                const isatbottom = chatwrapper.scrollHeight - chatwrapper.scrollTop <= chatwrapper.clientHeight + 5;
                if (isatbottom) {
                    scrollbtn.hide();
                } else {
                    scrollbtn.show();
                }
            });

            scrollbtn.on('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                self.controls.chatwrapper.animate({
                    scrollTop: self.controls.chatwrapper[0].scrollHeight
                }, 'smooth');
            });
        },

        /**
         * Add metadata to results
         *
         * @param {object} currentthing
         */
        add_metadata: function (currentthing) {
            if (currentthing && 'markup' in currentthing && Array.isArray(currentthing.markup)) {
                currentthing.md = {};
                // For each markup entry, add the property name and values object to currentthing
                currentthing.markup.forEach(function (entry) {
                    if ('properties' in entry && 'name' in entry) {
                        currentthing.md[entry.name] = entry.properties;
                    }
                });
                if (currentthing.md.character?.name) {
                    const charname = currentthing.md.character.name.toLowerCase();
                    currentthing.md.character.picturesrc = this.itemdata.filenamesmap
                        .find(fileinfo => fileinfo.filekey === charname)?.fileurl || null;
                }
            }
            return currentthing;
        },

        /**
         * Register preview button
         *
         * @param {string} buttonid
         */
        register_previewbutton: function (buttonid) {
            var previewbtn = document.getElementById(buttonid);
            if (!previewbtn) {
                return;
            }
            previewbtn.addEventListener('click', function (e) {
                e.preventDefault();
                var form = previewbtn.form;
                if (!form) {
                    return;
                }
                ModalFactory.create({
                    type: ModalFactory.types.CANCEL,
                    large: true,
                    removeOnClose: true,
                    templateContext: {
                        classes: 'minilesson-fiction-preview-modal'
                    },
                    title: str.get_string('fiction:previewmodaltitle', 'mod_minilesson'),
                    body: Fragment.loadFragment('mod_minilesson', 'preview_fiction', M.cfg.contextid, {
                        formdata: new URLSearchParams([...new FormData(form).entries()]).toString()
                    })
                }).then(function (modal) {
                    modal.getFooter().addClass('d-none');
                    modal.show();
                });
                return;
            });

        },

        reset_chat_data: function () {
            this.chatdata = {
                charactername: null,
                charactermedia: null,
                charactertext: null,
                picturesrc: null,
                playertext: null,
            };
        },

        scrolltobottom: function () {
            if (!this.controls.chatwrapper.length) {
                return;
            }
            this.controls.chatwrapper.scrollTop(this.controls.chatwrapper[0].scrollHeight);
            this.controls.chatwrapper.animate({
                scrollTop: this.controls.chatwrapper[0].scrollHeight + 5,
                behavior: 'smooth'
            });
        }
    };
});