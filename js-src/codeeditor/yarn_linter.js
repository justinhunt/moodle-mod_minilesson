import { linter } from "@codemirror/lint";

export const yarnLinter = linter((view) => {
    let diagnostics = [];
    let doc = view.state.doc;

    // Simplistic node-level checks
    // The Yarn syntax has specific constraints:
    // - Header starts with `title: `
    // - Header delimiter `---`
    // - Node terminator `===`
    // - Commands in `<< >>` must be lowercase.
    // - Variables must start with `$`

    let inCommand = false;
    let commandStart = 0;

    for (let i = 1; i <= doc.lines; i++) {
        let lineObj = doc.line(i);
        let text = lineObj.text;

        // Check commands constraints
        let cmdMatch = text.match(/<</g);
        let cmdCloseMatch = text.match(/>>/g);

        let openings = cmdMatch ? cmdMatch.length : 0;
        let closings = cmdCloseMatch ? cmdCloseMatch.length : 0;

        if (openings !== closings) {
            diagnostics.push({
                from: lineObj.from,
                to: lineObj.to,
                severity: "error",
                message: "Unclosed command block (mismatched << >>)",
            });
        }

        // Find individual commands to check for uppercase and validity
        let cmdRegex = /<<([^>]+)>>/g;
        let match;
        while ((match = cmdRegex.exec(text)) !== null) {
            let cmdInner = match[1].trim();
            // First token is the command name
            let tokens = cmdInner.split(/\s+/);
            let cmdName = tokens[0];

            if (!cmdName) continue;

            let cmdStartPos = lineObj.from + match.index + match[0].indexOf(cmdName);

            // Allow variables as commands maybe (like <<set $var = 1>>), but if it's a structural command:
            if (!cmdName.startsWith('$')) {
                if (cmdName !== cmdName.toLowerCase()) {
                    diagnostics.push({
                        from: cmdStartPos,
                        to: cmdStartPos + cmdName.length,
                        severity: "error",
                        message: `Commands must be lowercase. Found: ${cmdName}`,
                    });
                }

                const validCommands = ['jump', 'detour', 'return', 'declare', 'set', 'if', 'elseif', 'else', 'endif', 'picture', 'audio', 'video', 'translate', 'once', 'endonce'];
                if (!validCommands.includes(cmdName.toLowerCase())) {
                    diagnostics.push({
                        from: cmdStartPos,
                        to: cmdStartPos + cmdName.length,
                        severity: "warning",
                        message: `Unknown command: ${cmdName}`,
                    });
                }
            }

            // Check for unsupported compound operators: +=, -=, etc.
            if (cmdInner.match(/(\+=|-=|\*=|\/=)/)) {
                diagnostics.push({
                    from: lineObj.from + match.index,
                    to: lineObj.from + match.index + match[0].length,
                    severity: "error",
                    message: `Compound operators (+=, -=, etc.) are not supported. Use $var = $var + 1`,
                });
            }
        }

        // Check Character lines (must not contain spaces in names before the colon)
        let charLineMatch = text.match(/^([^:]+):/);
        if (charLineMatch && text.trim().length > 0) {
            let prefix = charLineMatch[1];
            // If it's not a header property, it's a character line. But wait, headers also have colons.
            // A simple heuristic: if it has spaces, it might be an invalid character name.
            if (prefix.trim().indexOf(' ') !== -1 && !text.startsWith('title:') && !text.startsWith('when:')) {
                diagnostics.push({
                    from: lineObj.from,
                    to: lineObj.from + prefix.length,
                    severity: "warning",
                    message: "Character names usually should not contain spaces",
                });
            }
        }
    }

    return diagnostics;
});
