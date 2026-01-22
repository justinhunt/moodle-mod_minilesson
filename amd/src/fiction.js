define([
    'jquery', 'core/log', 'mod_minilesson/definitions','mod_minilesson/yarn-bound',
    'core/str', 'core/modal_factory', 'core/fragment','core/templates'
],
    function ($, log, def, YarnBound, Str, ModalFactory, Fragment, Templates) {
        "use strict"; // jshint ;_;

        /*
       This file is to manage the fiction item type
        */

        log.debug('MiniLesson Fiction: initialising');

        return {

            runner: null,
            controls: {},
            itemdata: {},
            mobilechatdata: {},

            //for making multiple instances
            clone: function () {
                return $.extend(true, {}, this);
            },

            init: function (index, itemdata, quizhelper) {
                this.itemdata = itemdata;
                this.prepare_html(itemdata);
                this.register_events(index, itemdata, quizhelper);
                var yarnopts = {
                    "dialogue": itemdata.fictionyarn,
                    "combineTextAndOptionsResults": true,
                    "startAt": "Start"
                };
                log.debug('MiniLesson Fiction: initializing yarnbound with options');
                log.debug(yarnopts);
                try{
                    this.runner = new YarnBound(yarnopts);
                    this.do_render();
                } catch (e) {
                    var userFriendlyError = "Yarn Parse Error: " + e.message;
                    this.controls.yarncontainer.html(
                        `<div class="alert alert-danger">${userFriendlyError}</div>`
                    );
                    log.error("Full Yarn Error:");
                    log.error(e);
                }
            },

            prepare_html: function (itemdata) {
                this.controls.yarncontainer = $("#" + itemdata.uniqueid + "_container .minilesson_fiction_yarncontainer");
                this.controls.yarntext = this.controls.yarncontainer.find('.minilesson_fiction_yarntext');
                this.controls.yarnmedia = this.controls.yarncontainer.find('.minilesson_fiction_yarnmedia');
                this.controls.yarnoptions = this.controls.yarncontainer.find('.minilesson_fiction_yarnoptions');
                this.controls.yarncontinuebutton = $("#" + itemdata.uniqueid + "_container .minilesson_fiction_continuebutton");
                this.controls.chatwrapper = $("#" + itemdata.uniqueid + "_container .mobilechat .chat-wrapper");
            },

            do_render: function () {
                var that = this;
                var yarncontent = {
                    'yarntext': false,
                    'yarnoptions': false
                };

                var currentResult = this.runner.currentResult;
                currentResult = this.add_metadata(currentResult);
                log.debug('MiniLesson Fiction: doing render of currentResult');
                log.debug(currentResult);

                if(currentResult instanceof YarnBound.TextResult) {
                    // Render the text
                    yarncontent.yarntext = currentResult;

                    if (that.itemdata.presention_mobilechat) {
                        that.can_continuebutton(false);
                        that.mobilechatdata.picturesrc = yarncontent.yarntext.md?.character?.picturesrc;
                        that.mobilechatdata.charactername = yarncontent.yarntext.md?.character?.name;
                        that.mobilechatdata.charactertext = yarncontent.yarntext.text;
                        Templates.render('mod_minilesson/fiction_charactermessage', {
                            charactermedia: '<div class="chat-loader"></div>'
                        }).then(function(html,js) {
                            Templates.appendNodeContents(that.controls.chatwrapper, html, js);
                            that.scrolltobottom();
                            setTimeout(() => {
                                Templates.render('mod_minilesson/fiction_charactermessage', that.mobilechatdata).then(
                                    function(html,js) {
                                        Templates.replaceNode(
                                            that.controls.chatwrapper.find('> .chat-window').last(),
                                            html,
                                            js
                                        );
                                        that.scrolltobottom();
                                        const oldcharactermedia = that.mobilechatdata.charactermedia;
                                        that.reset_mobilechat_data();
                                        that.mobilechatdata.charactermedia = oldcharactermedia;
                                        // Enable the continue button
                                        if (!currentResult.isDialogueEnd) {
                                            that.can_continuebutton(true);
                                        }
                                    }
                                );
                            }, 2000);
                        });
                        that.controls.yarnoptions.html('');
                    }else {
                        Templates.render('mod_minilesson/fictionyarntext', yarncontent.yarntext).then(
                        function (html, js) {
                            that.controls.yarntext.html(html);
                            that.controls.yarnoptions.html('');
                            Templates.runTemplateJS(js);
                        });
                        // Enable the continue button
                        this.can_continuebutton(true);
                    }
                } else if(currentResult instanceof YarnBound.OptionsResult) {
                    // Render the options
                    yarncontent.yarnoptions = currentResult;
                    if (that.itemdata.presention_mobilechat) {
                        var mobilechatdata = {
                            'yarnoptions': currentResult,
                            'presention_mobilechat': true,
                        };
                        that.controls.yarnoptions.html('');
                    } else {
                        var mobilechatdata = currentResult;
                    }
                    Templates.render('mod_minilesson/fictionyarnoptions', mobilechatdata).then(
                    function (html, js) {
                        setTimeout(() => {
                            that.controls.yarnoptions.html(html);
                            Templates.runTemplateJS(js);
                        }, that.itemdata.presention_mobilechat ? 2000 : 1);
                    });

                    //If there is some text as well render that, or clear the existing text otherwise
                    if ('text' in yarncontent.yarnoptions) {

                        if (that.itemdata.presention_mobilechat) {
                            that.mobilechatdata.picturesrc = yarncontent.yarnoptions.md?.character?.picturesrc;
                            that.mobilechatdata.charactername = yarncontent.yarnoptions.md?.character?.name;
                            that.mobilechatdata.charactertext = yarncontent.yarnoptions.text;

                            Templates.render('mod_minilesson/fiction_charactermessage', {
                                charactermedia: '<div class="chat-loader"></div>'
                            }).then(function(html,js) {
                                Templates.appendNodeContents(that.controls.chatwrapper, html, js);
                                that.scrolltobottom();
                                setTimeout(() => {
                                    Templates.render('mod_minilesson/fiction_charactermessage', that.mobilechatdata).then(
                                        function(html,js) {
                                            Templates.replaceNode(
                                                that.controls.chatwrapper.find('> .chat-window').last(),
                                                html,
                                                js
                                            );
                                            that.scrolltobottom();
                                            const oldcharactermedia = that.mobilechatdata.charactermedia;
                                            that.reset_mobilechat_data();
                                            that.mobilechatdata.charactermedia = oldcharactermedia;
                                        }
                                    );
                                }, 2000);
                            });
                        } else {
                            Templates.render('mod_minilesson/fictionyarntext', yarncontent.yarnoptions).then(
                            function (html, js) {
                                that.controls.yarntext.html(html);
                                Templates.runTemplateJS(js);
                            });
                        }
                    } else {
                        that.controls.yarntext.html('');
                    }

                    // Disable the continue button, because they need to select an option
                    that.can_continuebutton(false);

                } else if (currentResult instanceof YarnBound.CommandResult) {
                    // Process the command string a little. so we have command name and args
                    //eg "picture 1.png"
                    var rawCommand = currentResult.command;
                    var parts = rawCommand.split(' ');
                    var commandName = parts[0]; // "picture" "audio etc"
                    var args = parts.slice(1); // ["1.png"]
                    let promise = null;


                    switch(commandName) {
                        case 'picture': {
                            log.debug('got picture command');
                            const imageURL = args[0]; // "picture https://blahblah"
                            promise = Templates.render('mod_minilesson/fictionyarnimage', {"imageurl": imageURL}).then(
                            function (html, js) {
                                that.controls.yarnmedia.html(html);
                                if (that.itemdata.presention_mobilechat) {
                                    that.mobilechatdata.charactermedia = html;
                                    that.mobilechatdata.classname = 'hasmedia';
                                }
                                Templates.runTemplateJS(js);
                            });
                            break;
                        }
                        case 'audio': {
                            log.debug('got audio command');
                            const audioURL = args[0]; // "audio https://blahblah"
                            promise = Templates.render('mod_minilesson/fictionyarnaudio', {"audiourl": audioURL}).then(
                            function (html, js) {
                                if (that.itemdata.presention_mobilechat) {
                                    that.mobilechatdata.charactermedia = html;
                                    that.mobilechatdata.classname = 'hasmedia';
                                }
                                that.controls.yarnmedia.html(html);
                                Templates.runTemplateJS(js);
                            });
                            break;
                        }
                        case 'video': {
                            log.debug('got video command');
                            const videoURL = args[0]; // "video https://blahblah"
                            promise = Templates.render('mod_minilesson/fictionyarnvideo', {"videourl": videoURL}).then(
                            function (html, js) {
                                if (that.itemdata.presention_mobilechat) {
                                    that.mobilechatdata.charactermedia = html;
                                    that.mobilechatdata.classname = 'hasmedia';
                                }
                                that.controls.yarnmedia.html(html);
                                Templates.runTemplateJS(js);
                            });
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
                    if(!currentResult.isDialogueEnd) {
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
                        return;
                    }
                } else {
                    log.debug('MiniLesson Fiction: unknown yarn result type');
                }

                 // In all cases on dialog end there is no continue
                if(currentResult.isDialogueEnd) {
                    that.can_continuebutton(false);
                    return;
                }
            },

            do_runner_advance: function (steps) {
                try {
                    if(steps !== null){
                        this.runner.advance(steps);
                    } else {
                        this.runner.advance();
                    }
                } catch (e) {
                    var userFriendlyError = "Yarn Parse Error: " + e.message;
                    this.controls.yarncontainer.html(
                        `<div class="alert alert-danger">${userFriendlyError}</div>`
                    );
                    log.error("Full Yarn Error:");
                    log.error(e);
                }
            },

            can_continuebutton: function (cancontinue) {
                this.controls.yarncontinuebutton.prop("disabled", !cancontinue);
                if (cancontinue){
                    this.controls.yarncontinuebutton.show();
                } else {
                    this.controls.yarncontinuebutton.hide();
                }
            },

            register_events: function (index, itemdata, quizhelper) {
                var self = this;
                //When click next button , report and leave it up to parent to eal with it.
                $("#" + itemdata.uniqueid + "_container .minilesson_nextbutton").on('click', function () {
                    var stepdata = {};
                    stepdata.index = index;
                    stepdata.hasgrade = false;
                    stepdata.totalitems = 0;
                    stepdata.correctitems = 0;
                    stepdata.grade = 0;
                    quizhelper.do_next(stepdata);
                });
                $("#" + itemdata.uniqueid + "_container").on("showElement", async () => {
                    if (!self.instance) {
                        // Maybe init yarn-bound here
                        //this.do_render();
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
                    if (self.itemdata.presention_mobilechat) {
                        const playertext = $(this).siblings('.optiontext').text().trim();
                        Templates.render('mod_minilesson/fiction_playermessage', {playertext: playertext}).then(
                            function (html, js) {
                                Templates.appendNodeContents(self.controls.chatwrapper, html, js);
                            }
                        );
                    }
                    self.do_runner_advance();
                    self.do_render();
                });

                // add an event listener for option buttons that handles option buttons added at runtim
                this.controls.yarncontainer.on('click', '.minilesson_fiction_optionbutton', function (e) {
                    log.debug('MiniLesson Fiction: yarn option button clicked');
                    e.preventDefault();

                    var buttons = self.controls.yarncontainer.find('.minilesson_fiction_optionbutton');
                    var optionindex = buttons.index(this); // 0-based position in the rendered list

                    if (self.itemdata.presention_mobilechat) {
                        const playertext = $(this).siblings('.optiontext').text().trim();
                        Templates.render('mod_minilesson/fiction_playermessage', {playertext: playertext}).then(
                            function (html, js) {
                                Templates.appendNodeContents(self.controls.chatwrapper, html, js);
                            }
                        );
                    }
                    self.do_runner_advance(optionindex);
                    self.do_render();
                });

                if (this.itemdata.presention_mobilechat) {
                    let scrollbtn = $("#" + itemdata.uniqueid + "_container .mobilechat #scroll-bottom-btn");

                    this.controls.chatwrapper.on("scroll", function() {
                        const chatwrapper = self.controls.chatwrapper[0];
                        const isatbottom = chatwrapper.scrollHeight - chatwrapper.scrollTop <= chatwrapper.clientHeight + 5;
                        if (isatbottom) {
                            scrollbtn.hide();
                        } else {
                            scrollbtn.show();
                        }
                    });

                    scrollbtn.on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        self.controls.chatwrapper.animate({
                            scrollTop: self.controls.chatwrapper[0].scrollHeight
                        }, 'smooth');
                    });
                }
            },

            add_metadata: function (currentthing) {
                if (currentthing && 'markup' in currentthing && Array.isArray(currentthing.markup)) {
                    currentthing.md = {};
                    //for each markup entry, add the property name and values object to currentthing
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
                        title: Str.get_string('fiction:previewmodaltitle', 'mod_minilesson'),
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

            reset_mobilechat_data: function() {
                this.mobilechatdata = {
                    charactername: null,
                    charactermedia: null,
                    charactertext: null,
                    picturesrc: null,
                    playertext: null,
                };
            },
            scrolltobottom: function() {
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