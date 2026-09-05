# Writing an interactive fiction story (Yarn) for mod_minilesson

This guide covers **how to write the story script itself**. It applies wherever you are asked
for a fiction story in Yarn format - whether you are composing a `fiction` item for direct
import, or supplying a story to a lesson template input that asks for "an interactive fiction
story written in Yarn v2 format".

It does not cover how to package the story into import JSON. If you are hand-composing an
import item, the field names, file areas and payload shape are in the item type's import spec
(`aigen_fetch_item_type_details` with `itemtype: "fiction"`).

---

## Part 1: Which kind of story?

There are two shapes of interactive fiction, and they are written differently. Decide which
one you are writing **before** you plan anything else.

- **Narrative fiction** - a plot advances through chapters. The learner's choices change what
  happens next and which ending they reach. Movement through *time*.
- **Spatial fiction** - the learner moves between locations, collects items, and uses them to
  unlock other locations. The goal is reached by solving the map. Movement through *space*.

Read the request for these signals:

| | Spatial | Narrative |
| --- | --- | --- |
| The request centres on | a place to move around in, and things to find in it | a plot that advances in time |
| Typical wording | "a haunted castle", "escape the lab", keys, locked doors, inventory, explore, find the... | "a spy in 1980s Moscow", "what happens next", chapters, a character arc, an adaptation |
| Language it drills | prepositions of place, directions, imperatives, object vocabulary, have/has got | past tenses, connectives, reported speech, reading stamina |
| Text per screen | short, with vocabulary repeating as rooms are revisited - sits well at A1-A2 | longer prose - sits well at B1 and above |
| What the learner does | explores, gets stuck, retries | reads once, straight through |

Then:

- **Pick one shape as the backbone.** A narrative story may contain a single chapter the
  learner explores spatially, but do not try to build both structures at once - the result is
  a map with no story and a story with no map.
- **Name the shape you chose in the plan** you present before creating anything, alongside the
  level and the topic. It is a decision you made on the user's behalf, so say so.
- **Ask the user only when the request reads equally well as either.** "A story set in an old
  museum" could be either; "a story about a night guard who discovers why the museum is being
  robbed" is narrative; "explore the closed museum and find out what the light in the window
  is" is spatial.

Once you have chosen: read Part 2 (the syntax, shared by both), then **Part 3A** for narrative
or **Part 3B** for spatial, then Part 4 to compile and check.

---

## Part 2: The syntax this runtime accepts

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

### Showing something only once

`<<once>>` marks content the learner should see or use a single time. It needs no variable of
your own - the runtime creates and sets a hidden one for you. There are three forms:

```
-> Search the desk <<once>>
    Under a pile of receipts you find a brass key.
    <<set $has_key = true>>
    <<jump Study>>
```

An option marked `<<once>>` disappears from the list after it has been taken. This is how a
pickup stops being collectable twice, and how a question to a character drops out of the menu
once it has been asked.

```
The floorboard by the window is loose. <<once>>
```

A line marked `<<once>>` shows on the first pass through the node and never again.

```
<<once>>
The hall smells of dust and cold stone. Portraits watch you from both walls.
<<else>>
You are back in the hall. The portraits have not moved.
<<endonce>>
```

The block form gives first-visit text and return text. The `<<else>>` half is optional. **A
block `<<once>>` must be closed with `<<endonce>>`** - without it the rest of the node is
swallowed.

Any of the three can be narrowed with a condition: `<<once if $lamp_lit>>` shows the content
one time, and only on a pass where the condition holds.

### Built-in functions

- `dice(n)` - a random whole number from 1 to n.
- `visited("NodeName")` - true if the learner has already been through that node. The other
  way to vary a place by whether it has been seen: `<<if visited("Cellar")>>` reads the state
  of a *different* node, where the `<<once>>` block reads only its own.
- `random()` - a random number between 0 and 1; `floor(n)` - round down.
- `translate("text")` - renders the text translated into the learner's native language.

### Two smaller tools

- **Node groups.** Several nodes may share one `title:` if each carries a `when:` line in its
  header (`when: $lamp_lit`, `when: always`). One eligible variant is picked when the node is
  entered. An alternative to a long `<<if>>` chain when a place changes a lot with state.
- **Line groups.** `=> Some line <<if $cond>>` - a group of `=>` lines, of which one eligible
  line is chosen automatically. Useful for a character's idle remarks.

Neither is needed for a good story; reach for them only when an `<<if>>` would be unwieldy.

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

When the story is getting pictures:

- Put the `<<picture XX.png>>` command at the **top of the node**, as the first line after the
  `---` divider, so the image is on screen for the whole of that node's text.
- Filenames are two digits, counting up from `01.png` with **no gaps**, to a maximum of 30.
- **Narrative:** one picture at the top of each chapter node, at most two in a chapter, placed
  at the dramatic moments. Not every node needs one.
