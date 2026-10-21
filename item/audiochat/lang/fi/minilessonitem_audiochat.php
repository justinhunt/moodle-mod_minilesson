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
 * Finnish strings for minilessonitem_audiochat
 *
 * @package    minilessonitem_audiochat
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Puhekeskustelu';
$string['audiochat_silenceduration'] = 'Tauko ennen kuin tekoäly vastaa';
$string['audiochat_silenceduration_details'] = 'Kuinka kauan opiskelijan on oltava puhumatta (millisekunteina), ennen kuin tekoäly pitää hänen vuoroaan päättyneenä ja vastaa. Suurenna arvoa, jos opiskelijoille halutaan antaa enemmän aikaa tauoille ja ajattelulle kesken lauseen. Pienennä sitä, jos haluat nopeampaa ja keskustelumaisempaa vuoropuhelua. Oletus on 3500 (3,5 sekuntia). Alueen 500–30000 ulkopuoliset arvot palautuvat oletukseen. Koskee vain sen jälkeen, kun opiskelija on puhunut, ja vain kun osiossa on Lähetä automaattisesti käytössä.';
$string['cantchat_ningxia'] = 'Chat EI ole käytettävissä. Tämän osiotyypin käyttö edellyttää, että MiniLesson-aktiviteetin ylläpitoasetuksissa on kelvollinen {$a}-avain. Chat ei ole käytettävissä myöskään Kiinassa (Ningxian alue).';
$string['cloudpoodll'] = 'Cloud Poodll';
$string['gemini'] = 'Gemini';
$string['geminilivemodel'] = 'Gemini Live -malli';
$string['geminilivemodel_details'] = 'Gemini Live -malli, jota käytetään, kun palveluntarjoaja on Gemini. Gemini 3.8 Live on Googlen nykyinen oletus matalan viiveen puheelle. 3.1-esiversio on vanhempi malli, ja Google suosittelee siirtymään pois siitä. Koskee Gemini- ja Cloud Poodll -palveluntarjoajia, ei OpenAI:ta.';
$string['gradingfailed'] = 'Tekoäly ei voinut arvioida tätä keskustelua, joten tulokseksi tuli 0. Litteraatti on tallennettu.';
$string['item_desc'] = 'Puhekeskustelu-osio luo puhutun keskustelun tekoälykumppanin kanssa. Sitä voi käyttää aiheen keskusteluun tai kuvasta kysymiseen. Tekoälybotille annettavat kehotteet ovat vapaata, muokattavaa tekstiä.';
$string['openai'] = 'OpenAI';
$string['pluginname'] = 'Puhekeskustelu';
$string['provider'] = 'Palveluntarjoaja';
$string['provider_details'] = 'Valitse tekoälypalveluntarjoaja Puhekeskustelu-osiolle. Cloud Poodllin pitäisi toimia suoraan, mutta ei Ningxian alueella. Muille sinun on annettava API-avain MiniLessonin BYOK-ylläpitoasetuksissa.';
$string['unknown'] = 'Tuntematon';
