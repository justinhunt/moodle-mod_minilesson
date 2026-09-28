const esbuild = require('esbuild');
const fs = require('fs');
const path = require('path');

const outPath = path.resolve(__dirname, '../../amd/src/codeeditor.js');

esbuild.build({
    entryPoints: ['index.js'],
    bundle: true,
    format: 'iife',
    globalName: 'CodeEditor',
    write: false
}).then(result => {
    const code = result.outputFiles[0].text;
    const wrapper = `define(['jquery', 'core/ajax', 'core/modal', 'core/modal_events', 'core/templates', 'core/str'], function($, Ajax, Modal, ModalEvents, Templates, Str) {
/* eslint-disable */
${code}
window.CodeEditor = CodeEditor;
CodeEditor.Moodle = { Ajax, Modal, ModalEvents, Templates, Str };
return {
  setupCodeEditor: CodeEditor.setupCodeEditor
};
});
`;
    fs.writeFileSync(outPath, wrapper);
    console.log('Successfully built and wrapped codeeditor.js');
}).catch(() => process.exit(1));