- **Spatial:** one picture per location, at the top of the room node, so it comes back each
  time the learner returns there. **The same room always reuses the same filename** - a place
  that looks different on every visit destroys the sense of a map. Add one for each ending and
  each major discovery. A 4-8 room story sits comfortably inside the 30 limit.

---

## Part 3A: Writing a narrative story

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

### Stage 4 - Compile

Go to Part 4.

---

## Part 3B: Writing a spatial story

Same discipline: four stages, in order, each finished before the next.

### Stage 1 - Blueprint

Fix the map and its logic before writing any prose:

1. **Objective and failure condition.** A clear way to win - escape the house before dawn,
   recover the stolen mask - and a clear way to lose.
2. **The map.** 4 to 8 interconnected locations, one node each. **Movement is bidirectional**:
   wherever the learner can go, they can come back. A one-way move is allowed only where the
   text warns them first ("Once the hatch closes behind you, it will not open again").
3. **Two or more key-and-lock gates.** A barrier - a locked door, a dark passage, a guard who
   will not let you past - that opens only with an item or a piece of knowledge found
   *somewhere else on the map*. Every key must be reachable before the lock it opens.
4. **Item pickups.** Each one guarded with `<<once>>` on the option, or with a boolean flag,
   so it cannot be collected twice.
5. **Re-entry.** Every room needs a first-visit description and a shorter return description
   (`<<once>>...<<else>>...<<endonce>>`), plus any state overrides - "The iron door now hangs
   open."
6. **A threat counter.** An integer the learner spends or loses: health, torch turns, minutes
   before the tide. At least one Game Over node it can lead to.
7. **Characters.** 1 to 3 NPCs, each anchored to a specific room. Track each one's disposition
   with an integer (`$guard_trust`) and their quest state with booleans.

The CEFR budget in Stage 1 of Part 3A applies here too, with one difference: a spatial story
spreads its words across rooms and revisits rather than chapters, so any single room's prose is
much shorter than a chapter's. Aim at the same whole-story total.

### Stage 2 - Room and dialogue prose

Write the text library before writing any Yarn. For every room:

- **First-visit description** - vivid, sets the atmosphere, names the exits and the objects
  worth examining.
- **Subsequent-visit description** - short and scannable. The learner has read the long one already. NB Describes the location, not the movement to it or subsequentness. e.g You are in the library. NOT You are back in the library. 
- **State overrides** - the extra lines that appear once something has changed.

For every character, write their actual lines as `Character_Name: text`. Second person
throughout for narration, CEFR-appropriate vocabulary, and a descriptive clause on each
character's first appearance.

### Stage 3 - The choice matrix

For each room, list every option and where it goes:

- **Movement.** One option per adjacent room, naming the destination so the learner can build
  a mental map: `-> Go north to the courtyard`.
- **Investigation.** Things to examine or search, marked `<<once>>` so the room's option list
  gets shorter as it is exhausted rather than repeating forever.
- **Consequential actions.** Spending a resource, attempting a gate, taking a risk. Gate
  attempts are conditional options: `-> Unlock the cellar door <<if $has_brass_key>>`.
- **Character options.** Trades and requests gated with `<<if $has_item>>`; lore questions
  marked `<<once>>`; a conversation with several turns routed through
  `<<detour Guard_Conversation>>` that ends in `<<return>>`, so the learner comes back to the
  room's own options afterwards.

Every option must be one a learner might genuinely choose, for a believable motive. An option
that exists only to be the wrong answer wastes a turn.

### Stage 4 - Compile

Go to Part 4.

---

## Part 4: Compile and check

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
- [ ] Every node can be reached from `Start` by some route.
- [ ] Every detour node ends with `<<return>>`.
- [ ] Every option ends with a jump or a detour.
- [ ] No two option sets sit next to each other without narrative text between them.
- [ ] Every variable used is declared, and no compound operators (`+=`) appear.
- [ ] Every block `<<once>>` is closed with `<<endonce>>`.
- [ ] Every media filename matches a file actually supplied.
- [ ] No stray formatting or editorial notes (`[side story]`, chapter headings, word counts).

And for a spatial story, also:

- [ ] Every room can be left again, by every route into it - or the text warned first.
- [ ] Every gate's key is reachable before the gate itself.
- [ ] Every pickup is guarded by `<<once>>` or a flag, so it cannot be taken twice.
- [ ] Every room has a return description as well as a first-visit one.
- [ ] The objective is reachable: walk the winning route yourself, room by room, and confirm
      every gate along it opens with something you have already collected.

The server checks the structural rules it can see - a missing `Start`, jumps and detours that
point nowhere, a detour that never returns, a node that offers choices but routes nowhere,
compound assignment, variables that are never given a value, unclosed `<<once>>` blocks - and
reports all of them at once, so a rejected story comes back with a list you can fix directly.
It cannot tell you whether the story is winnable, whether a room is unreachable, or whether
the prose is any good. Those are yours to check.
