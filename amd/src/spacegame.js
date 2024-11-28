
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
 * Spacegame app javascript for Poodll minilesson
 *
 * @package    mod_minilesson
 * @copyright  2024 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/notification', 'mod_minilesson/definitions', 'core/log', 'core/templates', 'core/ajax', 'core/str'],
    function($, notification, def, log, templates, Ajax, str) {

  "use strict"; // jshint ;_;

  /*
  This file is to manage the Space Game item type.
   */

  log.debug('MiniLesson Space Game: initialising');

    class Rectangle {
        /**
         * Constructor for storing information about a rectangle shape.
         * @param {int} left
         * @param {int} top
         * @param {int} width
         * @param {int} height
         */
        constructor(left, top, width, height) {
            this.left = left || 0;
            this.top = top || 0;
            this.width = width || 0;
            this.height = height || 0;
        }

        right () {
            return this.left + this.width;
        };

        bottom() {
            return this.top + this.height;
        };

        Contains (point) {
            return point.x > this.left &&
                point.x < this.right() &&
                point.y > this.top &&
                point.y < this.bottom();
        };

        Intersect(rectangle) {
            var retval = !(rectangle.left > this.right() ||
                rectangle.right() < this.left ||
                rectangle.top > this.bottom() ||
                rectangle.bottom() < this.top);
            return retval;
        };
    }
    class GameObject {
        /**
         * Generate game object.
         * @param {text} src
         * @param {int} x
         * @param {int} y
         */
        constructor(src, x, y) {
            if (src !== null) {
                this.image = this.loadImage(src);
            }
            this.x = x;
            this.y = y;
            this.velocity = {x: 0, y: 0};
            this.direction = {x: 0, y: 0};
            this.movespeed = {x: 5, y: 3};
            this.alive = true;
            this.decay = 0.7;
        }

        loadImage(src) {
            if (!this.image) {
                this.image= app.loadedImages[src];
            }if (!this.image) {
                this.image = new Image();
                this.image.src = M.cfg.wwwroot +'/mod/minilesson/' + src;
            }
           // this.image.src = src;
            return this.image;
        }

        update() {
            this.velocity.x += this.direction.x * this.movespeed.x;
            this.velocity.y += this.direction.y * this.movespeed.y;
            this.x += this.velocity.x;
            this.y += this.velocity.y;
            this.velocity.y *= this.decay;
            this.velocity.x *= this.decay;
        };

        draw (context) {
            context.drawImage(this.image, this.x, this.y, this.image.width, this.image.height);
        };

        getRect() {
            return new Rectangle(this.x, this.y, this.image.width, this.image.height);
        };

        die() {
            this.alive = false;
        };
    }

    /**
     * Represents a player in the game, handling movement, shooting, and collision detection.
     */
    class Player extends GameObject {
        /**
         * Constructor for Player class, all the information about the player
         * @param {string} src
         * @param {int} x
         * @param {int} y
         */
        constructor(src, x, y) {
            super( src, x, y);
            this.mouse = {x: 0, y: 0};
            this.movespeed = {x: 6, y: 4};
            this.lives = 3;
            this.lastScore = 0;
        }

        update(bounds) {
            if (app.mouseDown || app.touchDown) {
                if (this.x < this.mouse.x - (this.image.width)) {
                    app.player.direction.x = 1;
                } else if (this.x > this.mouse.x) {
                    app.player.direction.x = -1;
                } else {
                    app.player.direction.x = 0;
                }
                if (this.y < this.mouse.y - (this.image.height)) {
                    app.player.direction.y = 1;
                } else if (this.y > this.mouse.y) {
                    app.player.direction.y = -1;
                } else {
                    app.player.direction.y = 0;
                }
            }
            super.update(bounds);

            if (this.x < bounds.x - this.image.width) {
                this.x = bounds.width;
            } else if (this.x > bounds.width) {
                this.x = bounds.x - this.image.width;
            }
            if (this.y < bounds.y) {
                this.y = bounds.y;
            } else if (this.y > bounds.height - this.image.height) {
                this.y = bounds.height - this.image.height;
            }
        };

        Shoot() {
            app.playSound("laser");
            var shooter = this;
            app.gameObjects.unshift(new Laser( app.player.x, app.player.y,shooter, true, 24));
            app.canShoot = false;
        }

        die() {
            super.die();
            app.playSound("explosion");
            app.spray(this.x + this.image.width / 2, this.y + this.image.height / 2, 200, "#FFCC00");
            this.lastScore = app.score;
            app.endGame();
        }

        gotShot(shot) {
            if (shot.alive) {
                if (this.lives <= 1) {
                    this.die();
                } else {
                    this.lives--;
                    app.spray(this.x + this.image.width / 2, this.y + this.image.height / 2, 100, "#FFCC00");
                }
            }
        }
    }

    // Represents a planet in the game, serving as a background object.
    class Planet extends GameObject{
        /**
         * Constructor for Planet (background objects) extends GameObject
         * @param {string} src
         * @param {int} x
         * @param {int} y
         */
        constructor(src, x, y ) {
            super(src,x,y);
        }

        update(bounds) {
            this.image.width = app.displayRect.width;
            this.image.height = app.displayRect.height;
            super.update();
        }

    }

    // Represents an enemy in the game. Adds enemy-specific behavior like shooting and movement patterns.
    class Enemy extends GameObject {
        constructor(src, x, y, text, itempoints, termid) {
            super(src,x,y);
            this.xspeed = app.enemySpeed;
            this.yspeed = app.enemySpeed * (2 + Math.random()) / 4;
            this.movespeed.x = 0;
            this.movespeed.y = 0;
            this.direction.y = 1;
            this.text = text;
            this.itempoints = itempoints;
            this.movementClock = 0;
            this.shotFrequency = 80;
            this.shotClock = (1 + Math.random()) * this.shotFrequency;
            this.level = app.level;
            this.termid = termid;

        }

        update(bounds) {
            if (this.y < bounds.height / 10 || this.y > bounds.height * 9 / 10) {
                this.movespeed.x = this.xspeed * 1;
                this.movespeed.y = this.yspeed * 5;
            } else {
                this.movespeed.x = this.xspeed;
                this.movespeed.y = this.yspeed;
            }

            super.update(bounds);

            this.movementClock--;
            this.movementClock--;

            if (this.movementClock <= 0) {
                this.direction.x = Math.floor(Math.random() * 3) - 1;
                this.movementClock = (2 + Math.random()) * 30;
            }

            this.shotClock -= app.enemySpeed;

            if (this.shotClock <= 0) {
                if (this.y < bounds.height * 0.6) {
                    app.playSound("enemylaser");
                    var shooter=this;
                    var laser = new Laser(this.x, this.y, shooter);
                    laser.direction.y = 1;
                    laser.friendly = false;
                    app.gameObjects.unshift(laser);
                    this.shotClock = (1 + Math.random()) * this.shotFrequency;
                }
            }

            if (this.x < bounds.x - this.image.width) {
                this.x = bounds.width;
            } else if (this.x > bounds.width) {
                this.x = bounds.x - this.image.width;
            }
            if (this.y > bounds.height + this.image.height && this.alive) {
                this.alive = false;
                if (this.itempoints > 0) {
                    app.currentPointsLeft -= this.itempoints;
                    app.score -= 1000 * this.itempoints;
                }

                app.shipReachedEnd.call(this);
            }
        }

        draw(context){

            super.draw(context);
            context.fillStyle = '#FFFFFF';
            context.font = "20px Audiowide";
            context.textAlign = 'center';

            app.wrapText(context, this.text, true, 17, app.displayRect.width * 0.2, this.x + this.image.width / 2, this.y - 5);
        }

        die() {
            super.die();
            app.spray(this.x + this.image.width, this.y + this.image.height, 50 + (this.itempoints * 150), "#FF0000");

            // Adjust Score.
            app.score += this.itempoints * 1000;

            //report the positive association
            app.reportSuccess(this.termid);

            // Kill off the ship.
            app.playSound("explosion");
        }

        gotShot(shot) {
            // Default behaviour, to be overridden.
            shot.die();
            this.die();

        }
    }

    // Represents a laser in the game, capable of moving across the screen and interacting with other game objects.
    class Laser extends GameObject {
        constructor(x, y, shooter, friendly, laserSpeed) {

            super(friendly ? "pix/spacegame/laser.png" : "pix/spacegame/enemylaser.png", x, y);
            this.x = this.x + ((shooter.image.width - this.image.width) / 2);
            this.direction.y = -1;
            this.friendly = friendly ? 1 : 0;
            this.laserSpeed = laserSpeed || 12;
        }

        update(bounds) {
            super.update(bounds);
            if (this.x < bounds.x - this.image.width ||
                this.x > bounds.width ||
                this.y < bounds.y - this.image.height ||
                this.y > bounds.height) {
                this.alive = false;
            }
            this.velocity.y = this.laserSpeed * this.direction.y;
        };

        deflect() {
            this.image = this.loadImage("pix/spacegame/enemylaser.png");
            this.direction.y *= -1;
            this.friendly = !this.friendly;
            app.playSound("deflect");
        };
    }

    class Particle extends GameObject {
        constructor(x, y, velocity, colour) {
            super(null, x, y);
            this.width = 2;
            this.height = 2;
            this.velocity.x = velocity.x;
            this.velocity.y = velocity.y;
            this.aliveTime = 0;
            this.colour = colour;
            this.decay = 1;
        }

        update(bounds){
            super.update(bounds);
            if (this.x < bounds.x - this.width ||
                this.x > bounds.width ||
                this.y < bounds.y - this.height ||
                this.y > bounds.height) {
                this.alive = false;
            }
            this.aliveTime++;
            if (this.aliveTime > (Math.random() * 15) + 5) {
                this.alive = false;
            }
        }

        getRect(){
            return new Rectangle(this.x, this.y, this.width, this.height);
        }

        draw(context){
            context.fillStyle = this.colour;
            context.fillRect(this.x, this.y, this.width, this.height);
            context.stroke();
        }
    }

    // Represents a star in the background of the game, moving downwards to create a parallax effect.
    class Star extends GameObject {
        constructor(bounds) {
            super( null, Math.random() * bounds.width, 0);
            this.width = 2;
            this.height = 2;
            this.direction.y = 1;
            this.movespeed.y = 0.2 + (Math.random() / 2);
            this.aliveTime = 0;
        }

        update(bounds){
            super.update(bounds);
            if (this.y > bounds.height) {
                this.alive = false;
            }
        }

        draw(context){
            context.fillStyle = '#9999AA';
            context.fillRect(this.x, this.y, this.width, this.height);
            context.stroke();
        }
    }

    /**
     * MultiEnemy extends the Enemy class to represent a more complex enemy type in the game.
     *
     * @param {int} x
     * @param {int} y
     * @param {string} text
     * @param {float} itempoints
     * @param {boolean} single
     */
    class MultiEnemy extends Enemy {
        constructor(x, y, text, itempoints, single,termid) {
            super("pix/spacegame/ship-enemy-yellow-" + app.shipsize + ".png", x, y, text, itempoints, termid);
            this.single = single;
        }

        die() {
            super.die();
            if (this.itempoints > 0) {
                app.currentPointsLeft -= this.itempoints;
            }
            // Store the result for later processing.
            app.storeResult(this.termid, this.itempoints);
            if ((this.single && this.itempoints === 1) && this.itempoints >= 1 || (this.itempoints > 0 && app.currentPointsLeft <= 0)) {
                app.killAllAlive();
                app.nextLevel();
            }
        }

        gotShot(shot) {
            if (this.itempoints > 0) {
                shot.die();
                this.die();

                // Report the positive association.
                app.reportSuccess(this.termid);

            } else {
                app.score += (this.itempoints - 0.5) * 600;

                // Record negative association for later processing.
                app.storeResult(this.termid, 0);

                shot.deflect();
            }
        }
    }

    // MatchEnemy extends the Enemy class to represent enemies that can be matched/paired in the game.
    class MatchEnemy extends Enemy {
        constructor(x, y, text, itempoints, pairid, stem, termid  ) {

            if (stem) {
                super("pix/spacegame/ship-enemy-green-" + app.shipsize + ".png", x, y, text, itempoints, termid);
            } else {
               // super("pix/spacegame/ship-enemy-purple-64.png", x, y, text, itempoints, termid);
                super("pix/spacegame/ship-enemy-green-" + app.shipsize + ".png", x, y, text, itempoints, termid);
            }
            this.stem = stem ? true : false;
            this.pairid = pairid;
            this.shotFrequency = 160;
            this.hightlighted = false;
        }

        die() {
            app.currentPointsLeft -= this.itempoints;
            // Sets the itempoints as 0 to stop it adding to the score in #die()
            this.itempoints = 0;
            super.die();

        }

        gotShot(shot) {
            if (shot.alive && this.alive) {
                if (app.lastShot == -this.pairid) {

                    // Increasing the score here instead of in #die(), due to rounding issues being a few numbers off.
                    // This must be done before because when #die is invoked, as it sets the itempoints as 0.
                    app.score += this.itempoints * 1000 * 2;

                    //store the result for later processing
                    app.storeResult(this.termid, this.itempoints);

                    //report the positive association
                    app.reportSuccess(this.termid);


                    shot.die();
                    this.die();
                    var alives = 0;
                    app.currentTeam.forEach(function (match) {
                        if (match.pairid == app.lastShot) {
                            match.die();
                        }
                        if (match.alive) {
                            alives++;
                        }
                    });

                    if (alives <= 0) {
                        app.nextLevel();
                    }
                } else {
                    if (app.lastShot == this.pairid) {
                        shot.deflect();
                    } else {
                        shot.die();
                        this.hightlight();
                        app.lastShot = this.pairid;
                    }
                }
            }
        }

        hightlight() {
            app.currentTeam.forEach(function (match) {
                match.unhightlight();
            });
            if (this.stem) {
                this.loadImage("pix/spacegame/ship-enemy-blue-" + app.shipsize + ".png");
            } else {
                this.loadImage("pix/spacegame/ship-enemy-blue-" + app.shipsize + ".png");
            }
            this.hightlighted = true;
        };

        unhightlight() {
            if (this.hightlighted) {
                if (this.stem) {
                    this.loadImage("pix/spacegame/ship-enemy-green-" + app.shipsize + ".png");
                } else {
                    this.loadImage("pix/spacegame/ship-enemy-green-" + app.shipsize + ".png");
                }
            }
            this.hightlighted = false;
        }
    }

    // Start of app declaration.
var app = {
    isFreeMode:  false,
    strings: {"shootthepairs": "Shoot the Pairs"},
    termAsAlien: "0",
    questions: [],
    quizgame: null,
    stage: null,
    score: 0,
    particles: [],
    gameObjects: [],
    shipsize: "48", //TO DO: make this a setting and refactor image loading to be not clunky
    images: [
        'pix/spacegame/icon.gif',
        'pix/spacegame/planet.png',
        'pix/spacegame/ship.png',
        'pix/spacegame/ship-poodll-64.png',
        'pix/spacegame/ship-enemy-green-64.png',
        'pix/spacegame/ship-enemy-yellow-64.png',
        'pix/spacegame/ship-enemy-blue-64.png',
        'pix/spacegame/ship-poodll-48.png',
        'pix/spacegame/ship-enemy-green-48.png',
        'pix/spacegame/ship-enemy-yellow-48.png',
        'pix/spacegame/ship-enemy-blue-48.png',
        'pix/spacegame/space-bckg.png',
        'pix/spacegame/enemy.png',
        'pix/spacegame/enemystem.png',
        'pix/spacegame/enemychoice.png',
        'pix/spacegame/enemystemselected.png',
        'pix/spacegame/enemychoiceselected.png',
        'pix/spacegame/laser.png',
        'pix/spacegame/enemylaser.png'
    ],
    imagesLoaded:  0,
    loadedImages:[],
    loaded: false,
    player: null,
    planet: null,
    level: -1,
    displayRect: {x: 0, y: 0, width: 0, height: 0},
    question: "",
    interval: null,
    enemySpeed: null,
    touchDown: false,
    mouseDown: false,
    currentTeam: [],
    lastShot: 0,
    currentPointsLeft:  0,
    context: null,
    inFullscreen: false,
    canShoot: true,
    dryRun: false,
    ttslanguage: 'en-US',
    distractors: [],
    controls: {},
    results: [],
    timer: null,



    registerSpaceGameEvents: function(){

        document.onkeyup = this.keyup;
        document.onkeydown = this.keydown;
        document.onmouseup = this.mouseup;
        document.onmousedown = this.mousedown;
        document.onmousemove = this.mousemove;
        document.ontouchstart = this.touchstart;
        document.ontouchend = this.touchend;
        document.addEventListener('touchmove',this.touchmove, {passive: false});
        window.onresize = this.orientationChange;

        document.addEventListener("gesturestart", this.cancelled, false);
        document.addEventListener("gesturechange", this.cancelled, false);
        document.addEventListener("gestureend", this.cancelled, false);
    }  ,

    /**
     * Play sound effect.
     * @param {string} soundName
     */
    playSound: function(soundName) {
        if (document.getElementById("mod_minilesson_spacegame_sound_on").checked) {
            var soundElement = document.getElementById("mod_minilesson_sound_" + soundName);
            soundElement.currentTime = 0;
            soundElement.play();
        }
    },

    /**
     * Adjust for small screens.
     */
    smallscreen: function() {
        app.inFullscreen = false;

        if (document.fullscreenElement) {
            if (document.exitFullscreen) {
                let exitPromise = document.exitFullscreen();
                if (exitPromise instanceof Promise) {
                    exitPromise.then(() => console.log("Document Exited from Full screen mode"))
                               .catch((err) => console.error(err));
                } else {
                    console.log("Document Exited from Full screen mode");
                }
            } else if (document.webkitExitFullscreen) { /* Safari */
                document.webkitExitFullscreen();
                console.log("Document Exited from Full screen mode (webkit)");
            } else if (document.msExitFullscreen) { /* IE11 */
                document.msExitFullscreen();
                console.log("Document Exited from Full screen mode (ms)");
            } else {
                console.error("Fullscreen exit is not supported by this browser");
            }
        } else {
            console.log("Document is not in fullscreen mode");
        }

        app.stage.removeAttribute("width");
        app.stage.removeAttribute("height");
        app.stage.removeAttribute("style");

        app.stage.classList.remove("floating-game-canvas");
        $("#button_container").removeClass("floating-button-container fixed-bottom");

        app.displayRect.width = app.stage.clientWidth;
        app.displayRect.height = app.stage.clientHeight;
        app.stage.style.width = app.displayRect.width;
        app.stage.style.height = app.displayRect.height;
        app.sizeScreen(app.stage);
    },

    /**
     * Adjust screen size (switch between modes).
     */
    fschange: function() {
        if (this.inFullscreen) {
            app.smallscreen();
        }
    },

    /**
     * Expand to full screen.
     */
    fullscreen: function() {
        var landscape = window.matchMedia("(orientation: landscape)").matches;
        try {
            if (app.stage.requestFullscreen) {
                app.stage.requestFullscreen();
            } else if (app.stage.msRequestFullscreen) {
                app.stage.msRequestFullscreen();
            } else if (app.stage.mozRequestFullScreen) {
                app.stage.mozRequestFullScreen();
            } else {
                console.error("Fullscreen API is not supported by this browser");
            }
        } catch (e) {
            log.debug(e, "Fullscreen error: ");
        }
        // The stage.webkitRequestFullscreen() method was removed, due to very easily exiting of full screen in iOS,
        // along with browser messages asking if you are typing in fullscreen.

        app.inFullscreen = true;
        var buttonContainer = $("#button_container");

        var width = window.innerWidth;

        // The window.innerHeight returns an offset value on iOS devices in safari only
        // while in portrait mode for some reason.
        var height = $(window).height();

        // Switch width and height
        if (landscape && width < height) {
            height = [width, width = height][0];
        }

        // Gets the actual button container height, then adds 16px; 8px on the
        // top and 8px on the bottom for the page margin.
        height -= buttonContainer.height() + 16;

        app.displayRect.width = width;
        app.displayRect.height = height;

        app.stage.style.width = width + "px";
        app.stage.style.height = height + "px";

        // Makes the canvas float.
        app.stage.classList.add("floating-game-canvas");

        // This makes the button container float below the game canvas.
        buttonContainer.addClass("floating-button-container fixed-bottom");

        $("#mod_minilesson_spacegame_fullscreen_button").blur(); // The button pressed was still focused, so a blur is necessary.

        app.sizeScreen(app.stage);
    },

    /**
     * Adjust screen size based on browser window.
     * @param {object} stage
     */
    sizeScreen: function(stage) {

        stage.width = app.displayRect.width;
        stage.height = app.displayRect.height;
        app.context.imageSmoothingEnabled = false;
    },

    /**
     * Helper function for when the screen size chages due to rotating on mobile.
     */
    orientationChange: function() {
        if (app.inFullscreen) {
            app.fullscreen();
        } else {
            app.smallscreen();
        }
    },

    /**
     * Helper function to clear all events.
     */
    clearEvents: function() {
        document.onkeydown = null;
        document.onkeyup = null;
        document.onmousedown = null;
        document.onmouseup = null;
        document.onmousemove = null;
        document.ontouchstart = null;
        document.ontouchend = null;
        document.ontouchmove = null;
        window.onresize = null;
    },

    storeResult: function(termid, points){
        var theterm = false;
        app.terms.forEach(function(aterm){
            if(aterm.id == termid){
                theterm = aterm
            }
        });
        //if we found the term, store the result
        if (theterm) {
            var result = {
                question: app.strip_html(theterm['definition']),
                selected: '',
                correct: theterm['term'],
                points: points,
                id: theterm.id
            };
            for (var i = 0; i < app.results.length; i++) {
                if (app.results[i].id == theterm.id) {
                    //if we have a duplicate and the earlier one is a fail, but this is a pass, replace it
                    if (points > 0 && app.results[i].points == 0) {
                        app.results.splice(i, 1);
                    //otherwise we just keep the previous result
                    } else {
                        return;
                    }
                }
            }
            //storing result
            app.results.push(result);
        }
    },

    reportSuccess: function(termid) {
        if (app.dryRun) {
            return;
        }
        //In wordcards we report the success of the association, but not here
        /*
        Ajax.call([{
            methodname: 'mod_minilesson_report_successful_association',
            args: {
                termid: termid,
                isfreemode: app.isFreeMode
            }
        }]);
        */
    },

    reportFailure: function(term1id, term2id) {
        if (app.dryRun) {
            return;
        }
        //In wordcards we report the success of the association, but not here
        /*
        Ajax.call([{
            methodname: 'mod_minilesson_report_failed_association',
            args: {
                term1id: term1id,
                term2id: term2id,
                isfreemode: app.isFreeMode
            }
        }]);
        */
    },

    /**
     * Helper function to handle JS Events.
     */
    menuEvents: function() {
        app.clearEvents();
        document.onkeydown = app.menukeydown;
        document.onmouseup = app.menumousedown;
        document.ontouchend = app.menutouchend;
        window.onresize = app.orientationChange;
    },

    /**
     * Helper function to load game objects
     */
    loadGame: function() {

        // We already do this elsewhere.
        app.shuffle(app.questions);

        if (!app.loaded) {
            app.images.forEach(function (src) {
                var absolute_src = M.cfg.wwwroot +'/mod/minilesson/' + src;

                var image = new Image();
                image.src = absolute_src;
                image.onload = function () {
                    app.loadedImages[src] = image;
                    app.imagesLoaded++;
                    console.log(app.imagesLoaded);
                    if (app.imagesLoaded >= app.images.length) {
                        app.gameLoaded();
                    }
                };
            });
            app.loaded = true;
        } else {
            app.startGame();
        }
    },

    /**
     * Helper function process game-over.
     */
    endGame: function() {

        // Clear full screen.
        if (app.inFullscreen) {
            log.debug("quitting full screen");
            app.inFullscreen = false;
            app.smallscreen();
        }

        clearInterval(app.timer.interval);
        $("#minilesson-gameboard, #minilesson-start-button").hide();
        $("#minilesson-results").show();

        // Template data.
        var tdata = [];
        tdata['nexturl'] = app.nexturl;
        tdata['results'] = app.results;
        tdata['total'] = app.questions.length;
        var totalcorrect = app.calc_total_points(app.results);
        //remove any decimal (leave one decimal place)
        tdata['totalcorrect'] = Math.round(totalcorrect * 10) / 10;
        tdata['gamescore'] = Math.round(app.score);
        var total_time = app.timer.count;
         if (total_time == 0) {
             tdata['prettytime'] = '00:00';
         } else {
             tdata['prettytime'] = app.pretty_print_secs(total_time);
         }

        templates.render('mod_minilesson/feedback', tdata).then(
            function(html, js) {
                $("#results-inner").html(html);
            }
        );

        var data = {
            results: app.results,
            activity: "spacegame"
        };
/*
        if (!app.isFreeMode) {
            Ajax.call([{
                methodname: 'mod_minilesson_report_step_grade',
                args: {
                    modid: app.modid,
                    correct: tdata['totalcorrect']
                }
            }]);
        }
            */

        // ajax.call([{
        //     methodname: 'mod_wordcards_update_score',
        //     args: {quizgameid: quizgame, score: Math.trunc(score)},
        //     fail: notification.exception
        // }]);

        // We use wordcards end screen so we dont do menuevents.
        // We just clear events so that the game doesnt restart and kill any ships left on stage
        //this.menuEvents();
        this.killAllAlive();
        this.clearEvents();
    },

    /**
     * Helper function process game ready.
     */
    gameLoaded: function() {

        clearInterval(app.interval);

        app.interval = setInterval(function () {
            app.draw(app.context, app.displayRect, app.gameObjects, app.particles, app.question);
            app.update(app.displayRect, app.gameObjects, app.particles);
        }, 40);

        app.startGame();
    },

    /**
     * Helper function process game start.
     */
    startGame: function() {

        app.score = 0;
        app.gameObjects = [];
        app.particles = [];
        app.level = -1;
        app.enemySpeed = 0.5;
        app.touchDown = false;
        app.mouseDown = false;
        app.results=[];

        // Queue & trigger the game_started event.
        /*
                ajax.call([{
                    methodname: 'mod_wordcards_start_game',
                    args: {quizgameid: quizgame},
                    fail: notification.exception
                }]);
        */
        app.player = new Player("pix/spacegame/ship-poodll-" + app.shipsize + ".png", 0, 0);
        app.player.x = app.displayRect.width / 2;
        app.player.y = app.displayRect.height / 2;
        app.gameObjects.push(app.player);

        app.planet = new Planet("pix/spacegame/planet.png", 0, 0);
        app.planet.image.width = app.displayRect.width;
        app.planet.image.height = app.displayRect.height;
        app.planet.direction.y = 1;
        app.planet.movespeed.y = 0.7;
        app.particles.push(app.planet);
        // Set up the timer.
        app.timer = {
            interval: setInterval(function() {
                app.timer.update();
            }, 1000),
            count: 0,
            update: function() {
                app.timer.count++;
                $("#minilesson-time-counter").text(app.timer.count);
            }
        }

        app.nextLevel();

        app.registerSpaceGameEvents();
    },

    /**
     * Helper function process next level (question).
     */
    nextLevel: function() {
        app.level++;

        // If we've run out of questions
        if (app.level >= app.questions.length) {
            //kill the player and end the game
            app.player.die();

            //previously we raised the speed, but we don't want to do that anymore
            //we might go back on it though.. so its still here
            /*
            app.level = 0;
            app.enemySpeed *= 1.3;
            app.question = app.runLevel(app.questions, app.level, app.displayRect);
            */

        } else {
            app.question = app.runLevel(app.questions, app.level, app.displayRect);
        }
    },

    /**
     * Helper function process current level.
     * @param {array} questions
     * @param {object} level
     * @param {object} bounds
     * @returns {string}
     */
    runLevel: function(questions, level, bounds) {
        app.currentTeam = [];
        app.lastShot = 0;
        app.currentPointsLeft = 0;

        switch(questions[level].type){
            case 'matching':
                var i = 0;
                //so in the case of 3 pairs, we have 6 items, so each item is worth 1/6, one pair will require two shots = 2/6
                var itempoints = 1 / (questions[level].stems.length * 2);
                app.currentPointsLeft += 1;
                questions[level].stems.forEach(function (stem) {
                    i++;
                    var question = new MatchEnemy(Math.random() * bounds.width, -Math.random() * bounds.height / 2,
                        stem.question, itempoints, -i, true,stem.termid);
                    var answer = new MatchEnemy(Math.random() * bounds.width, -Math.random() * bounds.height / 2,
                        stem.answer, itempoints, i,false,stem.termid);
                    app.currentTeam.push(question);
                    app.currentTeam.push(answer);
                    app.gameObjects.push(question);
                    app.gameObjects.push(answer);
                });
                break;

            case 'multichoice':
                questions[level].answers.forEach(function(answer) {
                    var enemy = new MultiEnemy(Math.random() * bounds.width, -Math.random() * bounds.height / 2,
                        answer.text, answer.itempoints, questions[level].single,  questions[level].termid);

                    if (answer.itempoints < 1) {
                        app.currentTeam.push(enemy);
                        if (answer.itempoints > 0) {
                            app.currentPointsLeft += answer.itempoints;
                        }
                    }
                    app.gameObjects.push(enemy);
                });
        }
        return questions[level].question;
    },

    /**
     * Helper function to place text on screen
     * @param {object} context
     * @param {object} displayRect
     * @param {objectc} objects
     * @param {object} particles
     * @param {string} question
     */
    draw: function(context, displayRect, objects, particles, question) {
        context.clearRect(0, 0, displayRect.width, displayRect.height);
        //draw the background
        app.drawSpaceBackground(context,displayRect);

        //draw particles
        var i = 0;
        for (i = 0; i < particles.length; i++) {
            particles[i].draw(context);
        }

        //draw objects
        for (i = 0; i < objects.length; i++) {
            objects[i].draw(context);
        }

        if (app.player.alive) {
            context.fillStyle = '#FFFFFF';
            context.font = "18px Audiowide";
            context.textAlign = 'left';
            context.fillText(app.strings.score + ' ' + Math.round(app.score) +  '  ' + app.strings.lives + ' ' +  app.player.lives,
                5, displayRect.height - 20);
            context.textAlign = 'center';

            app.wrapText(context, question, false, 20, displayRect.width * 0.9, displayRect.width / 2, 20);
        } else {
            context.fillStyle = '#FFFFFF';
            context.font = "18px Audiowide";
            context.textAlign = 'center';
            context.fillText(app.strings.endofgame + ' ' + Math.round(app.player.lastScore),
                displayRect.width / 2, displayRect.height / 2);
        }
    },

    /**
     * Helper function main game logic: process movements and behaviours of game objects
     * @param {object} bounds
     * @param {object} objects
     * @param {object} particles
     */
    update: function(bounds, objects, particles) {
        var i = 0;
        for (i = 0; i < 3; i++) {
            particles.push(new Star(bounds));
        }
        for (i = 0; i < particles.length; i++) {
            particles[i].update(bounds);
            if (!particles[i].alive) {
                particles.splice(i, 1);
                i--;
            }
        }
        for (i = 0; i < objects.length; i++) {
            objects[i].update(bounds);
            for (var j = i + 1; j < objects.length; j++) {
                app.collide(objects[i], objects[j]);
            }
            if (!objects[i].alive) {
                objects.splice(i, 1);
                i--;
            }
        }
    },

    /**
     * Helper function to remove any stray ships on level advance
     */
    killAllAlive: function() {
        app.currentTeam.forEach(function (enemy) {
            if (enemy.alive) {
                // Make the itempoints 0 so it won't count as anything and make a new level.
                enemy.itempoints = 0;
                enemy.die();
            }
        });
        app.currentTeam = [];
    },

    /**
     * Helper function to handle collisions between gameobjects.
     * @param {object} object1
     * @param {object} object2
     * @return {boolean}
     */
    collide: function(object1, object2) {
        return object1.alive && object2.alive && (this.collideOrdered(object1, object2) || this.collideOrdered(object2, object1));
    },

    /**
     * Helper funcction to handle collisions.
     * @param {object} object1
     * @param {object} object2
     * @returns {boolean}
     */
    collideOrdered: function(object1, object2) {
        if (object1 instanceof Laser && object2 instanceof Player) {
            if (!object1.friendly && app.objectsIntersect(object1, object2)) {
                object2.gotShot(object1);
                object1.die();
                return true;
            }
        }
        if (object1 instanceof Laser && object2 instanceof Enemy) {
            if (object1.friendly && app.objectsIntersect(object1, object2)) {
                object2.gotShot(object1);
                return true;
            }
        }
        if (object1 instanceof Player && object2 instanceof Enemy) {
            if (app.objectsIntersect(object1, object2)) {
                object1.die();
                return true;
            }
        }
        return false;
    },

    /**
     * Helper function to handle intersections between GameObjects.
     * @param {object} object1
     * @param {object} object2
     * @return {boolean}
     */
    objectsIntersect: function(object1, object2) {
        var rect1 = object1.getRect();
        var rect2 = object2.getRect();
        return rect1.Intersect(rect2);
    },

    /**
     * Helper function for spraying particle (explosion) effects
     * @param {int} x
     * @param {int} y
     * @param {int} num
     * @param {string} colour
     */
    spray: function(x, y, num, colour) {
        for (var i = 0; i < num; i++) {
            app.particles.push(new Particle(x, y, {
                x: (Math.random() - 0.5) * 16,
                y: ((Math.random() - 0.5) * 16) + 3
            }, colour));
        }
    },

    /**
     * Helper function to display answers.
     * @param {object} context
     * @param {string} input
     * @param {bool} wrapUpwards
     * @param {int} textHeight
     * @param {int} maxLineWidth
     * @param {int} x
     * @param {int} y
     */
    wrapText: function(context, input, wrapUpwards, textHeight, maxLineWidth, x, y) {
        var drawLines = [];
        var originalY = y;
        var words = input.split(' ');
        var line = '';
        var maxTextWidth = 0;
        // Loops through the words, and preprocesses each line with the correct string value and y location.
        words.forEach(function (word) {
            var tempLine ='';
            if(line===''){
                tempLine = line + ' ' + word;
            }else{
                tempLine = line + ' ' + word;
            }

            var metrics = context.measureText(tempLine);
            var textWidth = metrics.width;
            maxTextWidth = Math.max(maxTextWidth, textWidth);

            // If the line with the new word is too long, then push the current line without the new word to drawLines.
            if (textWidth > maxLineWidth) {
                drawLines.push({
                    text: line,
                    y: y += textHeight
                });

                line = word;
            } else {
                // If it's shorter than the limit, just add the word to the line and move on.
                line = tempLine;
            }
        });

        // Push the last line, if it exists.
        drawLines.push({
            text: line,
            y: y += textHeight
        });

        // The offset the text was created.
        var yOffset = y - originalY;

        //box mods
        var boxPadding = 20;
        var boxmodifier = wrapUpwards ? -(yOffset + textHeight + boxPadding / 2) : -(textHeight + boxPadding / 2);

        var borderColor = wrapUpwards ?'transparent':'white';
        app.drawRoundRect(context, borderColor,maxTextWidth + boxPadding, textHeight * drawLines.length + boxPadding, x - (maxTextWidth + boxPadding) / 2, y + boxmodifier);

        context.fillStyle="white";
        drawLines.forEach(function (drawLine) {
            // If it is suppose to wrap upwards (i.e. for enemy ships) it shifts all questions upwards the amount the
            // questions go down.
            var modifier = wrapUpwards ? -yOffset : 0;

            context.fillText(drawLine.text, x, drawLine.y + modifier);
        });
    },

    drawRoundRect: function(context, borderColor, width, height , x , y) {
        var cornerRadius = 4;
        var borderWidth = 4;

        var fillColor = 'rgba(75, 71, 77, 0.5)';
        //var borderColor = 'red';
        if(borderColor !== 'white') {
                borderColor = fillColor;
        }

        // Draw the box with border
        context.fillStyle = fillColor; // Box background color
        context.fillRect(x,y, width, height); // Draw the box
        context.strokeStyle = borderColor; // Border color
        context.lineWidth = borderWidth; // Border width
        context.strokeRect(x - borderWidth / 2, y - borderWidth  / 2, width + borderWidth, height + borderWidth); // Draw the border
    },

    drawSpaceBackground: function(context,displayRect) {

        // Set the source of the image (URL of the background image)
        var img = app.loadedImages['pix/spacegame/space-bckg.png'];

        // Calculate the number of tiles needed to cover the canvas
        var tilesX = Math.ceil(displayRect.width / img.width);
        var tilesY = Math.ceil(displayRect.height / img.height);

        // Loop through and draw the image tiles to cover the canvas
        for (var i = 0; i < tilesX; i++) {
            for (var j = 0; j < tilesY; j++) {
                context.drawImage(img, i * img.width, j * img.height, img.width, img.height);
            }
        }
    },

    /**
     * Helper function for end of level.
     */
    shipReachedEnd: function() {

        var amountLeft = app.currentTeam.filter(function (enemy) {
            return enemy.alive;
        }).length;

        if (amountLeft === 0
            // && (app.currentPointsLeft < app.itempoints || app.currentPointsLeft <= 0)
            && app.player.alive) {
            app.nextLevel();
        }
    },

    /**
     * Helper function for game menu from keyboard.
     * @param {object} e
     */
    menukeydown: function(e) {
        if ([32, 37, 38, 39, 40].indexOf(e.keyCode) !== -1) {
            e.preventDefault();
            if (e.keyCode === 32) {
                app.loadGame();
            }
        }
    },

    /**
     * Helper function for game menu on mobile.
     * @param {object} e
     */
    menumousedown: function(e) {
        if (e.target === app.stage) {
            app.loadGame();
        }
    },

    /**
     * Helper function for game menu on mobile.
     * @param {object} e
     */
    menutouchend: function(e) {
        if (e.target === app.stage) {
            app.loadGame();
        }
    },

    /**
     * Helper function for keyboard movement.
     * @param {object} e
     */
    keydown: function(e) {
        if ([32, 37, 38, 39, 40].indexOf(e.keyCode) !== -1) {
            e.preventDefault();
            if (e.keyCode === 32 && app.player.alive && app.canShoot) {
                app.player.Shoot();
            } else if (e.keyCode === 37) {
                app.player.direction.x = -1;
            } else if (e.keyCode === 38) {
                app.player.direction.y = -1;
            } else if (e.keyCode === 39) {
                app.player.direction.x = 1;
            } else if (e.keyCode === 40) {
                app.player.direction.y = 1;
            }
        }
    },

    /**
     * Helper function for keyboard movement.
     * @param {object} e
     */
    keyup: function(e) {
        if (e.keyCode === 32) {
            app.canShoot = true;
        } else if ([37, 39].indexOf(e.keyCode) !== -1) {
            app.player.direction.x = 0;
        } else if ([38, 40].indexOf(e.keyCode) !== -1) {
            app.player.direction.y = 0;
        }
    },

    /**
     * Helper function for mouse click (starts the player shooting if you clicked on them, moving if you didn't).
     * @param {object} e
     */
    mousedown: function(e) {
        if (e.target === app.stage) {
            var playerWasClicked = app.player.getRect().Contains({x: e.offsetX, y: e.offsetY});
            if (playerWasClicked && app.player.alive) {
                app.player.Shoot();
            }
            if (!app.mouseDown) {
                app.player.mouse.x = e.offsetX;
                app.player.mouse.y = e.offsetY;
                app.mouseDown = true;
            }
        }
    },

    /**
     * Helper function for mouse release. (stops the Player) for mouse mode
     */
    mouseup: function() {
        app.player.direction.x = 0;
        app.player.direction.y = 0;
        app.mouseDown = false;
    },

    /**
     * Helper function for mouse movement.
     * @param {object} e
     */
    mousemove: function(e) {
        app.player.mouse.x = e.offsetX;
        app.player.mouse.y = e.offsetY;
    },

    /**
     * Helper function for cancelled event.
     * @param {object} event
     */
    cancelled: function(event) {
        if (event.target === app.stage) {
            event.preventDefault();
        }
    },

    /**
     * Helper function for movement on mobile touch devices.
     * @param {object} e
     */
    touchstart: function(e) {
        if (e.target === app.stage) {
            if (app.player.alive && e.touches.length > 1) {
                app.player.Shoot();
            } else {
                app.touchDown = true;
                app.touchmove(e);
            }

            e.preventDefault();
        }
    },

    /**
     * Helper function for movement on mobile touch devices.
     * @param {object} e
     */
    touchend: function(e) {
        if (e.touches.length === 0) {
            app.touchDown = false;
        }
        app.player.direction.x = 0;
        app.player.direction.y = 0;

        if (e.target === app.stage) {
            e.preventDefault();
        }
    },

    /**
     * Helper function for movement on mobile touch devices.
     * @param {object} e
     */
    touchmove: function(e) {
        var rect = e.target.getBoundingClientRect();
        // Required for getting the stage's relative touch position, due to a previous significant offset.
        var x = e.touches[0].pageX - rect.left;
        var y = e.touches[0].clientY - rect.top;

        window.stage = app.stage;
        app.player.mouse.x = x;
        app.player.mouse.y = y - (2 * app.player.image.height);

        if (e.target === app.stage) {
            e.preventDefault();
        }
    },

    /**
     * Helper function to shuffle levels.
     * @param {array} array
     * @return {array}
     */
    shuffle: function(array) {
        var currentIndex = array.length;
        var temporaryValue;
        var randomIndex;

        while (0 !== currentIndex) {

            randomIndex = Math.floor(Math.random() * currentIndex);
            currentIndex -= 1;

            temporaryValue = array[currentIndex];
            array[currentIndex] = array[randomIndex];
            array[randomIndex] = temporaryValue;
        }

        return array;
    },

    strip_html: function(htmlstring) {
        return htmlstring.replace(/<\/?[^>]+(>|$)/g, "");
    },

    clone: function () {
        return $.extend(true, {}, this);
    },

    split_array: function(array, chunkSize) {
        if(chunkSize == null){ chunkSize = 4};
        var result = [];
        var currentIndex = 0;

        while (currentIndex < array.length) {
            const chunk = array.slice(currentIndex, currentIndex + chunkSize);
            if (chunk.length < chunkSize) {
                const remaining = chunkSize - chunk.length;
                // Reuse elements from previous chunks
                for (var i = 0; i < remaining; i++) {
                    chunk.push(array[i]);
                }
            }
            result.push(chunk);
            currentIndex += chunkSize;
        }

        return result;
    },

    shuffle_array: function(array) {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [array[i], array[j]] = [array[j], array[i]];
        }
    },

    calc_total_points: function(results) {
        var total = 0;
        $.each(results, function(i, o) {
          if (o.points != undefined) {
            total += o.points;
          }
        });
        return total;
      },

      pretty_print_secs: function(time) {
        var minutes = Math.floor(time / 60);
        var seconds = time - minutes * 60;
        return app.str_pad_left(minutes, '0', 2) + ':' + app.str_pad_left(seconds, '0', 2);
      },

      str_pad_left: function(string, pad, length) {
        return (new Array(length + 1).join(pad) + string).slice(-length);
      },

      init_strings: function(){
       
        str.get_strings([
            { "key": "shootthepairs", "component": 'mod_minilesson'},
            { "key": "emptyquiz", "component": 'mod_minilesson'},
            { "key": "score", "component": 'mod_minilesson'},
            { "key": "lives", "component": 'mod_minilesson'},
            { "key": "spacetostart", "component": 'mod_minilesson'},

        ]).done(function (s) {
            var i = 0;
            app.strings.shootthepairs = s[i++];
            app.strings.emptyquiz = s[i++];
            app.strings.score = s[i++];
            app.strings.lives = s[i++];
            app.strings.spacetostart = s[i++];
        });
    },  

    process: function(definitions) {
        var terms = definitions.spacegameitems.map(function(item,index) {
            var theitem = JSON.parse(item);
            theitem.id = index;
            return theitem;
        });
        var multichoice_alien_chunksize = definitions.customint1;
        var matching_alien_chunksize = definitions.customint2;
        var include_matching_questions = definitions.customint3;
        app.terms = terms;
        app.shuffle_array(terms);

        // Multichoice questions.
        // Split into groups(ie "levels") of 5 terms (a single of mc of more than 5 terms is too much).
        var chunkSize = multichoice_alien_chunksize;
        if(terms.length) {chunkSize = terms.length;}
        var mc_levels = app.split_array(terms, chunkSize);
        // For each level build a set of chunksize questions with 1 correct and chunksize -1  distractors.
        for (var thelevel = 0; thelevel < mc_levels.length; thelevel++) {
            var level = mc_levels[thelevel];
            // Multiple choice questions.
            for (var i = 0; i < level.length; i++) {
                var answers = [];
                for (var j = 0; j < level.length; j++) {
                    var answertext = app.strip_html(level[j].definition);

                    if(app.sgoptions === app.termAsAlien){
                        answertext = level[j].term;
                    }
                    answers.push({"text": answertext,"itempoints": (i === j ? 1 : 0)});
                }
                var questiontext = level[i].term;
                if(app.sgoptions === app.termAsAlien){
                    questiontext = app.strip_html(level[i].definition);
                }
                app.questions.push({
                    "question": questiontext,
                    "termid": level[i].id,
                    "answers": answers,
                    "type": "multichoice",
                    "single": true
                });
            }
        }

        // Matching questions.
        if(include_matching_questions == 1){
            // Split into groups(ie "levels") of 3 terms (even 4 terms = 8 items to shoot, its quite hard).
            chunkSize=matching_alien_chunksize;
            var matching_levels = app.split_array(terms, chunkSize);
            for (var thelevel = 0; thelevel < matching_levels.length; thelevel++) {
                var level = matching_levels[thelevel];
                var subquestions = [];
                for (var i = 0; i < level.length; i++) {
                    subquestions.push({question: level[i].term, answer: app.strip_html(level[i].definition),"termid": level[i].id});
                }
                // Show a --- in place of a real question, so the user knows its a matching question.
                app.questions.push({"question": app.strings.shootthepairs, "stems": subquestions, "type": "matching"});
            }
        }
        console.log("After process:", app.questions);
    },

    init_controls: function() {
        app.controls.gameboard = $("#minilesson-gameboard");
        app.controls.time_counter = $("#minilesson-time-counter");
    },

    /**
     * Helper function to display game start screen
     */
    showMenu: function() {

        app.context.clearRect(0, 0, app.displayRect.width, app.displayRect.height);

        app.context.fillStyle = '#FFFFFF';
        app.context.font = "18px Audiowide";
        app.context.textAlign = 'center';

        log.debug('Display screen: initialising');

        if (app.questions !== null && app.questions.length > 0) {
            app.context.fillText(app.strings.spacetostart, app.displayRect.width / 2, app.displayRect.height / 2);
            app.menuEvents();
        } else {
            app.context.fillText(app.strings.emptyquiz, app.displayRect.width / 2, app.displayRect.height / 2);
        }
        // If we are height 0 then we are in a collapsed state
        // We call our orientation change function to fix this
        if(app.stage.height == 0){
            app.orientationChange();
        }
    },

    start: function() {
        // Hide the intro screen with word cards.
        app.results = [];
        app.controls.gameboard.show();
        app.controls.time_counter.text("00:00");

        app.timer = {
            interval: setInterval(function() {
                app.timer.update();
            }, 1000),
            count: 0,
            update: function() {
                app.timer.count++;
               // app.controls.time_counter.text('zerozero');
            }
        }

        // Begin the game screen.
        if (document.addEventListener) {
            document.addEventListener('fullscreenchange', app.fschange, false);
            document.addEventListener('MSFullscreenChange', app.fschange, false);
            document.addEventListener('mozfullscreenchange', app.fschange, false);
            document.addEventListener('webkitfullscreenchange', app.fschange, false);
        }
        app.stage = document.getElementById("mod_minilesson_spacegame");
        app.context = app.stage.getContext("2d");
        app.smallscreen();
        app.interval = setInterval(function () {
            if (app.questions && app.questions.length > 0) {
                app.showMenu();
            } else {
                console.log("Questions are not ready or empty.");
            }
        }, 500);

        // Full screen toggle button handler.
        $('#mod_minilesson_spacegame_fullscreen_button').on('click', function () {
            if (app.inFullscreen) {
                app.inFullscreen = false;
                app.smallscreen();
            } else {
                app.fullscreen();
            }
        });
    },

    next_question: function() {
        var self = this;
        var stepdata = {};
        stepdata.index = self.index;
        stepdata.hasgrade = true;
        stepdata.totalitems = app.questions.length;
        var totalcorrect = app.calc_total_points(app.results);
        //remove any decimal (leave one decimal place)
        stepdata.correctitems = Math.round(totalcorrect * 10) / 10;
        stepdata.grade = Math.round(100 * (stepdata.correctitems / stepdata.totalitems));
        self.quizhelper.do_next(stepdata);
    },

    register_events: function(index, itemdata, quizhelper) {
        app.index = index;
        app.quizhelper = quizhelper;
        var nextbutton = $("#" + itemdata.uniqueid + "_container .minilesson_nextbutton");
        var clickbutton = $("#" + itemdata.uniqueid + "_container .ml_sg_clickclick");

        nextbutton.on('click', function(e) {
            app.next_question(0);
        });

        clickbutton.on('click', function(e) {
            app.start();
        });

        $('body').on('click', "#minilesson-try-again", function() {
            location.reload();
        });

        $('body').on('click', "#minilesson-close-results", function() {
            var total_time = app.timer.count;
            var url = app.nexturl.replace(/&amp;/g, '&') + "&localscattertime=" + total_time
            window.location.replace(url);
        });
    },

    /**
     * Initialization of the game.
     * @param {array} props
     */
    init: function(index, itemdata, quizhelper) {
        log.debug('MiniLesson Space Game: init function');

        var definitions = itemdata;
        app.definitions = definitions;
        app.init_strings();
        app.register_events(index, itemdata, quizhelper);
        app.init_controls();
        app.process(definitions);
        app.start();
    },
}; // End of app declaration.

    return app;
});