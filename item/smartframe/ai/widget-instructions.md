# How to build a Poodll MiniLesson widget (Poodll Widget Package v1)

You are helping a teacher build an interactive learning activity ("widget") for a Poodll MiniLesson. The
teacher describes the activity in their message. If anything they ask for breaks a rule here, follow the rule
and tell them what you changed.

## 1. How we work: build, try, export

**Stage 1: build it so the teacher can try it here.**

- Make the widget as **one self-contained HTML file**, shown in your preview: in Claude an **HTML artifact**
  (not a React/JSX artifact), in ChatGPT an HTML canvas, in other tools whatever shows an HTML page.
- Put all CSS, JavaScript, interface strings and small images **inside that one file** (section 2).
- Include the lesson protocol code (section 6) from the start. It does nothing in your preview, so the file
  the teacher tries is exactly the file that will run in the lesson.
- The teacher tries it and asks for changes. Change the same file each time; keep it complete and working.

**Stage 2: export it when the teacher is happy** (they say "export it for Poodll", or similar).

- Add the manifest block (section 3) if it is not there, and go through the checklist (section 9). Fix
  anything that fails.
- Give the teacher **the file to download**, named after the activity, ending in `.html` (e.g.
  `maori-greetings.html`). If you can not make files, give the whole file in one code block and tell the
  teacher to save it as a `.html` file.
- Tell the teacher in two or three lines what it does, its interface languages, and whether it reports a
  score. They upload the file into a MiniLesson "Widget" item.

**Changing an existing widget:** if the teacher gives you a widget file (`.html`, or a `.zip`), change what they
ask for and keep everything else: the manifest block, the protocol code and the rules here.

A widget needs a ZIP instead of one HTML file only when it has audio, video or many large images. See section 10.

## 2. The HTML file

- **Everything inline:** `<style>` and `<script>` in the page. No other files, nothing loaded from the internet.
- **No libraries from CDNs**, even if your preview allows them, and no frameworks that need a build step (React,
  JSX, TypeScript, Tailwind, npm). Write plain HTML, CSS and JavaScript. The lesson blocks every other site, so a
  widget that loads a library works in your preview and breaks in the lesson.
- **Images:** prefer inline SVG, CSS or emoji. A picture the teacher gives you can go in as a `data:` URI
  (compress it: WebP or JPEG, a few hundred KB at most). Never link to an image on another site.
- At most **15 MB**, saved as UTF-8.
- Start with `<!doctype html>`, `<meta charset="utf-8">` and
  `<meta name="viewport" content="width=device-width, initial-scale=1">`.

## 3. The manifest block

Put this in the `<head>`, filled in for the activity:

```html
<script type="application/json" id="poodll-widget-manifest">
{
  "schemaVersion": 1,
  "id": "maori-greetings",
  "title": "Te Reo Māori: One, Two or Many",
  "defaultUiLanguage": "en",
  "uiLanguages": ["en", "ja"],
  "targetLanguage": "mi",
  "capabilities": {"resize": true, "grade": true},
  "minimumHostProtocol": 1
}
</script>
```

- It must be valid JSON (double quotes, no comments, no trailing commas). Keep the `id` attribute exactly
  `poodll-widget-manifest` and the `type` `application/json`.
- `schemaVersion` and `minimumHostProtocol`: always `1`.
- `title`: required; the teacher sees it, and it names the frame for screen readers.
- `id`: a short descriptive name.
- `uiLanguages`: the **interface** languages the widget really has strings for, as BCP 47 tags (`en`, `ja`,
  `pt-BR`). One is fine: `["en"]`. `defaultUiLanguage` must be one of them.
- `targetLanguage`: the language being taught, if any (`mi` for Māori). Optional.
- `capabilities.resize`: `true` (the widget reports its height). `capabilities.grade`: `true` only if the widget
  reports a score; `false` for an activity with no score.
- Use only these fields.

## 4. The browser sandbox: what a widget can and can not do

