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
defined('MOODLE_INTERNAL') || die();

use mod_minilesson\constants;


/**
 * AI transcript Functions used generally across this mod
 *
 * @package    mod_minilesson
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aitranscriptutils {


    public static function render_passage($passage, $markuptype='passage') {
        // load the HTML document
        $doc = new \DOMDocument;
        // it will assume ISO-8859-1  encoding, so we need to hint it:
        //see: http://stackoverflow.com/questions/8218230/php-domdocument-loadhtml-not-encoding-utf-8-correctly
        $safepassage = mb_convert_encoding($passage, 'HTML-ENTITIES', 'UTF-8');
        @$doc->loadHTML($safepassage, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);

        // select all the text nodes
        $xpath = new \DOMXPath($doc);
        $nodes = $xpath->query('//text()');

        // Base CSS class.
        // We will add _mu_passage_word and _mu_passage_space. Can be customized though
        $cssword = constants::M_CLASS . '_mu_' .$markuptype . '_word';
        $cssspace = constants::M_CLASS . '_mu_' .$markuptype . '_space';


        // Init the text count.
        $wordcount = 0;
        foreach ($nodes as $node) {
            $trimmednode = trim($node->nodeValue);
            if (empty($trimmednode)) {
                continue;
            }

            // Explode missed new lines that had been copied and pasted. eg A[newline]B was not split and was one word.
            // This resulted in ai selected error words, having different index to their passage text counterpart.
            $seperator = ' ';
            // $words = explode($seperator, $node->nodeValue);

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
                // $newnode->appendChild($spacenode);
                // print_r($newnode);
                $newnode->setAttribute('id', $cssword . '_' . $wordcount);
                $newnode->setAttribute('data-wordnumber', $wordcount);
                $newnode->setAttribute('class', $cssword);
                $spacenode->setAttribute('id', $cssspace . '_' . $wordcount);
                $spacenode->setAttribute('data-wordnumber', $wordcount);
                $spacenode->setAttribute('class', $cssspace);
                $node->parentNode->appendChild($newnode);
                $node->parentNode->appendChild($spacenode);
                // $newnode = $doc->createElement('span', $word);
            }
            $node->nodeValue = "";
        }

        $usepassage = $doc->saveHTML();
        // Remove container 'p' tags, they mess up formatting in solo.
        $usepassage = str_replace('<p>', '', $usepassage);
        $usepassage = str_replace('</p>', '', $usepassage);

        $ret = \html_writer::div($usepassage, constants::M_CLASS . '_' . $markuptype .'cont ' . constants::M_CLASS . '_summarytranscriptplaceholder');
        return $ret;
    }

    /*
    * Turn a passage with text "lines" into html "brs"
    *
    * @param String The passage of text to convert
    * @param String An optional pad on each replacement (needed for processing when marking up words as spans in passage)
    * @return String The converted passage of text
    */
    public static function lines_to_brs($passage, $seperator='') {
        // See https://stackoverflow.com/questions/5946114/how-to-replace-newline-or-r-n-with-br .
        return str_replace("\r\n", $seperator . '<br>' . $seperator, $passage);
        // This is better but we can not pad the replacement and we need that.
        /* return nl2br($passage); */
    }

}
