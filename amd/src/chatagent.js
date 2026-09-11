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
 * The chat agent panel.
 *
 * The browser drives the agent loop: one request performs one model call and at most one tool
 * execution, and this keeps calling step while there is more to do. That is what lets a turn
 * needing several tools show its progress instead of hanging on a single long request.
 *
 * @module     mod_minilesson/chatagent
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Fragment from 'core/fragment';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {get_string as getString} from 'core/str';

/** @var {number} Stop stepping after this many, in case the server ever stops saying 'complete'. */
const MAX_STEPS = 20;

/**
 * One panel.
 */
class ChatAgent {

    /**
     * @param {HTMLElement} root the panel's outer element
     */
    constructor(root) {
        this.root = root;
        this.cmid = parseInt(root.dataset.cmid, 10);
        this.contextid = parseInt(root.dataset.contextid, 10);
        this.conversationid = 0;
        this.sinceid = 0;
        this.busy = false;

        this.messages = root.querySelector('[data-region="messages"]');
        this.intro = root.querySelector('[data-region="intro"]');
        this.status = root.querySelector('[data-region="status"]');
        this.statustext = root.querySelector('[data-region="statustext"]');
        this.spinner = root.querySelector('[data-region="spinner"]');
        this.input = root.querySelector('[data-region="input"]');
        this.attachform = root.querySelector('[data-region="attachform"]');
        this.itemlist = root.querySelector('[data-region="itemlist"]');

        this.registerEvents();
        this.start();
    }

