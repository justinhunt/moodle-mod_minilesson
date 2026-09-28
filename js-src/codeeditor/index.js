import { EditorState, Compartment } from "@codemirror/state";
import { EditorView, keymap, lineNumbers, highlightSpecialChars, drawSelection, dropCursor, crosshairCursor } from "@codemirror/view";
import { defaultKeymap, history, historyKeymap } from "@codemirror/commands";
import { searchKeymap, highlightSelectionMatches } from "@codemirror/search";
import { bracketMatching, syntaxHighlighting, defaultHighlightStyle } from "@codemirror/language";
import { autocompletion, completionKeymap, closeBrackets, closeBracketsKeymap } from "@codemirror/autocomplete";
import { lintKeymap } from "@codemirror/lint";
import { markdown } from "@codemirror/lang-markdown";
import { html } from "@codemirror/lang-html";

// Custom Yarn specifics
import { yarnLanguage, yarnCompletions } from "./yarn_language.js";
import { yarnLinter } from "./yarn_linter.js";

const basicSetup = [
    lineNumbers(),
    highlightSpecialChars(),
    history(),
    drawSelection(),
    dropCursor(),
    syntaxHighlighting(defaultHighlightStyle, { fallback: true }),
    EditorState.allowMultipleSelections.of(true),
    bracketMatching(),
    closeBrackets(),
    autocompletion(),
    crosshairCursor(),
    highlightSelectionMatches(),
    keymap.of([
        ...closeBracketsKeymap,
        ...defaultKeymap,
        ...searchKeymap,
        ...historyKeymap,
        ...completionKeymap,
        ...lintKeymap
    ]),
    EditorView.lineWrapping
];

// Access Moodle globals passed from the wrapper
const getMoodle = () => window.CodeEditor ? window.CodeEditor.Moodle : null;

// Compartment for dynamic language switching
const languageConf = new Compartment;

function getLanguageExtension(lang) {
    if (lang === 'yarn') {
        return [
            yarnLanguage,
            yarnLinter,
            autocompletion({ override: [yarnCompletions] })
        ];
    } else if (lang === 'markdown') {
        return [markdown()];
    } else if (lang === 'html') {
        return [html()];
    }
    return [];
}

export function setupCodeEditor(elementId, config = {}) {
    let element = typeof elementId === 'string' ? document.getElementById(elementId) : elementId;

    if (!element) {
        console.error("Code Editor: Target element not found", elementId);
        return null;
    }

    let isTextArea = element.tagName === 'TEXTAREA';
    let docContent = config.doc !== undefined ? config.doc : (isTextArea ? element.value : "");

    let maxLines = config.lines || 30;
    let editorTheme = EditorView.theme({
        "&": {
            maxHeight: (maxLines * 1.5) + "em",
            border: "1px solid #ced4da",
            backgroundColor: "#fff",
            width: "100%",
            maxWidth: "1200px"
        },
        ".cm-scroller": {
            overflow: "auto"
        }
    });

    let state = EditorState.create({
        doc: docContent,
        extensions: [
            basicSetup,
            languageConf.of(getLanguageExtension(config.language)),
            editorTheme,
            EditorView.updateListener.of((update) => {
                if (update.docChanged) {
                    if (isTextArea) {
                        element.value = update.state.doc.toString();
                    }
                    if (config.onChange) {
                        config.onChange(update.state.doc.toString());
                    }
                }
            })
        ]
    });

    let view = new EditorView({
        state,
        ...(isTextArea ? {} : { parent: element })
    });

    // Create a container for the editor and the button
    const container = document.createElement('div');
    container.className = 'mod_minilesson_codeeditor_container';
    container.style.position = 'relative';
    container.style.width = '100%';
    container.style.maxWidth = '1200px';
    container.style.marginTop = '40px'; // Space for the AI button

    if (isTextArea) {
        element.style.display = 'none';
        element.parentNode.insertBefore(container, element.nextSibling);
        container.appendChild(view.dom);
    } else {
        // If not a textarea, we wrap the content
        element.appendChild(container);
        container.appendChild(view.dom);
    }

    // AI Helper
    if (config.aihelper) {
        addAIHelperButton(container, view, config);
    }

    // Listen for language change events
    element.addEventListener('ml_codeeditor_set_language', (e) => {
        const newLang = e.detail.language;
        if (newLang) {
            console.log("CodeEditor: Switching language to " + newLang);
            config.language = newLang; // Update config for AI Helper
            view.dispatch({
                effects: languageConf.reconfigure(getLanguageExtension(newLang))
            });
        }
    });

    // Compatibility for slides item specifically
    element.addEventListener('ml_slides_contenttype_change', (e) => {
        const newLang = e.detail.language;
        element.dispatchEvent(new CustomEvent('ml_codeeditor_set_language', { detail: { language: newLang } }));
    });

    // Allow external code (e.g. the shadow item's transcript fetcher) to replace
    // the editor content. Dispatching the change runs the update listener, which
    // keeps the underlying textarea in sync.
    element.addEventListener('ml_codeeditor_set_content', (e) => {
        const newContent = e.detail.content || '';
        view.dispatch({
            changes: { from: 0, to: view.state.doc.length, insert: newContent }
        });
    });

    return view;
}

