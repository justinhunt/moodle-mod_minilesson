# Smart Frame: how its contents are sandboxed

A Smart Frame item shows a page that a teacher uploads: one HTML file, or a ZIP of HTML, CSS,
JavaScript and media. That page can run any JavaScript its author wrote. This document explains
what stops that JavaScript from acting as the person who is viewing it, and what it can still do.

## Summary

- The page runs in a browser sandbox, in an anonymous origin of its own, not as part of the Moodle
  site.
- It cannot read Moodle's cookies, storage or pages, or the lesson page around it.
- It can only load files from its own folder. It cannot send requests to Moodle or to any other
  site, submit forms, open windows, or move the main page to another address.
- It can talk to the lesson only through a small set of messages: its height, and an optional
  score.

## Where the files are served from

The uploaded package is checked, unpacked into Moodle's file storage, and served by
`mod/minilesson/item/smartframe/widget.php`. Each file has an address of this form:

```
widget.php/<item id>/<revision>/<path in the package>
```

The revision is the SHA-256 hash of the uploaded file. Only the current revision of an item is
kept. A file never changes at a given address.

`widget.php` does not use a Moodle session, and it does not check who is asking. It sets
`NO_MOODLE_COOKIES`, so it does not read or set Moodle's session cookie. Anyone who has the
address can load the files. The revision makes the address hard to guess, but it is not a
password: do not put anything private in a Smart Frame package.

## The sandbox

The sandbox is applied in two places, so it holds both inside the lesson and when the page is
opened on its own:

1. The lesson's iframe has `sandbox="allow-scripts allow-forms"` and
   `referrerpolicy="no-referrer"`.
2. Every response from `widget.php` has a `Content-Security-Policy` header that starts with the
   same `sandbox allow-scripts allow-forms` directive. A browser applies it to the page even when
   someone opens the file's address directly in a tab.

The sandbox does not include `allow-same-origin`. This is the most important part. Without it,
the browser gives the page a unique, anonymous origin (shown as `null`), even though the files
come from the Moodle site's own address. As a result the page:

- cannot read or write Moodle's cookies, `localStorage`, `sessionStorage` or IndexedDB;
- cannot read the lesson page that holds it (the parent window) or any other Moodle page;
- cannot read the responses of requests to Moodle, so it cannot obtain a `sesskey`.

The sandbox also leaves out these permissions, so the page cannot:

- open pop-up windows or new tabs (`allow-popups`);
- move the main page, or the lesson, to another address (`allow-top-navigation`);
- open modal dialogs such as `alert()` (`allow-modals`);
- download files (`allow-downloads`);
- use the camera, microphone, location or other device features. The iframe has no `allow`
  attribute, and these features are off by default for a frame from another origin.

## The content security policy

The rest of the `Content-Security-Policy` header limits what the page can load or send:

| Directive | Effect |
|---|---|
| `default-src 'none'` | Anything not listed below is blocked. |
| `script-src <own folder> 'unsafe-inline'` | Scripts only from the page's own folder, or written in the page. |
| `style-src <own folder> 'unsafe-inline'` | Styles only from the page's own folder, or written in the page. |
| `img-src`, `media-src`, `font-src` `<own folder> data:` (and `blob:` for images and media) | Images, audio, video and fonts only from the page's own folder, or made by the page itself. |
| `connect-src <own folder>` | `fetch()`, `XMLHttpRequest`, `WebSocket` and `EventSource` only to the page's own folder. |
| `form-action 'none'` | A form cannot be submitted to any address. |
| `base-uri 'none'` | The page cannot change the base address that its links are resolved against. |
| `object-src 'none'`, `worker-src 'none'`, `manifest-src 'none'` | No plugins, web workers or web app manifests. |

"Own folder" means `widget.php/<item id>/<revision>/`, not the whole Moodle site. So the page cannot
send a request to any other Moodle address, such as `login/change_password.php`,
`user/editadvanced.php` or `lib/ajax/service.php`. The browser blocks the request before it is
sent.

`allow-forms` is in the sandbox only because some pages handle a form's submit event in
JavaScript, and without it the browser drops the event. `form-action 'none'` still stops the form
from being sent anywhere.

There is no `frame-ancestors` directive, because a lesson may itself be embedded in another site.
A Smart Frame page that some other site frames is still sandboxed, and can reach nothing.

Other headers on each file:

- `X-Content-Type-Options: nosniff`: the browser uses the file type that Moodle sends, and does not
  guess another one.
