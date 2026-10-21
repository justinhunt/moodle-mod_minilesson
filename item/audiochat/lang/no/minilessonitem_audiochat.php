<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Norwegian strings for minilessonitem_audiochat
 *
 * @package    minilessonitem_audiochat
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Lydsamtale';
$string['audiochat_silenceduration'] = 'Pause før AI-en svarer';
$string['audiochat_silenceduration_details'] = 'Hvor lenge eleven må slutte å snakke, i millisekunder, før AI-en regner turen som ferdig og svarer. Øk verdien for å gi elevene mer rom til å ta pauser og tenke midt i en setning. Senk den for en raskere, mer samtalepreget veksling. Standard er 3500 (3,5 sekunder). Verdier utenfor området 500 til 30000 går tilbake til standard. Gjelder bare etter at eleven har snakket, og bare når Send automatisk er på for elementet.';
$string['cantchat_ningxia'] = 'Prat er IKKE tilgjengelig. For å bruke denne elementtypen må du ha en gyldig {$a}-nøkkel angitt i MiniLesson-aktivitetens administrasjonsinnstillinger. Prat er heller ikke tilgjengelig i Kina (Ningxia-regionen).';
$string['cloudpoodll'] = 'Cloud Poodll';
$string['gemini'] = 'Gemini';
$string['geminilivemodel'] = 'Gemini Live-modell';
$string['geminilivemodel_details'] = 'Gemini Live-modellen som brukes når leverandøren er Gemini. Gemini 3.8 Live er Googles nåværende standard for tale med lav latens. 3.1-forhåndsvisningen er den eldre modellen, og Google anbefaler å gå bort fra den. Gjelder for leverandørene Gemini og Cloud Poodll, ikke for OpenAI.';
$string['gradingfailed'] = 'AI-en kunne ikke vurdere denne samtalen, så den fikk 0. Transkripsjonen er lagret.';
$string['item_desc'] = 'Lydsamtale-elementet setter opp en muntlig samtale med en AI-partner. Det kan brukes til å diskutere et emne eller stille spørsmål om et bilde. Ledetekstene til AI-boten er fri tekst og kan redigeres.';
$string['openai'] = 'OpenAI';
$string['pluginname'] = 'Lydsamtale';
$string['provider'] = 'Leverandør';
$string['provider_details'] = 'Velg en AI-leverandør for Lydsamtale-elementet. Cloud Poodll skal fungere rett ut av boksen, men ikke i Ningxia-regionen. For de andre må du oppgi en API-nøkkel i MiniLessons BYOK-administrasjonsinnstillinger.';
$string['unknown'] = 'Ukjent';