In the lesson the widget runs in a **sandboxed frame with an opaque origin**, under a strict security policy.
Write it for that from the start:

- **No storage and no cookies.** `localStorage`, `sessionStorage`, IndexedDB and `document.cookie` throw an
  error. Keep all state in JavaScript variables.
- **No network.** No APIs, AI calls, analytics, CDNs or web fonts. Use the system fonts
  (`font-family: system-ui, sans-serif`).
- **No `eval`, `new Function()` or string timers** (`setTimeout("code")`).
- **No pop-ups or dialogs:** `alert()`, `confirm()`, `prompt()`, `window.open()` and `target="_blank"` links do
  not work. Show messages in the page.
- **Forms:** handle them in JavaScript and call `event.preventDefault()`; a form can not be sent anywhere.
- **No workers, camera, microphone, downloads, full screen or external links.** Do not rely on speech
  synthesis or recognition; if you use them, the widget must still work without them.
- Audio and video in the file (or the ZIP) work. Start playback from a click or tap.
- The widget must work on its own too: in your preview and opened directly in a browser.

## 5. Interface languages

- The **interface** (instructions, buttons, labels, feedback, explanations, alt text, ARIA labels) is translated.
  The **content being taught is never translated** because the interface language changed: Māori stays Māori
  in the English and in the Japanese interface.
- Keep the interface strings in one JavaScript object, one entry per language, with the same keys:

  ```js
  const STRINGS = {
      en: {chooseAudience: 'How many people are you addressing?', check: 'Check answer'},
      ja: {chooseAudience: '何人に話しかけますか？', check: '答え合わせ'},
  };
  ```

- Choose the language like this: the page URL's `?lang=<tag>` for the first paint; then the language in the
  lesson's `init` message (section 6), which wins. If a requested language is not in `STRINGS`, try its base
  language (`fr-CA` -> `fr`), then the default. Set `<html lang="...">` to the language in use.
- If the teacher wants to check a language in your preview, add a small language switch that shows **only when
  the widget is not in a lesson** (when `embedded` in section 6 is false).

## 6. Talking to the lesson: the `poodll-widget` v1 protocol

The widget and the lesson talk only with `window.postMessage`. Every message has this envelope:

```json
{"protocol":"poodll-widget","version":1,"type":"poodll-widget:resize","instanceId":"abc123","payload":{"height":520}}
```

**Rules**

- The lesson's origin is in the URL parameter `parentOrigin` (e.g. `https://school.example.org` or
  `http://localhost:8051`). Accept any value that is `http://` or `https://` plus a host (and optional port),
  and nothing else. If `parentOrigin` is missing, or the page is not in a frame, run standalone and send no
  messages. (That is the case in your preview.)
- Accept a message only if `event.source === window.parent` **and** `event.origin === parentOrigin`, and its
  `protocol`, `version` and `type` are right. Ignore anything else, including unknown types.
- Send only to `window.parent`, with `parentOrigin` as the target origin. **Never use `"*"`.**
- Never run or insert as HTML anything that arrives in a message.

**Order of messages**

1. Widget -> lesson, once loaded: `hello` (no `instanceId`).
   `{"protocol":"poodll-widget","version":1,"type":"poodll-widget:hello","payload":{"capabilities":{"resize":true,"grade":true}}}`
2. Lesson -> widget: `init`.
   `{"protocol":"poodll-widget","version":1,"type":"poodll-widget:init","instanceId":"abc123","payload":{"uiLanguage":"ja","parentOrigin":"https://school.example.org","gradeReportingEnabled":true}}`
   Keep `instanceId` and put it in **every** later message. Switch to `uiLanguage` (with the fallback in
   section 5). Remember `gradeReportingEnabled`.
3. Widget -> lesson: `ready`, with the language actually used.
   `{"protocol":"poodll-widget","version":1,"type":"poodll-widget:ready","instanceId":"abc123","payload":{"uiLanguage":"ja"}}`
