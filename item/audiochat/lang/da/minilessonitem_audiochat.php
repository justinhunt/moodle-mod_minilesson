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
 * Danish strings for minilessonitem_audiochat
 *
 * @package    minilessonitem_audiochat
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Lydsamtale';
$string['aiactions_instructions'] = 'AI\'en kan afslutte chatten og vise billeder, når dine AI-instruktioner beder den om det. For eksempel: »Når eleven har betalt for æblet, så vis apple.png, sig farvel og afslut chatten.«';
$string['aiactions_unsupported'] = 'Gemini Live-modellen, der er valgt i administratorindstillingerne (Extended Thinking), understøtter endnu ikke disse handlinger, så AI\'en vil ikke afslutte chatten eller vise billeder.';
$string['aiactionsheading'] = 'AI-handlinger';
$string['aicanend'] = 'AI\'en kan afslutte chatten';
$string['aicanend_desc'] = 'Lad AI\'en afslutte chatten, når den er slut';
$string['aicanend_help'] = 'Hvis det er markeret, kan AI\'en selv afslutte chatten, som om eleven havde trykket på afslut-knappen. Skriv i AI-instruktionerne, hvornår chatten skal slutte. AI\'en siger farvel først. Eleven kan stadig afslutte chatten med afslut-knappen når som helst.';
$string['aichat_endedbyai'] = 'AI\'en afsluttede chatten.';
$string['aipictures'] = 'Billeder, AI\'en kan vise';
$string['aipictures_help'] = 'Billeder, AI\'en kan vise eleven under chatten. AI\'en kan ikke se billederne og kender dem kun ved deres filnavne, så giv hvert billede et navn, der siger, hvad det viser, fx redapple.png eller blackkitten.jpg. Skriv i AI-instruktionerne, hvornår hvert billede skal vises.';
$string['aipictureshown'] = 'Billede: {$a}';
$string['audiochat_silenceduration'] = 'Pause før AI\'en svarer';
$string['audiochat_silenceduration_details'] = 'Hvor længe eleven skal holde op med at tale, i millisekunder, før AI\'en betragter turen som færdig og svarer. Hæv værdien for at give eleverne mere rum til at holde pause og tænke midt i en sætning. Sænk den for en hurtigere, mere samtalepræget udveksling. Standarden er 3500 (3,5 sekunder). Værdier uden for området 500 til 30000 falder tilbage til standarden. Gælder kun, efter at eleven har talt, og kun når Send automatisk er slået til for elementet.';
$string['cantchat_ningxia'] = 'Chat er IKKE tilgængelig. For at bruge denne elementtype skal du have en gyldig {$a}-nøgle angivet i MiniLesson-aktivitetens administrationsindstillinger. Chat er heller ikke tilgængelig i Kina (Ningxia-regionen).';
$string['cloudpoodll'] = 'Cloud Poodll';
$string['gemini'] = 'Gemini';
$string['geminilivemodel'] = 'Gemini Live-model';
$string['geminilivemodel_details'] = 'Gemini Live-modellen, der bruges, når udbyderen er Gemini. Gemini 3.8 Live er Googles nuværende standard til tale med lav latenstid. 3.1-forhåndsvisningen er den ældre model, og Google anbefaler at gå væk fra den. Gælder for udbyderne Gemini og Cloud Poodll, ikke for OpenAI.';
$string['gradingfailed'] = 'AI\'en kunne ikke bedømme denne samtale, så den fik 0. Transskriptionen er gemt.';
$string['item_desc'] = 'Lydsamtale-elementet opsætter en mundtlig samtale med en AI-partner. Det kan bruges til at diskutere et emne eller stille spørgsmål om et billede. Prompterne til AI-botten er fri tekst og kan redigeres.';
$string['openai'] = 'OpenAI';
$string['pluginname'] = 'Lydsamtale';
$string['provider'] = 'Udbyder';
$string['provider_details'] = 'Vælg en AI-udbyder til Lydsamtale-elementet. Cloud Poodll bør fungere fra start, men ikke i Ningxia-regionen. For de øvrige skal du angive en API-nøgle i MiniLessons BYOK-administrationsindstillinger.';
$string['unknown'] = 'Ukendt';
