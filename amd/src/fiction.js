define([
    'jquery',
    'core/log',
    'core/str',
    'core/notification',
    'mod_minilesson/definitions',
    'mod_minilesson/external/yarn-bound',
    'core/modal_factory',
    'core/fragment',
    'core/templates',
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
        storyscore: false,
        strings: {},
        presentationmode: 'plain',
        index: 0,
        quizhelper: null,
        storydata: null,

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
            this.presentationmode = itemdata.presention_mobilechat ? 'mobilechat' : (itemdata.presention_storymode ? 'storymode' : 'plain');
            this.flowthroughmode = itemdata.flowthroughmode;
            this.filenamesmap = itemdata.filenamesmap;
            this.preload_images();
            // Initial user and other data
            this.storydata = new Map();
            this.storydata.set('userfirstname', itemdata.userfirstname);
            this.storydata.set('userlastname', itemdata.userlastname);
            this.storydata.set('userfullname', itemdata.userfullname);
            // Auto-declare variables from Yarn script
            // This makes sure indialogue variables are initialized as well as out of dialogue ones
            this.autodeclareVariables(itemdata.fictionyarn, this.storydata);

            // Set all the data for Yarn
            var yarnopts = {
                "dialogue": itemdata.fictionyarn,
                "combineTextAndOptionsResults": true,
                "startAt": "Start",
                "variableStorage": this.storydata,
            };
            log.debug('MiniLesson Fiction: initializing yarnbound with options');
            log.debug(yarnopts);
            try {
                this.runner = new YarnBound(yarnopts);
                this.do_render();
            } catch (e) {
                var userFriendlyError = "Yarn Parse Error: ";
                // Format the error nicely
                let errorMessage = e.message;
                if (typeof errorMessage === 'undefined') {
                    // If err is not an Error object (e.g. a string), use err directly
                    errorMessage = e ? String(e) : 'syntax or other error';
                }
                userFriendlyError += errorMessage;
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
            if (this.presentationmode === 'storymode') {
                this.controls.chatwrapper = $("#" + itemdata.uniqueid + "_container .story-wrapper");
            } else {
                this.controls.chatwrapper = $("#" + itemdata.uniqueid + "_container .chat-wrapper");
            }
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

                if (that.presentationmode === 'storymode') {
                    Templates.render('mod_minilesson/fiction_storymessage', {
                        charactermedia: '<div class="chat-loader"></div>'
                    }).then(function (html, js) {
                        Templates.appendNodeContents(that.controls.chatwrapper, html, js);
                        that.scrolltobottom();
                        Templates.render('mod_minilesson/fiction_storymessage', that.chatdata).then(
                            function (html, js) {
                                // In storymode we replace the loader with the real content
                                // finding the last story-paragraph
                                Templates.replaceNode(
                                    that.controls.chatwrapper.find('.story-paragraph').last(),
                                    html,
                                    js
                                );
                                that.scrolltobottom();
                                that.reset_chat_data();
                                if (!currentResult.isDialogueEnd) {
                                    if (that.flowthroughmode) {
                                        that.do_runner_advance();
                                        that.do_render();
                                    } else {
                                        that.can_continuebutton(true);
                                    }

                                }
                            }
                        );
                    });
                } else {
                    Templates.render('mod_minilesson/fiction_charactermessage', {
                        charactermedia: '<div class="chat-loader"></div>'
                    }).then(function (html, js) {
                        Templates.appendNodeContents(that.controls.chatwrapper, html, js);
                        that.scrolltobottom();
                        var waittime = 1000;
                        if (that.itemdata.presention_mobilechat) {
                            waittime = 1500;
                        } else if (that.itemdata.presention_storymode) {
                            waittime = 50;
                        }
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
                                        if (that.flowthroughmode) {
                                            that.do_runner_advance();
                                            that.do_render();
                                        } else {
                                            that.can_continuebutton(true);
                                        }

                                    }
                                }
                            );
                        }, waittime);
                    });
                }
                that.controls.yarnoptions.html('');
            } else if (currentResult instanceof YarnBound.OptionsResult) {
                yarncontent.yarnoptions = currentResult;
                var chatdata = {
                    'yarnoptions': currentResult,
                    'presention_mobilechat': that.itemdata.presention_mobilechat,
                    'presention_storymode': that.itemdata.presention_storymode,
                    'presention_plain': that.itemdata.presention_plain,
                    'shownonoptions': that.itemdata.shownonoptions,
                };
                that.controls.yarnoptions.html('');
                var waittime = 1000;
                if (that.itemdata.presention_mobilechat) {
                    waittime = 1500;
                } else if (that.itemdata.presention_storymode) {
                    waittime = 50;
                }
                Templates.render('mod_minilesson/fictionyarnoptions', chatdata).then(
                    function (html, js) {
                        setTimeout(() => {
                            that.controls.yarnoptions.html(html);
                            Templates.runTemplateJS(js);
                        }, waittime);
                    }
                );

                if ('text' in yarncontent.yarnoptions) {
                    that.chatdata.picturesrc = yarncontent.yarnoptions.md?.character?.picturesrc;
                    that.chatdata.charactername = yarncontent.yarnoptions.md?.character?.name;
                    that.chatdata.charactertext = yarncontent.yarnoptions.text;

                    if (that.presentationmode === 'storymode') {
                        Templates.render('mod_minilesson/fiction_storymessage', {
                            charactermedia: '<div class="chat-loader"></div>'
                        }).then(function (html, js) {
                            Templates.appendNodeContents(that.controls.chatwrapper, html, js);
                            that.scrolltobottom();
                            Templates.render('mod_minilesson/fiction_storymessage', that.chatdata).then(
                                function (html, js) {
                                    Templates.replaceNode(
                                        that.controls.chatwrapper.find('.story-paragraph').last(),
                                        html,
                                        js
                                    );
                                    that.scrolltobottom();
                                    that.reset_chat_data();
                                }
                            );
                        });
                    } else {
                        Templates.render('mod_minilesson/fiction_charactermessage', {
                            charactermedia: '<div class="chat-loader"></div>'
                        }).then(function (html, js) {
                            Templates.appendNodeContents(that.controls.chatwrapper, html, js);
                            that.scrolltobottom();
                            var waittime = 1000;
                            if (that.itemdata.presention_mobilechat) {
                                waittime = 1500;
                            } else if (that.itemdata.presention_storymode) {
                                waittime = 50;
                            }
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
                            }, waittime);
                        });
                    }
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
                        //check imageURL is in filenamesmap
                        var theimage = that.filenamesmap.find(function (file) {
                            return file.fileurl === imageURL;
                        });
                        if (theimage) {
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
                        }
                        break;
                    }
                    case 'audio': {
                        log.debug('got audio command');
                        const audioURL = args[0];
                        // Check audio file exists
                        var theaudio = that.filenamesmap.find(function (file) {
                            return file.fileurl === audioURL;
                        });
                        if (theaudio) {
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
                        }
                        break;
                    }
                    case 'video': {
                        log.debug('got video command');
                        const videoURL = args[0];
                        // Check video file exists
                        var thevideo = that.filenamesmap.find(function (file) {
                            return file.fileurl === videoURL;
                        });
                        if (thevideo) {
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
                        }
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
                // If there is a score we retrieve it
                if (this.storydata.has('score')) {
                    this.storyscore = this.storydata.get('score');
                    // If it is numeric, round it
                    if (!isNaN(this.storyscore)) {
                        this.storyscore = Math.round(this.storyscore);
                        if (this.storyscore < 0) {
                            this.storyscore = 0;
                        } else if (this.storyscore > 100) {
                            this.storyscore = 100;
                        }
                    } else {
                        this.storyscore = false;
                    }
                }
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
                var userFriendlyError = "Yarn Parse Error: ";
                if (this.runner && this.runner.currentResult && this.runner.currentResult.metadata && this.runner.currentResult.metadata.title) {
                    userFriendlyError += "(Node: " + this.runner.currentResult.metadata.title + " or maybe the node you are jumping to) ";
                }
                // Format the error nicely
                let errorMessage = e.message;
                if (typeof errorMessage === 'undefined') {
                    // If err is not an Error object (e.g. a string), use err directly
                    errorMessage = e ? String(e) : 'syntax or other error';
                }
                userFriendlyError += errorMessage;
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
            stepdata.hasgrade = true;

            // If the story has a score, use it
            if (self.storyscore !== false) {
                stepdata.grade = self.storyscore;
                stepdata.totalitems = 100;
                stepdata.correctitems = self.storyscore;
            } else {
                stepdata.correctitems = 1;
                stepdata.totalitems = 1;
                stepdata.grade = 100;
            }
            self.quizhelper.do_next(stepdata);
        },

        /**
         * Preload images
         */
        preload_images: function () {
            if (this.filenamesmap && this.filenamesmap.length > 0) {
                log.debug('MiniLesson Fiction: Preloading ' + this.filenamesmap.length + ' images');
                this.filenamesmap.forEach(function (file) {
                    if (file.fileurl) {
                        var img = new Image();
                        img.src = file.fileurl;
                    }
                });
            }
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
            $("#" + itemdata.uniqueid + "_container").on("showElement", async () => {
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
                // No need to show the "continue" text
                self.do_runner_advance();
                self.do_render();
            });

            // Add an event listener for option buttons that handles option buttons added at runtime
            this.controls.yarncontainer.on('click', '.minilesson_fiction_optionbutton', function (e) {
                log.debug('MiniLesson Fiction: yarn option button clicked');
                e.preventDefault();

                var buttons = self.controls.yarncontainer.find('.minilesson_fiction_optionbutton');
                var optionindex = $(this).data('optionindex');
                const playertext = $(this).text().trim();
                if (self.presentationmode === 'storymode') {
                    Templates.render('mod_minilesson/fiction_storyplayermessage', {
                        playertext: playertext
                    }).then(
                        function (html, js) {
                            Templates.appendNodeContents(self.controls.chatwrapper, html, js);
                            self.do_runner_advance(optionindex);
                            self.do_render();
                        }
                    );
                } else {
                    Templates.render('mod_minilesson/fiction_playermessage', {
                        playertext: playertext
                    }).then(
                        function (html, js) {
                            Templates.appendNodeContents(self.controls.chatwrapper, html, js);
                            self.do_runner_advance(optionindex);
                            self.do_render();
                        }
                    );
                }
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
         * Scans a Yarn string for <<declare>> statements and populates a Map.
         * @param {string} yarnText - The raw Yarn story string.
         * @param {Map} storageMap - Your yarn-bound variableStorage Map.
         */
        autodeclareVariables: function (yarnText, storageMap) {
            // Regex matches: <<declare $variableName = value>>
            // Captures group 1: variableName, group 2: value
            const declareRegex = /<<declare\s+\$([\w\d_]+)\s*=\s*(.*?)>>/g;
            let match;

            while ((match = declareRegex.exec(yarnText)) !== null) {
                let varName = match[1];
                let rawValue = match[2].trim();
                let finalValue;

                // Type conversion: numbers, booleans, or strings
                if (!isNaN(rawValue)) {
                    finalValue = Number(rawValue);
                } else if (rawValue === "true") {
                    finalValue = true;
                } else if (rawValue === "false") {
                    finalValue = false;
                } else {
                    // Remove quotes if it's a string literal
                    finalValue = rawValue.replace(/^["']|["']$/g, '');
                }

                storageMap.set(varName, finalValue);
            }
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
        },


        /* Check yarn script for syntax errors
         * @param {string} yarnContent - The raw Yarn story string.
         * @param {string} resultscontainer - The id of thecontainer element to display results in. 
         */
        syntaxcheck: function (yarnContent, resultscontainerid) {
            const results = {
                valid: true,
                errors: []
            };
            var runner = null;
            var storydata = new Map();
            storydata.set('userfirstname', 'bob');
            storydata.set('userlastname', 'smith');
            storydata.set('userfullname', 'bob smith');
            // Auto-declare variables from Yarn script
            // This makes sure indialogue variables are initialized as well as out of dialogue ones
            this.autodeclareVariables(yarnContent, storydata);

            // Set all the data for Yarn
            var yarnopts = {
                "dialogue": yarnContent,
                "combineTextAndOptionsResults": true,
                "startAt": "Start",
                "variableStorage": storydata,
            };
            // Step 1: Initialize YarnBound
            // This catches top-level errors (bad headers, duplicate titles, invalid declarations)
            try {
                var yarnBound = new YarnBound(yarnopts);
            } catch (err) {
                log.debug('Yarn initialization error: ' + err.message);
                results.valid = false;
                results.errors.push(`Initialization Error: ${err.message}`);
            }
            // Step 2 & 3: Iterate through nodes and check syntax
            // We access the internal 'runner' to get the nodes list
            if (results.valid) {
                var runner = yarnBound.runner;
                const nodeNames = Object.keys(runner.yarnNodes);
                nodeNames.forEach(nodeName => {
                    try {
                        // getParserNodes parses the body text of the node.
                        // It will throw if it encounters invalid Yarn syntax (e.g. invalid <<command>> or <<if>>)
                        runner.getParserNodes(nodeName);
                        // jump will also check variable state , but that is not syntax but we could do it
                        // yarnBound.jump(nodeName);
                    } catch (err) {
                        results.valid = false;
                        // Format the error nicely
                        let errorMessage = err.message;
                        if (typeof errorMessage === 'undefined') {
                            // If err is not an Error object (e.g. a string), use err directly
                            errorMessage = err ? String(err) : 'syntax or other error';
                        }
                        results.errors.push(`Node '${nodeName}': ${errorMessage}`);
                    }
                });
            }

            // Step 4: Display results
            Templates.render('mod_minilesson/fiction_syntaxcheckresults', results)
                .then(function (html, js) {
                    $('#' + resultscontainerid).html(html);
                });
        }, // End syntaxcheck

        register_syntaxcheckbutton: function (buttonid, yarneditorid, resultscontainerid) {
            var syntaxcheckbtn = document.getElementById(buttonid);
            var that = this;
            if (!syntaxcheckbtn) {
                return;
            }
            syntaxcheckbtn.addEventListener('click', function (e) {
                e.preventDefault();
                var yarntext = $('#' + yarneditorid).val();
                that.syntaxcheck(yarntext, resultscontainerid);
            });
        },
    } // End module
});