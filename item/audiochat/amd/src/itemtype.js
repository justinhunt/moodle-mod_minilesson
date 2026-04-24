// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * audiochat module itemtype (UI layer).
 *
 * This module owns all DOM-touching work: controls wiring, mic waveform,
 * template rendering, and session UI state. Protocol concerns (RTC/WebSocket
 * handshake, event stream) are delegated to a chat driver selected from
 * itemdata.chatprovider.
 *
 * @module     minilessonitem_audiochat/itemtype
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(
    ['jquery', 'core/log', 'mod_minilesson/definitions',
        'mod_minilesson/ttrecorder', 'core/templates', 'core/str', 'core/url'],
    function ($, log, def, ttrecorder, templates, str, Url) {
        "use strict"; // jshint ;_;

        log.debug('MiniLesson AudioChat: initialising');

        return {
            strings: {},
            controls: {},
            itemdata: {},
            index: 0,
            quizhelper: {},

            // UI-owned session state.
            cantChat: false,
            isSessionStarted: false,
            isSessionStopped: false,
            isSessionActive: false,
            isLoading: false,
            isMicActive: false,
            isMicInitialized: false,

            // Mic waveform (UI audio analyser). The driver owns the MediaStream itself.
            audioContext: null,
            analyser: null,
            dataArray: null,
            sourceNode: null,
            animationFrameId: null,
            canvasCtx: null,

            // Protocol driver (OpenAI RTC or Gemini WebSocket).
            driver: null,

            // Mirrored from the driver for rendering.
            orderedItems: [],
            loadingMessages: new Set(),

            // For making multiple instances.
            clone: function () {
                return $.extend(true, {}, this);
            },

            init: function (index, itemdata, quizhelper) {
                var self = this;
                self.itemdata = itemdata;
                log.debug('itemdata', itemdata);
                self.quizhelper = quizhelper;
                self.index = index;
                self.cantChat = !itemdata.canchat;
                self.init_strings();
                self.init_controls(quizhelper, itemdata).then(function () {
                    return self.init_driver();
                }).then(function () {
                    self.register_events(index, itemdata, quizhelper);
                    self.renderUI();
                });
            },

            init_strings: function () {
                var self = this;
                str.get_strings([
                    { "key": "gradebywordcount", "component": "mod_minilesson" },
                ]).done(function (s) {
                    var i = 0;
                    self.strings.gradebywordcount = s[i++];
                });
            },

            /**
             * Dynamically load the chat driver for the configured provider.
             * Defaults to OpenAI when not specified.
             */
            init_driver: function () {
                var self = this;
                var provider = self.itemdata.chatprovider || 'gemini';
                var driverModule = provider === 'gemini'
                    ? 'minilessonitem_audiochat/chatdriver_gemini'
                    : 'minilessonitem_audiochat/chatdriver_openai';

                return new Promise(function (resolve, reject) {
                    require([driverModule], function (driver) {
                        self.driver = driver.clone();
                        self.driver.init({
                            itemdata: self.itemdata,
                            audioElement: self.controls.hiddenaudio,
                            callbacks: self._buildDriverCallbacks(),
                        });
                        resolve();
                    }, reject);
                });
            },

            _buildDriverCallbacks: function () {
                var self = this;
                return {
                    onStateChange: function (state) {
                        self._onDriverStateChange(state);
                    },
                    onItemsChanged: function (orderedItems, loadingMessages) {
                        self.orderedItems = orderedItems || [];
                        self.loadingMessages = loadingMessages || new Set();
                        self.renderUI();
                    },
                    onGradingData: function (/*data*/) {
                        // Nothing to do immediately; grade_activity/showResults reads
                        // the data from the driver at render time.
                    },
                    onMicAvailabilityChange: function (available) {
                        if (available) {
                            self.enableMic();
                        } else {
                            self.disableMic();
                        }
                    },
                    onUserSpeechStopped: function () {
                        // If the user was mid-turn and autocreate is on, release the mic.
                        if (self.isMicActive && self.driver.autocreateresponse) {
                            self.toggleMute();
                            self.disableMic();
                        }
                    },
                    onOutputAudioStopped: function () {
                        // AI finished speaking: re-open the mic so the student can reply.
                        if (!self.isMicActive) {
                            self.toggleMute();
                        }
                    },
                    onMediaStreamReady: function (stream) {
                        self._attachAnalyserToStream(stream);
                    },
                    onGradingWindowClosed: function () {
                        if (self.quizhelper.showitemreview) {
                            self.showResults();
                        }
                    },
                    onScrollRequested: function () {
                        self.scrollToBottom();
                    },
                    onError: function (err) {
                        log.debug('Chat driver error:', err);
                    },
                };
            },

            _onDriverStateChange: function (state) {
                var self = this;
                log.debug('Driver state:', state);
                switch (state) {
                    case 'connecting':
                        self.isLoading = true;
                        break;
                    case 'connected':
                        self.isLoading = false;
                        self.isSessionActive = true;
                        self.isSessionStarted = true;
                        self.isSessionStopped = false;
                        break;
                    case 'aborted':
                    case 'error':
                        self.isLoading = false;
                        break;
                    case 'stopped':
                        self.isSessionActive = false;
                        self.isSessionStopped = true;
                        break;
                }
                self.renderUI();
            },

            next_question: function () {
                var self = this;
                var stepdata = {};
                stepdata.index = self.index;
                stepdata.hasgrade = true;
                stepdata.lessonitemid = self.itemdata.id;
                stepdata.totalitems = self.itemdata.totalmarks;
                stepdata.resultsdata = { 'items': Object.values(self.driver.getItems()) };
                stepdata = self.grade_activity(stepdata);
                stepdata.correctitems = Math.round((self.itemdata.totalmarks * stepdata.grade) / 100);
                self.quizhelper.do_next(stepdata);
            },

            count_words: function () {
                var self = this;
                var userTranscript = [];
                Object.values(self.driver.getItems()).forEach(function (item) {
                    if (item.content) {
                        userTranscript.push(item.content);
                    }
                });
                return userTranscript.join(' ').split(/\s+/).length;
            },

            toggle_autocreate_response: function () {
                var self = this;
                var newvalue = !self.driver.autocreateresponse;
                self.driver.setAutoCreateResponse(newvalue);
                log.debug("Autocreate response toggled:", newvalue);
                self.renderUI();
            },

            grade_activity: function (stepdata) {
                var self = this;
                var gradingData = self.driver.getGradingData();
                var wordcount = self.count_words();

                if (gradingData && gradingData.score !== undefined) {
                    log.debug("Using grading data from AI:", gradingData);
                    stepdata.grade = gradingData.score;

                    // Lower the grade if the student fell short of the target word count.
                    if (typeof stepdata.grade === 'number' &&
                        typeof wordcount === 'number' &&
                        self.itemdata.targetwordcount > 0 &&
                        wordcount < self.itemdata.targetwordcount) {
                        stepdata.grade = Math.round(stepdata.grade * (wordcount / self.itemdata.targetwordcount));
                    }

                    stepdata.resultsdata.aifeedback = gradingData.feedback || "";
                    stepdata.resultsdata.gradeexplanation = gradingData.gradeexplanation || "";
                } else {
                    log.debug("No grading data from AI: counting words", gradingData);
                    stepdata.resultsdata.gradeexplanation = self.strings.gradebywordcount;
                    if (self.itemdata.countwords === false || self.itemdata.targetwordcount === 0) {
                        stepdata.grade = 100;
                    } else {
                        // Calculate grade based on word count
                        stepdata.grade = Math.min(wordcount / self.itemdata.targetwordcount, 1) * 100;
                    }
                }

                return stepdata;
            },

            register_events: function (index, itemdata) {
                var self = this;

                self.controls.startSessionBtn.addEventListener("click", self.startSession.bind(self));
                self.controls.stopSessionBtn.addEventListener("click", self.stopSession.bind(self));
                self.controls.retrySessionBtn.addEventListener("click", self.resetSession.bind(self));
                self.controls.autocreateresponseCheckbox.addEventListener(
                    "change",
                    self.toggle_autocreate_response.bind(self)
                );
                self.controls.cancelStartSessionBtn.addEventListener("click", () => {
                    log.debug("Cancelling session start");
                    if (self.driver && typeof self.driver.abort === 'function') {
                        self.driver.abort();
                    }
                });

                $(self.controls.nextbutton).on('click', function () {
                    self.next_question();
                });

                var container = $(self.controls.container);
                container.on('showElement', () => {
                    if (itemdata.timelimit > 0) {
                        container.find(".progress-container").show();
                        container.find(".progress-container i").show();
                        container.find(".progress-container #progresstimer").progressTimer({
                            height: '5px',
                            timeLimit: itemdata.timelimit,
                            onFinish: function () {
                                self.controls.nextbutton.trigger('click');
                            }
                        });
                    }
                });

                if (self.controls.toggleMicBtn) {
                    self.controls.toggleMicBtn.addEventListener("click", self.toggleMute.bind(self));
                }
            },

            init_controls: async function () {
                var self = this;
                var container = document.getElementById(self.itemdata.uniqueid + "_container");
                self.controls = {
                    hiddenaudio: container.querySelector('.ml_ac_hiddenaudio'),
                    nextbutton: container.querySelector('.minilesson_nextbutton'),
                    cantChatWarning: container.querySelector(".ml_ac_cantchat"),
                    startSessionBtn: container.querySelector(".ml_ac_start-session-btn"),
                    stopSessionBtn: container.querySelector(".ml_ac_stop-session-btn"),
                    loadingIndicator: container.querySelector(".ml_ac_loading-indicator"),
                    aiAvatarSection: container.querySelector(".ml_ac_ai-avatar-section"),
                    chatActiveMessage: container.querySelector(".ml_ac_chat-active-message"),
                    conversationSection: container.querySelector(".ml_ac_conversation-section"),
                    messagesContainer: container.querySelector(".ml_ac_messages-container"),
                    micButtonContainer: container.querySelector(".mic-button-container"),
                    toggleMicBtn: container.querySelector(".toggle-mic-btn"),
                    micWaveformCanvas: container.querySelector(".mic-waveform-canvas"),
                    micSelect: container.querySelector('.ml_ac_micselect'),
                    finishMessage: container.querySelector('.ml_ac_finished-message'),
                    retrySessionBtn: container.querySelector('.ml_ac_retrybtn'),
                    cancelStartSessionBtn: container.querySelector('.ml_ac_cancel-start-session-btn'),
                    autocreateresponseCheckbox: container.querySelector('.ml_ac_autoresponse-checkbox'),
                    resultscontainer: container.querySelector('.ml_ac_results_container'),
                    resultscontent: container.querySelector('.ml_ac_results_content'),
                    autocreateresponseToggle: container.querySelector('.ml_ac_autoresponse-toggle'),
                    clicktosendlabel: container.querySelector('.ml_ac_clicktosend'),
                    mainWrapper: container.querySelector('.minilesson_audiochat_box .ml_unique_mainwrapper'),
                    sessionControls: container.querySelector('.ml_unique_sessioncontrols'),
                    itemtextAfterchatactive: container.querySelector('.itemtext_afterchatactive'),
                    micColumn: container.querySelector('.ml_ac_mic-column'),
                };
                self.canvasCtx = !self.controls.micWaveformCanvas ? null :
                    self.controls.micWaveformCanvas.getContext("2d");

                await self.populateMicList();
            },

            scrollToBottom: function () {
                var self = this;
                if (!self.controls.conversationSection || !self.controls.conversationSection.firstElementChild) {
                    return;
                }
                self.controls.conversationSection.firstElementChild.scrollIntoViewIfNeeded();
                self.controls.conversationSection.firstElementChild.scrollTop =
                    self.controls.conversationSection.firstElementChild.scrollHeight;
            },

            scrollMicButtonIntoView: function () {
                var self = this;
                if (self.controls.micButtonContainer) {
                    self.controls.micButtonContainer.scrollIntoView({ behavior: "smooth", block: "center" });
                }
            },

            renderUI: function () {
                var self = this;
                var autocreate = self.driver ? self.driver.autocreateresponse : false;

                // Session Controls.
                self.controls.startSessionBtn.classList.toggle(
                    "hidden",
                    self.isSessionActive || self.isLoading || self.isSessionStarted || self.cantChat
                );
                self.controls.cantChatWarning.classList.toggle("hidden", !self.cantChat);
                self.controls.loadingIndicator.classList.toggle("hidden", !self.isLoading);
                self.controls.stopSessionBtn.classList.toggle("hidden", !self.isSessionActive);
                self.controls.micButtonContainer.classList.toggle("hidden", !self.isSessionActive);
                var endScreen = self.isSessionStarted && self.isSessionStopped;
                self.controls.resultscontainer.classList.toggle(
                    "hidden",
                    !endScreen && self.quizhelper.showitemreview
                );
                self.controls.finishMessage.classList.toggle("hidden", !endScreen);
                self.controls.retrySessionBtn.classList.toggle(
                    "hidden",
                    !endScreen && self.itemdata.allowretry
                );
                // The cancel button was disabled after ICE-gathering changes made it unreliable.
                self.controls.autocreateresponseToggle.classList.toggle("hidden", !self.isSessionActive);
                if (self.controls.micSelect) {
                    var mics = self.controls.micSelect.querySelectorAll('option');
                    var noshowmics = mics.length < 2;
                    self.controls.micSelect.parentElement.classList.toggle(
                        'hidden',
                        noshowmics || self.isSessionStarted || self.isLoading || self.controls.micSelect.disabled
                    );
                }

                // The cute dog avatar.
                self.controls.aiAvatarSection.classList.toggle(
                    "hidden",
                    self.isSessionStarted || self.isSessionActive || self.isSessionStopped
                );
                // The conversation area.
                self.controls.conversationSection.classList.toggle(
                    "hidden",
                    !(self.isSessionActive || self.isSessionStopped)
                );
                self.controls.sessionControls.classList.toggle(
                    'hidden',
                    self.isSessionStarted || self.isSessionActive || self.isSessionStopped
                );
                if (self.controls.itemtextAfterchatactive) {
                    self.controls.itemtextAfterchatactive.classList.toggle(
                        'hidden',
                        !(self.isSessionActive || self.isSessionStopped)
                    );
                }
                self.controls.micColumn.classList.toggle('hidden', !self.isSessionActive);

                // Render messages from the driver-provided ordered list.
                self.controls.messagesContainer.innerHTML = "";
                self.orderedItems.forEach((message) => {
                    if (!message.content) {
                        return;
                    }
                    var messageDiv = document.createElement("div");
                    messageDiv.className = `ml_unique_ordered_message_${message.usertype === "user" ? "user" : "assistant"}`;

                    var contentDiv = document.createElement("div");
                    contentDiv.className = `rounded-lg ${message.usertype === "user" ? "bg-blue-500 text-white" : "bg-gray-200 text-gray-800"
                        } ml_unique_content_${message.usertype === "user" ? "user" : "assistant"
                        }`;

                    var headerDiv = document.createElement("div");
                    headerDiv.className = "mb-1 ml_unique_headerdiv";
                    if (message.usertype === "assistant") {
                        var pictureDiv = document.createElement('div');
                        pictureDiv.innerHTML = `
                            <img src="${self.itemdata.avatarimage}?themerev=${M.cfg.themerev}"
                            alt="AI Assistant" class="mr-2 rounded-circle shadow-lg ml_unique_assistant_img">
                            `;
                        headerDiv.appendChild(pictureDiv);
                    }
                    str.get_strings([
                        { key: 'audiochataiassistant', component: 'mod_minilesson' },
                        { key: 'audiochatstudent', component: 'mod_minilesson' }
                    ]).then(function (strings) {
                        headerDiv.innerHTML += message.usertype === "user" ? strings[1] : strings[0];
                    });
                    contentDiv.appendChild(headerDiv);

                    var textDiv = document.createElement("div");
                    textDiv.className = "ml_unique_textsmall";
                    textDiv.textContent = message.content;
                    contentDiv.appendChild(textDiv);

                    if (self.loadingMessages.has(message.id)) {
                        var loaderDiv = document.createElement("div");
                        loaderDiv.className = "py-1 message-loader ml_unique_loadingmessage";
                        loaderDiv.innerHTML = `
                            <div class="ml_unique_loader">
                                <div class="ml_unique_loader_dot"></div>
                                <div class="ml_unique_loader_dot"></div>
                                <div class="ml_unique_loader_dot"></div>
                            </div>
                            <span class="ml_unique_loader_text">AI is thinking...</span>
                        `;
                        contentDiv.appendChild(loaderDiv);
                    }

                    messageDiv.appendChild(contentDiv);
                    self.controls.messagesContainer.appendChild(messageDiv);
                });

                self.scrollToBottom();

                // Mic button visual state.
                if (self.controls.micButtonContainer) {
                    self.controls.micButtonContainer.classList.toggle("active", self.isMicActive);
                    self.controls.micButtonContainer.classList.toggle("bg-blue-500", self.isMicActive);
                    self.controls.micButtonContainer.classList.toggle("text-white", self.isMicActive);
                    self.controls.micButtonContainer.classList.toggle("bg-gray-200", !self.isMicActive);
                    self.controls.micButtonContainer.classList.toggle("text-gray-800", !self.isMicActive);
                }
                if (self.controls.micWaveformCanvas) {
                    self.controls.micWaveformCanvas.classList.toggle("active", self.isMicActive);
                }
                if (self.controls.toggleMicBtn) {
                    self.controls.toggleMicBtn.innerHTML = self.isMicActive
                        ? `<svg id="mic-icon" class="mic-icon mic-icon-svg ml_unique_micsvg" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
  <rect id="primary" x="2" y="2" width="20" height="20" rx="2" style="fill: rgb(0, 0, 0);"/>
</svg>
`
                        : `<svg width="48" height="48" viewBox="0 0 59 59" fill="none" xmlns="http://www.w3.org/2000/svg">
  <g filter="url(#filter0_d_2299_874)">
    <circle cx="29.2834" cy="27.2834" r="23.2834" fill="#5067FF"/>
  </g>
  <rect x="25.641" y="16.3383" width="7.28658" height="13.0053" rx="3.64329" stroke="white" stroke-width="1.7"/>
  <path d="M37.0438 25.7002C37.0438 29.9866 33.569 33.4613 29.2826 33.4613C24.9963 33.4613 21.5215 29.9866 21.5215 25.7002" stroke="white" stroke-width="1.7" stroke-linecap="round"/>
  <path d="M29.2832 37.138V33.8701" stroke="white" stroke-width="1.7"/>
  <path d="M25.6074 37.5459H32.9601" stroke="white" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
  <defs>
    <filter id="filter0_d_2299_874" x="0" y="0" width="58.5664" height="58.5664" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
      <feFlood flood-opacity="0" result="BackgroundImageFix"/>
      <feColorMatrix in="SourceAlpha" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" result="hardAlpha"/>
      <feOffset dy="2"/>
      <feGaussianBlur stdDeviation="3"/>
      <feComposite in2="hardAlpha" operator="out"/>
      <feColorMatrix type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.22 0"/>
      <feBlend mode="normal" in2="BackgroundImageFix" result="effect1_dropShadow_2299_874"/>
      <feBlend mode="normal" in="SourceGraphic" in2="effect1_dropShadow_2299_874" result="shape"/>
    </filter>
  </defs>
</svg>
`;
                }

                if (self.isMicActive && !autocreate) {
                    self.controls.clicktosendlabel.classList.remove("hidden");
                } else {
                    self.controls.clicktosendlabel.classList.add("hidden");
                }
            },

            resetSession: function () {
                log.debug("reset session");
                var self = this;
                self.isLoading = false;
                self.isSessionActive = false;
                self.isSessionStopped = false;
                self.isSessionStarted = false;
                self.orderedItems = [];
                self.loadingMessages = new Set();
                self.init_driver().then(function () {
                    self.renderUI();
                });
            },

            startSession: async function () {
                var self = this;
                log.debug("Session starting");
                self.isLoading = true;
                self.renderUI();
                await self.driver.start();
            },

            stopSession: function () {
                var self = this;
                log.debug("Session stopping (UI)");
                self.isSessionActive = false;
                self.isSessionStopped = true;
                self.releaseMicResources();
                self.renderUI();

                if (self.itemdata.audiochatgradeinstructions && self.itemdata.audiochatgradeinstructions !== "") {
                    self.controls.resultscontent.innerHTML = `<i class="fa fa-spinner fa-spin fa-2x"></i>`;
                }

                self.driver.stopSession();
            },

            showResults: function () {
                var self = this;
                var tdata = {};
                tdata.resultsdata = { 'items': Object.values(self.driver.getItems()) };
                tdata = self.grade_activity(tdata);

                const stars = [];
                const maxStars = 5;
                if (typeof tdata.grade === 'undefined' || isNaN(tdata.grade) || tdata.grade === null || tdata.grade === "") {
                    tdata.grade = 0;
                }
                const filledStars = Math.round((tdata.grade / 100) * maxStars);
                for (let i = 0; i < maxStars; i++) {
                    stars.push({ filled: i < filledStars });
                }
                tdata.stars = stars;
                tdata.yellow_starImgurl = Url.imageUrl('yellow_star', 'mod_minilesson');
                tdata.gray_starImgurl = Url.imageUrl('gray_star', 'mod_minilesson');

                templates.render('minilessonitem_audiochat/audiochatimmediatefeedback', tdata).then(
                    function (html, js) {
                        self.controls.resultscontent.innerHTML = html;
                        templates.runTemplateJS(js);
                    }
                );
            },

            enableMic: function () {
                var self = this;
                if (self.controls.toggleMicBtn) {
                    log.debug('Enabling mic');
                    self.controls.toggleMicBtn.parentElement.classList.remove('disabled');
                }
            },

            disableMic: function () {
                var self = this;
                if (self.controls.toggleMicBtn) {
                    log.debug('Disabling mic');
                    self.controls.toggleMicBtn.parentElement.classList.add('disabled');
                }
            },

            /**
             * Build the local analyser graph on top of the driver's MediaStream so we can
             * draw a waveform. Reusable for switchMic (reconnects the new stream).
             */
            _attachAnalyserToStream: function (stream) {
                var self = this;
                if (!stream) {
                    return;
                }
                try {
                    if (!self.audioContext) {
                        self.audioContext = new (window.AudioContext || window.webkitAudioContext)();
                        self.analyser = self.audioContext.createAnalyser();
                        self.analyser.fftSize = 2048;
                        self.dataArray = new Uint8Array(self.analyser.frequencyBinCount);
                    }
                    if (self.sourceNode) {
                        try {
                            self.sourceNode.disconnect();
                        } catch (e) { log.debug('analyser disconnect failed', e); }
                    }
                    self.sourceNode = self.audioContext.createMediaStreamSource(stream);
                    self.isMicInitialized = true;
                } catch (err) {
                    log.debug("Error setting up mic analyser:", err);
                    self.isMicInitialized = false;
                }
            },

            initializeMicStream: async function () {
                var self = this;
                if (self.isMicInitialized) {
                    return true;
                }
                var stream = self.driver.getMediaStream();
                if (!stream) {
                    log.debug('No media stream available from driver');
                    return false;
                }
                self._attachAnalyserToStream(stream);
                return self.isMicInitialized;
            },

            toggleMute: async function () {
                var self = this;
                if (!self.isMicInitialized) {
                    const success = await self.initializeMicStream();
                    if (!success) {
                        return;
                    }
                }

                if (self.isMicActive) {
                    if (self.sourceNode && self.analyser) {
                        try {
                            self.sourceNode.disconnect(self.analyser);
                        } catch (e) { log.debug('analyser detach failed', e); }
                    }
                    if (self.animationFrameId) {
                        cancelAnimationFrame(self.animationFrameId);
                        self.animationFrameId = null;
                    }
                    if (self.canvasCtx) {
                        self.canvasCtx.clearRect(
                            0, 0,
                            self.controls.micWaveformCanvas.width,
                            self.controls.micWaveformCanvas.height
                        );
                    }
                    self.isMicActive = false;
                    self.driver.setMicActive(false);
                } else {
                    if (self.sourceNode && self.analyser) {
                        self.sourceNode.connect(self.analyser);
                    }
                    self.driver.setMicActive(true);
                    self.isMicActive = true;
                    self.drawWave();
                }
                self.renderUI();
            },

            releaseMicResources: function () {
                var self = this;
                if (self.animationFrameId) {
                    cancelAnimationFrame(self.animationFrameId);
                    self.animationFrameId = null;
                }
                if (self.sourceNode) {
                    try { self.sourceNode.disconnect(); } catch (e) { log.debug(e); }
                    self.sourceNode = null;
                }
                if (self.audioContext) {
                    try { self.audioContext.close(); } catch (e) { log.debug(e); }
                    self.audioContext = null;
                }
                if (self.driver && typeof self.driver.releaseResources === 'function') {
                    self.driver.releaseResources();
                }
                self.isMicActive = false;
                self.isMicInitialized = false;
                if (self.canvasCtx) {
                    self.canvasCtx.clearRect(
                        0, 0,
                        self.controls.micWaveformCanvas.width,
                        self.controls.micWaveformCanvas.height
                    );
                }
                self.renderUI();
            },

            drawWave: function () {
                var self = this;
                if (!self.canvasCtx || !self.analyser || !self.dataArray || !self.isMicActive) {
                    self.animationFrameId = null;
                    return;
                }

                const WIDTH = self.controls.micWaveformCanvas.width;
                const HEIGHT = self.controls.micWaveformCanvas.height;

                self.animationFrameId = requestAnimationFrame(self.drawWave.bind(self));
                self.analyser.getByteTimeDomainData(self.dataArray);

                self.canvasCtx.clearRect(0, 0, WIDTH, HEIGHT);
                self.canvasCtx.lineWidth = 2;
                self.canvasCtx.strokeStyle = "rgb(255, 255, 255)";
                self.canvasCtx.beginPath();

                const sliceWidth = (WIDTH * 1.0) / self.dataArray.length;
                let x = 0;
                for (let i = 0; i < self.dataArray.length; i++) {
                    const v = self.dataArray[i] / 128.0;
                    const y = (v * HEIGHT) / 2;
                    if (i === 0) {
                        self.canvasCtx.moveTo(x, y);
                    } else {
                        self.canvasCtx.lineTo(x, y);
                    }
                    x += sliceWidth;
                }
                self.canvasCtx.lineTo(WIDTH, HEIGHT / 2);
                self.canvasCtx.stroke();
            },

            populateMicList: async function () {
                var self = this;
                try {
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    const mics = devices.filter(device => device.kind === "audioinput");
                    const select = self.controls.micSelect;
                    if (!select) {
                        return;
                    }
                    select.innerHTML = "";

                    // Group by groupId to remove duplicates.
                    const uniqueMics = [];
                    const seenGroups = new Set();
                    for (const mic of mics) {
                        if (!seenGroups.has(mic.groupId)) {
                            uniqueMics.push(mic);
                            seenGroups.add(mic.groupId);
                        }
                    }

                    if (uniqueMics.length <= 1) {
                        select.disabled = true;
                        return;
                    }
                    uniqueMics.forEach((mic, index) => {
                        const option = document.createElement("option");
                        option.value = mic.deviceId;
                        option.text = mic.label || `Microphone ${index + 1}`;
                        select.appendChild(option);
                    });
                    select.parentElement.classList.remove('hidden');

                    select.addEventListener("change", async (e) => {
                        const deviceId = e.target.value;
                        if (self.driver && typeof self.driver.switchMic === 'function') {
                            await self.driver.switchMic(deviceId);
                        }
                    });
                } catch (err) {
                    log.debug("Failed to get microphone list:", err);
                }
            },

        };
    }
);
