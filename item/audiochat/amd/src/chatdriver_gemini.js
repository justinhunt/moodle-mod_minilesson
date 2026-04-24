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
 * audiochat Gemini Live API (WebSocket) chat driver.
 *
 * Implements the same driver interface as chatdriver_openai but uses the
 * Gemini Live API over a WebSocket, streaming 16 kHz PCM audio up and playing
 * 24 kHz PCM audio down via the Web Audio API.
 *
 * Backend contract: the driver fetches geminilive.php?contextid=N and expects
 * JSON {url, ephemeralToken, model} used to open the WebSocket.
 *
 * @module     minilessonitem_audiochat/chatdriver_gemini
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/log', 'core/fragment'], function ($, log, Fragment) {
    "use strict";

    log.debug('MiniLesson AudioChat: Gemini driver loading');

    var INPUT_SAMPLE_RATE = 16000;
    var OUTPUT_SAMPLE_RATE = 24000;
    var DEFAULT_MODEL = 'gemini-3.1-flash-live-preview';

    return {
        // Runtime state.
        itemdata: {},
        audioElement: null,
        callbacks: {},

        // Transport.
        ws: null,
        mediaStream: null,
        inputAudioCtx: null,
        outputAudioCtx: null,
        scriptProcessor: null,
        sourceNode: null,
        _micEnabled: false,
        _setupComplete: false,
        _stopped: false,

        // Conversation state.
        items: {},
        loadingMessages: null,
        currentUserItemId: null,
        currentAssistantItemId: null,
        itemCounter: 0,

        // Playback scheduling for raw PCM output.
        nextPlayTime: 0,

        // Grading.
        gradingData: false,
        _gradingPending: false,
        _gradingBuffer: '',

        // Session options.
        audiochat_voice: 'Aoede',
        autocreateresponse: false,
        datainputbuffer: false,
        setupConfig: {
            model: 'models/' + DEFAULT_MODEL,
            generationConfig: {
                responseModalities: ['AUDIO'],
                temperature: 0.7,
                speechConfig: {
                    voiceConfig: {
                        prebuiltVoiceConfig: {
                            voiceName: self.audiochat_voice
                        }
                    }
                }
            },
            realtimeInputConfig: {
                automaticActivityDetection: {
                    disabled: false,
                }
            },
            sessionResumption: {},
            inputAudioTranscription: {},
            outputAudioTranscription: {}
        },
        sessionResumptionToken: null,
        resumingSession: false,

        clone: function () {
            return $.extend(true, {}, this);
        },

        init: function (options) {
            var self = this;
            self.itemdata = options.itemdata;
            self.audioElement = options.audioElement;
            self.callbacks = options.callbacks || {};
            self.autocreateresponse = options.itemdata.audiochat_autoresponse || false;
            self.audiochat_voice = self._resolveVoice(options.itemdata.audiochat_voice);
            self.items = {};
            self.loadingMessages = new Set();
            self.gradingData = false;
            self.itemCounter = 0;
            self.currentUserItemId = null;
            self.currentAssistantItemId = null;
            self._pendingFirstMessage = null;
            self._setupComplete = false;
        },

        _resolveVoice: function (voice) {
            var voiceMap = {
                'alloy': 'Aoede', 'ash': 'Charon', 'ballad': 'Kore',
                'coral': 'Aoede', 'echo': 'Puck', 'sage': 'Charon',
                'shimmer': 'Aoede', 'verse': 'Puck', 'marin': 'Fenrir', 'cedar': 'Charon',
            };
            var geminiVoices = ['Aoede', 'Charon', 'Fenrir', 'Kore', 'Puck'];
            if (voice && geminiVoices.includes(voice)) {
                return voice;
            }
            return voiceMap[voice] || 'Aoede';
        },

        // ── Callback helpers ──

        _fire: function (name) {
            var cb = this.callbacks[name];
            if (typeof cb === 'function') {
                cb.apply(null, Array.prototype.slice.call(arguments, 1));
            }
        },

        _notifyItems: function () {
            this._fire('onItemsChanged', this.getOrderedItems(), this.loadingMessages);
        },

        // ── Public getters (UI contract) ──

        getItems: function () { return this.items; },

        getOrderedItems: function () {
            return Object.values(this.items).sort(function (a, b) { return a.order - b.order; });
        },

        getGradingData: function () { return this.gradingData; },
        getMediaStream: function () { return this.mediaStream; },
        getLoadingMessages: function () { return this.loadingMessages; },

        setAutoCreateResponse: function (enabled) {
            var self = this;
            self.autocreateresponse = enabled;
            log.debug('Gemini driver: autocreate response toggled:');
            log.debug(enabled);
            self._fetchSessionInfo().then(function(sessionInfo) {
                if (self.ws && self.ws.readyState === WebSocket.OPEN) {
                    self.ws.close();
                }
                self.resumingSession = true;
                return self._openWebSocket(sessionInfo).then(function() {
                    self._sendSetupAndFirstTurn(sessionInfo.model);
                });
            });
        },

        setDataInputBuffer: function (value, source) {
            this.datainputbuffer = value;
            log.debug('Gemini driver: setDataInputBuffer');
            log.debug(value);
            log.debug(source);
        },

        // ── Session lifecycle ──

        start: async function () {
            var self = this;
            log.debug('Gemini driver: session starting');
            self._fire('onStateChange', 'connecting');
            self.items = {};
            self.loadingMessages = new Set();
            self._setupComplete = false;
            self._notifyItems();

            // 1. Acquire mic.
            try {
                self.mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (err) {
                log.debug('Gemini driver: getUserMedia failed');
                log.debug(err);
                self._fire('onStateChange', 'error');
                self._fire('onError', err);
                return;
            }
            self._fire('onMediaStreamReady', self.mediaStream);

            // 2. Fetch ephemeral token.
            var sessionInfo;
            try {
                sessionInfo = await self._fetchSessionInfo();
            } catch (err) {
                log.debug('Gemini driver: session info fetch failed');
                log.debug(err);
                self._stopped = true;
                self._teardownConnection();
                self._fire('onStateChange', 'error');
                self._fire('onError', err);
                return;
            }

            // 3. Open WebSocket.
            try {
                await self._openWebSocket(sessionInfo);
            } catch (err) {
                log.debug('Gemini driver: ws open failed');
                log.debug(err);
                self._stopped = true;
                self._teardownConnection();
                self._fire('onStateChange', 'error');
                self._fire('onError', err);
                return;
            }

            // 4. Build mic capture pipeline.
            try {
                await self._startMicPipeline();
            } catch (err) {
                log.debug('Gemini driver: mic pipeline failed');
                log.debug(err);
                self._stopped = true;
                self._teardownConnection();
                self._fire('onStateChange', 'error');
                self._fire('onError', err);
                return;
            }

            // 5. Send setup (first turn is queued until setupComplete arrives).
            self._sendSetupAndFirstTurn(sessionInfo.model);
            self._fire('onStateChange', 'connected');
        },

        _fetchSessionInfo: function () {
            const self = this;
            const formData = new FormData();
            formData.append('contextid', M.cfg.contextid);
            formData.append('sesskey', M.cfg.sesskey);
            formData.append('voice', self.audiochat_voice);
            formData.append('disablevad', !self.autocreateresponse);
            return fetch(
                M.cfg.wwwroot + '/mod/minilesson/item/audiochat/geminilive.php',
                {
                    method: 'POST',
                    body: formData
                }
            ).then(function (r) {
                if (!r.ok) {
                    throw new Error('geminilive endpoint returned ' + r.status);
                }
                return r.json();
            });
        },

        _openWebSocket: function (sessionInfo) {
            var self = this;
            return new Promise(function (resolve, reject) {
                var url = sessionInfo.url;
                if (sessionInfo.ephemeralToken) {
                    url += (url.indexOf('?') === -1 ? '?' : '&') +
                        'access_token=' + encodeURIComponent(sessionInfo.ephemeralToken);
                }
                self.ws = new WebSocket(url);
                self.ws.binaryType = 'arraybuffer';
                self.ws.onopen = function () {
                    log.debug('Gemini WS open');
                    resolve();
                };
                self.ws.onerror = function (err) {
                    log.debug('Gemini WS error');
                    log.debug(err);
                    reject(err);
                };
                self.ws.onclose = function (ev) {
                    log.debug('Gemini WS closed');
                    log.debug(ev.code);
                    log.debug(ev.reason);
                };
                self.ws.onmessage = function (ev) { self._handleWSMessage(ev); };
            });
        },

        /**
         * Send the BidiGenerateContentSetup message and queue the first text
         * turn. The first turn is held back until setupComplete is received.
         */
        _sendSetupAndFirstTurn: function (model) {
            var self = this;
            var instructions = self.itemdata.audiochatinstructions || '';

            Fragment.loadFragment(
                'minilessonitem_audiochat',
                'audiochat_fetchstudentsubmission',
                M.cfg.contextid,
                { itemid: self.itemdata.id }
            ).done(function (studentsubmission) {
                log.debug('Gemini: Loaded studentsubmission:');
                log.debug(studentsubmission);
                if (studentsubmission) {
                    instructions = instructions.replace('{student submission}', studentsubmission);
                    self.itemdata.audiochatgradeinstructions =
                        self.itemdata.audiochatgradeinstructions.replace('{student submission}', studentsubmission);
                    self.itemdata.studentsubmission = studentsubmission;
                }

                // Send setup – this MUST be the very first message on the WS.
                // The setup fields must match what is in the ephemeral token's
                // bidiGenerateContentSetup (Constrained endpoint enforces this).
                log.debug('Gemini: sending setup message, WS state:');
                log.debug(self.ws ? self.ws.readyState : 'null');
                self.setupConfig.model = 'models/' + (model || DEFAULT_MODEL);
                self.setupConfig.realtimeInputConfig.automaticActivityDetection.disabled = !self.autocreateresponse;
                self.setupConfig.sessionResumption = {};
                if (self.sessionResumptionToken) {
                    self.setupConfig.sessionResumption.handle = self.sessionResumptionToken;
                    self.sessionResumptionToken = null;
                }
                self._sendWS({
                    setup: self.setupConfig
                });
                log.debug('Gemini: setup message sent');

                // If resuming session then no need to send first instruction.
                if (self.resumingSession) {
                    if (self._micEnabled && !self.autocreateresponse) {
                        self._sendWS({
                            realtimeInput: {
                                activityStart: {}
                            }
                        });
                    }
                    self.resumingSession = false;
                    return;
                }

                // Queue the first turn – sent once setupComplete arrives.
                // Instructions are included here (not in setup.systemInstruction)
                // because the Constrained endpoint strips fields not in the token.
                self._pendingFirstMessage = instructions +
                    '\n\nPlease introduce yourself to the student and explain todays topic.';
                log.debug('Gemini: pendingFirstMessage set, setupComplete:');
                log.debug(self._setupComplete);

                // If setupComplete already arrived (unlikely but possible).
                if (self._setupComplete && self._pendingFirstMessage) {
                    self._flushPendingFirstMessage();
                }
            });
        },

        _flushPendingFirstMessage: function () {
            var self = this;
            if (!self._pendingFirstMessage) {
                log.debug('Gemini: _flushPendingFirstMessage called but no pending message');
                return;
            }
            log.debug('Gemini: flushing first message:');
            log.debug(self._pendingFirstMessage);
            // Use realtimeInput.text per Google's WS tutorial (not clientContent).
            self._sendWS({
                realtimeInput: {
                    text: self._pendingFirstMessage
                }
            });
            log.debug('Gemini: first message sent via realtimeInput.text, waiting for model response...');
            self._pendingFirstMessage = null;
        },

        _sendWS: function (obj) {
            var self = this;
            if (self.ws && self.ws.readyState === WebSocket.OPEN) {
                var keys = Object.keys(obj);
                log.debug('Gemini: _sendWS:');
                log.debug(keys.join(','));
                log.debug('WS state:');
                log.debug(self.ws.readyState);
                self.ws.send(JSON.stringify(obj));
            } else {
                log.debug('Gemini: _sendWS SKIPPED, WS not open. State:');
                log.debug(self.ws ? self.ws.readyState : 'null');
            }
        },

        // ── Mic capture pipeline (16 kHz PCM16-LE) ──

        _startMicPipeline: async function () {
            var self = this;
            var AC = window.AudioContext || window.webkitAudioContext;

            try {
                self.inputAudioCtx = new AC({ sampleRate: INPUT_SAMPLE_RATE });
            } catch (_e) {
                self.inputAudioCtx = new AC();
            }

            self.sourceNode = self.inputAudioCtx.createMediaStreamSource(self.mediaStream);
            self.scriptProcessor = self.inputAudioCtx.createScriptProcessor(4096, 1, 1);
            self.scriptProcessor.onaudioprocess = function (event) { self._processAudioFrame(event); };
            // ScriptProcessor must be connected to destination to fire.
            self.scriptProcessor.connect(self.inputAudioCtx.destination);

            // Separate output context at 24 kHz for playback.
            self.outputAudioCtx = new AC({ sampleRate: OUTPUT_SAMPLE_RATE });
            self.nextPlayTime = self.outputAudioCtx.currentTime;
        },

        _processAudioFrame: function (event) {
            var self = this;
            if (!self._micEnabled || !self.ws || self.ws.readyState !== WebSocket.OPEN) {
                return;
            }
            var input = event.inputBuffer.getChannelData(0);
            self._sendAudio(input);
        },

        _sendAudio: function (input) {
            var self = this;
            var pcm16;
            if (self.inputAudioCtx.sampleRate === INPUT_SAMPLE_RATE) {
                pcm16 = self._floatToPCM16(input);
            } else {
                pcm16 = self._floatToPCM16(
                    self._resampleLinear(input, self.inputAudioCtx.sampleRate, INPUT_SAMPLE_RATE)
                );
            }
            self._sendWS({
                realtimeInput: {
                    audio: {
                        mimeType: 'audio/pcm;rate=' + INPUT_SAMPLE_RATE,
                        data: self._arrayBufferToBase64(pcm16)
                    }
                }
            });
        },

        // ── Audio conversion helpers ──

        _floatToPCM16: function (float32) {
            var buffer = new ArrayBuffer(float32.length * 2);
            var view = new DataView(buffer);
            for (var i = 0; i < float32.length; i++) {
                var s = Math.max(-1, Math.min(1, float32[i]));
                view.setInt16(i * 2, s < 0 ? s * 0x8000 : s * 0x7FFF, true); // little-endian
            }
            return buffer;
        },

        _resampleLinear: function (input, inRate, outRate) {
            var ratio = inRate / outRate;
            var newLen = Math.round(input.length / ratio);
            var out = new Float32Array(newLen);
            for (var i = 0; i < newLen; i++) {
                var pos = i * ratio;
                var left = Math.floor(pos);
                var right = Math.min(left + 1, input.length - 1);
                var frac = pos - left;
                out[i] = input[left] * (1 - frac) + input[right] * frac;
            }
            return out;
        },

        _arrayBufferToBase64: function (buffer) {
            var bytes = new Uint8Array(buffer);
            var binary = '';
            for (var i = 0; i < bytes.byteLength; i++) {
                binary += String.fromCharCode(bytes[i]);
            }
            return btoa(binary);
        },

        _base64ToArrayBuffer: function (base64) {
            var binary = atob(base64);
            var len = binary.length;
            var bytes = new Uint8Array(len);
            for (var i = 0; i < len; i++) {
                bytes[i] = binary.charCodeAt(i);
            }
            return bytes.buffer;
        },

        _pcm16ToFloat32: function (buffer) {
            var view = new DataView(buffer);
            var len = buffer.byteLength / 2;
            var out = new Float32Array(len);
            for (var i = 0; i < len; i++) {
                var v = view.getInt16(i * 2, true); // little-endian
                out[i] = v < 0 ? v / 0x8000 : v / 0x7FFF;
            }
            return out;
        },

        // ── PCM playback ──

        _playPCMChunk: function (base64) {
            var self = this;
            if (!self.outputAudioCtx) {
                return;
            }
            var buf = self._base64ToArrayBuffer(base64);
            var float32 = self._pcm16ToFloat32(buf);
            if (float32.length === 0) {
                return;
            }
            var audioBuffer = self.outputAudioCtx.createBuffer(1, float32.length, OUTPUT_SAMPLE_RATE);
            audioBuffer.copyToChannel(float32, 0);

            var source = self.outputAudioCtx.createBufferSource();
            source.buffer = audioBuffer;
            source.connect(self.outputAudioCtx.destination);

            var now = self.outputAudioCtx.currentTime;
            if (self.nextPlayTime < now) {
                self.nextPlayTime = now;
            }
            source.start(self.nextPlayTime);
            self.nextPlayTime += audioBuffer.duration;
        },

        // ── WebSocket message handling ──

        _handleWSMessage: function (ev) {
            var self = this;
            var msg;
            try {
                msg = (typeof ev.data === 'string')
                    ? JSON.parse(ev.data)
                    : JSON.parse(new TextDecoder().decode(ev.data));
            } catch (err) {
                log.debug('Gemini WS parse failed');
                log.debug(err);
                return;
            }

            // setupComplete – gate for first turn.
            if (msg.setupComplete) {
                log.debug('Gemini: setupComplete received');
                self._setupComplete = true;
                if (self._pendingFirstMessage) {
                    self._flushPendingFirstMessage();
                }
                return;
            }

            if (msg.toolCall || msg.toolCallCancellation) {
                return;
            }

            if (msg.sessionResumptionUpdate && msg.sessionResumptionUpdate.resumable) {
                log.debug('Session resumption message received:');
                log.debug(msg);
                self.sessionResumptionToken = msg.sessionResumptionUpdate.newHandle;
            }

            if (msg.serverContent) {
                log.debug('Gemini message:');
                log.debug(msg);
                self._handleServerContent(msg.serverContent);
            }
        },

        _handleServerContent: function (content) {
            var self = this;

            // Grading in-flight: buffer text, skip audio.
            if (self._gradingPending) {
                self._accumulateGrading(content);
                if (content.generationComplete) {
                    self._finalizeGrading();
                }
                return;
            }

            // User input transcription.
            if (content.inputTranscription && content.inputTranscription.text) {
                if (!self.currentUserItemId) {
                    self.currentUserItemId = self._newItem('user');
                }
                self.items[self.currentUserItemId].content += content.inputTranscription.text;
            }

            // Model turn (audio + optional text).
            if (content.modelTurn && content.modelTurn.parts) {
                if (!self.currentAssistantItemId) {
                    self.currentAssistantItemId = self._newItem('assistant');
                    self.loadingMessages.add(self.currentAssistantItemId);
                    self._fire('onMicAvailabilityChange', true);
                }
                var item = self.items[self.currentAssistantItemId];
                content.modelTurn.parts.forEach(function (part) {
                    if (part.inlineData && part.inlineData.data) {
                        self._playPCMChunk(part.inlineData.data);
                    }
                    if (part.text) {
                        item.content += part.text;
                    }
                });
            }

            // Output transcription (text mirror of spoken audio).
            if (content.outputTranscription && content.outputTranscription.text) {
                if (!self.currentAssistantItemId) {
                    self.currentAssistantItemId = self._newItem('assistant');
                    self.loadingMessages.add(self.currentAssistantItemId);
                }
                self.items[self.currentAssistantItemId].content += content.outputTranscription.text;
            }

            // Interrupted by barge-in.
            if (content.interrupted) {
                if (self.currentAssistantItemId) {
                    self.loadingMessages.delete(self.currentAssistantItemId);
                    self.currentAssistantItemId = null;
                }
            }

            // Turn complete.
            if (content.turnComplete) {
                if (self.currentAssistantItemId) {
                    self.loadingMessages.delete(self.currentAssistantItemId);
                    self.currentAssistantItemId = null;
                }
                self.currentUserItemId = null;
                self._fire('onOutputAudioStopped');
            }

            self._notifyItems();
        },

        _newItem: function (role) {
            var self = this;
            var id = 'gemini_' + (++self.itemCounter);
            self.items[id] = {
                id: id,
                usertype: role,
                content: '',
                order: self.itemCounter,
                events: [],
                previous_item_id: null
            };
            return id;
        },

        // ── Grading ──

        _accumulateGrading: function (content) {
            var self = this;
            if (content.modelTurn && content.modelTurn.parts) {
                content.modelTurn.parts.forEach(function (part) {
                    if (part.text) {
                        self._gradingBuffer += part.text;
                    }
                });
            }
            if (content.outputTranscription && content.outputTranscription.text) {
                self._gradingBuffer += content.outputTranscription.text;
            }
        },

        _finalizeGrading: function () {
            var self = this;
            self._gradingPending = false;
            var text = self._gradingBuffer;
            log.debug('Gemini: received grading text');
            log.debug(text);
            self._gradingBuffer = '';
            try {
                var match = text.match(/\{[\s\S]*?\}/);
                if (!match) {
                    log.debug('No valid grading JSON in Gemini buffer:');
                    log.debug(text);
                    self.gradingData = false;
                    return;
                }
                self.gradingData = JSON.parse(match[0]);
                log.debug('Gemini grading:');
                log.debug(self.gradingData);
                self._fire('onGradingData', self.gradingData);
            } catch (err) {
                log.debug('Failed to parse Gemini grading:', err, text);
                self.gradingData = false;
            }
            log.debug('Gemini: closing session resources');
            self.closeDataChannel();
            self._fire('onGradingWindowClosed');
        },

        // ── Mic control ──

        setMicActive: function (active) {
            var self = this;
            self._micEnabled = !!active;
            if (active) {
                if (self.sourceNode && self.scriptProcessor) {
                    try { self.sourceNode.connect(self.scriptProcessor); } catch (e) {
                        log.debug('Gemini: mic connect failed');
                        log.debug(e);
                    }
                }
                if (!self.autocreateresponse) {
                    self._sendWS({
                        realtimeInput: {
                            activityStart: {}
                        }
                    });
                }
            } else {
                if (self.sourceNode && self.scriptProcessor) {
                    try { self.sourceNode.disconnect(self.scriptProcessor); } catch (e) {
                        log.debug('Gemini: mic disconnect failed');
                        log.debug(e);
                    }
                }
                if (!self.autocreateresponse) {
                    self._sendWS({
                        realtimeInput: {
                            activityEnd: {}
                        }
                    });
                }
            }
        },

        assembleChunks: function (chunks) {
            // 1. Calculate total size
            const totalLength = chunks.reduce((acc, chunk) => acc + chunk.length, 0);

            // 2. Pre-allocate the final array
            const combinedArray = new Float32Array(totalLength);

            // 3. Copy chunks into the final array
            let offset = 0;
            for (const chunk of chunks) {
                combinedArray.set(chunk, offset);
                offset += chunk.length;
            }

            return combinedArray;
        },

        // ── Session stop / grading ──

        stopSession: function () {
            var self = this;
            log.debug('Gemini driver: session stopping');
            self.loadingMessages.clear();
            self._fire('onStateChange', 'stopped');
            self._stopped = true;

            if (self.itemdata.audiochatgradeinstructions && self.itemdata.audiochatgradeinstructions !== '') {
                log.debug('Gemini: sending grading request');
                self.sendGradingRequest();
            } else {
                log.debug('Gemini: no grading instructions, closing session');
                self._stopped = true;
                self.closeDataChannel();
            }
            log.debug('Gemini session stopped');
        },

        sendGradingRequest: function () {
            var self = this;
            var gradingInstructions =
                'Please provide a percentage score for the session, an explanation of the score (for teachers), ' +
                'and feedback (for the student). ' + self.itemdata.audiochatgradeinstructions +
                ' Return ONLY a JSON object in the format: ' +
                '{"score": "the score (0-100)", "gradeexplanation": "the explanation", "feedback": "the feedback"}.';
            self._gradingPending = true;
            self._gradingBuffer = '';
            if (self._micEnabled && !self.autocreateresponse) {
                self._sendWS({
                    realtimeInput: {
                        activityEnd: {}
                    }
                });
            }
            self._sendWS({
                realtimeInput: {
                    text: gradingInstructions
                }
            });
        },

        abort: function () {
            this.closeDataChannel();
        },

        closeDataChannel: function () {
            this._teardownConnection();
        },

        _teardownConnection: function () {
            var self = this;
            if (self.ws && self._stopped) {
                try { self.ws.close(); } catch (e) { log.debug('Gemini: ws close error');log.debug(e); }
                self.ws = null;
            }
            if (self.scriptProcessor) {
                try { self.scriptProcessor.disconnect(); } catch (e) { log.debug(e); }
                self.scriptProcessor = null;
            }
            if (self.sourceNode) {
                try { self.sourceNode.disconnect(); } catch (e) { log.debug(e); }
                self.sourceNode = null;
            }
            if (self.inputAudioCtx) {
                try { self.inputAudioCtx.close(); } catch (e) { log.debug(e); }
                self.inputAudioCtx = null;
            }
            if (self.outputAudioCtx) {
                try { self.outputAudioCtx.close(); } catch (e) { log.debug(e); }
                self.outputAudioCtx = null;
            }
        },

        switchMic: async function (deviceId) {
            var self = this;
            var wasEnabled = self._micEnabled;
            if (self.mediaStream) {
                self.mediaStream.getTracks().forEach(function (t) { t.stop(); });
            }
            try {
                self.mediaStream = await navigator.mediaDevices.getUserMedia({
                    audio: { deviceId: { exact: deviceId } }
                });
                if (self.sourceNode) {
                    try { self.sourceNode.disconnect(); } catch (e) { log.debug(e); }
                }
                self.sourceNode = self.inputAudioCtx.createMediaStreamSource(self.mediaStream);
                if (wasEnabled && self.scriptProcessor) {
                    self.sourceNode.connect(self.scriptProcessor);
                }
                self._fire('onMediaStreamReady', self.mediaStream);
                log.debug('Gemini: switched microphone to ' + deviceId);
            } catch (err) {
                log.debug('Gemini: switchMic failed');
                log.debug(err);
            }
        },

        releaseResources: function () {
            var self = this;
            if (self.mediaStream) {
                self.mediaStream.getTracks().forEach(function (t) { t.stop(); });
                self.mediaStream = null;
            }
            self._teardownConnection();
        },
    };
});