    /**
     * Wire up the composer.
     */
    registerEvents() {
        this.root.querySelector('[data-region="composer"]').addEventListener('submit', (e) => {
            e.preventDefault();
            this.send();
        });

        // Enter sends, shift+enter starts a new line - what people expect of a chat box.
        this.input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.send();
            }
        });

        this.root.querySelector('[data-action="reset"]').addEventListener('click', () => this.reset());
        this.root.querySelector('[data-action="refresh"]').addEventListener('click', () => this.refreshItems(true));

        // Approval buttons are added as the conversation goes, so listen on the container.
        this.messages.addEventListener('click', (e) => {
            const button = e.target.closest('[data-action="approve"], [data-action="decline"]');
            if (button) {
                this.decide(button.dataset.callid, button.dataset.action === 'approve');
                return;
            }
            if (e.target.closest('[data-action="continue"]')) {
                this.continueTurn();
            }
        });
    }

    /**
     * Open the conversation and draw whatever it already holds.
     */
    async start() {
        try {
            const response = await this.call('start', {cmid: this.cmid, sinceid: 0});
            this.handle(response, true);
        } catch (error) {
            Notification.exception(error);
        }
    }

    /**
     * Send what the teacher typed, then run the loop to the end of the turn.
     */
    async send() {
        const text = this.input.value.trim();
        const draftitemid = this.draftItemId();
        if (this.busy || (text === '' && !draftitemid)) {
            return;
        }

        this.setBusy(true, await getString('chatagent_thinking', 'mod_minilesson'));
        this.appendLocalMessage('user', text);
        this.input.value = '';

        try {
            const response = await this.call('send', {
                conversationid: this.conversationid,
                text: text,
                draftitemid: draftitemid,
                sinceid: this.sinceid,
            });
            await this.runLoop(response);
        } catch (error) {
            this.showError(error.message);
        } finally {
            this.setBusy(false);
        }
    }

    /**
     * Keep stepping while the assistant has work to do.
     *
     * @param {Object} response the reply that started the turn
     */
    async runLoop(response) {
        let steps = 0;
        this.handle(response);

        while (response.status === 'requires_tool' && steps++ < MAX_STEPS) {
            response = await this.call('step', {
                conversationid: this.conversationid,
                sinceid: this.sinceid,
            });
            this.handle(response);
        }

        if (response.job && response.job.poll) {
            this.watchJob(response.job.jobid);
        }
    }

    /**
     * Record the teacher's decision, then carry on with the turn.
     *
     * @param {String} callid the pending call
     * @param {Boolean} approved
     */
    async decide(callid, approved) {
        if (this.busy) {
            return;
        }
        const card = this.messages.querySelector('[data-callid="' + CSS.escape(callid) + '"]');
        if (card) {
            card.remove();
        }

        this.setBusy(true, await getString(approved ? 'chatagent_working' : 'chatagent_thinking', 'mod_minilesson'));
        try {
            const response = await this.call('approve', {
                conversationid: this.conversationid,
                callid: callid,
                approved: approved,
                sinceid: this.sinceid,
            });
            await this.runLoop(response);
        } catch (error) {
            this.showError(error.message);
        } finally {
            this.setBusy(false);
        }
    }

    /**
     * Throw the conversation away and start again.
     */
    async reset() {
        const confirmed = await Notification.saveCancelPromise(
            await getString('chatagent_startover', 'mod_minilesson'),
            await getString('chatagent_startoverconfirm', 'mod_minilesson'),
            await getString('chatagent_startover', 'mod_minilesson')
        ).then(() => true).catch(() => false);

        if (!confirmed) {
            return;
        }

        try {
            const response = await this.call('reset', {conversationid: this.conversationid});
            this.messages.innerHTML = '';
            this.messages.appendChild(this.intro);
            this.sinceid = 0;
            this.handle(response);
        } catch (error) {
            this.showError(error.message);
        }
    }

    /**
     * Draw whatever came back: new messages, an approval card, an error.
     *
     * @param {Object} response
     * @param {Boolean} restored true when this is a conversation read back from the database on
     *                           page load, rather than something happening right now
     */
    handle(response, restored) {
        this.conversationid = response.conversationid;

        // Only the server knows whether a tool actually changed the items, so it says so and the
        // page believes it. Guessing from tool names here would go stale the moment a new tool
        // was added.
        if (response.lessonchanged) {
            this.refreshItems();
        }

        (response.messages || []).forEach((message) => {
            this.sinceid = Math.max(this.sinceid, message.id);
            this.appendMessage(message);
        });

        // Clear the progress line first, so that every path out of here either sets a new one or
        // leaves none. Setting it without ever clearing it is how a spinner outlives the work it
        // was reporting on - including across a reset, where nothing is happening at all.
        this.setStatus('');

        if (response.status === 'error') {
            this.showError(response.error);
            return;
        }
        if (response.status === 'requires_approval' && response.pending) {
            this.appendApproval(response.pending);
            return;
        }
        if (response.status === 'requires_tool' && response.pending) {
            if (restored) {
                // Restored from the database, not happening now: the teacher closed the page
                // part way through a turn. Showing the progress line here would promise work
                // that nobody is doing, so offer to pick it up instead. It is not resumed
                // automatically because that would spend on the AI service for a page load.
                this.appendInterrupted(response.pending);
            } else {
                this.setStatus(response.pending.summary);
            }
        }
    }

    /**
     * Offer to finish a turn that was interrupted, rather than pretending it is still running.
     *
     * @param {Object} pending the tool the assistant had been about to run
     */
    async appendInterrupted(pending) {
        const element = document.createElement('div');
        element.className = 'ml_chatagent_interrupted';
        element.dataset.region = 'interrupted';

        const text = document.createElement('span');
        text.textContent = await getString('chatagent_interrupted', 'mod_minilesson', pending.summary);
        element.appendChild(text);

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-secondary btn-sm';
        button.dataset.action = 'continue';
        button.textContent = await getString('chatagent_continue', 'mod_minilesson');
        element.appendChild(button);

        this.messages.appendChild(element);
        this.scroll();
    }

    /**
     * Pick up a turn that was left unfinished.
     */
    async continueTurn() {
        if (this.busy) {
            return;
        }
        const notice = this.messages.querySelector('[data-region="interrupted"]');
        if (notice) {
            notice.remove();
        }

        this.setBusy(true, await getString('chatagent_working', 'mod_minilesson'));
        try {
            await this.runLoop(await this.call('step', {
                conversationid: this.conversationid,
                sinceid: this.sinceid,
            }));
        } catch (error) {
            this.showError(error.message);
        } finally {
            this.setBusy(false);
        }
    }

    /**
     * Add one message from the server.
     *
     * @param {Object} message
     */
    appendMessage(message) {
        // The user's own message was drawn the moment they sent it; drawing the server's copy
        // as well would show everything they type twice.
        if (message.role === 'user' && this.messages.querySelector('[data-pending-user="1"]')) {
            const pending = this.messages.querySelector('[data-pending-user="1"]');
            pending.removeAttribute('data-pending-user');
            return;
        }
        if (message.role === 'tool') {
            this.setStatus(message.toolname.replace('mod_minilesson_aigen_', '').replace(/_/g, ' '));
            return;
        }

        const element = document.createElement('div');
        element.className = 'ml_chatagent_message ml_chatagent_message_' + message.role;
        element.innerHTML = message.contenthtml;
        this.messages.appendChild(element);
        this.scroll();
    }

    /**
     * Show the teacher's own message straight away, without waiting for the round trip.
     *
     * @param {String} role
     * @param {String} text
     */
    appendLocalMessage(role, text) {
        if (this.intro && this.intro.parentNode) {
            this.intro.remove();
        }
        if (text === '') {
            return;
        }
        const element = document.createElement('div');
        element.className = 'ml_chatagent_message ml_chatagent_message_' + role;
        element.dataset.pendingUser = '1';
        element.textContent = text;
        this.messages.appendChild(element);
        this.scroll();
    }

    /**
     * Show what the assistant wants to do, and ask.
     *
     * @param {Object} pending
     */
    async appendApproval(pending) {
        const card = await Templates.render('mod_minilesson/chatagent_approval', {
            callid: pending.callid,
            summary: pending.summary,
            argsjson: pending.argsjson,
        });
        Templates.appendNodeContents(this.messages, card, '');
        this.setStatus('');
        this.scroll();
    }

    /**
     * Follow a background generation job to its end, then tell the assistant what happened.
     *
     * The assistant is deliberately not left to poll for this: every poll would be another
     * paid model call, and the job only moves when cron runs.
     *
     * @param {Number} jobid
     */
    async watchJob(jobid) {
        this.setStatus(await getString('chatagent_generating', 'mod_minilesson'));

        // The status field is a display string, so what counts as "still running" has to be
        // the same string the server built it from - comparing against an English literal
        // would leave every non-English site polling forever.
        const running = await getString('progress', 'mod_minilesson');

        const poll = async () => {
            const jobs = await Ajax.call([{
                methodname: 'mod_minilesson_aigen_fetch_create_items_status',
                args: {jobids: [jobid]},
            }])[0];

            const job = (jobs.jobs || [])[0];
            if (!job) {
                return;
            }
            if (job.status === running) {
                setTimeout(poll, 5000);
                return;
            }

            this.setStatus('');
            await this.refreshItems();
            const summary = await getString('chatagent_jobfinished', 'mod_minilesson', job.status);
            // Report the outcome back into the conversation, so the assistant can pick the
            // thread up rather than believing the job is still queued.
            this.input.value = summary + (job.message ? ' ' + job.message : '');
            this.send();
        };

        setTimeout(poll, 5000);
    }

    /**
     * Redraw the lesson pane, so the teacher sees what just changed.
     *
     * @param {Boolean} manual true when the teacher pressed refresh, rather than the page
     *                         redrawing itself after the assistant changed something
     */
    async refreshItems(manual) {
        const button = this.root.querySelector('[data-action="refresh"]');
        button.classList.add('ml_chatagent_refreshing');
        try {
            const html = await Fragment.loadFragment(
                'mod_minilesson',
                'chatagent_items',
                this.contextid,
                {}
            );
            this.itemlist.innerHTML = html;
        } catch (error) {
            if (manual) {
                // Asked for by hand, so silence would look like the button did nothing.
                Notification.exception(error);
            } else {
                // The conversation is what matters here; a stale list is not worth interrupting it.
                window.console.warn('mod_minilesson chat agent: could not refresh the item list', error);
            }
        } finally {
            button.classList.remove('ml_chatagent_refreshing');
        }
    }

    /**
     * Call one of the chat agent web services.
     *
     * @param {String} name the function's short name
     * @param {Object} args
     * @return {Promise<Object>}
     */
    call(name, args) {
        return Ajax.call([{
            methodname: 'mod_minilesson_chatagent_' + name,
            args: args,
        }])[0];
    }

    /**
     * Show or clear the progress line.
     *
     * The spinner runs whenever the line is showing, because the line itself can sit unchanged
     * for a long time - a model thinking, or a tool fetching a large spec - and a static message
     * that does not move is indistinguishable from one that has stalled.
     *
     * @param {String} text
     */
    setStatus(text) {
        this.statustext.textContent = text || '';
        this.status.hidden = !text;
        this.spinner.hidden = !text;
    }

    /**
     * Lock the composer while a turn is in flight.
     *
     * @param {Boolean} busy
     * @param {String} text what to show while busy
     */
    setBusy(busy, text) {
        this.busy = busy;
        this.root.classList.toggle('ml_chatagent_busy', busy);
        this.root.querySelectorAll('button, textarea').forEach((el) => {
            el.disabled = busy;
        });
        if (busy) {
            this.setStatus(text);
        } else {
            this.setStatus('');
            this.input.focus();
        }
    }

    /**
     * Show something that went wrong, in the conversation where it happened.
     *
     * @param {String} message
     */
    showError(message) {
        const element = document.createElement('div');
        element.className = 'alert alert-danger ml_chatagent_error';
        element.setAttribute('role', 'alert');
        element.textContent = message;
        this.messages.appendChild(element);
        this.scroll();
    }

    /**
     * The draft area behind the composer's file manager.
     *
     * Read fresh each time rather than cached: the file manager owns that hidden input, and a
     * teacher can add or remove files between one message and the next.
     *
     * @return {Number} the draft item id, or 0 when there is no file manager
     */
    draftItemId() {
        const input = this.attachform ? this.attachform.querySelector('input[name="chatagent_filemanager"]') : null;
        return input ? parseInt(input.value, 10) || 0 : 0;
    }

    /**
     * Keep the newest message in view.
     */
    scroll() {
        this.messages.scrollTop = this.messages.scrollHeight;
    }
}

export const init = () => {
    document.querySelectorAll('[data-region="chatagent"]').forEach((root) => new ChatAgent(root));
};