4. Widget -> lesson: `resize`, right after `ready` and whenever the content height changes.
   `{"protocol":"poodll-widget","version":1,"type":"poodll-widget:resize","instanceId":"abc123","payload":{"height":520}}`
5. Lesson -> widget, sometimes: `measure` (the page width changed). Reply with a `resize`.
6. Widget -> lesson, optional: `grade`, when the learner finishes a try (section 7).
   `{"protocol":"poodll-widget","version":1,"type":"poodll-widget:grade","instanceId":"abc123","payload":{"earned":4,"possible":9,"final":true}}`

If a second `init` arrives (the lesson restarted the session), use its new `instanceId` and send `ready` again.

**Height**

- Wrap the whole page in one root element (`<main id="widget-root">`) and report **that element's** height.
  Do not give `html`, `body` or the root `height: 100%`, `100vh` or a `min-height` tied to the window: the
  frame's height follows your report, so measuring something that follows the frame makes a loop.
- Use `body { margin: 0; }` and put spacing inside the root, so the root's height is the whole page.
- The lesson owns the width. Lay out for any width from 320 px up, with no horizontal scrolling.

**Reference code.** Put it at the end of the page's script, after `STRINGS` and your own code. Keep the checks.
`applyLanguage(tag)` is yours: show the page in that language (one of the keys of `STRINGS`).

```js
const PROTOCOL = 'poodll-widget';
const params = new URLSearchParams(window.location.search);
const parentOrigin = params.get('parentOrigin') || '';
const embedded = window.parent !== window && /^https?:\/\/[^/?#\s]+$/.test(parentOrigin);
let instanceId = null;
let gradeReportingEnabled = false;
let lastHeight = 0;

// Pick a language this widget has strings for: exact, then base language, then the default.
function pickLanguage(tag) {
    const wanted = String(tag || '').replace('_', '-');
    const keys = Object.keys(STRINGS);
    return keys.find((k) => k.toLowerCase() === wanted.toLowerCase())
        || keys.find((k) => k.toLowerCase() === wanted.split('-')[0].toLowerCase())
        || DEFAULT_LANGUAGE;
}

function send(type, payload) {
    if (!embedded) {
        return;
    }
    const message = {protocol: PROTOCOL, version: 1, type: PROTOCOL + ':' + type, payload: payload || {}};
    if (instanceId) {
        message.instanceId = instanceId;
    }
    window.parent.postMessage(message, parentOrigin);
}

function reportHeight(force) {
    if (!instanceId) {
        return;
    }
    const height = Math.ceil(document.getElementById('widget-root').getBoundingClientRect().height);
    if (force || Math.abs(height - lastHeight) >= 2) {
        lastHeight = height;
        send('resize', {height: height});
    }
}

let heightQueued = false;
new ResizeObserver(() => {
    if (!heightQueued) {
        heightQueued = true;
        requestAnimationFrame(() => {
            heightQueued = false;
            reportHeight(false);
        });
    }
}).observe(document.getElementById('widget-root'));

window.addEventListener('message', (event) => {
    if (!embedded || event.source !== window.parent || event.origin !== parentOrigin) {
        return;
    }
    const message = event.data;
    if (!message || message.protocol !== PROTOCOL || message.version !== 1 || typeof message.type !== 'string') {
        return;
    }
    const payload = (message.payload && typeof message.payload === 'object') ? message.payload : {};
    if (message.type === PROTOCOL + ':init' && typeof message.instanceId === 'string') {
        instanceId = message.instanceId;
        gradeReportingEnabled = payload.gradeReportingEnabled === true;
        const used = pickLanguage(payload.uiLanguage);
        applyLanguage(used);
        send('ready', {uiLanguage: used});
        reportHeight(true);
    } else if (message.type === PROTOCOL + ':measure' && message.instanceId === instanceId) {
        reportHeight(true);
    }
});

// Call this when the learner finishes a try. earned and possible are whole numbers.
function reportGrade(earned, possible) {
    if (gradeReportingEnabled && instanceId) {
        send('grade', {earned: earned, possible: possible, final: true});
    }
}

applyLanguage(pickLanguage(params.get('lang')));
send('hello', {capabilities: {resize: true, grade: true}});
```

