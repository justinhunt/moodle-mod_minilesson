define(['jquery', 'core/log', 'mod_minilesson/definitions',
    'mod_minilesson/ttrecorder',  'core/templates'],
    function($, log, def, ttrecorder, templates) {
  "use strict"; // jshint ;_;

  /*
  This file is to manage the free speaking item type
   */

  log.debug('MiniLesson AudioChat: initialising');

  return {

    controls: {}, //controls for the item
    itemdata: {}, //item data for the item
    index: 0, //index of the item in the quiz
    quizhelper: {}, //quiz helper for the item
    pc: null,
    dc: null,
    micStream: null,

    //for making multiple instances
      clone: function () {
          return $.extend(true, {}, this);
     },

    init: function(index, itemdata, quizhelper) {
      this.itemdata = itemdata;
      log.debug('itemdata',itemdata);
      this.quizhelper = quizhelper;
      this.index = index;
      this.init_controls(quizhelper,itemdata);
      this.register_events(index, itemdata, quizhelper);

    },

    next_question: function() {
      var self = this;
      var stepdata = {};
      stepdata.index = self.index;
      stepdata.hasgrade = true;
      stepdata.totalitems = self.itemdata.totalmarks;
      stepdata.correctitems = self.itemdata.totalmarks;
      stepdata.grade = 1;
      stepdata.resultsdata = {};
      self.quizhelper.do_next(stepdata);
    },

    register_events: function(index, itemdata, quizhelper) {
      
      var self = this;

      self.controls.startbutton.on("click", function(e) {
        self.startSession();
      });

      self.controls.stopbutton.on("click", function(e) {
        self.stopSession();
      });

      // Push to talk button - currently hidden because we dont need it yet
      self.controls.talkbutton.on("click", function(e) {
        self.pushToTalk();
      });

      self.controls.nextbutton.on('click', function(e) {
        self.next_question();
      });

      $("#" + itemdata.uniqueid + "_container").on('showElement', () => {
        if (itemdata.timelimit > 0) {
            $("#" + itemdata.uniqueid + "_container .progress-container").show();
            $("#" + itemdata.uniqueid + "_container .progress-container i").show();
            $("#" + itemdata.uniqueid + "_container .progress-container #progresstimer").progressTimer({
                height: '5px',
                timeLimit: itemdata.timelimit,
                onFinish: function() {
                    nextbutton.trigger('click');
                }
            });
        }
      });

    },

    init_controls: function() {
        var self = this;
        self.controls = {
          //ml_audiochat_sessionpending_box
          'sessionpending': $("#" + self.itemdata.uniqueid + "_container .ml_audiochat_sessionpending_box"),
          'sessionactive': $("#" + self.itemdata.uniqueid + "_container .ml_audiochat_sessionactive_box"),
          'startbutton': $("#" + self.itemdata.uniqueid + "_container .ml_ac_start"),
          'stopbutton': $("#" + self.itemdata.uniqueid + "_container .ml_ac_stop"),
          'talkbutton': $("#" + self.itemdata.uniqueid + "_container .ml_ac_talk"),
          'studenttranscript': $("#" + self.itemdata.uniqueid + "_container .ml_ac_studenttranscript"),
          'aitranscript': $("#" + self.itemdata.uniqueid + "_container .ml_ac_aitranscript"),
          'hiddenaudio': $("#" + self.itemdata.uniqueid + "_container .ml_ac_hiddenaudio"),
          'nextbutton': $("#" + self.itemdata.uniqueid + "_container .minilesson_nextbutton"),
      };
   },

   waitForIceGathering: function(pc) {
    return new Promise((resolve) => {
      if (pc.iceGatheringState === "complete") resolve();
      else {
        function checkState() {
          if (pc.iceGatheringState === "complete") {
            pc.removeEventListener("icegatheringstatechange", checkState);
            resolve();
          }
        }
        pc.addEventListener("icegatheringstatechange", checkState);
      }
    });
  },

  sendEvent: function(obj) {
    var self = this;
    if (self.dc && self.dc.readyState === "open") {
      self.dc.send(JSON.stringify(obj));
    }
  },

  handleRTCEvent: function(msg) {
    var self = this;
    var aitranscript = self.controls.aitranscript[0];
    var studenttranscript = self.controls.studenttranscript[0];
    log.debug("Received event:", msg);

    switch (msg.type) {
      case "response.done":
        if(msg.response) {
          if (msg.response.output && msg.response.output[0].content) {
            const content = msg.response.output[0].content[0];
            if (content.transcript) {
              aitranscript.textContent += content.transcript + "\n";
            }
          }
        }
        break;
      case "response.output_text.delta":
        aitranscript.textContent += msg.delta;
        break;
      case "response.output_text.completed":
        aitranscript.textContent += "\n";
        break;
      case "conversation.item.input_audio_transcription.completed":
      case "input_audio_buffer.speech_recognized":
        if (msg.transcript) studenttranscript.textContent += msg.transcript + "\n";
        break;
    }
  },

  stopSession: function() {
    var self = this;
    log.debug("Session stopping...");
    var startBtn = self.controls.startbutton[0];
    var stopBtn = self.controls.stopbutton[0];
      // Close data channel if open
      if (typeof self.dc !== 'undefined' && self.dc) {
          self.dc.close();
          self.dc = null;
      }
      // Close peer connection if open
      if (typeof self.pc !== 'undefined' && self.pc) {
          self.pc.close();
          self.pc = null;
      }
      // Stop microphone stream if open
      if (typeof self.micStream !== 'undefined' && self.micStream) {
          self.micStream.getTracks().forEach(track => track.stop());
          self.micStream = null;
      }
      startBtn.disabled = false;
      stopBtn.disabled = true;
      self.controls.sessionpending.hide();
      self.controls.sessionactive.hide();
      log.debug("Session stopped");
  },

  startSession: async function() {
    var self = this;
    log.debug("Session starting");
    var startBtn = self.controls.startbutton[0];
    var stopBtn = self.controls.stopbutton[0];
    var talkBtn = self.controls.talkbutton[0];
    var twoletterlang = self.itemdata.language.substr(0, 2);
    var twoletternativelang = self.itemdata.audiochatnativelanguage.substr(0, 2);
    var hiddenaudio = self.controls.hiddenaudio[0];
    startBtn.disabled = true;

    // Show the session pending box and hide the session active box
    self.controls.sessionpending.show();
    self.controls.sessionactive.hide();

    //Open the RTC PeerConnection via Stun and ICE servers
    log.debug("Opening peer connection...");
    self.pc = new RTCPeerConnection({ iceServers: [{ urls: "stun:stun.l.google.com:19302" }] });

    //Create a DataChannel for sending events (text and audio)
    log.debug("creating data channel...");
    self.dc = self.pc.createDataChannel("oai-events");
    self.dc.onopen = () => {
        log.debug("DataChannel open");
        // Set session-wide instructions
        self.sendEvent({
          type: "session.update",
          session: {
            instructions: self.itemdata.audiochatinstructions,
            input_audio_format: "pcm16",  // Ensure correct audio encoding
            input_audio_transcription: {
              language: twoletterlang,
              model: "whisper-1"//"gpt-4o-mini-transcribe"  // Use a transcription model
            },
            speed: 0.9,
          }
        });

        //Send the first message to tell AI to say something
        self.sendEvent({
          type: "response.create",
          response: {
            modalities: ["audio", "text"],
            instructions: "Please introduce yourself to the student and explain todays topic.",
            audio: { voice: "alloy" }
          }
        });

      };

      // Handle incoming messages on the DataChannel
      self.dc.onmessage = (e) => {
        log.debug("DataChannel message:", e.data);
        try {
          const lines = e.data.split("\n").filter(Boolean);
          for (const line of lines) self.handleRTCEvent(JSON.parse(line));
        } catch (err) { log.debug("Failed to parse", err); }
      };

      // Set up the audio element to play incoming audio.
      self.pc.ontrack = (event) => hiddenaudio.srcObject = event.streams[0];

      // Set up the Mic stream.
      self.micStream = await navigator.mediaDevices.getUserMedia({ audio: true });
      self.micStream.getTracks().forEach((track) => self.pc.addTrack(track, self.micStream));

      // Set up the RTC Connection by bouncing our request off the Moodle server
      const offer = await self.pc.createOffer({ offerToReceiveAudio: true });
      await self.pc.setLocalDescription(offer);
      await self.waitForIceGathering(self.pc);

      const sdpResponse = await fetch(M.cfg.wwwroot + "/mod/minilesson/openairtc.php", {
        method: "POST",
        headers: { "Content-Type": "application/sdp" },
        body: self.pc.localDescription.sdp
      });
      if (!sdpResponse.ok) {
        log.debug("Failed /rtc:", await sdpResponse.text());
        return;
      }
      log.debug("Received SDP answer from server");
      log.debug(sdpResponse);
      const answer = await sdpResponse.text();
      log.debug(answer);
      await self.pc.setRemoteDescription({ type: "answer", sdp: answer });

      // Hide the session pending box and show the session active box
      self.controls.sessionpending.hide();
      self.controls.sessionactive.show();

      // Enable the talk button now that the session is ready
      talkBtn.disabled = false;
      stopBtn.disabled = false;

      log.debug("Session started");
     
    },

 // We dont really need this, the mic stream is active and sending audio
  pushToTalk: function() {
    var self = this;
    if (self.dc && self.dc.readyState === "open") {
      self.sendEvent({
        type: "response.create",
        response: {
          modalities: ["audio", "text"],
          audio: { voice: "alloy" }
        }
      });
    }
  }

  }; // End of return object.
});