- `Referrer-Policy: no-referrer`: requests from the page do not reveal the address it came from.
- `Access-Control-Allow-Origin: *`: needed because the page's anonymous origin counts as another
  origin, even when it loads its own files. These files are public anyway (see above).

## An example: changing a password

Say a page's JavaScript tries to set the viewer's Moodle password, and an administrator opens the
lesson. The attempt fails for several separate reasons. Each one would stop it on its own:

1. `connect-src` and `form-action 'none'` stop the request from being sent to any Moodle address.
2. The page runs in an anonymous origin, so it cannot read a Moodle page to get the viewer's
   `sesskey`. Moodle rejects password changes, user edits and web service calls without it.
3. Requests from an anonymous origin count as coming from another site. Browsers then send
   Moodle's session cookie only if it is set with `SameSite=None`, which is a further barrier, not
   one to rely on.

## Checks when a package is uploaded

These checks protect the server, and keep the served files predictable:

- Only these file types are accepted, and each is served with a fixed `Content-Type`: HTML, CSS,
  JavaScript, JSON, plain text, Markdown, WebVTT, common image types (including SVG), web fonts,
  and common audio and video types. Any other file type rejects the package.
- File names are checked: they must be UTF-8, relative paths that stay inside the package. No
  absolute paths, no `.` or `..` parts, no hidden files or folders (names starting with `.`, such
  as `.htaccess`), no backslashes or other special characters, no links, and no two files with the
  same name.
- Password protected ZIPs are rejected.
- Size limits: 20 MB for the ZIP, 15 MB for any one file, 80 MB in total once unpacked, 200 files,
  and folders 8 levels deep. Sizes are measured while reading, and do not trust the sizes the ZIP
  says its files have.
- The package must have a valid manifest (`manifest.json`, or a manifest block in a single HTML
  file).
- Files are read into memory and saved to Moodle's file storage. Nothing is unpacked to the server's
  disk.

SVG files are served for display rather than forced to download. They are sandboxed by the same
header as the HTML pages.

## Messages between the page and the lesson

The page and the lesson talk with `postMessage`, using the `poodll-widget` protocol, version 1.
The lesson treats every message as untrusted:

- It only accepts messages from its own frame's window, with the anonymous origin `null`, and,
  after the first message, carrying a random instance id that the lesson gave the page.
- It accepts four message types: `hello`, `ready`, `resize` and `grade`. Anything else is ignored.
- Nothing in a message is ever run, or added to the lesson page as HTML.
- `resize` sets the frame's height, kept between 120 and 5000 pixels.
- `grade` is only used when the item's grade setting allows it. It must be a final score, with
  whole numbers for points earned and points possible (possible between 1 and 100000, earned
  between 0 and possible). Otherwise it is ignored.

The lesson sends the page its interface language and whether scores are reported. It sends no
user details, and no Moodle data.

## What the sandbox does not prevent

- **Misleading content.** The page decides what it shows. It could show a fake login box or other
  false instructions. Its ways of sending out what someone types are limited, but not all of them
  are closed:
  - The score: points earned and points possible are numbers the page chooses, and teachers can
    see them in the lesson's reports. A few characters could be hidden in them.
  - WebRTC: a page can start a peer connection, and the server lookups that a peer connection
    makes are not covered by browser content security policies. This is a general limit of these
    policies, not something specific to Moodle.
  Both only matter if someone types private information into the page.
- **Unsuitable content.** Nothing checks what the page says or shows.
- **Heavy pages.** A page can use a lot of processor time or memory, and slow down the learner's
  browser tab.
- **Very old browsers.** The protection relies on the browser supporting iframe sandboxes and
  `Content-Security-Policy`. All current browsers do.
- **Private files.** The files are public to anyone with their address (see "Where the files are
  served from").

## Who can add Smart Frame items

Adding or editing items needs the capability `mod/minilesson:itemedit`, which is marked
`RISK_XSS`. By default the editing teacher and manager roles have it. A site that does not want teachers to
add pages with their own JavaScript can disable the Smart Frame item type on the MiniLesson item
types page in site administration.

## The code

- `widget.php`: serves the files.
- `classes/local/widgetstore.php`: stores the files, and sets the policy and headers (`csp()`,
  `serve()`).
- `classes/local/package.php`: checks uploaded packages.
- `templates/smartframe.mustache`: the sandboxed iframe.
- `amd/src/widgethost.js`: the lesson's side of the message protocol.
