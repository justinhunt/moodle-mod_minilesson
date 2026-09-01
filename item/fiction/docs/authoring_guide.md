# Writing an interactive fiction story (Yarn) for mod_minilesson

This guide covers **how to write the story script itself**. It applies wherever you are asked
for a fiction story in Yarn format - whether you are composing a `fiction` item for direct
import, or supplying a story to a lesson template input that asks for "an interactive fiction
story written in Yarn v2 format".

It does not cover how to package the story into import JSON. If you are hand-composing an
import item, the field names, file areas and payload shape are in the item type's import spec
(`aigen_fetch_item_type_details` with `itemtype: "fiction"`).

---

## Part 1: The syntax this runtime accepts

The story runs on a customised build of yarn-bound. What follows is what this player
actually supports. Do not assume other Yarn Spinner features are available.

### Nodes

A story is a series of nodes:

```
title: Start
---
The rain has not stopped for three days.
<<jump Harbour>>
===
```

- `title: NodeName` on its own line, then `---` alone, then the body, then `===` alone.
- **The first node must be `title: Start`.** The player always begins there.
- Node names are identifiers: letters, digits and underscores, **no spaces**.

### Story lines

- Plain text is narration.
- `Character: some dialogue` attributes the line to a speaker. Character names cannot
  contain spaces - use underscores, which are displayed as spaces (`Old_Sailor: ...`).

### Choices

```
The door is not locked. Somewhere below deck, something moves.

-> Go down the steps
    You feel your way down into the dark.
    <<jump LowerDeck>>
-> Stay where you are and listen
    You hold still. The sound does not come again.
    <<jump WaitOnDeck>>
```

- Each option is a `-> Option text` line; its consequence is the indented block beneath it.
- **Every option must end by routing somewhere**: `<<jump NodeName>>` or `<<detour NodeName>>`.
- **Two option sets must be separated by narrative text.** Consecutive sets with nothing
  between them merge into one list and behave unpredictably.
- Conditional options take a trailing `<<if>>` with **no** `<<endif>>`:
  `-> Unlock the chest <<if $has_key>>`

### Flow commands

| Command | Effect |
| --- | --- |
| `<<jump Node>>` | Go to `Node` and do not come back. |
| `<<detour Node>>` | Run `Node`, then resume where you left off. The detour node ends with `<<return>>`. |
| `<<return>>` | Ends a detour node and returns to the interrupted flow. |
| `<<stop>>` | Ends the story. |

A detour is the right tool for an inconsequential choice: the side node adds colour, then
control returns to the main path automatically.

### Variables

```
<<declare $has_stake = false>>
<<declare $health = 5>>
<<set $health = $health - 1>>
```

- Declare every variable with `<<declare $name = value>>` before it is used. Declarations are
  read from the whole script up front, so a declare in any node initialises the variable.
- **Compound operators are not supported.** Write `<<set $v = $v + 1>>`, never `<<set $v += 1>>`.
- Show a value in text with an inline expression: `You have {$health} strength left.`

### Conditionals

```
<<if $has_stake and $forcefield_off>>
    You have everything you need.
<<elseif $has_stake>>
    You grip the stake, but the hum of the forcefield says you are not ready.
<<else>>
    You have nothing to fight with.
<<endif>>
```

Operators: `and`, `or`, `not`, `xor`, `==`, `!=`, `<`, `<=`, `>`, `>=`, `+`, `-`, `*`, `/`, `%`.

### Built-in functions

- `dice(n)` - a random whole number from 1 to n.
- `visited("NodeName")` - true if the learner has already been through that node.
- `random()` - a random number between 0 and 1; `floor(n)` - round down.
- `translate("text")` - renders the text translated into the learner's native language.

### System variables

`$userfirstname`, `$userlastname`, `$userfullname` are filled in with the learner's name.
`$cantranslate` is true when translation is available to this learner.

### Scoring

If the story sets `$score`, its final value becomes the learner's grade for the item, rounded
and clamped to 0-100. Use it when you want the story's outcome to count towards the lesson
grade, e.g. `<<set $score = 100>>` on the winning ending and a lower value on weaker endings.
If you never set `$score`, the item is not graded on outcome.

