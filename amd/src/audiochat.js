define(
    ['jquery', 'core/log', 'mod_minilesson/definitions',
        'mod_minilesson/ttrecorder', 'core/templates', 'core/str', 'core/fragment'],
    function ($, log, def, ttrecorder, templates, str, Fragment) {
        "use strict"; // jshint ;_;

        /*
    This file is to manage the free speaking item type
            */

          log.debug('MiniLesson AudioChat: initialising');

        return {
            autocreateresponse : false, // If true, the response will be created automatically
            gradingrequesttag: "gradingrequest", // Tag for the grading request
            gradingData: false, // Data returne by the grading request
            strings: {},
            controls: {}, // Controls for the item
            itemdata: {}, // Item data for the item
            index: 0, // Index of the item in the quiz
            quizhelper: {}, // Quiz helper for the item
            pc: null,
            dc: null,
            cantChat: false,
            audiochat_voice: "alloy", // Default voice for the AI
            isSessionStarted: false,
            isSessionStopped: false,
            isSessionActive: false,
            isLoading: false,
            isMicActive: false,
            isMicInitialized: false, // True when getUserMedia has successfully run once
            loadingMessages: new Set(), // To track messages that are currently loading,
            audioContext: null,
            analyser: null,
            dataArray: null,
            sourceNode: null,
            mediaStream: null,
            animationFrameId: null,
            canvasCtx: null,
            eventlogs: [],
            items: {},
            responses: {},
            abortcontroller: new AbortController(),
            datainputbuffer: false,
            inputBufferInterval: null,
            //Turn detection - semantic is good for native speakers, but awful for language learners
            // time based we give 3.5s of silence detection before posting
            semantic_vad: {
                type: "semantic_vad",
                eagerness: "low",
            },

            timebased_vad: {
                type: "server_vad",
                silence_duration_ms: 3500,
                create_response: true, // true = it will turn on and off the mic and respond
                interrupt_response: true, // only in conversation mode
                threshold: 0.3,
                //  "prefix_padding_ms": 300,
            },

            // For making multiple instances
            clone: function () {
                return $.extend(true, {}, this);
            },

            init: function (index, itemdata, quizhelper) {
                this.itemdata = itemdata;
                this.autocreateresponse = itemdata.audiochat_autoresponse || false;
                log.debug('itemdata', itemdata);
                this.quizhelper = quizhelper;
                this.index = index;
                this.cantChat = !itemdata.canchat;
                this.init_strings();
                this.init_controls(quizhelper, itemdata);
                this.init_voice(itemdata.audiochat_voice);
                this.register_events(index, itemdata, quizhelper);
                this.renderUI();
            },

            init_strings: function () {
                var self = this;
                // Set up strings
                str.get_strings([
                    { "key": "gradebywordcount", "component": "mod_minilesson" },
                ]).done(function (s) {
                    var i = 0;
                    self.strings.gradebywordcount = s[i++];
                });
            },

            next_question: function () {
                var self = this;
                var stepdata = {};
                stepdata.index = self.index;
                stepdata.hasgrade = true;
                stepdata.lessonitemid = self.itemdata.id;
                stepdata.totalitems = self.itemdata.totalmarks;
                stepdata.resultsdata = {'items': Object.values(self.items)};
                // Add grade and other results data
                stepdata = self.grade_activity(stepdata);
                stepdata.correctitems = Math.round((self.itemdata.totalmarks * stepdata.grade) / 100);
                self.quizhelper.do_next(stepdata);
            },

            count_words: function () {
                var self = this;
                var userTranscript = [];
                Object.values(self.items).forEach(item => {
                    if (item.content) {
                        userTranscript.push(item.content);
                    }
                });
                var wordCount = userTranscript.join(' ').split(/\s+/).length;
                return wordCount;
            },

            toggle_autocreate_response: function () {
                var self = this;
                self.autocreateresponse = !self.autocreateresponse;
                self.timebased_vad.create_response = self.autocreateresponse;
                log.debug("Autocreate response toggled:", self.autocreateresponse);
                self.dc.send(JSON.stringify({
                    type: "session.update",
                    session: {
                        turn_detection: self.timebased_vad,
                    }
                }));
            },

            grade_activity: function (stepdata) {
                //loop through items and form a complete user transcript
                var self = this;

                //count words in the transcript
                var wordcount = self.count_words();

                if (self.gradingData && self.gradingData.score !== undefined) {
                    log.debug("Using grading data from AI:", self.gradingData);
                    // If grading data is available, use it
                    stepdata.grade = self.gradingData.score;

                    //If target word count is greater then 0, we lower the grade if it is lower then that target word count
                    if (typeof stepdata.grade === 'number' &&
                        typeof wordcount === 'number' &&
                        self.itemdata.targetwordcount > 0 &&
                         wordcount < self.itemdata.targetwordcount) {
                        stepdata.grade = Math.round(stepdata.grade * (wordcount / self.itemdata.targetwordcount));
                    }


                    stepdata.resultsdata.aifeedback = self.gradingData.feedback || "";
                    stepdata.resultsdata.gradeexplanation = self.gradingData.gradeexplanation || "";
                } else {
                    //Otherwise we default to counting words
                    log.debug("No grading data from AI: counting words", self.gradingData);
                    stepdata.resultsdata.gradeexplanation = self.strings.gradebywordcount;
                    if (self.itemdata.countwords === false || self.itemdata.targetwordcount === 0) {
                        stepdata.grade =  100;
                    }



                    // Calculate grade based on word count
                    stepdata.grade = Math.min(wordcount / self.itemdata.targetwordcount, 1) * 100;
                }

                // return stepdata
                return stepdata;

            },

            register_events: function (index, itemdata, quizhelper) {

                var self = this;

                // Event Listeners
                self.controls.startSessionBtn.addEventListener("click", self.startSession.bind(this));
                self.controls.stopSessionBtn.addEventListener("click", self.stopSession.bind(this));
                self.controls.retrySessionBtn.addEventListener("click", self.resetSession.bind(this));
                self.controls.autocreateresponseCheckbox.addEventListener("change", self.toggle_autocreate_response.bind(self));
                self.controls.cancelStartSessionBtn.addEventListener("click", () => {
                    log.debug("Cancelling session start");
                    self.abortcontroller.abort();
                    self.abortcontroller = new AbortController();
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
                                nextbutton.trigger('click');
                            }
                        });
                    }
                });


                if (self.controls.toggleMicBtn) {
                    self.controls.toggleMicBtn.addEventListener("click", self.toggleMute.bind(self))
                }
            },

            init_voice: function (voice) {
                var self = this;
                var voices = ['alloy', 'ash', 'ballad', 'coral', 'echo', 'sage', 'shimmer', 'verse', 'marin', 'cedar'];
                if (voice && voices.includes(voice)) {
                    self.audiochat_voice = voice;
                } else {
                    self.audiochat_voice = 'alloy'; // Default voice
                }
                log.debug("AudioChat voice set to:", this.audiochat_voice);
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
                    micIcon: container.querySelector(".mic-icon"),
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
                };
                self.canvasCtx = !self.controls.micWaveformCanvas ? null :
                    self.controls.micWaveformCanvas.getContext("2d");

                // Initial render
                await self.populateMicList();

            },

            scrollToBottom: function () {
                var self = this;
                self.controls.conversationSection.firstElementChild.scrollIntoViewIfNeeded();
                self.controls.conversationSection.firstElementChild.scrollTop = self.controls.conversationSection.firstElementChild.scrollHeight;
            },

            scrollMicButtonIntoView: function () {
                var self = this;
                if (self.controls.micButtonContainer) {
                    self.controls.micButtonContainer.scrollIntoView({behavior: "smooth", block: "center"});
                }
            },

            renderUI: function () {
                var self = this;
                // Session Controls
                self.controls.startSessionBtn.classList.toggle("hidden", self.isSessionActive || self.isLoading || self.isSessionStarted || self.cantChat);
                self.controls.cantChatWarning.classList.toggle("hidden", !self.cantChat);
                self.controls.loadingIndicator.classList.toggle("hidden", !self.isLoading);
                self.controls.stopSessionBtn.classList.toggle("hidden", !self.isSessionActive);
                self.controls.micButtonContainer.classList.toggle("hidden", !self.isSessionActive);
                var endScreen = self.isSessionStarted && self.isSessionStopped;
                self.controls.resultscontainer.classList.toggle("hidden", !endScreen && self.quizhelper.showitemreview);
                self.controls.finishMessage.classList.toggle("hidden", !endScreen);
                self.controls.retrySessionBtn.classList.toggle("hidden", !endScreen && self.itemdata.allowretry);
                // We no longer show the cancel button. It does not work after changes we made to icegathering.
                // If we set abortcontroller.signal to work with it will work, but it is complex
                //self.controls.cancelStartSessionBtn.classList.toggle('hidden', !(self.isLoading && !self.isSessionActive));
                self.controls.autocreateresponseToggle.classList.toggle("hidden", !self.isSessionActive);
                if (self.controls.micSelect) {
                    //how many options are in micselect
                    var mics = self.controls.micSelect.querySelectorAll('option');
                    var noshowmics = mics.length < 2;
                    self.controls.micSelect.parentElement.classList.toggle(
                        'hidden',
                        noshowmics || self.isSessionStarted || self.isLoading || self.controls.micSelect.disabled
                    );
                }

                var orderedItems = [];
                var idMap = new Map();
                var previousMap = new Map();
                var currentItem;
                Object.values(self.items).forEach(item => {
                    idMap.set(item.id, item);
                    previousMap.set(item.previous_item_id, item);
                    if (item.previous_item_id === null) {
                        currentItem = item;
                    }
                });
                while (currentItem) {
                    orderedItems.push(currentItem);
                    currentItem = previousMap.get(currentItem.id);
                }

                // The cute dog avatar
                self.controls.aiAvatarSection.classList.toggle("hidden", self.isSessionStarted || self.isSessionActive || self.isSessionStopped);
                //The chat session is active message
                self.controls.chatActiveMessage.classList.toggle("hidden", !self.isSessionActive);
                // The conversation area
                self.controls.conversationSection.classList.toggle("hidden", !(self.isSessionActive || self.isSessionStopped));

                // Render messages
                self.controls.messagesContainer.innerHTML = ""; // Clear existing messages

                orderedItems.forEach((message) => {
                    if (!message.content) {
                        return;
                    }
                    var messageDiv = document.createElement("div");
                    messageDiv.className = `flex ${message.usertype === "user" ? "justify-end" : "justify-start"} ml_unique_ordered_message_${message.usertype === "user" ? "user" : "assistant"}`;

                    var contentDiv = document.createElement("div");
                    contentDiv.className = `max - w - xs lg:max - w - md px - 4 py - 2 rounded - lg ${
                            message.usertype === "user" ? "bg-blue-500 text-white" : "bg-gray-200 text-gray-800"
                    } ml_unique_content_${
                        message.usertype === "user" ? "user" : "assistant"
                    }`;

                    var headerDiv = document.createElement("div");
                    headerDiv.className = "flex items-center text-xs font-medium mb-1 ml_unique_headerdiv";
                    if (message.usertype === "assistant") {
                        var pictureDiv = document.createElement('div');
                        pictureDiv.innerHTML = `
                            < img src = "${self.itemdata.avatarimage}?themerev=${M.cfg.themerev}"
                            alt = "AI Assistant" class = "mr-2 rounded-circle shadow-lg ml_unique_assistant_img" >
                            `;
                        headerDiv.appendChild(pictureDiv);
                    }
                    headerDiv.innerHTML += message.usertype === "user" ? "Student" : "AI Assistant";
                    contentDiv.appendChild(headerDiv);

                    var textDiv = document.createElement("div");
                    textDiv.className = "text-sm ml_unique_textsmall";
                    textDiv.textContent = message.content;
                    contentDiv.appendChild(textDiv);

                    if (self.loadingMessages.has(message.id)) {
                        var loaderDiv = document.createElement("div");
                        loaderDiv.className = "flex items-center space-x-1 py-1 message-loader ml_unique_loadingmessage";
                        loaderDiv.innerHTML = `
                            < div class = "flex space-x-1 ml_unique_loader" >
                                < div class = "w-2 h-2 bg-current rounded-full ml_unique_loader_dot" > < / div >
                                < div class = "w-2 h-2 bg-current rounded-full ml_unique_loader_dot" > < / div >
                                < div class = "w-2 h-2 bg-current rounded-full ml_unique_loader_dot" > < / div >
                            <  / div >
                            < span class = "text-xs opacity-70 ml_unique_loader_text" > AI is thinking... < / span >
                        `;
                        contentDiv.appendChild(loaderDiv);
                    }

                    messageDiv.appendChild(contentDiv);
                    self.controls.messagesContainer.appendChild(messageDiv);
                });

                self.scrollToBottom();
               // self.scrollMicButtonIntoView();

                // Update mic button container and canvas visibility
                if (self.controls.micButtonContainer) {
                    self.controls.micButtonContainer.classList.toggle("active", self.isMicActive);
                    self.controls.micButtonContainer.classList.toggle("bg-blue-500", self.isMicActive); // Active background color
                    self.controls.micButtonContainer.classList.toggle("text-white", self.isMicActive); // Active icon color
                    self.controls.micButtonContainer.classList.toggle("bg-gray-200", !self.isMicActive); // Inactive background color
                    self.controls.micButtonContainer.classList.toggle("text-gray-800", !self.isMicActive); // Inactive icon color
                }


                if (self.controls.micWaveformCanvas) {
                    self.controls.micWaveformCanvas.classList.toggle("active", self.isMicActive);
                }

                if (self.controls.micIcon) {
                    // Set icon based on mic state
                    self.controls.micIcon.innerHTML = self.isMicActive
                        ? ` < rect id = "primary" x = "2" y = "2" width = "20" height = "20" rx = "2" style = "fill: rgb(0, 0, 0);" > < / rect > ` // Mic On icon
                        : ` < path id = "secondary" d = "M12,15h0a4,4,0,0,1-4-4V7a4,4,0,0,1,4-4h0a4,4,0,0,1,4,4v4A4,4,0,0,1,12,15Z" style = "fill: rgb(44, 169, 188); stroke-width: 2;" > < / path > < path id = "primary" d = "M18.24,16A8,8,0,0,1,5.76,16" style = "fill: none; stroke: rgb(0, 0, 0); stroke-linecap: round; stroke-linejoin: round; stroke-width: 2;" > < / path > < path id = "primary-2" data - name = "primary" d = "M12,19v2m4-10V7a4,4,0,0,0-4-4h0A4,4,0,0,0,8,7v4a4,4,0,0,0,4,4h0A4,4,0,0,0,16,11Z" style = "fill: none; stroke: rgb(0, 0, 0); stroke-linecap: round; stroke-linejoin: round; stroke-width: 2;" > < / path > `; // Mic Off icon
                }

                //show or not show clicktosendlabel
                if (self.isMicActive && !self.autocreateresponse) {
                    self.controls.clicktosendlabel.classList.remove("hidden");
                } else {
                    self.controls.clicktosendlabel.classList.add("hidden");
                }

            },

            //setter for datainputbuffer
            setDataInputBuffer: function (value, source) {
                var self = this;
                self.datainputbuffer = value;
                log.debug("Data in input buffer set to:", value);
                log.debug("Data input buffer set source:", source);
            },

            resetSession: function () {
                log.debug("reset  session");
                var self = this;
                self.isLoading = false;
                self.isSessionActive = false;
                self.isSessionStopped = false;
                self.isSessionStarted = false;
                self.renderUI();
            },

            startSession: async function () {
                var self = this;
                var twoletterlang = self.itemdata.language.substr(0, 2);
                var hiddenaudio = self.controls.hiddenaudio;
                log.debug("Session starting");
                self.isLoading = true;
                self.items = [];
                self.renderUI();
                // Open the RTC PeerConnection via Stun and ICE servers
                log.debug("Opening peer connection...");
                self.pc = new RTCPeerConnection({
                    iceServers: [{
                        urls: "stun:stun.l.google.com:19302"
                    }]
                });

                // Create a DataChannel for sending events (text and audio)
                log.debug("creating data channel...");
                self.dc = self.pc.createDataChannel("oai-events");

                // Handle incoming messages on the DataChannel
                self.dc.onmessage = (e) => {
                    self.eventlogs.push(e.data);
                    //log.debug("DataChannel message:", e.data);
                    try {
                        var lines = e.data.split("\n").filter(Boolean);
                        for (var line of lines) {
                            self.handleRTCEvent.call(self, JSON.parse(line));
                        }
                    } catch (err) {
                        log.debug("Failed to parse", err);
                    }
                };
                self.dc.onopen = () => {
                    log.debug("DataChannel open");

                    //Turn detection - semantic is good for native speakers, but awful for language learners
                    // time based we give 1.5s of silence detection before posting
                    var semantic_vad = {
                        type: "semantic_vad",
                        eagerness: "low",
                    };

                    // Set the auto turn detection, or manual submit flag
                    self.timebased_vad.create_response = self.autocreateresponse;

                    log.debug(self.itemdata.audiochatinstructions);
                    var updateinstructions = self.itemdata.audiochatinstructions;
                    Fragment.loadFragment(
                        'mod_minilesson',
                        'audiochat_fetchstudentsubmission',
                        M.cfg.contextid,
                        {
                            itemid: self.itemdata.id
                        }
                    ).done(function (studentsubmission) {
                        log.debug("Loaded audio chat studentsubmission:", studentsubmission);
                        if (studentsubmission) {
                            // Replace "{student submission}" placeholder with actual submission
                            updateinstructions = updateinstructions.replace('{student submission}', studentsubmission);

                            // Replace student submission in grade instructions too
                            self.itemdata.audiochatgradeinstructions = self.itemdata.audiochatgradeinstructions.replace('{student submission}', studentsubmission);

                            // Save student submission in itemdata for later use
                            self.itemdata.studentsubmission = studentsubmission;
                        }

                        self.sendEvent({
                            type: "session.update",
                            session: {
                                instructions: updateinstructions,
                                input_audio_format: "pcm16", // Ensure correct audio encoding
                                input_audio_transcription: {
                                    language: twoletterlang,
                                    model: "whisper-1" // "gpt-4o-mini-transcribe"  // Use a transcription model
                                },
                                turn_detection: self.timebased_vad,
                                speed: 0.9,
                                voice: self.audiochat_voice,
                                modalities: ["text", "audio"],
                            }
                        });

                        // Send the first message to tell AI to say something
                        // the response create function overrides the session instructions, so we need to double up here
                        var firstmessageinstructions =  "Please introduce yourself to the student and explain todays topic.";
                            self.sendEvent({
                                type: "response.create",
                                response: {
                                    modalities: ["audio", "text"],
                                    instructions:  updateinstructions + " " + firstmessageinstructions,
                                    voice: self.audiochat_voice
                                }
                            });
                    });
                };

                // Set up the audio element to play incoming audio.
                self.pc.ontrack = (event) => {
                    hiddenaudio.srcObject = event.streams[0];
                };

                // Set up the Mic stream.
                self.mediaStream = await navigator.mediaDevices.getUserMedia({audio: true});
                self.mediaStream.getTracks().forEach((track) => {
                    track.enabled = false;
                    self.pc.addTrack(track, self.mediaStream);
                });

                // Set up the RTC Connection by bouncing our request off the Moodle server
                var offer = await self.pc.createOffer({
                    offerToReceiveAudio: true
                });
                await self.pc.setLocalDescription(offer);
                // Search for server candidates for relaying messages, may take 15s
                await self.waitForIceGathering(self.pc);

                try {
                    var sdpResponse = await fetch(M.cfg.wwwroot + "/mod/minilesson/openairtc.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/sdp"
                        },
                        body: self.pc.localDescription.sdp,
                        signal: self.abortcontroller.signal
                    });
                    if (!sdpResponse.ok) {
                        log.debug("Failed /rtc:", await sdpResponse.text());
                        return;
                    }
                    log.debug("Received SDP answer from server");
                    var answer = await sdpResponse.text();
                    log.debug(answer);
                    await self.pc.setRemoteDescription({
                        type: "answer",
                        sdp: answer
                    });
                    log.debug("Session started");
                } catch (e) {
                    if (e.name === 'AbortError') {
                        log.debug("Session start aborted by user.");
                        // Reset UI and state as needed
                        self.isLoading = false;
                        self.renderUI();
                        return;
                    }

                    // Close data channel if open
                    if (self.dc) {
                        self.dc.close();
                    }
                    // Close peer connection if open
                    if (self.pc) {
                        self.pc.close();
                    }
                    if (self.mediaStream) {
                        self.mediaStream.getTracks().forEach((track) => track.stop());
                    }
                    self.isLoading = false;
                    self.renderUI();
                    return;
                }

                self.isLoading = false;
                self.isSessionActive = true;
                self.isSessionStarted = true;
                self.isSessionStopped = false;
                self.renderUI();
            },

            sendGradingRequest: function () {
                var self = this;
                // Send a final message to tell AI to grade the session and give feedback
                var gradingInstructions = "Please provide a percentage score for the session, an explanation of the score (for teachers), and feedback (for the student). " +
                     self.itemdata.audiochatgradeinstructions +
                    "Return the response as JSON in the format: {\"score\": \"the score  ( 0-100 ) \", \"gradeexplanation\": \"the explanation\", \"feedback\": \"the feedback\"}.";

                var responsedata = {
                    // The response is out of band and not be added to the default conversation
                    conversation: "none",
                    modalities: ["text"],
                    instructions: gradingInstructions,
                    // Add the gradingrequest tag to make handltertc life easier
                    metadata: { tag: self.gradingrequesttag},
                    max_output_tokens: 500, // Keeps it tight
                    temperature: 0.6, // Optional: makes grading more deterministic
                };

                //If we wanted to reutrn an audio response (but lets not)
                //responsedata.voice = self.audiochat_voice;

                self.sendEvent({
                    type: "response.create",
                    response: responsedata,
                });
            },

            stopSession: function () {
                var self = this;

                log.debug("Session stopping...");
                self.isSessionActive = false;
                self.isSessionStopped = true;
                self.loadingMessages.clear();

                // Release mic resources when session ends
                self.releaseMicResources();
                self.renderUI();

                // request grading information
                // after that response, we will close the data channel and peer connection
                //but shut it down after 2s just in case there is an error or something
                if (self.itemdata.audiochatgradeinstructions && self.itemdata.audiochatgradeinstructions !== "") {
                    self.sendGradingRequest();

                    //add a spinner to the results content
                    //if we are not showing item review we do not need this, but in that case it is hidden anyway
                    self.controls.resultscontent.innerHTML = ` < i class = "fa fa-spinner fa-spin fa-2x" > < / i > `;

                    setTimeout(() => {
                        //now show the results content if that is what we are doing
                        if (self.quizhelper.showitemreview) {
                            self.showResults();
                        }
                        log.debug("Closing session resources...");
                        self.closeDataChannel();
                    }, 7000);
                } else {
                    log.debug("Closing session resources...");
                    self.closeDataChannel();
                }
                log.debug("Session stopped");
            },

            closeDataChannel: function () {
                var self = this;
                // Tidy up the data channel and peer connection
                if (typeof self.dc !== 'undefined' && self.dc) {
                    self.dc.close();
                    self.dc = null;
                }
                if (typeof self.pc !== 'undefined' && self.pc) {
                    self.pc.close();
                    self.pc = null;
                }
            },

            showResults: function () {
                var self = this;
                var tdata = {};
                tdata.resultsdata = {'items': Object.values(self.items)};
                // Add grade and other results data
                tdata = self.grade_activity(tdata);

                //calculate stars from grade
                const stars = [];
                const maxStars = 5;
                // if tdata grade is NAn or undefined, set it to 0
                if (typeof tdata.grade === 'undefined' || isNaN(tdata.grade) || tdata.grade === null || tdata.grade === "") {
                    tdata.grade = 0;
                }

                const filledStars = Math.round((tdata.grade / 100) * maxStars);
                for (let i = 0; i < maxStars; i++) {
                    stars.push({ filled: i < filledStars });
                }
                tdata.stars = stars;

                templates.render('mod_minilesson/audiochatimmediatefeedback', tdata).then(
                    function (html, js) {
                        self.controls.resultscontent.innerHTML = html;
                    }
                );
            },

            waitForIceGathering: function (pc, timeout = 15000) {
                return new Promise((resolve) => {
                    let timer;

                    function checkState()
                    {
                        if (pc.iceGatheringState === "complete") {
                            clearTimeout(timer);
                            pc.removeEventListener("icegatheringstatechange", checkState);
                            resolve();
                        }
                    }

                    pc.addEventListener("icegatheringstatechange", checkState);

                    // Timeout to resolve with current state
                    timer = setTimeout(() => {
                        pc.removeEventListener("icegatheringstatechange", checkState);
                        resolve(); // Resolve with as many candidates as gathered so far
                    }, timeout);
                });
            },

            sendEvent: function (obj) {
                var self = this;
                if (self.dc && self.dc.readyState === "open") {
                    self.dc.send(JSON.stringify(obj));
                }
            },

            handleRTCEvent: function (msg) {
                var self = this;
                log.debug("Received event:");

                // Check if its the final grading message, which we don't want to enter "items"
                if (msg.type === "response.done" &&
                    msg.response.metadata?.tag === self.gradingrequesttag) {
                    // Check if the response corresponds to the grading event
                    log.debug("It is a grading event:");
                    try {
                        var jsonresponse = msg.response.output[0].content[0].text;
                        if (!jsonresponse || jsonresponse === "") {
                            log.debug("No valid grading data received .. msg is ..");
                            log.debug(msg);
                            self.closeDataChannel();
                            return;
                        }

                        self.gradingData = JSON.parse(jsonresponse);
                        log.debug("Grading and Feedback:", self.gradingData);
                    } catch (err) {
                        self.gradingData = false;
                        log.debug("Failed to parse grading feedback:", err);
                        log.debug(jsonresponse);
                    }
                        return;
                }

                // log.debug(msg);
                var msgresponse_id = msg.response ? msg.response.id : msg.response_id;
                var msgitem_id = msg.item ? msg.item.id : msg.item_id;
                if (msgresponse_id) {
                    self.responses[msgresponse_id] = self.responses[msgresponse_id] || {
                        id: msgresponse_id,
                        itemid: msgitem_id,
                        stack: []
                    };
                }
                if (msgitem_id) {
                    if (typeof self.items[msgitem_id] === 'undefined') {
                        self.scrollToBottom();
                    }
                    self.items[msgitem_id] = self.items[msgitem_id] || {
                        id: msgitem_id,
                        events: [],
                        responses: null,
                        content: ''
                    };
                    if (msgresponse_id) {
                        self.items[msgitem_id].responses = self.responses[msgresponse_id];
                    }
                }

                msg.time = Date.now().toString();

                switch (msg.type) {
                    case "response.created": {
        /*```
    {
    "type": "response.created",
    "event_id": "event_Bzbmm1vOdUpYK5LcKMPAU",
    "response": {
        "object": "realtime.response",
        "id": "resp_BzbmmfvbvyUuYz2hvigPm",
        "status": "in_progress",
        "status_details": null,
        "output": [],
        "conversation_id": "conv_BzbmjU4iAZBReV6QpTbKH",
        "modalities": [
            "audio",
            "text"
        ],
        "voice": "alloy",
        "output_audio_format": "pcm16",
        "temperature": 0.8,
        "max_output_tokens": "inf",
        "usage": null,
        "metadata": null
    }
    }
        ```*/
                        self.responses[msg.response.id].stack.push(msg);
                        break;
                    }
                    case "response.output_item.added": {
        /*```
    {
    "type": "response.output_item.added",
    "event_id": "event_BzbmnNUVJeqok4KqSLSVl",
    "response_id": "resp_BzbmmfvbvyUuYz2hvigPm",
    "output_index": 0,
    "item": {
        "id": "item_BzbmmIsy7p3YZXC9HroUp",
        "object": "realtime.item",
        "type": "message",
        "status": "in_progress",
        "role": "assistant",
        "content": []
    }
    }
        ```*/
                        self.responses[msg.response_id].stack.push(msg);
                        break;
                    }
                    case "conversation.item.created": {
        /*```
    {
    "type": "conversation.item.created",
    "event_id": "event_BzbmnttqDhvU3h2he1tK7",
    "previous_item_id": "item_BzbmmcBxU60HVn8xvCWA2",
    "item": {
        "id": "item_BzbmmIsy7p3YZXC9HroUp",
        "object": "realtime.item",
        "type": "message",
        "status": "in_progress",
        "role": "assistant",
        "content": []
    }
    }
        ```*/
                        self.items[msg.item.id].previous_item_id = msg.previous_item_id;
                        self.items[msg.item.id].usertype = msg.item.role;
                        self.items[msg.item.id].events.push(msg);
                        if (msg.item.role === 'assistant') {
                            self.loadingMessages.add(msgitem_id);
                        }
                        break;
                    }
                    case "response.content_part.added": {
        /*```
    {
    "type": "response.content_part.added",
    "event_id": "event_Bzbmn7lA1i7fOfFq8ju3F",
    "response_id": "resp_BzbmmfvbvyUuYz2hvigPm",
    "item_id": "item_BzbmmIsy7p3YZXC9HroUp",
    "output_index": 0,
    "content_index": 0,
    "part": {
        "type": "audio",
        "transcript": ""
    }
    }
        ```*/
                        self.enableMic();// Let's enable mic
                        self.responses[msg.response_id].stack.push(msg);
                        break;
                    }
                    case "response.audio_transcript.delta": {
        /*```
    {
    "type": "response.audio_transcript.delta",
    "event_id": "event_BzbmnpxUmLA0zAnR5mRy3",
    "response_id": "resp_BzbmmfvbvyUuYz2hvigPm",
    "item_id": "item_BzbmmIsy7p3YZXC9HroUp",
    "output_index": 0,
    "content_index": 0,
    "delta": "Hi"
    }
        ```*/
                        self.responses[msg.response_id].stack.push(msg);
                        self.items[msg.item_id].content += msg.delta;
                        break;
                    }

                    case "output_audio_buffer.cleared": {
        /*```
    {
    "type": "output_audio_buffer.cleared",
    "event_id": "event_f7273193069b4938",
    "response_id": "resp_BzbmmfvbvyUuYz2hvigPm"
    }
        ```*/
                        self.responses[msg.response_id].stack.push(msg);
                        break;
                    }
                    case "response.audio.done": {
        /*```
    {
    "type": "response.audio.done",
    "event_id": "event_Bzbmn7aEtTnprkzqE9i6X",
    "response_id": "resp_BzbmmfvbvyUuYz2hvigPm",
    "item_id": "item_BzbmmIsy7p3YZXC9HroUp",
    "output_index": 0,
    "content_index": 0
    }
        ```*/
                        self.responses[msg.response_id].stack.push(msg);
                        break;
                    }
                    case "response.audio_transcript.done": {
        /*```
    {
    "type": "response.audio_transcript.done",
    "event_id": "event_BzbmnNGRs7797nz1Qh7em",
    "response_id": "resp_BzbmmfvbvyUuYz2hvigPm",
    "item_id": "item_BzbmmIsy7p3YZXC9HroUp",
    "output_index": 0,
    "content_index": 0,
    "transcript": "Hi! How are you today? What did you do today?"
    }
        ```*/
                        self.responses[msg.response_id].stack.push(msg);
                        self.items[msg.item_id].content = msg.transcript;
                        break;
                    }
                    case "response.content_part.done": {
        /*```
    {
    "type": "response.content_part.done",
    "event_id": "event_BzbmnYdAvAKMU7ti311Vj",
    "response_id": "resp_BzbmmfvbvyUuYz2hvigPm",
    "item_id": "item_BzbmmIsy7p3YZXC9HroUp",
    "output_index": 0,
    "content_index": 0,
    "part": {
        "type": "audio",
        "transcript": "Hi! How are you today? What did you do today?"
    }
    }
        ```*/
                        self.responses[msg.response_id].stack.push(msg);
                        break;
                    }
                    case "response.output_item.done": {
        /*```
    {
    "type": "response.output_item.done",
    "event_id": "event_BzbmnhD5RdLxAOnko94Z1",
    "response_id": "resp_BzbmmfvbvyUuYz2hvigPm",
    "output_index": 0,
    "item": {
        "id": "item_BzbmmIsy7p3YZXC9HroUp",
        "object": "realtime.item",
        "type": "message",
        "status": "incomplete",
        "role": "assistant",
        "content": [
            {
                "type": "audio",
                "transcript": "Hi! How are you today? What did you do today?"
            }
        ]
    }
    }
        ```*/
                        self.responses[msg.response_id].stack.push(msg);
                        self.loadingMessages.delete(msg.item.id);
                        break;
                    }
                    case "response.done": {
        /*```
    {
    "type": "response.done",
    "event_id": "event_Bzbmn8bIaGB59d6qG7LQS",
    "response": {
        "object": "realtime.response",
        "id": "resp_BzbmmfvbvyUuYz2hvigPm",
        "status": "cancelled",
        "status_details": {
            "type": "cancelled",
            "reason": "turn_detected"
        },
        "output": [
            {
                "id": "item_BzbmmIsy7p3YZXC9HroUp",
                "object": "realtime.item",
                "type": "message",
                "status": "incomplete",
                "role": "assistant",
                "content": [
                    {
                        "type": "audio",
                        "transcript": "Hi! How are you today? What did you do today?"
                    }
                ]
            }
        ],
        "conversation_id": "conv_BzbmjU4iAZBReV6QpTbKH",
        "modalities": [
            "audio",
            "text"
        ],
        "voice": "alloy",
        "output_audio_format": "pcm16",
        "temperature": 0.8,
        "max_output_tokens": "inf",
        "usage": {
            "total_tokens": 170,
            "input_tokens": 94,
            "output_tokens": 76,
            "input_token_details": {
                "text_tokens": 87,
                "audio_tokens": 7,
                "cached_tokens": 0,
                "cached_tokens_details": {
                    "text_tokens": 0,
                    "audio_tokens": 0
                }
            },
            "output_token_details": {
                "text_tokens": 23,
                "audio_tokens": 53
            }
        },
        "metadata": null
    }
    }
        ```*/
                        self.responses[msg.response.id].stack.push(msg);
                        break;
                    }
                    case "output_audio_buffer.stopped": {
        /*```
    {
    "type":"output_audio_buffer.stopped",
    "event_id":"event_0ebd8495b5a945e5",
    "response_id":"resp_C17PJbWxcyg7tgQBVTAaL"
    }
        ```*/
                        if (!self.isMicActive) {
                            self.toggleMute();
                        }
                        self.responses[msg.response.id].stack.push(msg);
                        break;
                    }
                    case "conversation.item.truncated": {
        /*```
    {
    "type": "conversation.item.truncated",
    "event_id": "event_BzbmnGqfHdq1hy2Wr2fnA",
    "item_id": "item_BzbmmIsy7p3YZXC9HroUp",
    "content_index": 0,
    "audio_end_ms": 261
    }
        ```*/
                        self.items[msg.item_id].events.push(msg);
                        break;
                    }
                    // User events.
                    case "input_audio_buffer.speech_started": {
        /*```
    {
    "type": "input_audio_buffer.speech_started",
    "event_id": "event_Bzbmm9FJ5oCTmCpng9tem",
    "audio_start_ms": 820,
    "item_id": "item_BzbmmcBxU60HVn8xvCWA2"
    }
        ```*/
                        log.debug("Input audio buffer speech started");
                        self.setDataInputBuffer(true, 'audiobufferstarted');
                        self.items[msg.item_id].events.push(msg);
                        break;
                    }
                    case "input_audio_buffer.speech_stopped": {
        /*```
    {
    "type": "input_audio_buffer.speech_stopped",
    "event_id": "event_BzbmmUgLX2JKgJr1eMx0l",
    "audio_end_ms": 1568,
    "item_id": "item_BzbmmcBxU60HVn8xvCWA2"
    }
        ```*/
                        if (self.isMicActive) {
                            // Only auto-toggle mic if autocreateresponse is true
                            if (self.autocreateresponse) {
                                self.toggleMute();
                                self.disableMic();
                            }
                        }
                        self.items[msg.item_id].events.push(msg);
                        break;
                    }
                    case "input_audio_buffer.committed": {
        /*```
    {
    "type": "input_audio_buffer.committed",
    "event_id": "event_BzbmmOICFLRU8vnGEJ6vM",
    "previous_item_id": null,
    "item_id": "item_BzbmmcBxU60HVn8xvCWA2"
    }
        ```*/               log.debug("Input audio buffer committed");
                        self.setDataInputBuffer(false, 'audiobuffercommitted');
                        self.items[msg.item_id].events.push(msg);
                        break;
                    }
                    case "conversation.item.created": {
        /*```
    {
    "type": "conversation.item.created",
    "event_id": "event_BzbmmiskBBbCkRSHvUVrL",
    "previous_item_id": null,
    "item": {
        "id": "item_BzbmmcBxU60HVn8xvCWA2",
        "object": "realtime.item",
        "type": "message",
        "status": "completed",
        "role": "user",
        "content": [
            {
                "type": "input_audio",
                "transcript": null
            }
        ]
    }
    }
        ```*/
                        self.items[msg.item.id].events.push(msg);
                        break;
                    }
                    case "conversation.item.input_audio_transcription.delta": {
        /*```
    {
    "type": "conversation.item.input_audio_transcription.delta",
    "event_id": "event_BzbmonQuYXZ7QxUCOLlZd",
    "item_id": "item_BzbmmcBxU60HVn8xvCWA2",
    "content_index": 0,
    "delta": "Hey."
    }
        ```*/
                        self.items[msg.item_id].events.push(msg);
                        self.items[msg.item_id].content += msg.delta;
                        break;
                    }
                    case "conversation.item.input_audio_transcription.completed": {
        /*```
    {
    "type": "conversation.item.input_audio_transcription.completed",
    "event_id": "event_BzbmotVIjHphgHjViNkZk",
    "item_id": "item_BzbmmcBxU60HVn8xvCWA2",
    "content_index": 0,
    "transcript": "Hey.",
    "usage": {
        "type": "duration",
        "seconds": 1
    }
    }
        ```*/
                        self.items[msg.item_id].events.push(msg);
                        self.items[msg.item_id].content = msg.transcript;
                        self.loadingMessages.delete(msg.item_id);
                        break;
                    }
                }
                self.renderUI();
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

            initializeMicStream: async function () {
                var self = this;
                try {
                    self.audioContext = new(window.AudioContext || window.webkitAudioContext)();
                    self.analyser = self.audioContext.createAnalyser();
                    self.analyser.fftSize = 2048;
                    const bufferLength = self.analyser.frequencyBinCount;
                    self.dataArray = new Uint8Array(bufferLength);

                    self.sourceNode = self.audioContext.createMediaStreamSource(self.mediaStream);
                    // Source is connected/disconnected in toggleMute
                    self.isMicInitialized = true;
                    return true;
                } catch (err) {
                    log.debug("Error accessing microphone:", err);
                    self.isMicInitialized = false;
                    log.debug("Could not access microphone. Please ensure it's connected and permissions are granted.");
                    return false;
                }
            },

            // Toggles mute/unmute state of the mic
            toggleMute: async function () {
                var self = this;
                if (!self.isMicInitialized) {
                    const success = await self.initializeMicStream();
                    if (!success) {
                        return;
                    } // If initialization failed, stop here
                }

                if (self.isMicActive) {
                    // Mute mic: Disconnect source from analyser
                    if (self.sourceNode && self.analyser) {
                        self.sourceNode.disconnect(self.analyser);
                    }
                    if (self.animationFrameId) {
                        cancelAnimationFrame(self.animationFrameId);
                        self.animationFrameId = null;
                    }
                    if (self.pc) {
                        self.mediaStream.getTracks().forEach((track) => {
                            track.enabled = false;
                        });
                    }
                    if (self.canvasCtx) {
                        self.canvasCtx.clearRect(0, 0, self.controls.micWaveformCanvas.width, self.controls.micWaveformCanvas.height); // Clear canvas
                    }
                    self.isMicActive = false;

                    // Send response event when mic is disabled and autocreateresponse is false
                    if (!self.autocreateresponse) {
                        if (!self.datainputbuffer) {
                            log.debug(" sending response.create");
                            self.sendEvent({
                                type: "response.create",
                                response: {
                                    modalities: ["audio", "text"],
                                    instructions: self.itemdata.audiochatinstructions,
                                    voice: self.audiochat_voice
                                }
                            });
                        } else {
                            //set a recurring 500ms timeout that will send the response.create event if self.datainputbuffer is false
                            log.debug("Waiting for input audio buffer to commit before sending response.create");
                            let attempts = 0;
                            if (self.inputBufferInterval) {
                                clearInterval(self.inputBufferInterval);
                                self.inputBufferInterval = null;
                            }
                            const maxAttempts = 3;
                            self.inputBufferInterval = setInterval(() => {
                                log.debug("Checking input buffer status, attempt:", attempts);
                                if (!self.datainputbuffer || attempts >= maxAttempts) {
                                    clearInterval(self.inputBufferInterval);
                                    self.setDataInputBuffer(false, 'setInterval:' + attempts);
                                    log.debug(" sending response.create");
                                    self.sendEvent({
                                        type: "response.create",
                                        response: {
                                            modalities: ["audio", "text"],
                                            instructions: self.itemdata.audiochatinstructions,
                                            voice: self.audiochat_voice
                                        }
                                    });
                                }
                                attempts++;
                            }, 1500);
                        }
                    }
                } else {
                    // Unmute mic: Connect source to analyser
                    if (self.sourceNode && self.analyser) {
                        self.sourceNode.connect(self.analyser);
                    }

                    if (self.pc) {
                        self.mediaStream.getTracks().forEach((track) => {
                            track.enabled = true;
                        });
                    }
                    self.isMicActive = true;
                    self.drawWave(); // Start drawing waveform
                }
                self.renderUI(); // Update UI
            },

            releaseMicResources: function () {
                var self = this;
                if (self.animationFrameId) {
                    cancelAnimationFrame(self.animationFrameId);
                    self.animationFrameId = null;
                }
                if (self.mediaStream) {
                    self.mediaStream.getTracks().forEach((track) => {
                        if (typeof self.pc !== 'undefined' && self.pc) {
                            // Find the RTCRtpSender associated with the track
                            const sender = self.pc.getSenders().find(s => s.track === track);
                            // Remove the sender if it exists
                            if (sender) {
                                self.pc.removeTrack(sender);
                            }
                        }
                        track.stop();
                    });
                    self.mediaStream = null;
                }
                if (self.sourceNode) {
                    self.sourceNode.disconnect();
                    self.sourceNode = null;
                }
                if (self.audioContext) {
                    self.audioContext.close();
                    self.audioContext = null;
                }
                self.isMicActive = false;
                self.isMicInitialized = false;
                if (self.canvasCtx) {
                    self.canvasCtx.clearRect(0, 0, self.controls.micWaveformCanvas.width, self.controls.micWaveformCanvas.height); // Clear canvas
                }
                self.renderUI(); // Update UI to show mic inactive
            },

            drawWave: function () {
                var self = this;
                if (!self.canvasCtx || !self.analyser || !self.dataArray || !self.isMicActive) {
                    self.animationFrameId = null; // Stop animation if conditions are not met
                    return;
                }

                const WIDTH = self.controls.micWaveformCanvas.width;
                const HEIGHT = self.controls.micWaveformCanvas.height;

                self.animationFrameId = requestAnimationFrame(self.drawWave.bind(self));

                self.analyser.getByteTimeDomainData(self.dataArray); // Get waveform data

                self.canvasCtx.clearRect(0, 0, WIDTH, HEIGHT); // Clear previous drawing
                self.canvasCtx.lineWidth = 2;
                self.canvasCtx.strokeStyle = "rgb(255, 255, 255)"; // White color for wave on blue background

                self.canvasCtx.beginPath();

                const sliceWidth = (WIDTH * 1.0) / self.dataArray.length;
                let x = 0;

                for (let i = 0; i < self.dataArray.length; i++) {
                    const v = self.dataArray[i] / 128.0; // Normalize to 0-2
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

                    select.innerHTML = ""; // Clear existing options

                    // Group by groupId to remove duplicates
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

                    // Set change listener
                    select.addEventListener("change", async(e) => {
                        const deviceId = e.target.value;
                        await self.switchMic(deviceId);
                    });
                } catch (err) {
                    log.debug("Failed to get microphone list:", err);
                }
            },

            switchMic: async function (deviceId) {
                var self = this;

                // Stop and release current mic
                if (self.mediaStream) {
                    self.mediaStream.getTracks().forEach(track => track.stop());
                }

                try {
                    // Get new media stream from selected device
                    self.mediaStream = await navigator.mediaDevices.getUserMedia({
                        audio: {deviceId: {exact: deviceId}}
                    });

                    // Replace tracks in PeerConnection
                    if (self.pc) {
                        const senders = self.pc.getSenders();
                        const audioTrack = self.mediaStream.getAudioTracks()[0];
                        const audioSender = senders.find(sender => sender.track.kind === 'audio');
                        if (audioSender) {
                            audioSender.replaceTrack(audioTrack);
                        }
                    }

                    // Reinitialize mic stream and waveform
                    await self.initializeMicStream();
                    if (self.isMicInitialized) {
                        self.sourceNode.connect(self.analyser);
                        self.mediaStream.getTracks().forEach((track) => {
                            track.enabled = self.isMicActive;
                        });
                        if (self.isMicActive) {
                            self.drawWave();
                        }
                    }

                    log.debug("Switched microphone to:" + deviceId);
                } catch (err) {
                    log.debug("Failed to switch microphone:");
                    log.debug(err);
                }
            },

        }; // End of return object.
    }
);