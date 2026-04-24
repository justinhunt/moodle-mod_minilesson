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
 * audiochat OpenAI realtime (WebRTC) chat driver.
 *
 * Encapsulates all OpenAI realtime-API protocol concerns (ICE/SDP handshake,
 * DataChannel event stream, session.update, response.create) behind a transport
 * agnostic driver interface consumed by the audiochat itemtype UI module.
 *
 * @module     minilessonitem_audiochat/chatdriver_openai
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/log', 'core/fragment'], function ($, log, Fragment) {
    "use strict";

    log.debug('MiniLesson AudioChat: OpenAI driver loading');

    return {
        gradingrequesttag: "gradingrequest",

        // Runtime state.
        itemdata: {},
        audioElement: null,
        callbacks: {},

        // Connection.
        pc: null,
        dc: null,
        mediaStream: null,
        abortcontroller: null,

        // Conversation state.
        eventlogs: [],
        items: {},
        responses: {},
        loadingMessages: null,
        gradingData: false,

        // Session options.
        audiochat_voice: "alloy",
        autocreateresponse: false,

        // Input-buffer bookkeeping for "click to send" mode.
        datainputbuffer: false,
        inputBufferInterval: null,

        // Turn detection (time-based works better for language learners than semantic_vad).
        timebased_vad: {
            type: "server_vad",
            silence_duration_ms: 3500,
            create_response: true,
            interrupt_response: true,
            threshold: 0.3,
        },
        semantic_vad: {
            type: "semantic_vad",
            eagerness: "low",
        },

        gradeRequestTrial: 0,
        maxGradeRequestTrial: 3,

        clone: function () {
            return $.extend(true, {}, this);
        },

        /**
         * Initialise the driver with item config and UI callbacks.
         *
         * @param {object} options
         * @param {object} options.itemdata - Item record (audiochat instructions, voice, language, etc).
         * @param {HTMLAudioElement} options.audioElement - Element that will play AI audio output.
         * @param {object} options.callbacks - UI callbacks (see below).
         */
        init: function (options) {
            var self = this;
            self.itemdata = options.itemdata;
            self.audioElement = options.audioElement;
            self.callbacks = options.callbacks || {};
            self.autocreateresponse = options.itemdata.audiochat_autoresponse || false;
            self.audiochat_voice = self._resolveVoice(options.itemdata.audiochat_voice);
            self.abortcontroller = new AbortController();
            self.items = {};
            self.responses = {};
            self.loadingMessages = new Set();
            self.eventlogs = [];
            self.gradingData = false;
        },

        _resolveVoice: function (voice) {
            var voices = ['alloy', 'ash', 'ballad', 'coral', 'echo', 'sage', 'shimmer', 'verse', 'marin', 'cedar'];
            return (voice && voices.includes(voice)) ? voice : 'alloy';
        },

        // UI notification helpers.
        _fire: function (name, ...args) {
            var cb = this.callbacks[name];
            if (typeof cb === 'function') {
                cb.apply(null, args);
            }
        },

        _notifyItems: function () {
            this._fire('onItemsChanged', this.getOrderedItems(), this.loadingMessages);
        },

        // Public getters used by the UI.
        getItems: function () {
            return this.items;
        },

        getOrderedItems: function () {
            var self = this;
            var orderedItems = [];
            var previousMap = new Map();
            var currentItem;
            Object.values(self.items).forEach((item) => {
                previousMap.set(item.previous_item_id, item);
                if (item.previous_item_id === null) {
                    currentItem = item;
                }
            });
            while (currentItem) {
                orderedItems.push(currentItem);
                currentItem = previousMap.get(currentItem.id);
            }
            return orderedItems;
        },

        getGradingData: function () {
            return this.gradingData;
        },

        getMediaStream: function () {
            return this.mediaStream;
        },

        getLoadingMessages: function () {
            return this.loadingMessages;
        },

        /**
         * Change auto-create-response mode. When enabled the server VAD creates responses
         * automatically; when disabled the user must explicitly end their turn.
         */
        setAutoCreateResponse: function (enabled) {
            var self = this;
            self.autocreateresponse = enabled;
            self.timebased_vad.create_response = enabled;
            log.debug("OpenAI driver: autocreate response toggled:", enabled);
            if (self.dc && self.dc.readyState === 'open') {
                self.sendEvent({
                    type: "session.update",
                    session: {
                        turn_detection: self.timebased_vad,
                    }
                });
            }
        },

        setDataInputBuffer: function (value, source) {
            var self = this;
            self.datainputbuffer = value;
            log.debug("Data in input buffer set to:", value, 'source:', source);
        },

        /**
         * Open the PeerConnection and negotiate through the Moodle proxy.
         */
        start: async function () {
            var self = this;
            var twoletterlang = self.itemdata.language.substr(0, 2);
            log.debug("OpenAI driver: session starting");
            self._fire('onStateChange', 'connecting');
            self.items = {};
            self.responses = {};
            self.loadingMessages = new Set();
            self._notifyItems();

            // Acquire mic first so the UI can attach a waveform analyser.
            try {
                self.mediaStream = await navigator.mediaDevices.getUserMedia({audio: true});
            } catch (err) {
                log.debug('Failed to getUserMedia', err);
                self._fire('onStateChange', 'error');
                self._fire('onError', err);
                return;
            }
            self._fire('onMediaStreamReady', self.mediaStream);

            log.debug("Opening peer connection...");
            self.pc = new RTCPeerConnection({
                iceServers: [{
                    urls: "stun:stun.l.google.com:19302"
                }]
            });

            log.debug("creating data channel...");
            self.dc = self.pc.createDataChannel("oai-events");

            self.dc.onmessage = (e) => {
                self.eventlogs.push(e.data);
                try {
                    var lines = e.data.split("\n").filter(Boolean);
                    for (var line of lines) {
                        self.handleRTCEvent.call(self, JSON.parse(line));
                    }
                } catch (err) {
                    log.debug("Failed to parse");
                    log.debug(err);
                }
            };
            self.dc.onopen = () => self._handleDataChannelOpen(twoletterlang);

            self.pc.ontrack = (event) => {
                if (self.audioElement) {
                    self.audioElement.srcObject = event.streams[0];
                }
            };

            // Add (muted) mic tracks to the peer connection.
            self.mediaStream.getTracks().forEach((track) => {
                track.enabled = false;
                self.pc.addTrack(track, self.mediaStream);
            });

            var offer = await self.pc.createOffer({offerToReceiveAudio: true});
            await self.pc.setLocalDescription(offer);
            // Server candidates may take up to ~15s to gather.
            await self.waitForIceGathering(self.pc);

            try {
                var sdpResponse = await fetch(M.cfg.wwwroot + "/mod/minilesson/item/audiochat/openairtc.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/sdp"
                    },
                    body: self.pc.localDescription.sdp,
                    signal: self.abortcontroller.signal
                });
                if (!sdpResponse.ok) {
                    log.debug("Failed /rtc:", await sdpResponse.text());
                    self._fire('onStateChange', 'error');
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
                log.debug(e, 'connect error');
                if (e.name === 'AbortError') {
                    log.debug("Session start aborted by user.");
                    self._fire('onStateChange', 'aborted');
                    return;
                }
                self._teardownConnection();
                self._fire('onStateChange', 'error');
                self._fire('onError', e);
                return;
            }

            self._fire('onStateChange', 'connected');
        },

        _handleDataChannelOpen: function (twoletterlang) {
            var self = this;
            log.debug("DataChannel open");

            self.timebased_vad.create_response = self.autocreateresponse;
            log.debug(self.itemdata.audiochatinstructions);
            var updateinstructions = self.itemdata.audiochatinstructions;

            Fragment.loadFragment(
                'minilessonitem_audiochat',
                'audiochat_fetchstudentsubmission',
                M.cfg.contextid,
                {
                    itemid: self.itemdata.id
                }
            ).done(function (studentsubmission) {
                log.debug("Loaded audio chat studentsubmission:", studentsubmission);
                if (studentsubmission) {
                    updateinstructions = updateinstructions.replace('{student submission}', studentsubmission);
                    self.itemdata.audiochatgradeinstructions =
                        self.itemdata.audiochatgradeinstructions.replace('{student submission}', studentsubmission);
                    self.itemdata.studentsubmission = studentsubmission;
                }

                self.sendEvent({
                    type: "session.update",
                    session: {
                        type: 'realtime',
                        instructions: updateinstructions,
                        audio: {
                            input: {
                                transcription: {
                                    language: twoletterlang,
                                    model: "whisper-1"
                                },
                                turn_detection: self.timebased_vad,
                            },
                            output: {
                                speed: 0.9,
                                voice: self.audiochat_voice,
                            }
                        },
                        output_modalities: ["audio"],
                    }
                });

                // response.create overrides session instructions, so we repeat them here.
                var firstmessageinstructions = "Please introduce yourself to the student and explain todays topic.";
                self.sendEvent({
                    type: "response.create",
                    response: {
                        output_modalities: ["audio"],
                        instructions: updateinstructions + " " + firstmessageinstructions,
                        audio: {
                            output: {
                                voice: self.audiochat_voice,
                            },
                        },
                    }
                });
            });
        },

        /**
         * Gracefully end the session: request grading then close the data channel.
         */
        stopSession: function () {
            var self = this;

            log.debug("OpenAI driver: session stopping...");
            self.loadingMessages.clear();
            self._fire('onStateChange', 'stopped');

            if (self.itemdata.audiochatgradeinstructions && self.itemdata.audiochatgradeinstructions !== "") {
                self.sendGradingRequest();
            } else {
                log.debug("Closing session resources...");
                self.closeDataChannel();
            }
            log.debug("Session stopped");
            if (self.inputBufferInterval) {
                clearInterval(self.inputBufferInterval);
                self.inputBufferInterval = null;
            }
        },

        sendGradingRequest: function () {
            var self = this;
            var gradingInstructions =
                "Please provide a percentage score for the session, an explanation of the score (for teachers), " +
                "and feedback (for the student). " +
                self.itemdata.audiochatgradeinstructions +
                "Return the response as JSON in the format: " +
                "{\"score\": \"the score  ( 0-100 ) \", \"gradeexplanation\": \"the explanation\", " +
                "\"feedback\": \"the feedback\"}.";

            var responsedata = {
                // Out-of-band; does not join the default conversation.
                conversation: "none",
                output_modalities: ["text"],
                instructions: gradingInstructions,
                metadata: {tag: self.gradingrequesttag},
                max_output_tokens: 500,
            };

            self.sendEvent({
                type: "response.create",
                response: responsedata,
            });
        },

        abort: function () {
            var self = this;
            if (self.abortcontroller) {
                self.abortcontroller.abort();
                self.abortcontroller = new AbortController();
            }
        },

        closeDataChannel: function () {
            var self = this;
            if (typeof self.dc !== 'undefined' && self.dc) {
                self.dc.close();
                self.dc = null;
            }
            if (typeof self.pc !== 'undefined' && self.pc) {
                self.pc.close();
                self.pc = null;
            }
        },

        _teardownConnection: function () {
            var self = this;
            if (self.dc) {
                try {
                    self.dc.close();
                } catch (e) { log.debug('dc close error', e); }
                self.dc = null;
            }
            if (self.pc) {
                try {
                    self.pc.close();
                } catch (e) { log.debug('pc close error', e); }
                self.pc = null;
            }
            if (self.mediaStream) {
                self.mediaStream.getTracks().forEach((t) => t.stop());
                self.mediaStream = null;
            }
        },

        waitForIceGathering: function (pc, timeout = 15000) {
            return new Promise((resolve) => {
                let timer;
                function checkState() {
                    if (pc.iceGatheringState === "complete") {
                        clearTimeout(timer);
                        pc.removeEventListener("icegatheringstatechange", checkState);
                        resolve();
                    }
                }
                pc.addEventListener("icegatheringstatechange", checkState);
                timer = setTimeout(() => {
                    pc.removeEventListener("icegatheringstatechange", checkState);
                    resolve();
                }, timeout);
            });
        },

        sendEvent: function (obj) {
            var self = this;
            if (self.dc && self.dc.readyState === "open") {
                self.dc.send(JSON.stringify(obj));
            }
        },

        /**
         * Enable or disable the outgoing mic (toggles track.enabled on the RTC sender).
         * When muting with autocreate off, also kicks off a manual response.create.
         */
        setMicActive: function (active) {
            var self = this;
            if (!self.mediaStream) {
                return;
            }
            if (self.pc) {
                self.mediaStream.getTracks().forEach((t) => {
                    t.enabled = !!active;
                });
            }
            if (!active && !self.autocreateresponse) {
                self._sendManualResponse();
            }
        },

        _sendManualResponse: function () {
            var self = this;
            var payload = {
                type: "response.create",
                response: {
                    output_modalities: ["audio"],
                    instructions: self.itemdata.audiochatinstructions,
                    audio: {
                        output: {
                            voice: self.audiochat_voice,
                        },
                    },
                }
            };

            if (!self.datainputbuffer) {
                log.debug(" sending response.create");
                self.sendEvent(payload);
                return;
            }

            // Wait for the server to commit the just-spoken input before asking for a response.
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
                    self.sendEvent(payload);
                }
                attempts++;
            }, 1500);
        },

        switchMic: async function (deviceId) {
            var self = this;
            if (self.mediaStream) {
                self.mediaStream.getTracks().forEach((track) => track.stop());
            }
            try {
                self.mediaStream = await navigator.mediaDevices.getUserMedia({
                    audio: {deviceId: {exact: deviceId}}
                });
                if (self.pc) {
                    const senders = self.pc.getSenders();
                    const audioTrack = self.mediaStream.getAudioTracks()[0];
                    const audioSender = senders.find((s) => s.track && s.track.kind === 'audio');
                    if (audioSender) {
                        audioSender.replaceTrack(audioTrack);
                    }
                }
                self._fire('onMediaStreamReady', self.mediaStream);
                log.debug("Switched microphone to:" + deviceId);
            } catch (err) {
                log.debug("Failed to switch microphone:");
                log.debug(err);
            }
        },

        releaseResources: function () {
            var self = this;
            if (self.mediaStream) {
                self.mediaStream.getTracks().forEach((track) => {
                    if (typeof self.pc !== 'undefined' && self.pc) {
                        const sender = self.pc.getSenders().find((s) => s.track === track);
                        if (sender) {
                            self.pc.removeTrack(sender);
                        }
                    }
                    track.stop();
                });
                self.mediaStream = null;
            }
        },

        /**
         * Handle a single parsed event from the realtime DataChannel.
         * Updates items/responses state and fires callbacks for UI side-effects.
         */
        handleRTCEvent: function (msg) {
            var self = this;
            log.debug("Received event:");
            log.debug(msg);

            // Grading response (out-of-band via the gradingrequest tag).
            if (msg.type === "response.done" &&
                msg.response.metadata?.tag === self.gradingrequesttag) {
                log.debug("It is a grading event:");
                var jsonresponse;
                try {
                    jsonresponse = msg.response.output[0].content[0].text;
                    const jsonextractregex = /\{[\s\S]*?\}/;
                    if (!jsonresponse || jsonresponse === "" || !jsonresponse.match(jsonextractregex)) {
                        log.debug("No valid grading data received .. msg is ..");
                        log.debug(msg);
                        if (self.gradeRequestTrial < self.maxGradeRequestTrial) {
                            self.gradeRequestTrial++;
                            self.sendGradingRequest();
                            log.debug('Grading Request Retry number: ' + self.gradeRequestTrial);
                            return;
                        }
                        self.closeDataChannel();
                        return;
                    }
                    if (self.gradeRequestTrial > 0) {
                        self.gradeRequestTrial = 0;
                    }
                    self.gradingData = JSON.parse(jsonresponse.match(jsonextractregex)[0]);
                    log.debug("Grading and Feedback:", self.gradingData);
                    self._fire('onGradingData', self.gradingData);
                } catch (err) {
                    self.gradingData = false;
                    log.debug("Failed to parse grading feedback:", err);
                    log.debug(jsonresponse);
                }
                self.closeDataChannel();
                self._fire('onGradingWindowClosed');
                return;
            }

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
                    self._fire('onScrollRequested');
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
                    self.responses[msg.response.id].stack.push(msg);
                    break;
                }
                case "response.output_item.added": {
                    self.responses[msg.response_id].stack.push(msg);
                    break;
                }
                case "conversation.item.added":
                case "conversation.item.created": {
                    self.items[msg.item.id].previous_item_id = msg.previous_item_id;
                    self.items[msg.item.id].usertype = msg.item.role;
                    self.items[msg.item.id].events.push(msg);
                    if (msg.item.role === 'assistant') {
                        self.loadingMessages.add(msgitem_id);
                    }
                    break;
                }
                case "response.content_part.added": {
                    self._fire('onMicAvailabilityChange', true);
                    self.responses[msg.response_id].stack.push(msg);
                    break;
                }
                case "response.output_audio_transcript.delta":
                case "response.audio_transcript.delta": {
                    self.responses[msg.response_id].stack.push(msg);
                    self.items[msg.item_id].content += msg.delta;
                    break;
                }
                case "output_audio_buffer.cleared": {
                    self.responses[msg.response_id].stack.push(msg);
                    break;
                }
                case "response.output_audio.done":
                case "response.audio.done": {
                    self.responses[msg.response_id].stack.push(msg);
                    break;
                }
                case "response.output_audio_transcript.done":
                case "response.audio_transcript.done": {
                    self.responses[msg.response_id].stack.push(msg);
                    self.items[msg.item_id].content = msg.transcript;
                    break;
                }
                case "response.content_part.done": {
                    self.responses[msg.response_id].stack.push(msg);
                    break;
                }
                case "response.output_item.done": {
                    self.responses[msg.response_id].stack.push(msg);
                    self.loadingMessages.delete(msg.item.id);
                    break;
                }
                case "response.done": {
                    self.responses[msg.response.id].stack.push(msg);
                    break;
                }
                case "output_audio_buffer.stopped": {
                    self._fire('onOutputAudioStopped');
                    self.responses[msg.response_id].stack.push(msg);
                    break;
                }
                case "conversation.item.truncated": {
                    self.items[msg.item_id].events.push(msg);
                    break;
                }
                // User events.
                case "input_audio_buffer.speech_started": {
                    log.debug("Input audio buffer speech started");
                    self.setDataInputBuffer(true, 'audiobufferstarted');
                    self.items[msg.item_id].events.push(msg);
                    break;
                }
                case "input_audio_buffer.speech_stopped": {
                    self._fire('onUserSpeechStopped');
                    self.items[msg.item_id].events.push(msg);
                    break;
                }
                case "input_audio_buffer.committed": {
                    log.debug("Input audio buffer committed");
                    self.setDataInputBuffer(false, 'audiobuffercommitted');
                    self.items[msg.item_id].events.push(msg);
                    break;
                }
                case "conversation.item.input_audio_transcription.delta": {
                    self.items[msg.item_id].events.push(msg);
                    self.items[msg.item_id].content += msg.delta;
                    break;
                }
                case "conversation.item.input_audio_transcription.completed": {
                    self.items[msg.item_id].events.push(msg);
                    self.items[msg.item_id].content = msg.transcript;
                    self.loadingMessages.delete(msg.item_id);
                    break;
                }
            }
            self._notifyItems();
        },
    };
});