Define `DEFAULT_LANGUAGE` (the manifest's `defaultUiLanguage`) next to `STRINGS`. Set the `hello`
capabilities to match the manifest.

## 7. Scores

- Only for activities with a score (`capabilities.grade: true`), and only when `init` said
  `gradeReportingEnabled: true` (`reportGrade()` above checks that).
- `earned` and `possible` are **whole numbers**: `possible` at least 1, `earned` from 0 to `possible`. Never
  percentages, decimals or strings. Example: 3 right out of 4 is `reportGrade(3, 4)`.
- Report once when the learner finishes a try (e.g. on the results screen). If they try again, report again at
  the end of that try; the lesson uses the last one.
- The widget never moves the lesson on. The learner presses the lesson's own Next button when they are done.
- An activity with no score is fine: set `capabilities.grade` to `false` and never call `reportGrade()`.

## 8. Quality

- Real interactions, no dead controls. Clear instructions in the interface language.
- Accessible: real `<button>` elements, keyboard use, visible focus, labels, alt text, enough contrast, no
  meaning by colour alone. Announce feedback that appears later (`aria-live="polite"`).
- Mobile first: works at 320 px wide, in portrait and landscape, with tap targets of at least 44 px.
- Give images `width`/`height` or `aspect-ratio`, so the page does not jump while they load.

## 9. Export checklist

Check before you hand over the file, and fix anything that fails:

- [ ] One HTML file with the manifest block in its `<head>`; the block is valid JSON with the right `id` and
      `type`; `uiLanguages` lists exactly the languages in `STRINGS`; `capabilities` match what the widget does.
- [ ] Nothing is loaded from another site: no CDN `<script>` or `<link>`, no web fonts, no remote images.
- [ ] No `localStorage`, `sessionStorage`, IndexedDB, cookies, `eval`, `alert`/`confirm`/`prompt`,
      `window.open` or `target="_blank"`.
- [ ] No syntax errors; the widget works on its own, and with `?lang=` for each language.
- [ ] The protocol code checks `event.source`, `event.origin` and `protocol`/`version`, sends to
      `parentOrigin` (never `"*"`), and includes `instanceId` after `init`.
- [ ] The height report measures the root element; no `100vh`/`height: 100%` on `html`, `body` or the root; no
      horizontal scroll at 320 px.
- [ ] Scores are whole numbers, reported only through `reportGrade()`.
- [ ] Target-language content is the same in every interface language.

## 10. When a ZIP is needed

Use a ZIP only for audio, video or images too large to put in the HTML file. Make it only if you can create
files; otherwise tell the teacher.

```text
manifest.json         the manifest (section 3, as a file), with "entry": "index.html"
index.html            the page
assets/...            audio, video, images
widget.js, widget.css optional, if not inline
```

- Put the files at the top of the ZIP (or all inside one folder). Reference them by **relative paths**
  (`assets/cat.webp`, never `/assets/cat.webp` or `https://...`).
- **Allowed file types only:** `html htm css js mjs json txt md vtt png jpg jpeg gif webp avif svg ico woff
  woff2 ttf otf mp3 m4a ogg oga wav mp4 webm`. Anything else rejects the ZIP.
- No hidden files (names starting with a dot), no `..` or absolute paths, no backslashes or `: * ? " < > |` in
  names, no symlinks, no password, no two names that differ only in case.
- **Limits:** ZIP at most 20 MB; at most 80 MB unpacked; each file at most 15 MB; at most 200 files; folders at
  most 8 deep.
- A ZIP widget can also load `locales/<tag>.json` with `fetch('locales/ja.json')` (relative URL), but inline
  `STRINGS` work just as well.