function addAIHelperButton(container, view, config) {
    const button = document.createElement('button');
    button.className = 'btn btn-secondary btn-sm mod_minilesson_aihelper_btn';
    button.innerHTML = '<i class="fa fa-magic"></i> AI Wizard';
    button.title = "AI Helper";
    
    // Position it at the top right of the container (above the editor)
    button.style.position = 'absolute';
    button.style.top = '-35px';
    button.style.right = '0';
    button.style.zIndex = '10';
    
    container.appendChild(button);

    button.addEventListener('click', (e) => {
        e.preventDefault();
        openAIHelperModal(view, config);
    });
}

function openAIHelperModal(view, config) {
    const Moodle = getMoodle();
    if (!Moodle) {
        console.error("Moodle APIs not available");
        return;
    }

    const { Ajax, Modal, ModalEvents, Templates, Str } = Moodle;

    Modal.create({
        title: Str.get_string('aihelper_modal_title', 'mod_minilesson'),
        body: Templates.render('mod_minilesson/aihelper_modal', {
            placeholder: config.ai_placeholder || ''
        }),
        footer: Templates.render('mod_minilesson/aihelper_modal_footer', {}),
        large: false
    }).then(modal => {
        modal.show();

        const root = modal.getRoot();
        
        root.on('click', '[data-action="generate"]', () => {
            const promptInput = root.find('#mod_minilesson_aihelper_prompt');
            const generateBtn = root.find('[data-action="generate"]');
            const responseContainer = root.find('.mod_minilesson_aihelper_response_container');
            const responsePre = root.find('#mod_minilesson_aihelper_response');
            const loading = root.find('.mod_minilesson_aihelper_loading');
            const applyBtn = root.find('[data-action="apply"]');

            const prompt = promptInput.val();
            if (!prompt) return;

            loading.removeClass('d-none');
            responseContainer.addClass('d-none');
            generateBtn.prop('disabled', true);

            Ajax.call([{
                methodname: 'mod_minilesson_fetch_codeeditor_aihelp',
                args: {
                    itemtype: config.itemtype,
                    language: config.language,
                    prompt: prompt,
                    currentcode: view.state.doc.toString(),
                    contextid: config.contextid
                }
            }])[0].then(res => {
                loading.addClass('d-none');
                generateBtn.prop('disabled', false);
                if (res.status) {
                    console.log('AI Generation successful using: ' + res.provider);
                    responsePre.text(res.response);
                    responseContainer.removeClass('d-none');
                    applyBtn.removeClass('d-none');
                    generateBtn.addClass('d-none'); // Hide generate button after success
                } else {
                    console.log('AI Generation failed using: ' + res.provider);
                    responsePre.text('Error: ' + (res.message || 'Unknown error'));
                    responseContainer.removeClass('d-none');
                }
            }).catch(e => {
                loading.addClass('d-none');
                generateBtn.prop('disabled', false);
                responsePre.text('Error: ' + e.message);
                responseContainer.removeClass('d-none');
                console.error(e);
            });
        });

        root.on('click', '[data-action="apply"]', () => {
            const responsePre = root.find('#mod_minilesson_aihelper_response');
            const newContent = responsePre.text();
            const transaction = view.state.update({
                changes: { from: 0, to: view.state.doc.length, insert: newContent }
            });
            view.dispatch(transaction);
            modal.hide();
        });

        root.on('click', '[data-action="cancel"]', () => {
            modal.hide();
        });

        modal.getRoot().on(ModalEvents.hidden, () => {
            modal.destroy();
        });

        return modal;
    });
}

// Attach to global window for Moodle AMD usage if necessary
window.setupCodeEditor = setupCodeEditor;
