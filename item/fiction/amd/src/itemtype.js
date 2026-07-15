define([
    'jquery',
    'core/log',
    'core/str',
    'core/notification',
    'mod_minilesson/definitions',
    'mod_minilesson/translate',
    'mod_minilesson/external/yarn-bound',
    'core/modal_cancel',
    'core/fragment',
    'core/templates',
    'mod_minilesson/progresstimer',
], function($, log, str, notification, def, translate, YarnBound, ModalCancel, Fragment, Templates) {
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
        visitednodes: [],

        // For making multiple instances
        clone: function() {
            return $.extend(true, {}, this);
        },

        /**
         * Initialize the module
         *
         * @param {int} index
         * @param {object} itemdata
         * @param {object} quizhelper
         */
        init: function(index, itemdata, quizhelper) {
            var that = this;
            this.index = index;
            this.itemdata = itemdata;
            this.quizhelper = quizhelper;
            this.presentationmode = itemdata.presention_immersivepaper ? 'immersivepaper'
                : itemdata.presention_immersivebright ? 'immersivebright'
                : itemdata.presention_immersivedark ? 'immersivedark'
                : (itemdata.presention_mobilechat ? 'mobilechat'
                : (itemdata.presention_storymode ? 'storymode' : 'plain'));
            this.isImmersive = (this.presentationmode === 'immersivedark'
                || this.presentationmode === 'immersivebright'
                || this.presentationmode === 'immersivepaper');
            this.flowthroughmode = itemdata.flowthroughmode;
            this.taptotranslate = itemdata.taptotranslate;
            this.taptotranslatearia = itemdata.taptotranslatearia;
            this.filenamesmap = itemdata.filenamesmap;
            if (this.isImmersive) {
                this.im_init_state();
            }
            this.init_strings();
            this.prepare_html(itemdata);
            this.register_events(this.index, itemdata);
            this.preload_images();
            // Initial user and other data
            this.storydata = new Map();
            this.storydata.set('userfirstname', itemdata.userfirstname);
            this.storydata.set('userlastname', itemdata.userlastname);
            this.storydata.set('userfullname', itemdata.userfullname);
            this.storydata.set('cantranslate', false);

            // Full locale tags (e.g. en-US -> pt-BR). nativelanguage is already resolved
            // server side from the activity setting and the user's native language
            // preference. The translate module degrades to base languages (pt) itself
            // when the browser has no model for the regional variant.
            this.sourceLang = this.itemdata.language;
            this.destLang = this.itemdata.nativelanguage;
            // Give the translate module what it needs for the web service fallback,
            // used when the browser has no native translation API.
            translate.init(quizhelper.cmid, itemdata.id);
            // We need to wait for the availability check before we start the story,
            // otherwise $cantranslate will be false in the first render's conditions.
            translate.check_availability(this.sourceLang, this.destLang).then(function (availability) {
                that.storydata.set('cantranslate', availability !== 'unavailable');

                // Auto-declare variables from Yarn script
                // This makes sure indialogue variables are initialized as well as out of dialogue ones
                that.autodeclareVariables(itemdata.fictionyarn, that.storydata);

                // Set all the data for Yarn
                var yarnopts = {
                    "dialogue": itemdata.fictionyarn,
                    "combineTextAndOptionsResults": true,
                    "startAt": "Start",
                    "variableStorage": that.storydata,
                    "functions": {
                        dice: (sides) => {
                            return Math.floor(Math.random() * sides) + 1;
                        },
                        translate: (text) => {
                            if (that.isImmersive) {
                                // Immersive cards strip inline HTML before the typewriter runs,
                                // so an inline span can never be filled in. Instead offer the
                                // text through the card's translation panel for this beat.
                                if (that.storydata.get('cantranslate')) {
                                    that.im_pendingInlineTranslate = text;
                                }
                                return "";
                            }
                            var randomId = Math.random().toString(36).substring(2, 9);
                            var updateStory = function (themessage, attemptsleft) {
                                var el = $("#ml-f-" + randomId);
                                if (el.length) {
                                    el.html(themessage);
                                } else if (attemptsleft > 0) {
                                    setTimeout(updateStory, 100, themessage, attemptsleft - 1);
                                }
                            };
                            that.call_translate(that.sourceLang, that.destLang, text, function (themessage) {
                                updateStory(themessage, 100);
                            });
                            return "<span id='ml-f-" + randomId + "' class='ml-f-inline-translated-text'></span>";
                        }
                    }
                };

                log.debug('MiniLesson Fiction: initializing yarnbound with options');
                log.debug(yarnopts);
                try {
                    that.runner = new YarnBound(yarnopts);
                    if (!that.isImmersive) {
                        that.do_render();
                    }
                    // For immersive modes, initial render is triggered by the Start card click.
                } catch (e) {
                    var userFriendlyError = "Yarn Parse Error: ";
                    // Format the error nicely
                    let errorMessage = e.message;
                    if (typeof errorMessage === 'undefined') {
                        // If err is not an Error object (e.g. a string), use err directly
                        errorMessage = e ? String(e) : 'syntax or other error';
                    }
                    userFriendlyError += errorMessage;
                    that.controls.yarncontainer.html(
                        '<div class="alert alert-danger">' + userFriendlyError + '</div>'
                    );
                    log.error("Full Yarn Error:");
                    log.error(e);
                }
            });
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
                {
                    "key": "downloadtranslationmodel",
                    "component": 'mod_minilesson'
                },
                {
                    "key": "downloadtranslationmodel_desc",
                    "component": 'mod_minilesson'
                },
                {
                    "key": "download",
                    "component": 'mod_minilesson'
                },
                {
                    "key": "skip",
                    "component": 'mod_minilesson'
                },
                {
                    "key": "downloadingtranslator",
                    "component": 'mod_minilesson'
                },
                {
                    "key": "fiction:translating",
                    "component": 'mod_minilesson'
                },
            ]).done(function (s) {
                var i = 0;
                self.strings.nextlessonitem = s[i++];
                self.strings.confirm_desc = s[i++];
                self.strings.yes = s[i++];
                self.strings.no = s[i++];
                self.strings.downloadtranslationmodel = s[i++];
                self.strings.downloadtranslationmodel_desc = s[i++];
                self.strings.download = s[i++];
                self.strings.skip = s[i++];
                self.strings.downloadingtranslator = s[i++];
                self.strings.translating = s[i++];
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
            if (this.isImmersive) {
                var $c = this.controls.yarncontainer;
                this.controls.im_text = $c.find('.card__text');
                this.controls.im_translation = $c.find('.card__translation');
                this.controls.im_translate = $c.find('.card__translate');
                this.controls.im_media = $c.find('.card__media');
                this.controls.im_mute = $c.find('.card__mute');
                this.controls.im_start = $c.find('.card__start');
                this.controls.im_actions = $c.find('.card__actions');
                this.controls.im_history = $c.find('.card__history');
                this.controls.im_historyOverlay = $c.find('.card__history-overlay');
                this.controls.im_historyLog = $c.find('.card__history-log');
                this.controls.im_historyClose = $c.find('.card__history-close');
            }
            // To speed up rendering later prefetch some templates
            Templates.prefetchTemplates(['minilessonitem_fiction/fiction_playermessage']);
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
            this.can_continuebutton(false);
            this.controls.yarnoptions.html('');

            var yarncontent = {
                'yarntext': false,
                'yarnoptions': false
            };

            if (currentResult instanceof YarnBound.TextResult) {
                yarncontent.yarntext = currentResult;
                this.chatdata.picturesrc = yarncontent.yarntext.md?.character?.picturesrc;
                this.chatdata.charactername = yarncontent.yarntext.md?.character?.name;
                this.chatdata.charactertext = yarncontent.yarntext.text;
                this.set_translate_data(this.chatdata, yarncontent.yarntext.text);

                this.post_message_to_story(this.chatdata, currentResult);

            } else if (currentResult instanceof YarnBound.OptionsResult) {
                if (currentResult.options && currentResult.options.length) {
                    currentResult.options.forEach(function (opt, i) {
                        opt.displayNumber = i + 1;
                        // Ensure index exists just in case
                        if (!('index' in opt)) {
                            opt.index = i;
                        }
                    });
                }
                yarncontent.yarnoptions = currentResult;
                var chatdata = {
                    'yarnoptions': currentResult,
                    'presention_mobilechat': that.itemdata.presention_mobilechat,
                    'presention_storymode': that.itemdata.presention_storymode,
                    'presention_plain': that.itemdata.presention_plain,
                    'presention_immersivedark': that.itemdata.presention_immersivedark,
                    'presention_immersivebright': that.itemdata.presention_immersivebright,
                    'presention_immersivepaper': that.itemdata.presention_immersivepaper,
                    'shownonoptions': that.itemdata.shownonoptions,
                };
                if (that.isImmersive) {
                    // Defer options render until the typewriter finishes.
                    that.im_pendingOptions = chatdata;
                } else {
                    var waittime = 1000;
                    if (that.itemdata.presention_mobilechat) {
                        waittime = 1500;
                    } else if (that.itemdata.presention_storymode) {
                        waittime = 50;
                    }
                    Templates.render('minilessonitem_fiction/fictionyarnoptions', chatdata).then(
                        function (html, js) {
                            setTimeout(() => {
                                that.controls.yarnoptions.html(html);
                                Templates.runTemplateJS(js);
                            }, waittime);
                        }
                    );
                }


                if ('text' in yarncontent.yarnoptions) {
                    that.chatdata.picturesrc = yarncontent.yarnoptions.md?.character?.picturesrc;
                    that.chatdata.charactername = yarncontent.yarnoptions.md?.character?.name;
                    that.chatdata.charactertext = yarncontent.yarnoptions.text;
                    this.set_translate_data(this.chatdata, yarncontent.yarnoptions.text);
                    this.post_message_to_story(this.chatdata, currentResult, false);
                } else {
                    that.controls.yarntext.html('');
                    if (that.isImmersive) {
                        // No text — render options immediately.
                        that.im_renderPendingOptions();
                    }
                }
            } else if (currentResult instanceof YarnBound.CommandResult) {
                // Process the command string a little. so we have command name and args
                // eg "picture 1.png"
                var rawCommand = currentResult.command;
                var parts = rawCommand.split(' ');
                var commandName = parts[0]; // "picture" "audio etc"
                var args = parts.slice(1); // ["1.png"]
                let promise = null;
                var cancel_runner_advance = false;

                switch (commandName) {
                    case 'picture': {
                        log.debug('got picture command');
                        const imageURL = args[0];
                        // Check imageURL is in filenamesmap
                        var theimage = that.filenamesmap.find(function (file) {
                            return file.fileurl === imageURL;
                        });
                        if (theimage) {
                            that.chatdata.charactermedia_url = imageURL;
                            that.chatdata.charactermedia_type = 'picture';
                            promise = Templates.render('minilessonitem_fiction/fictionyarnimage', {
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
                            that.chatdata.charactermedia_url = audioURL;
                            that.chatdata.charactermedia_type = 'audio';
                            promise = Templates.render('minilessonitem_fiction/fictionyarnaudio', {
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
                            that.chatdata.charactermedia_url = videoURL;
                            that.chatdata.charactermedia_type = 'video';
                            promise = Templates.render('minilessonitem_fiction/fictionyarnvideo', {
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
                        that.chatdata.charactermedia_url = '';
                        that.chatdata.charactermedia_type = '';
                        if (that.isImmersive) {
                            that.im_updateMedia('', '');
                        }
                        break;
                    }
                    case 'translate': {
                        log.debug('got translate command');
                        const text = args.join(' ');
                        cancel_runner_advance = true;

                        const sourceLang = that.sourceLang;
                        const destLang = that.destLang;

                        let progressPromise = null;
                        const messageCallback = function (message) {
                            // If this is a progress update we try to find the existing div
                            // This is because we will get 100s of messages and do not want hundreds
                            // of divs in the story area
                            if (message.indexOf('translation-download-progress') !== -1) {
                                if (progressPromise) {
                                    progressPromise.then(() => {
                                        var progressDiv = that.controls.chatwrapper.find('.translation-download-progress').last();
                                        if (progressDiv.length > 0) {
                                            progressDiv.html(message);
                                        }
                                    });
                                    return;
                                }
                                progressPromise = that.post_message_to_story({
                                    charactermedia: message
                                }, currentResult, false);
                                return;
                            }

                            // For regular messages (translations), ensure they are posted after any progress
                            let p = progressPromise || Promise.resolve();
                            p.then(() => {
                                that.post_message_to_story({
                                    charactermedia: message
                                }, currentResult, true);
                                progressPromise = null;
                            });
                        };

                        this.call_translate(sourceLang, destLang, text, messageCallback);
                        break;
                    }
                    case 'blahblah':
                    default:
                }
                // In all cases just do command and then jump to next line
                if (!currentResult.isDialogueEnd && !cancel_runner_advance) {
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

        post_message_to_story: function (messagedata, currentResult, showContinue = true) {
            var that = this;
            if (that.isImmersive) {
                return that.im_post_message(messagedata, currentResult, showContinue);
            }
            if (that.presentationmode === 'storymode') {
                return Templates.render('minilessonitem_fiction/fiction_storymessage', {
                    charactermedia: '<div class="chat-loader"></div>'
                }).then(function (html, js) {
                    Templates.appendNodeContents(that.controls.chatwrapper, html, js);
                    that.scrolltobottom();
                    return Templates.render('minilessonitem_fiction/fiction_storymessage', messagedata).then(
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
                            if (showContinue && currentResult && !currentResult.isDialogueEnd) {
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
                return Templates.render('minilessonitem_fiction/fiction_charactermessage', {
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
                    return new Promise((resolve) => {
                        setTimeout(() => {
                            Templates.render('minilessonitem_fiction/fiction_charactermessage', messagedata).then(
                                function (html, js) {
                                    Templates.replaceNode(
                                        that.controls.chatwrapper.find('> .chat-window').last(),
                                        html,
                                        js
                                    );
                                    that.scrolltobottom();
                                    that.reset_chat_data();
                                    if (showContinue && currentResult && !currentResult.isDialogueEnd) {
                                        if (that.flowthroughmode) {
                                            that.do_runner_advance();
                                            that.do_render();
                                        } else {
                                            that.can_continuebutton(true);
                                        }

                                    }
                                    resolve();
                                }
                            );
                        }, waittime);
                    });
                });
            }
        },

        call_translate: function (sourceLang, destLang, text, messageCallback) {
            var that = this;
            // Check if we already have a session
            if (translate.session && translate.sourceLang === sourceLang && translate.destLang === destLang) {
                log.debug('Using existing translation session');

                translate.translate(text).then((translation) => {
                    if (translation) {
                        messageCallback(translation);
                    } else {
                        log.debug('translation failed');
                    }
                }).catch((e) => {
                    log.error("Translation error: " + e);
                });
                return;
            }

            // Check availability first
            translate.check_availability(sourceLang, destLang).then((status) => {
                log.debug('Translation availability: ' + status);

                if (status === 'unavailable') {
                    messageCallback("translation not available for this language pair: " + sourceLang + " -> " + destLang);
                    log.debug('Translation not available for this language pair');
                    return;
                }

                if (status === 'download_needed') {
                    // Show popup to ask user to download model
                    notification.confirm(
                        that.strings.downloadtranslationmodel,
                        that.strings.downloadtranslationmodel_desc,
                        that.strings.download,
                        that.strings.skip,
                        function () {
                            log.debug('User clicked "Download" creating session');
                            // User clicked "Download" - this provides the required gesture

                            // Show initial progress
                            var progressMessage = that.strings.downloadingtranslator.replace('{$a}', '0');
                            messageCallback('<div class="translation-download-progress">' + progressMessage + '</div>');

                            translate.create_session(sourceLang, destLang, function (percent) {
                                var updatedMessage = that.strings.downloadingtranslator.replace('{$a}', percent);
                                messageCallback('<div class="translation-download-progress">' + updatedMessage + '</div>');
                            }).then((success) => {
                                if (success) {
                                    return translate.translate(text);
                                } else {
                                    log.error('Failed to create translation session');
                                    return null;
                                }
                            }).then((translation) => {
                                if (translation) {
                                    messageCallback(translation);
                                }
                            }).catch((e) => {
                                log.error("Translation error: " + e);
                            });
                        },
                        function () {
                            // User clicked "Skip" - fall back to remote translation if we can.
                            log.debug('User skipped translation model download');
                            if (translate.force_remote()) {
                                translate.create_session(sourceLang, destLang).then(() => {
                                    return translate.translate(text);
                                }).then((translation) => {
                                    if (translation) {
                                        messageCallback(translation);
                                    } else {
                                        log.debug('translation failed');
                                    }
                                }).catch((e) => {
                                    log.error("Translation error: " + e);
                                });
                            } else {
                                messageCallback("translation model not downloaded");
                            }
                        }
                    );
                } else if (status === 'ready') {
                    // Model already available, create session and translate
                    translate.create_session(sourceLang, destLang).then(() => {
                        return translate.translate(text);
                    }).then((translation) => {
                        if (translation) {
                            messageCallback(translation);
                        } else {
                            log.debug('translation failed');
                        }
                    }).catch((e) => {
                        log.error("Translation error: " + e);
                    });
                }
            });
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
                var errStr = "Yarn Parse Error: ";
                if (this.runner && this.runner.currentResult && this.runner.currentResult.metadata) {
                    if (this.runner.currentResult.metadata.title) {
                        errStr += "(Node: " + this.runner.currentResult.metadata.title + ") ";
                    }
                }
                // Format the error nicely
                let errorMessage = e.message;
                if (typeof errorMessage === 'undefined') {
                    // If err is not an Error object (e.g. a string), use err directly
                    errorMessage = e ? String(e) : 'syntax or other error';
                }
                errStr += errorMessage;
                this.controls.yarncontainer.html(
                    '<div class="alert alert-danger">' + errStr + '</div>'
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
         */
        register_events: function (index, itemdata) {
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
                if (self.isImmersive) {
                    self.im_playButtonClick();
                }
                self.do_runner_advance();
                self.do_render();
            });

            if (this.isImmersive) {
                this.im_register_events();
            }

            // Add an event listener for option buttons that handles option buttons added at runtime
            this.controls.yarncontainer.on('click', '.minilesson_fiction_optionbutton', function (e) {
                log.debug('MiniLesson Fiction: yarn option button clicked');
                e.preventDefault();

                var optionindex = $(this).data('optionindex');
                const optiontext = $(this).data('optiontext');
                const playertext = optiontext ? optiontext.toString().trim() : $(this).text().trim();
                if (self.isImmersive) {
                    self.im_playButtonClick();
                    self.im_pushChoiceToHistory(playertext);
                    self.do_runner_advance(optionindex);
                    self.do_render();
                    return;
                }
                if (self.presentationmode === 'storymode') {
                    Templates.render('minilessonitem_fiction/fiction_storyplayermessage', {
                        playertext: playertext
                    }).then(
                        function (html, js) {
                            Templates.appendNodeContents(self.controls.chatwrapper, html, js);
                            self.do_runner_advance(optionindex);
                            self.do_render();
                        }
                    );
                } else {
                    Templates.render('minilessonitem_fiction/fiction_playermessage', {
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

            // Tap-to-translate: delegate clicks on the per-node translate icons.
            this.controls.chatwrapper.on('click', '.fiction-translate-icon', function (e) {
                e.preventDefault();
                e.stopPropagation();
                self.tap_translate($(this));
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

            // Add keyboard navigation
            // Ensure we use a namespaced event to not conflict with other items
            $(document).on('keydown.minilesson_fiction_' + itemdata.uniqueid, function (e) {
                // Check if this item is currently visible (to handle multiple items on a page)
                if (!$("#" + itemdata.uniqueid + "_container").is(':visible')) {
                    return;
                }

                // If a modifier key is pressed, ignore it
                if (e.ctrlKey || e.altKey || e.metaKey || e.shiftKey) {
                    return;
                }

                // Handle Number keys 1-9 (keyCodes 49-57 for main keyboard, 97-105 for numpad)
                let num = null;
                if (e.keyCode >= 49 && e.keyCode <= 57) {
                    num = e.keyCode - 48;
                } else if (e.keyCode >= 97 && e.keyCode <= 105) {
                    num = e.keyCode - 96;
                }

                if (num !== null) {
                    let targetOptionIndex = num - 1;
                    let selector = '.minilesson_fiction_optionbutton[data-optionindex="' + targetOptionIndex + '"]';
                    let btn = self.controls.yarncontainer.find(selector);
                    if (btn.length > 0) {
                        e.preventDefault();
                        btn.click();
                    }
                    return;
                }

                // Handle Enter key for the 'continue' button
                if (e.keyCode === 13) {
                    if (self.controls.yarncontinuebutton.is(':visible') && !self.controls.yarncontinuebutton.prop('disabled')) {
                        e.preventDefault();
                        self.controls.yarncontinuebutton.click();
                    }
                }
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
                ModalCancel.create({
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
                taptotranslate: false,
                taptotranslatearia: null,
                translatesource: null,
            };
        },

        /**
         * Decorate message data with the tap-to-translate icon, if the setting is
         * on and on-device translation is available for this language pair.
         *
         * @param {object} messagedata The message data passed to the message template.
         * @param {string} text The displayed text node content (may contain HTML).
         */
        set_translate_data: function (messagedata, text) {
            if (!this.taptotranslate || !this.storydata.get('cantranslate') || !text) {
                messagedata.taptotranslate = false;
                return;
            }
            // Translate the plain text only, stripping any inline HTML (eg inline translate spans).
            var tmp = document.createElement('div');
            tmp.innerHTML = text;
            var plaintext = (tmp.textContent || tmp.innerText || '').trim();
            if (!plaintext) {
                messagedata.taptotranslate = false;
                return;
            }
            messagedata.taptotranslate = true;
            messagedata.taptotranslatearia = this.taptotranslatearia;
            messagedata.translatesource = plaintext;
        },

        /**
         * Handle a tap on a text node's translate icon. Translates the source text and
         * inserts (or toggles off) the translation directly beneath the source node,
         * behaving as though <<translate "...">> had been called for that node.
         *
         * @param {jQuery} iconbtn The clicked translate icon button.
         */
        tap_translate: function (iconbtn) {
            var that = this;
            // The source text node is the icon's nearest text-block wrapper.
            var sourcenode = iconbtn.closest('.story-paragraph, .chat-window');
            if (!sourcenode.length) {
                return;
            }

            // Toggle: if a translation already follows this node, remove it and stop.
            var existing = sourcenode.next('.fiction-translated-text');
            if (existing.length) {
                existing.remove();
                iconbtn.removeClass('is-active');
                return;
            }

            var text = iconbtn.data('translatetext');
            if (!text) {
                return;
            }
            text = text.toString();

            iconbtn.addClass('is-active');

            // Insert a loading placeholder directly beneath the source node.
            Templates.render('minilessonitem_fiction/fiction_translatedmessage', {
                content: '<span class="chat-loader"></span>'
            }).then(function (html, js) {
                var inserted = $(html);
                sourcenode.after(inserted);
                Templates.runTemplateJS(js);

                var messageCallback = function (message) {
                    inserted.find('.fiction-translated-text-content').html(message);
                };
                that.call_translate(that.sourceLang, that.destLang, text, messageCallback);
                return true;
            }).catch(function (e) {
                log.debug(e);
                return false;
            });
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
         * @param {string} resultscontainerid - The id of thecontainer element to display results in.
         */
        syntaxcheck: function (yarnContent, resultscontainerid) {
            const results = {
                valid: true,
                errors: []
            };
            var storydata = new Map();
            storydata.set('userfirstname', 'bob');
            storydata.set('userlastname', 'smith');
            storydata.set('userfullname', 'bob smith');
            storydata.set('cantranslate', false);
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
            let yarnBoundObj = null;
            // Step 1: Initialize YarnBound
            // This catches top-level errors (bad headers, duplicate titles, invalid declarations)
            try {
                yarnBoundObj = new YarnBound(yarnopts);
            } catch (err) {
                log.debug('Yarn initialization error: ' + err.message);
                results.valid = false;
                results.errors.push(`Initialization Error: ${err.message}`);
            }
            // Step 2 & 3: Iterate through nodes and check syntax
            // We access the internal 'runner' to get the nodes list
            if (results.valid && yarnBoundObj) {
                let ybRunner = yarnBoundObj.runner;
                const nodeNames = Object.keys(ybRunner.yarnNodes);
                nodeNames.forEach(nodeName => {
                    try {
                        // GetParserNodes parses the body text of the node.
                        // It will throw if it encounters invalid Yarn syntax (e.g. invalid <<command>> or <<if>>)
                        ybRunner.getParserNodes(nodeName);
                        // Jump will also check variable state , but that is not syntax but we could do it
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
            Templates.render('minilessonitem_fiction/fiction_syntaxcheckresults', results)
                .then(function (html) {
                    $('#' + resultscontainerid).html(html);
                    return true;
                }).catch(function () {
                    return false;
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

        // ======================================================================
        // Immersive Dark presentation mode
        // ======================================================================

        im_init_state: function () {
            this.im_audioCtx = null;
            this.im_keyBuffers = null;
            this.im_buttonBuffer = null;
            this.im_isMuted = false;
            this.im_typingTimer = null;
            this.im_finishTyping = null;
            this.im_soundLoopTimer = null;
            this.im_translateSource = '';
            this.im_translationResult = '';
            this.im_currentHistoryEntry = null;
            this.im_pendingInlineTranslate = null;
            this.im_currentMediaUrl = '';
            this.im_historylog = [];
            this.im_pendingOptions = null;
            this.im_soundsLoaded = false;
            this.im_fontsLoaded = false;
        },

        im_loadFonts: function () {
            var self = this;
            if (self.im_fontsLoaded || !('FontFace' in window)) {
                return;
            }
            self.im_fontsLoaded = true;
            var $c = self.controls.yarncontainer;
            var family = $c.data('font-family') || 'Jost';
            var regular = $c.data('font-regular');
            var italic = $c.data('font-italic');
            if (regular) {
                var f1 = new FontFace(family, 'url("' + regular + '")', {
                    style: 'normal',
                    weight: '100 900',
                    display: 'swap'
                });
                f1.load().then(function (f) {
                    document.fonts.add(f);
                }).catch(function () {});
            }
            if (italic) {
                var f2 = new FontFace(family, 'url("' + italic + '")', {
                    style: 'italic',
                    weight: '100 900',
                    display: 'swap'
                });
                f2.load().then(function (f) {
                    document.fonts.add(f);
                }).catch(function () {});
            }
            var titleFamily = $c.data('title-font-family');
            var titleRegular = $c.data('title-font-regular');
            if (titleFamily && titleRegular) {
                var f3 = new FontFace(titleFamily, 'url("' + titleRegular + '")', {
                    style: 'normal',
                    weight: '100 900',
                    display: 'swap'
                });
                f3.load().then(function (f) {
                    document.fonts.add(f);
                }).catch(function () {});
            }
        },

        im_register_events: function () {
            var self = this;
            var $c = this.controls.yarncontainer;

            self.im_loadFonts();

            $c.on('click', '.card__start', async function () {
                $(this).addClass('is-hidden');
                await self.im_setupAudio();
                self.im_playButtonClick();
                self.do_render();
            });

            $c.on('click', '.card__mute', async function (e) {
                e.stopPropagation();
                await self.im_setupAudio();
                self.im_isMuted = !self.im_isMuted;
                $(this).toggleClass('is-muted', self.im_isMuted);
            });

            $c.on('click', '.card__translate', function (e) {
                e.stopPropagation();
                self.im_playButtonClick();
                if (!self.im_translateSource) {
                    return;
                }
                var $panel = self.controls.im_translation;
                var $btn = $(this);
                if ($panel.hasClass('is-visible')) {
                    $panel.removeClass('is-visible');
                    $btn.removeClass('is-active');
                    return;
                }
                $panel.addClass('is-visible');
                $btn.addClass('is-active');
                if (self.im_translationResult) {
                    $panel.text(self.im_translationResult);
                    return;
                }
                // First open for this beat: fetch the translation.
                $panel.text(self.strings.translating);
                var source = self.im_translateSource;
                var historyentry = self.im_currentHistoryEntry;
                self.call_translate(self.sourceLang, self.destLang, source, function (message) {
                    // Ignore late results if the story has moved on to another beat.
                    if (self.im_translateSource !== source) {
                        return;
                    }
                    // Model download progress updates arrive as HTML snippets.
                    if (message.toString().indexOf('translation-download-progress') !== -1) {
                        $panel.html(message);
                        return;
                    }
                    self.im_translationResult = message;
                    $panel.text(message);
                    if (historyentry) {
                        historyentry.translation = message;
                    }
                });
            });

            $c.on('click', '.card__text', function () {
                if (self.im_finishTyping) {
                    self.im_finishTyping();
                }
            });

            $c.on('click', '.card__history', function (e) {
                e.stopPropagation();
                self.im_playButtonClick();
                self.im_showHistory();
                $('body').addClass('minilesson-fiction-im-noscroll');
            });

            $c.on('click', '.card__history-close', function (e) {
                e.stopPropagation();
                self.im_playButtonClick();
                self.controls.im_historyOverlay.attr('hidden', true);
                $('body').removeClass('minilesson-fiction-im-noscroll');
            });
        },

        im_setupAudio: async function () {
            if (this.im_audioCtx) {
                if (this.im_audioCtx.state === 'suspended') {
                    await this.im_audioCtx.resume();
                }
                return;
            }
            var Ctor = window.AudioContext || window.webkitAudioContext;
            if (!Ctor) {
                return;
            }
            this.im_audioCtx = new Ctor();
            if (this.im_audioCtx.state === 'suspended') {
                await this.im_audioCtx.resume();
            }
            await this.im_loadSounds();
        },

        im_loadSounds: async function () {
            if (this.im_soundsLoaded) {
                return;
            }
            var ctx = this.im_audioCtx;
            var $c = this.controls.yarncontainer;
            var soft = $c.data('sound-key-soft');
            var hard = $c.data('sound-key-hard');
            var btn = $c.data('sound-button');
            try {
                var keyUrls = [soft, hard].filter(Boolean);
                var keyBufs = await Promise.all(
                    keyUrls.map((u) => fetch(u).then((r) => r.arrayBuffer()))
                );
                this.im_keyBuffers = await Promise.all(
                    keyBufs.map((ab) => ctx.decodeAudioData(ab.slice(0)))
                );
                if (btn) {
                    var btnBuf = await fetch(btn).then((r) => r.arrayBuffer());
                    this.im_buttonBuffer = await ctx.decodeAudioData(btnBuf.slice(0));
                }
                this.im_soundsLoaded = true;
            } catch (e) {
                log.debug('MiniLesson Fiction: sound load failed');
                log.debug(e);
            }
        },

        im_playKeyClick: function () {
            if (this.im_isMuted) {
                return;
            }
            var ctx = this.im_audioCtx;
            if (!ctx || ctx.state !== 'running' || !this.im_keyBuffers) {
                return;
            }
            var buf = this.im_keyBuffers[Math.floor(Math.random() * this.im_keyBuffers.length)];
            var src = ctx.createBufferSource();
            src.buffer = buf;
            src.playbackRate.value = 0.94 + Math.random() * 0.12;
            var gain = ctx.createGain();
            gain.gain.value = 0.18 + Math.random() * 0.12;
            src.connect(gain);
            gain.connect(ctx.destination);
            src.start(0);
        },

        im_playButtonClick: function () {
            if (this.im_isMuted) {
                return;
            }
            var ctx = this.im_audioCtx;
            if (!ctx || ctx.state !== 'running' || !this.im_buttonBuffer) {
                return;
            }
            var src = ctx.createBufferSource();
            src.buffer = this.im_buttonBuffer;
            src.playbackRate.value = 0.97 + Math.random() * 0.06;
            var gain = ctx.createGain();
            gain.gain.value = 0.4;
            src.connect(gain);
            gain.connect(ctx.destination);
            src.start(0);
        },

        im_startKeyboardSound: function () {
            var self = this;
            this.im_stopKeyboardSound();
            var tick = function () {
                self.im_playKeyClick();
                var r = Math.random();
                var next;
                if (r < 0.12) {
                    // Occasional thinking pause (12%).
                    next = 260 + Math.random() * 280;
                } else if (r < 0.35) {
                    // Fast burst pair (23%).
                    next = 55 + Math.random() * 45;
                } else {
                    // Regular typing with wide jitter (65%).
                    next = 110 + Math.random() * 130;
                }
                self.im_soundLoopTimer = setTimeout(tick, next);
            };
            tick();
        },

        im_stopKeyboardSound: function () {
            if (this.im_soundLoopTimer) {
                clearTimeout(this.im_soundLoopTimer);
                this.im_soundLoopTimer = null;
            }
        },

        im_clearTyping: function () {
            if (this.im_typingTimer) {
                clearTimeout(this.im_typingTimer);
                this.im_typingTimer = null;
            }
            this.im_stopKeyboardSound();
            this.im_finishTyping = null;
            this.controls.im_text.removeClass('is-typing');
        },

        im_typeText: function (text, onDone) {
            var self = this;
            var TYPING_SPEED_MS = 12;
            var TYPING_JITTER = 0.45;
            var PUNCT_PAUSE_MS = 160;
            var SPACE_PAUSE_MS = 30;

            self.im_clearTyping();
            var $t = self.controls.im_text;
            $t.text('');
            $t.addClass('is-typing');
            self.im_startKeyboardSound();

            var i = 0;
            var reveal = function () {
                var ch = text.charAt(i);
                $t.text(text.slice(0, ++i));

                if (i < text.length) {
                    var delay = TYPING_SPEED_MS * (1 + (Math.random() - 0.5) * TYPING_JITTER);
                    if ('.,!?;:'.indexOf(ch) !== -1) {
                        delay += PUNCT_PAUSE_MS;
                    } else if (ch === ' ') {
                        delay += SPACE_PAUSE_MS;
                    }
                    self.im_typingTimer = setTimeout(reveal, delay);
                } else {
                    self.im_typingTimer = null;
                    self.im_finishTyping = null;
                    self.im_stopKeyboardSound();
                    $t.removeClass('is-typing');
                    if (onDone) {
                        onDone();
                    }
                }
            };

            self.im_finishTyping = function () {
                if (self.im_typingTimer) {
                    clearTimeout(self.im_typingTimer);
                }
                self.im_typingTimer = null;
                self.im_finishTyping = null;
                self.im_stopKeyboardSound();
                $t.text(text);
                $t.removeClass('is-typing');
                if (onDone) {
                    onDone();
                }
            };

            reveal();
        },

        im_updateMedia: function (url, type) {
            var $m = this.controls.im_media;
            if (!$m || !$m.length) {
                return;
            }
            if (this.im_currentMediaUrl === url) {
                return;
            }
            this.im_currentMediaUrl = url;
            $m.find('video, audio').remove();
            if (!url) {
                $m.css('background-image', '');
                return;
            }
            if (type === 'video') {
                $m.css('background-image', '');
                $m.append(
                    $('<video>').attr({
                        src: url,
                        autoplay: true,
                        controls: true,
                        playsinline: true
                    })
                );
            } else if (type === 'audio') {
                $m.append(
                    $('<audio>').attr({
                        src: url,
                        autoplay: true,
                        controls: true
                    })
                );
            } else {
                $m.css('background-image', 'url("' + url + '")');
            }
        },

        im_post_message: function (messagedata, currentResult, showContinue) {
            var self = this;
            // Cross-fade media if URL provided by preceding <<picture/audio/video>> command.
            if (messagedata.charactermedia_url) {
                self.im_updateMedia(messagedata.charactermedia_url, messagedata.charactermedia_type);
            }
            // Prefer charactertext but fall back to charactermedia (used by <<translate>>
            // command handler which delivers its output via charactermedia).
            var text = (messagedata.charactertext || messagedata.charactermedia || '').toString();
            // Strip any HTML that yarn may have injected (eg inline translate spans).
            var tmp = document.createElement('div');
            tmp.innerHTML = text;
            var plaintext = (tmp.textContent || tmp.innerText || '').trim();

            // Prefix the speaking character's name, as the other presentation modes do.
            // Yarn character labels cannot contain spaces, so underscores stand in for them.
            if (messagedata.charactername && plaintext) {
                plaintext = messagedata.charactername.replace(/_/g, ' ') + ': ' + plaintext;
            }

            // Seed translation panel data. Keep the translate button hidden
            // until the typewriter has finished revealing the text.
            self.im_translateSource = messagedata.translatesource || '';
            // An inline {translate("...")} in this line delivers its text through
            // the translation panel, even when tap-to-translate is off.
            if (self.im_pendingInlineTranslate) {
                self.im_translateSource = self.im_pendingInlineTranslate;
                self.im_pendingInlineTranslate = null;
            }
            self.im_translationResult = '';
            self.im_currentHistoryEntry = null;
            self.controls.im_translation.text('').removeClass('is-visible');
            self.controls.im_translate.removeClass('is-active').removeClass('is-ready');

            // Clear old actions while typing.
            self.controls.yarnoptions.html('');
            self.can_continuebutton(false);

            return new Promise(function (resolve) {
                self.im_typeText(plaintext, function () {
                    // Reveal translate button once the text has finished appearing.
                    if (self.im_translateSource) {
                        self.controls.im_translate.addClass('is-ready');
                    }
                    // Push beat to history log. The translation field is filled in
                    // later if the user requests a translation of this beat.
                    var historyentry = {
                        type: 'beat',
                        text: plaintext,
                        translation: '',
                        mediaUrl: self.im_currentMediaUrl,
                        mediaType: messagedata.charactermedia_type || ''
                    };
                    self.im_historylog.push(historyentry);
                    self.im_currentHistoryEntry = historyentry;
                    self.reset_chat_data();

                    // Render pending options (if this was an OptionsResult with text).
                    if (self.im_pendingOptions) {
                        self.im_renderPendingOptions().then(resolve);
                        return;
                    }

                    if (showContinue && currentResult && !currentResult.isDialogueEnd) {
                        if (self.flowthroughmode) {
                            self.do_runner_advance();
                            self.do_render();
                        } else {
                            self.can_continuebutton(true);
                        }
                    }
                    resolve();
                });
            });
        },

        im_renderPendingOptions: function () {
            var self = this;
            if (!self.im_pendingOptions) {
                return Promise.resolve();
            }
            var chatdata = self.im_pendingOptions;
            self.im_pendingOptions = null;
            return Templates.render('minilessonitem_fiction/fictionyarnoptions', chatdata).then(
                function (html, js) {
                    self.controls.yarnoptions.html(html);
                    Templates.runTemplateJS(js);
                }
            );
        },

        im_pushChoiceToHistory: function (playertext) {
            this.im_historylog.push({
                type: 'choice',
                text: playertext
            });
        },

        im_escape: function (s) {
            return $('<div>').text(s === null || s === undefined ? '' : s.toString()).html();
        },

        im_showHistory: function () {
            var self = this;
            var lastShownMediaUrl = null;
            var html = self.im_historylog.map(function (entry) {
                if (entry.type === 'beat') {
                    var isImage = entry.mediaUrl
                        && entry.mediaType !== 'audio'
                        && entry.mediaType !== 'video';
                    var mediaHTML = '';
                    if (isImage && entry.mediaUrl !== lastShownMediaUrl) {
                        mediaHTML = '<div class="history-log__media" style="background-image:url(\''
                            + entry.mediaUrl + '\')"></div>';
                        lastShownMediaUrl = entry.mediaUrl;
                    }
                    var translationHTML = entry.translation
                        ? '<p class="history-log__translation">' + self.im_escape(entry.translation) + '</p>'
                        : '';
                    return '<div class="history-log__beat">' + mediaHTML
                        + '<p class="history-log__text">' + self.im_escape(entry.text) + '</p>'
                        + translationHTML + '</div>';
                }
                return '<div class="history-log__choice">&rsaquo; ' + self.im_escape(entry.text) + '</div>';
            }).join('');
            self.controls.im_historyLog.html(html || '<p class="history-log__empty">&mdash;</p>');
            self.controls.im_historyOverlay.removeAttr('hidden');
        }
    }; // End module
});