### Media

`<<picture forest.png>>`, `<<audio storm.mp3>>`, `<<video arrival.mp4>>` show media alongside
the current line; `<<clearpicture>>` removes it. The filename must exactly match a file
supplied with the item. Only use these when the story is being given media files - a command
naming a file that was not supplied shows nothing.

---

## Part 2: How to write a good one

Work through these four stages **in order**, finishing each before starting the next. Do not
try to produce the finished Yarn in a single pass - the quality difference is large. If you
have somewhere to write intermediate files, use it; otherwise keep each stage's output in
your reply before moving on.

### Stage 1 - Blueprint

Before writing any prose, fix the logic:

1. **Objective and failure condition.** A clear way to win and a clear way to lose - kill
   Dracula before sunrise, escape the island with your dog.
2. **Three success pre-conditions:**
   - two binary states or items to collect (`$has_stake`, `$forcefield_off`)
   - one numeric track that choices raise and lower (`$health` starting at 5,
     `$time_remaining` starting at 6)
3. **Chapters.** Between 5 and 9, tracing the linear path to success.
4. **Length.** The story should take about 20 minutes to read. Target the learner's CEFR level:

   | Level | Whole story | Minimum per chapter |
   | --- | --- | --- |
   | A1 | 1400 words | 200 words |
   | A2 | 2000 words | 280 words |
   | B1 | 2700 words | 350 words |
   | B2 | 3600 words | 450 words |
   | C1 | 4200 words | 550 words |
   | C2 | 4800 words | 650 words |

### Stage 2 - The linear story

Write the successful path through the whole story as plain prose, with no Yarn formatting and
no choices yet.

- **Second person throughout** ("You push the door open").
- Hold to the CEFR level's vocabulary and sentence structure.
- Introduce every minor character with a descriptive clause rather than a bare name:
  *"Pribluda, the regional KGB chief, is standing by the window."*
- Write each chapter out in full to at least the minimum length above. Do not summarise or
  skip ahead - the length is what makes it a 20-minute read.

### Stage 3 - The choice matrix

For every chapter, design **exactly two sets of choices**:

- **One inconsequential set.** Extra narrative or detail about the surroundings, detouring and
  merging back without changing any variable. Make these choices of *action*, not of how "you"
  feel, think or notice.
- **One consequential set.** Changes a variable, costs time or health, awards one of the key
  items, or branches toward a premature bad ending.

Both options in a set must be ones a reader might genuinely pick:

```
Bad - nobody ignores a mysterious letter:
    -> Read the mysterious and exciting letter
    -> Don't read the letter

Good - either is reasonable:
    -> Read the letter now
    -> Put the letter in your pocket for later
```

Write out the concrete consequence of every single option before you compile.

### Stage 4 - Compile and check

Turn the prose and the choice matrix into Yarn, then verify it.

**Do not restate the options as a question in the prose above them.** Let the narrative lead
into the choices:

```
Bad:
    Do you want to look at the window or the bed?
    -> Look at the window
    -> Look at the bed

Good:
    The cabin is quiet except for the whistling wind. What now?
    -> Check the frost-covered window
    -> Check the messy unmade bed
```

Then check every one of these before you submit:

- [ ] The first node is `title: Start`.
- [ ] Every node has `title:`, `---` and `===`.
- [ ] Every `<<jump>>` and `<<detour>>` target exists. No dead ends, no hanging paths.
- [ ] Every detour node ends with `<<return>>`.
- [ ] Every option ends with a jump or a detour.
- [ ] No two option sets sit next to each other without narrative text between them.
- [ ] Every variable used is declared, and no compound operators (`+=`) appear.
- [ ] Every media filename matches a file actually supplied.
- [ ] No stray formatting or editorial notes (`[side story]`, chapter headings, word counts).

The server validates the structural rules on submission and reports every problem it finds,
so a story that fails comes back with a list you can fix directly.
