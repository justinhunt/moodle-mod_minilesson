import { StreamLanguage } from "@codemirror/language";
import { snippetCompletion } from "@codemirror/autocomplete";

export const yarnLanguage = StreamLanguage.define({
    name: "yarn",
    startState: () => ({ inHeader: true, inCommand: false, commandName: false, inOption: false }),
    token(stream, state) {
        // Node metadata headers (e.g. title: NodeName)
        if (state.inHeader) {
            if (stream.match(/^---/)) {
                state.inHeader = false;
                return "meta";
            }
            if (stream.match(/^[a-zA-Z0-9_]+:/)) {
                return "keyword";
            }
            stream.eatSpace();
            if (stream.eatWhile(/[^]/)) {
                return "string";
            }
            return null;
        }

        // Node separator
        if (stream.match(/^===/)) {
            state.inHeader = true;
            return "meta";
        }

        // Comments
        if (stream.match(/^\/\/.*/)) {
            return "lineComment";
        }

        // Inside a command << ... >>
        if (state.inCommand) {
            if (stream.eatSpace()) return null;
            if (stream.match(/^>>/)) {
                state.inCommand = false;
                state.commandName = false;
                return "keyword"; // Color bracket as keyword
            }
            if (stream.match(/^\$[a-zA-Z0-9_]+/)) {
                state.commandName = false;
                return "variable-2"; // Highly visible variable color
            }
            if (stream.match(/^"[^"]*"/)) {
                state.commandName = false;
                return "string";
            }
            if (stream.match(/^[+-]?([0-9]*[.])?[0-9]+/)) {
                state.commandName = false;
                return "number";
            }
            if (stream.match(/^[=+\-*/<>!&|]+/)) {
                state.commandName = false;
                return "operator";
            }
            if (stream.match(/^[a-zA-Z_][a-zA-Z0-9_]*/)) {
                let word = stream.current();
                let isKeyword = /^(?:if|else|elseif|endif|set|jump|detour|return|declare|picture|audio|video|translate|once|endonce|to|is|not|and|or|true|false)\b/i.test(word);
                let isFunction = /^(?:dice|visited|node_name|currentNode)\b/i.test(word);

                if (state.commandName) {
                    state.commandName = false;
                    return "keyword"; // First word is always highlighted as a main command keyword
                }
                if (isKeyword) {
                    return "keyword";
                }
                if (isFunction) {
                    return "builtin";
                }
                // Unquoted arguments like "cave" in <<jump cave>>
                return "string-2";
            }
            stream.next();
            return null;
        }

        if (stream.match(/^<</)) {
            state.inCommand = true;
            state.commandName = true;
            return "keyword";
        }

        // Shortcut Options and Line Groups -> or =>
        if (stream.match(/^(?:->|=>)/)) {
            return "keyword strong";
        }

        // Inside Options [[ ... ]]
        if (state.inOption) {
            if (stream.match(/^\]\]/)) {
                state.inOption = false;
                return "keyword";
            }
            if (stream.match(/^\|/)) {
                return "operator";
            }
            if (stream.match(/^\$[a-zA-Z0-9_]+/)) {
                return "variable-2";
            }
            if (stream.match(/^[{}]/)) {
                return "bracket";
            }
            stream.next();
            return "link";
        }

        if (stream.match(/^\[\[/)) {
            state.inOption = true;
            return "keyword";
        }

        // Inline Expressions { $var + 1 }
        if (stream.match(/^[{}]/)) {
            return "bracket";
        }

        // Variables anywhere
        if (stream.match(/^\$[a-zA-Z0-9_]+/)) {
            return "variable-2";
        }

        // Character lines "Guard:"
        if (stream.sol() || stream.string.slice(0, stream.pos).match(/^\s*$/)) {
            if (stream.match(/^[a-zA-Z0-9_]+:/)) {
                return "def"; // Blue/bold usually
            }
        }

        // Hashtags #line:1234
        if (stream.match(/^#[^\s]+/)) {
            return "tagName";
        }

        stream.next();
        return null;
    }
});

const commandOptions = [
    { label: "jump", type: "keyword", info: "Jumps to another node" },
    { label: "detour", type: "keyword", info: "Jumps to another node and returns" },
    { label: "return", type: "keyword", info: "Returns from a detour" },
    { label: "declare", type: "keyword", info: "Initializes a new variable" },
    { label: "set", type: "keyword", info: "Updates an existing variable" },
    { label: "if", type: "keyword", info: "Conditional logic block" },
    { label: "elseif", type: "keyword", info: "Alternative conditional logic" },
    { label: "else", type: "keyword", info: "Fallback conditional logic" },
    { label: "endif", type: "keyword", info: "Ends a conditional block" },
    { label: "picture", type: "keyword", info: "Poodll custom picture command" },
    { label: "audio", type: "keyword", info: "Poodll custom audio command" },
    { label: "video", type: "keyword", info: "Poodll custom video command" },
    { label: "translate", type: "keyword", info: "Poodll browser translation" },
    { label: "once", type: "keyword", info: "Displays a block only once" },
    { label: "endonce", type: "keyword", info: "Ends a once block" }
];

export function yarnCompletions(context) {
    let word = context.matchBefore(/<<\w*/);
    if (!word) return null;
    if (word.from === word.to && !context.explicit) return null;

    return {
        from: word.from + 2, // Skip the <<
        options: commandOptions
    };
}
