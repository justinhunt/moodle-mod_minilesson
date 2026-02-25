# SCSS Build

This plugin uses a SCSS 7-1 architecture with Dart Sass. The entry point is `main.scss`, which imports all partials using `@use` syntax. The compiled output is `styles.css` at the plugin root, which is the file Moodle loads. Never edit `styles.css` directly — always edit the SCSS source files and compile.

## Setup

npm install

This installs Dart Sass as a dev dependency (defined in `package.json` at the plugin root).

## Compile once

npm run scss


## Watch mode (auto-recompile on save)

npm run scss:watch


Both commands output `styles.css` at the plugin root.
