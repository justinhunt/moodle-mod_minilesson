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
 * Ukrainian strings for minilessonitem_audiochat
 *
 * @package    minilessonitem_audiochat
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Аудіочат';
$string['audiochat_silenceduration'] = 'Пауза перед відповіддю ШІ';
$string['audiochat_silenceduration_details'] = 'Скільки часу учень має мовчати (у мілісекундах), перш ніж ШІ вважатиме його репліку завершеною та відповість. Збільш, щоб дати учням більше часу на паузу й роздуми посеред речення. Зменш для швидшого, більш розмовного обміну. За замовчуванням 3500 (3,5 секунди). Значення поза діапазоном від 500 до 30000 замінюються значенням за замовчуванням. Діє лише після того, як учень заговорив, і лише коли для елемента ввімкнено «Автоматичне надсилання».';
$string['cantchat_ningxia'] = 'Чат НЕДОСТУПНИЙ. Щоб використовувати цей тип елемента, у налаштуваннях адміністратора активності MiniLesson має бути вказано дійсний ключ {$a}. Чат також недоступний у Китаї (регіон Нінся)';
$string['cloudpoodll'] = 'Cloud Poodll';
$string['gemini'] = 'Gemini';
$string['geminilivemodel'] = 'Модель Gemini Live';
$string['geminilivemodel_details'] = 'Модель Gemini Live, яку використовують, коли провайдер — Gemini. Gemini 3.8 Live — поточне значення Google за замовчуванням для голосу з низькою затримкою. Прев’ю 3.1 — старіша модель, і Google радить відмовитися від неї. Це стосується провайдерів Gemini та Cloud Poodll, але не OpenAI.';
$string['gradingfailed'] = 'ШІ не зміг оцінити цей чат, тому результат — 0. Транскрипт збережено.';
$string['item_desc'] = 'Елемент «Аудіочат» налаштовує голосову розмову з партнером на основі ШІ. Його можна використовувати для обговорення теми або запитань до зображення. Підказки для бота ШІ — вільний текст, який можна редагувати.';
$string['openai'] = 'OpenAI';
$string['pluginname'] = 'Аудіочат';
$string['provider'] = 'Провайдер';
$string['provider_details'] = 'Обери провайдера ШІ для елемента «Аудіочат». Cloud Poodll працює одразу, але не в регіоні Нінся. Для інших потрібно вказати ключ API в адміністративних налаштуваннях BYOK для minilesson.';
$string['unknown'] = 'Невідомо';
