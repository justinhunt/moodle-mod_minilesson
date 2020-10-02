define(['jquery', 'core/log', 'mod_poodlltime/ttwavencoder'], function ($, log, wavencoder) {
    "use strict"; // jshint ;_;
    /*
    This file is the engine that drives audio rec and canvas drawing. TT Recorder is the just the glory kid
     */

    log.debug('TT Audio Helper initialising');

    var aR =  {
        encoder: null,
        microphone: null,
        isRecording: false,
        audioContext: null,
        processor: null,
        uniqueid: null,

        config: {
            bufferLen: 4096,
            numChannels: 2,
            mimeType: 'audio/wav'
        },

        init: function(waveHeight, uniqueid, therecorder) {

            aR.waveHeight = waveHeight;
            aR.uniqueid=uniqueid;
            aR.therecorder= therecorder;
            this.prepare_html();


            window.AudioContext = window.AudioContext || window.webkitAudioContext;

        },

        onStop: function() {},
        onStream: function() {},
        onError: function() {},


        prepare_html: function(){
            aR.canvas =$('#' + aR.uniqueid + "_waveform");
            aR.canvasCtx = aR.canvas[0].getContext("2d");
        },

        start: function() {
            // Audio context
            aR.audioContext = new AudioContext();
            if (aR.audioContext.createJavaScriptNode) {
                aR.processor = aR.audioContext.createJavaScriptNode(aR.config.bufferLen, aR.config.numChannels, aR.config.numChannels);
            } else if (aR.audioContext.createScriptProcessor) {
                aR.processor = aR.audioContext.createScriptProcessor(aR.config.bufferLen, aR.config.numChannels, aR.config.numChannels);
            } else {
                log.debug('WebAudio API has no support on this browser.');
            }
            aR.processor.connect(aR.audioContext.destination);

            // Mic permission
            navigator.mediaDevices.getUserMedia({
                audio: true,
                video: false
            }).then(aR.gotStreamMethod).catch(aR.onError);
        },

        stop: function() {
            clearInterval(aR.interval);
            aR.canvasCtx.clearRect(0, 0, aR.canvas.width()*2, this.waveHeight * 2);
            aR.isRecording = false;
            aR.therecorder.update_audio('isRecording',false);
            aR.audioContext.close();
            aR.processor.disconnect();
            aR.tracks.forEach(track => track.stop());
            aR.onStop(aR.encoder.finish());

        },

        getBuffers: function(event) {
            var buffers = [];
            for (var ch = 0; ch < 2; ++ch)
                buffers[ch] = event.inputBuffer.getChannelData(ch);
            return buffers;
        },

        gotStreamMethod: function(stream) {
            aR.onStream(stream);
            aR.isRecording = true;
            aR.therecorder.update_audio('isRecording',true);
            aR.tracks = stream.getTracks();

            // Create a MediaStreamAudioSourceNode for the microphone

            aR.microphone = aR.audioContext.createMediaStreamSource(stream);

            // Connect the AudioBufferSourceNode to the gainNode

            aR.microphone.connect(aR.processor);
            aR.encoder = wavencoder;
            aR.encoder.init(aR.audioContext.sampleRate, 2);

            // Give the node a function to process audio events
            aR.processor.onaudioprocess = function(event) {
                aR.encoder.encode(aR.getBuffers(event));
            };

            aR.listener = aR.audioContext.createAnalyser();
            aR.microphone.connect(aR.listener);
            aR.listener.fftSize = 2048; // 256

            aR.bufferLength = aR.listener.frequencyBinCount;
            aR.analyserData = new Uint8Array(aR.bufferLength);

            aR.canvasCtx.clearRect(0, 0, aR.canvas.width()*2, aR.waveHeight*2);

            aR.interval = setInterval(function() {
                aR.drawWave();
            }, 100);

        },

        drawWave: function() {

            var width = aR.canvas.width() * 2;
            aR.listener.getByteTimeDomainData(aR.analyserData);

            aR.canvasCtx.fillStyle = 'white';
            aR.canvasCtx.fillRect(0, 0, width, aR.waveHeight*2);

            aR.canvasCtx.lineWidth = 5;
            aR.canvasCtx.strokeStyle = 'gray';
            aR.canvasCtx.beginPath();

            var slicewaveWidth = width / aR.bufferLength;
            var x = 0;

            for (var i = 0; i < aR.bufferLength; i++) {

                var v = aR.analyserData[i] / 128.0;
                var y = v * aR.waveHeight;

                if (i === 0) {
                    // aR.canvasCtx.moveTo(x, y);
                } else {
                    aR.canvasCtx.lineTo(x, y);
                }

                x += slicewaveWidth;
            }

            aR.canvasCtx.lineTo(width, aR.waveHeight);
            aR.canvasCtx.stroke();

        }
    }; //end of aR declaration
    return aR;

});