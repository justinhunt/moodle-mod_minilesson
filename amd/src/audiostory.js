define(['jquery','core/log'], function($,log) {
    "use strict"; // jshint ;_;


    log.debug('MiniLesson Audio Story: initialising');

    var audiostoryapp ={
        animateFrameId: null,
        currentIndex: -1,
        secondsPerImage:  20, //this is the animation length, before it reverses
        pp: 5,//panfactor +, // eg 5
        pm: -5,//panfactor -, eg -5
        maxzoom: 1.1, // zoom will go from maxzoom to 1.0 and back again
        zoomIn: false, // If true, start zoomed out and zoom in. If false, start zoomed in and zoom out
        zoomAndPan: true,
        entryTimes: [],
        controls: {},
        panOptions: null,

        //for making multiple instances
        clone: function () {
            return $.extend(true, {}, this);
        },

        init: function(uniqid){
            var self =this;
            self.panOptions = [
                { xStart: this.pm, yStart: 0,   xEnd: this.pp, yEnd: 0   },
                { xStart: this.pp,  yStart: 0,   xEnd: this.pm, yEnd: 0  },
                { xStart: 0,   yStart: this.pm, xEnd: 0, yEnd: this.pp   },
                { xStart: 0,   yStart: this.pp,  xEnd: 0, yEnd: this.pm  },
                { xStart: this.pm, yStart: this.pm, xEnd: this.pp, yEnd: this.pp  },
                { xStart: this.pp,  yStart: this.pp,  xEnd: this.pm, yEnd: this.pm }
            ];
            self.init_controls(uniqid);
            self.register_events();
        },

        init_controls: function(uniqid){
            var self = this;
            self.controls.aplayer = $('#' + uniqid + '_audiostory_audio');
            self.controls.slideshowcontainer = $('#' + uniqid + '_audiostory_slideshow_container');
            self.controls.captioncontainer = $('#' + uniqid + '_audiostory_caption');
            self.controls.images = $('#' + uniqid + '_audiostory_slideshow_container' + ' .audiostory_image_container');
            self.controls.overlay = self.controls.slideshowcontainer.find('.audiostory_overlay');
            self.controls.playbutton = self.controls.overlay.find('.audiostory_play_button');
            self.controls.layers = [];
            self.controls.entryTimes = [];

            // Set Zoom and Pan scale
            var zoomandpan  = self.controls.slideshowcontainer.data('zoomandpan');
            switch(zoomandpan){
                case 1:
                    self.zoomAndPan = true;
                    self.maxzoom = 1.1;
                    self.pp = 5; //panfactor +
                    self.pm = -5; //panfactor -
                    break;
                case 2:
                    self.zoomAndPan = true;
                    self.maxzoom = 1.2;
                    self.pp = 7; //panfactor +
                    self.pm = -7; //panfactor -
                    break;
                case 3:
                    self.zoomAndPan = true;
                    self.maxzoom = 1.3;
                    self.pp = 10; //panfactor +
                    self.pm = -10; //panfactor -
                    break;
                case 0:
                default:
                    self.zoomAndPan = false;
            }

            // Set up the layers and entry times
            self.controls.images.each(function(index, element) {
                var entrytime = element.dataset.entrytime;
                if(entrytime==''){ return;} //
                const pan = self.panOptions[Math.floor(Math.random() * self.panOptions.length)];
                self.controls.layers.push({ element: element, pan });
                self.controls.entryTimes.push(entrytime);
            });

            //add some more information to layers
            self.controls.layers.forEach(layer => {
                layer.animation = {
                    direction: 1, // 1 = zoom out, -1 = zoom in
                    progress: 0, // 0 to 1
                    lastTimestamp: null
                };
            });
            // Show the first image at rest (no pan/zoom)
            const firstImage = self.controls.layers[0];
            if (firstImage) {
                firstImage.element.style.opacity = '1';
                firstImage.element.style.transform = 'scale(1) translate(0, 0)';
            }

        },


        register_events: function(){
            var self = this;
            const audio = self.controls.aplayer[0];
            const captionDiv = self.controls.captioncontainer[0];
            const layers = self.controls.layers;
            var doUpdate = function(timestamp) {
                const audio = self.controls.aplayer[0];
                const layers = self.controls.layers;
                const currentTime = audio.currentTime;
                const entryTimes = self.controls.entryTimes;

                let newIndex = -1;
                for (let i = 0; i < entryTimes.length; i++) {
                    if (currentTime >= entryTimes[i]) {
                        newIndex = i;
                    } else {
                        break;
                    }
                }

                if (newIndex !== self.currentIndex) {
                    self.currentIndex = newIndex;
                    layers.forEach((l, i) => {
                        l.element.style.opacity = i === self.currentIndex ? '1' : '0';
                        l.animation.progress = 0;
                        l.animation.direction = 1;
                        l.animation.lastTimestamp = null;
                    });
                    //Toggle zoom direction
                    self.zoomIn = !self.zoomIn;
                }

                self.animateCurrentImage(timestamp);

                self.animationFrameId = requestAnimationFrame(doUpdate);
            };



            self.controls.playbutton.on('click', () => {
                self.controls.overlay.hide();
                audio.play(); // this triggers animation
            });

            audio.addEventListener('play', () => {
                self.controls.overlay.hide();
                if (!self.animationFrameId) {
                    self.animationFrameId = requestAnimationFrame(doUpdate);
                }
            });

            audio.addEventListener('pause', () => {
                if (self.animationFrameId) {
                    cancelAnimationFrame(self.animationFrameId);
                    self.animationFrameId = null;
                }
            });

            audio.addEventListener('seeked', () => {
                self.currentIndex = -1;
                if (!audio.paused) {
                    doUpdate();
                }
            });

            // Caption handling using textTracks
            const handleLoadedMetadata = () => {
                log.debug('Audio metadata loaded, setting up captions');
                const track = audio.textTracks[0];
                if (!track) {
                    log.debug('no text tracks not setting up captions');
                    return;
                }

                track.mode = 'hidden'; // load cues, but don't show default UI

                track.addEventListener('cuechange', () => {
                const cue = track.activeCues[0];
                if (cue) {
                    captionDiv.textContent = cue.text;
                } else {
                    captionDiv.textContent = '';
                }
                });
            };
            //depending on state of audio load, do metadata or register an event for it
            if (audio.readyState >= 1) {
                handleLoadedMetadata();
            } else {
                audio.addEventListener('loadedmetadata', handleLoadedMetadata);
            }

        },

        animateCurrentImage: function(timestamp) {
            var self = this;
            var layers = self.controls.layers;
            var entryTimes = self.controls.entryTimes;
            var audio = self.controls.aplayer[0];

            if (self.currentIndex < 0 || self.currentIndex >= layers.length) return;

            const imageObj = layers[self.currentIndex];
            const { element, pan, animation } = imageObj;

            if (!self.zoomAndPan) {
                // If zoomAndPan is false, reset the transform and skip animation
                element.style.transform = 'scale(1) translate(0, 0)';
                return;
            }


            if (animation.lastTimestamp != null) {
                const delta = timestamp - animation.lastTimestamp;
                // Set zoom timing
                const duration = self.secondsPerImage * 1000; // adjust as needed
                animation.progress += (delta / duration)  * animation.direction;

                if (animation.progress > 1) {
                    animation.progress = 1;
                    animation.direction = -1;
                } else if (animation.progress < 0) {
                    animation.progress = 0;
                    animation.direction = 1;
                }
            }
            animation.lastTimestamp = timestamp;

            const easedProgress = 0.5 - 0.5 * Math.cos(Math.PI * animation.progress);
            // Zoom logic: toggle between zoom in and zoom out based on flag
            let scale;
            if (self.zoomIn) {
                // Start zoomed out, zoom in
                scale = 1 + (self.maxzoom - 1) * easedProgress;
            } else {
                // Start zoomed in, zoom out
                scale = self.maxzoom - (self.maxzoom - 1) * easedProgress;
            }
            // from 0 (zoomed out) to 1 (zoomed in)  -  this means we dont pan when zoomed out
            const panFactor = easedProgress;
            const x = (pan.xStart + (pan.xEnd - pan.xStart) * panFactor);
            const y = (pan.yStart + (pan.yEnd - pan.yStart) * panFactor);

            element.style.transform = `scale(${scale}) translate(${x}px, ${y}px)`;
        },

    };//end of audiostoryapp def
    return audiostoryapp;
});