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

/**
 * Grade Now for solo plugin
 *
 * @package    mod_minilesson
 * @copyright  2019 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 namespace mod_minilesson;

use mod_minilesson\constants;


/**
 * AI transcript Functions used generally across this mod
 *
 * @package    mod_minilesson
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aitranscriptutils {
    /**
     * Render a passage of text into span-wrapped words for further processing
     *
     * @param string The passage of text to convert
     * @param string The markup type (passage|corrections)
     * @return string The converted passage of text
     */
    public static function render_passage($passage, $markuptype = 'passage') {
        // Load the HTML document.
        $doc = new \DOMDocument();
        // It will assume ISO-8859-1  encoding, so we need to hint it:
        // see: http://stackoverflow.com/questions/8218230/php-domdocument-loadhtml-not-encoding-utf-8-correctly.

        // The old way ... throws errors on PHP 8.2+
        // $safepassage = mb_convert_encoding($passage, 'HTML-ENTITIES', 'UTF-8');
        // @$doc->loadHTML($safepassage, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);.

        // This could work, but on some occasions the doc has a meta header already .. hmm
        // $safepassage = mb_convert_encoding($passage, 'HTML-ENTITIES', 'UTF-8');
        // @$doc->loadHTML('<?xml encoding="utf-8" ? >'
        // $safepassage, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);.

        // The new way .. incomprehensible but works.
        $safepassage = htmlspecialchars($passage, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        @$doc->loadHTML(mb_encode_numericentity($safepassage, [0x80, 0x10FFFF, 0, ~0], 'UTF-8'));

        // Select all the text nodes.
        $xpath = new \DOMXPath($doc);
        $nodes = $xpath->query('//text()');

        // Base CSS class.
        // We will add _mu_passage_word and _mu_passage_space. Can be customized though.
        $cssword = constants::M_CLASS . '_mu_' . $markuptype . '_word';
        $cssspace = constants::M_CLASS . '_mu_' . $markuptype . '_space';

        // Original CSS classes
        // The original classes are to show the original passage word before or after the corrections word
        // because of the layout, "rewritten/added words" [corrections] will show in green, after the original words [red]
        // but "removed(omitted) words" [corrections] will show as a green space  after the original words [red]
        // so the span layout for each word in the corrections is:
        // [original_preword][correctionsword][original_postword][correctionsspace]
        // suggested word: (original)He eat apples => (corrected)He eats apples =>
        // [original_preword: "eat->"][correctionsword: "eats"][original_postword][correctionsspace]
        // removed(omitted) word: (original)He eat devours the apples=> (corrected)He devours the apples =>
        // [original_preword: ][correctionsword: "He"][original_postword: "eat->" ][correctionsspace: " "].

        $cssoriginalpreword = constants::M_CLASS . '_mu_original_preword';
        $cssoriginalpostword = constants::M_CLASS . '_mu_original_postword';

        // Init the text count.
        $wordcount = 0;
        foreach ($nodes as $node) {
            $trimmednode = utils::super_trim($node->nodeValue);
            if (empty($trimmednode)) {
                continue;
            }

            // Explode missed new lines that had been copied and pasted. eg A[newline]B was not split and was one word.
            // This resulted in ai selected error words, having different index to their passage text counterpart.
            $seperator = ' ';

            $nodevalue = self::lines_to_brs($node->nodeValue, $seperator);
            $words = preg_split('/\s+/', $nodevalue);

            foreach ($words as $word) {
                // If its a new line character from lines_to_brs we add it, but not as a word.
                if ($word == '<br>') {
                    $newnode = $doc->createElement('br', $word);
                    $node->parentNode->appendChild($newnode);
                    continue;
                }

                $wordcount++;
                $newnode = $doc->createElement('span', $word);
                $spacenode = $doc->createElement('span', $seperator);
                $newnode->setAttribute('id', $cssword . '_' . $wordcount);
                $newnode->setAttribute('data-wordnumber', $wordcount);
                $newnode->setAttribute('class', $cssword);
                $spacenode->setAttribute('id', $cssspace . '_' . $wordcount);
                $spacenode->setAttribute('data-wordnumber', $wordcount);
                $spacenode->setAttribute('class', $cssspace);
                // Original pre node.
                if ($markuptype !== 'passage') {
                    $originalprenode = $doc->createElement('span', '');
                    $originalprenode->setAttribute('id', $cssoriginalpreword . '_' . $wordcount);
                    $originalprenode->setAttribute('data-wordnumber', $wordcount);
                    $originalprenode->setAttribute('class', $cssoriginalpreword);
                }
                // Original post node.
                if ($markuptype !== 'passage') {
                    $originalpostnode = $doc->createElement('span', '');
                    $originalpostnode->setAttribute('id', $cssoriginalpostword . '_' . $wordcount);
                    $originalpostnode->setAttribute('data-wordnumber', $wordcount);
                    $originalpostnode->setAttribute('class', $cssoriginalpostword);
                }
                // Add nodes to doc.
                if ($markuptype == 'passage') {
                    $node->parentNode->appendChild($newnode);
                    $node->parentNode->appendChild($spacenode);
                } else {
                    $node->parentNode->appendChild($originalprenode);
                    $node->parentNode->appendChild($newnode);
                    $node->parentNode->appendChild($originalpostnode);
                    $node->parentNode->appendChild($spacenode);
                }
            }
            $node->nodeValue = "";
        }

        $usepassage = $doc->saveHTML();
        // Remove container 'p' tags, they mess up formatting in solo.
        $usepassage = str_replace('<p>', '', $usepassage);
        $usepassage = str_replace('</p>', '', $usepassage);

        if ($markuptype == 'passage') {
            $ret = \html_writer::div(
                $usepassage,
                constants::M_CLASS . '_original ' . constants::M_CLASS . '_summarytranscriptplaceholder'
            );
        } else {
            $ret = \html_writer::div($usepassage, constants::M_CLASS . '_corrections ');
        }
        return $ret;
    }

    /**
     * Turn a passage with text "lines" into html "brs"
     *
     * @param string The passage of text to convert
     * @param string An optional pad on each replacement (needed for processing when marking up words as spans in passage)
     * @return string The converted passage of text
     */
    public static function lines_to_brs($passage, $seperator = '') {
        // See https://stackoverflow.com/questions/5946114/how-to-replace-newline-or-r-n-with-br .
        return str_replace("\r\n", $seperator . '<br>' . $seperator, $passage);
        // This is better but we can not pad the replacement and we need that.
    }
}
