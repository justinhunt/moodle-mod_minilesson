<?php
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

namespace mod_minilesson\local;

use mod_minilesson\aigen;
use mod_minilesson\utils;

/**
 * Prompts for the requests the AI manager sends to a Moodle AI provider.
 *
 * Every prompt is plain text for the stock core_ai generate_text action, so it works the same with any
 * provider. The role the model should take is written at the top of the prompt rather than set as a
 * system instruction, because the provider's system instruction belongs to the site admin. Prompts that
 * need structured data ask for one JSON object and say exactly which keys it must have; the AI manager
 * checks the reply against those keys.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aiprompts {
    /** @var string Said at the end of every JSON prompt. */
    const JSON_ONLY = 'Reply with the JSON object only: no code fences, no explanations, no other text.';

    /**
     * The English name of a language code, for use in a prompt.
     *
     * @param string|null $language a language code such as en-US, or a language name (returned as it is)
     * @return string
     */
    public static function language_name(?string $language): string {
        if (empty($language)) {
            return 'English';
        }
        $name = utils::get_lang_english_name($language);
        if ($name === $language && class_exists(\Locale::class)) {
            // A code MiniLesson has no name for, such as ja or pt-PT: ask intl for the language's name.
            $intlname = \Locale::getDisplayLanguage(str_replace('_', '-', $language), 'en');
            if (!empty($intlname) && strcasecmp($intlname, $language) !== 0) {
                $name = $intlname;
            }
        }
        return $name;
    }

    /**
     * Correct the grammar and spelling of a passage.
     *
     * Reply: {"correctedtext": "..."}
     *
     * @param string $passage
     * @param string|null $language
     * @return string
     */
    public static function grammar_correction(string $passage, ?string $language): string {
        $lang = self::language_name($language);
        $prompt = "You are a language teacher." . PHP_EOL;
        $prompt .= "Correct the grammar and spelling of the following {$lang} passage. Write in {$lang}." . PHP_EOL;
        $prompt .= 'Return a JSON object of the form {"correctedtext": "the corrected passage"}.' . PHP_EOL;
        $prompt .= self::JSON_ONLY . PHP_EOL;
        $prompt .= 'The passage is:' . PHP_EOL . $passage;
        return $prompt;
    }

    /**
     * Add punctuation to a speech transcript without changing its words.
     *
     * Reply: {"passage": "..."}
     *
     * @param string $passage
     * @param string|null $language
     * @return string
     */
    public static function punctuation(string $passage, ?string $language): string {
        $lang = self::language_name($language);
        $prompt = "Add punctuation to this passage of spoken {$lang}. Do not add, remove or change any of the words. ";
        $prompt .= "Do not correct grammar or word usage." . PHP_EOL;
        $prompt .= 'Return a JSON object of the form {"passage": "the punctuated passage"}.' . PHP_EOL;
        $prompt .= self::JSON_ONLY . PHP_EOL;
        $prompt .= 'The passage is:' . PHP_EOL . $passage;
        return $prompt;
    }

    /**
     * Grade a student's text or speech transcript.
     *
     * Reply: {"correctedtext": "...", "marks": number|null, "feedback": [strings]|null}
     *
     * @param string $submittedtext the student's text (already punctuated if it was speech)
     * @param object $instructions feedbackscheme, feedbacklanguage, markscheme, maxmarks
     * @param string|null $language
     * @param bool $isspeech the text is a speech transcript
     * @return string
     */
    public static function autograde(string $submittedtext, $instructions, ?string $language, bool $isspeech): string {
        $lang = self::language_name($language);
        $feedbackscheme = $instructions->feedbackscheme ?? '';
        $feedbacklanguage = $instructions->feedbacklanguage ?? '';
        $markscheme = $instructions->markscheme ?? '';
        $maxmarks = $instructions->maxmarks ?? '';

        $prompt = "You are a language teacher." . PHP_EOL;
        $prompt .= "Evaluate the following passage of {$lang} (between [[ and ]]) written by a student." . PHP_EOL;
        $prompt .= 'Return a JSON object with these four keys: '
            . '{"submittedtext": "string", "correctedtext": "string", "marks": number, "feedback": ["string"]}.' . PHP_EOL;
        $prompt .= 'Set "submittedtext" to the student\'s passage, unchanged.' . PHP_EOL;
        $prompt .= "Correct any grammar mistakes in the student's {$lang} and set the result to \"correctedtext\".";
        if ($isspeech) {
            $prompt .= " Do not correct spelling or punctuation, because this is an audio transcript.";
        }
        $prompt .= PHP_EOL;

        if (!empty($markscheme)) {
            $prompt .= 'Set "marks" to a single number summing all marks.';
            $prompt .= " The maximum score is: {$maxmarks}.";
            $prompt .= ' ' . $markscheme . PHP_EOL;
        } else {
            $prompt .= 'Set "marks" to null.' . PHP_EOL;
        }

        if (!empty($feedbackscheme)) {
            $prompt .= 'Set "feedback" to an array of strings. Each string is a feedback item.';
            $prompt .= ' ' . trim($feedbackscheme);
            if (!empty($feedbacklanguage)) {
                // Both are language codes, so compare them by name: en-GB and en-US are both English.
                $feedbacklangname = self::language_name($feedbacklanguage);
                if ($feedbacklangname !== $lang) {
                    $prompt .= ' Give feedback in ' . $feedbacklangname . '.';
                }
            }
            $prompt .= PHP_EOL;
        } else {
            $prompt .= 'Set "feedback" to null.' . PHP_EOL;
        }

        $prompt .= self::JSON_ONLY . PHP_EOL;
        $prompt .= '[[' . strip_tags($submittedtext) . ']]';
        return $prompt;
    }

    /**
     * Grade the student's part of a spoken conversation with an AI.
     *
     * Reply: {"score": 0-100, "gradeexplanation": "...", "feedback": "..."}
     *
     * @param string $transcript one turn per line, each starting "AI:" or "Student:"
     * @param string $criteria the teacher's grading instructions
     * @param string|null $targetlanguage
     * @param string|null $feedbacklanguage
     * @return string
     */
    public static function grade_chat(
        string $transcript,
        string $criteria,
        ?string $targetlanguage,
        ?string $feedbacklanguage
    ): string {
        $lang = self::language_name($targetlanguage);
        $feedbacklang = self::language_name($feedbacklanguage ?: $targetlanguage);
        $prompt = "You are a language teacher." . PHP_EOL;
        $prompt .= "Below, between [[ and ]], is the transcript of a spoken conversation in {$lang} between ";
        $prompt .= "an AI and a student who is learning {$lang}. Each line starts with \"AI:\" or \"Student:\". ";
        $prompt .= "The transcript comes from speech recognition, so ignore spelling and punctuation. ";
        $prompt .= "Grade the student's part of the conversation, using the AI's lines only as context." . PHP_EOL;
        $prompt .= "Give a percentage score for the session, an explanation of the score (for teachers), ";
        $prompt .= "and feedback (for the student). Write the feedback in {$feedbacklang}." . PHP_EOL;
        $prompt .= "Grading instructions from the teacher: " . trim($criteria) . PHP_EOL;
        $prompt .= "Write the explanation and the feedback as plain text in short paragraphs separated by a blank line. ";
        $prompt .= "Where a list of points helps, put each point on its own line starting with a hyphen. ";
        $prompt .= "Do not use markdown formatting: no headings, no asterisks and no bold." . PHP_EOL;
        $prompt .= 'Return a JSON object of the form {"score": number from 0 to 100, ';
        $prompt .= '"gradeexplanation": "the explanation", "feedback": "the feedback"}.' . PHP_EOL;
        $prompt .= self::JSON_ONLY . PHP_EOL . PHP_EOL;
        $prompt .= '[[' . $transcript . ']]';
        return $prompt;
    }

    /**
     * Rate how relevant a student's response is to the topic.
     *
     * Reply: {"relevance": 1-10}
     *
     * @param string $topic
     * @param string $submittedtext
     * @return string
     */
    public static function topic_relevance(string $topic, string $submittedtext): string {
        $prompt = "You are a generous language teacher." . PHP_EOL;
        $prompt .= 'Decide how relevant the student response is to the assignment topic. ';
        $prompt .= 'Relevance is a whole number between 1 (not relevant) and 10 (highly relevant).' . PHP_EOL;
        $prompt .= 'Return a JSON object of the form {"relevance": 7}.' . PHP_EOL;
        $prompt .= self::JSON_ONLY . PHP_EOL;
        $prompt .= json_encode(['topic' => $topic, 'student_response' => $submittedtext], JSON_UNESCAPED_UNICODE);
        return $prompt;
    }

    /**
     * Count the different ideas in a passage.
     *
     * Reply: {"count": number}
     *
     * @param string $passage
     * @param string|null $language
     * @return string
     */
    public static function count_unique_ideas(string $passage, ?string $language): string {
        $lang = self::language_name($language);
        $prompt = "Count the different concepts in the following {$lang} passage." . PHP_EOL;
        $prompt .= 'Return a JSON object of the form {"count": 3}.' . PHP_EOL;
        $prompt .= self::JSON_ONLY . PHP_EOL;
        $prompt .= 'The passage is:' . PHP_EOL . $passage;
        return $prompt;
    }

    /**
     * Estimate the CEFR level of a passage.
     *
     * Reply: {"cefr": "A1"|"A2"|"B1"|"B2"|"C1"|"C2"}
     *
     * @param string $passage
     * @param string|null $language
     * @return string
     */
    public static function predict_cefr(string $passage, ?string $language): string {
        $lang = self::language_name($language);
        $prompt = "Evaluate the {$lang} CEFR level of the following passage." . PHP_EOL;
        $prompt .= 'Return a JSON object of the form {"cefr": "B1"}, where the value is one of '
            . 'A1, A2, B1, B2, C1, C2.' . PHP_EOL;
        $prompt .= self::JSON_ONLY . PHP_EOL;
        $prompt .= 'The passage is:' . PHP_EOL . $passage;
        return $prompt;
    }

    /**
     * Generate lesson bank custom field values from a lesson's items.
     *
     * Reply: one JSON object keyed by the custom field short names.
     *
     * @param \core_customfield\field_controller[] $fields keyed by short name
     * @param array $importjson the lesson's items
     * @return string
     */
    public static function customfield_values(array $fields, array $importjson): string {
        $importjsontext = json_encode($importjson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $promptfields = $outputfields = [];
        foreach ($fields as $fieldshortname => $field) {
            $fieldtype = $field->get('type');
            $fielddesc = clean_param($field->get('description'), PARAM_TEXT);
            $fieldoptions = aigen::get_customfield_options($field);
            if ($fieldtype == 'text') {
                $promptfields[] = "**{$fieldshortname}**: {$fielddesc}";
                $outputfields[] = "\"{$fieldshortname}\": \"...\"";
            } else if ($fieldtype === 'select') {
                $fielddesc = str_replace('{optionslist}', join(', ', $fieldoptions), $fielddesc);
                $promptfields[] = "**{$fieldshortname}**: {$fielddesc}";
                $outputfields[] = "\"{$fieldshortname}\": \"...\"";
            } else if ($fieldtype === 'multiselect') {
                $fielddesc = str_replace('{optionslist}', join(', ', $fieldoptions), $fielddesc);
                $promptfields[] = "**{$fieldshortname}**: {$fielddesc}";
                $outputfields[] = "\"{$fieldshortname}\": [\"...\", \"...\"]";
            }
        }

        $promptfields = join(PHP_EOL, array_map(function ($promptfield) {
            return "* {$promptfield}";
        }, $promptfields));

        $outputfields = join(',' . PHP_EOL, array_map(function ($outputfield) {
            return "    {$outputfield}";
        }, $outputfields));

        $jsononly = self::JSON_ONLY;

        return <<<PROMPT
You are an expert linguistic analyst and content structured-data specialist.

**Task:** Analyze the provided JSON object and generate the following fields based on its content.

**Input JSON:**

```json
{$importjsontext}
```

**Output Requirements:**
Provide the output as one valid JSON object using the keys defined below.

{$promptfields}

**Output Format:**

{
{$outputfields}
}

{$jsononly}
PROMPT;
    }
}
