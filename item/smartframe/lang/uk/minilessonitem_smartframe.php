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
 * Ukrainian strings for minilessonitem_smartframe
 *
 * @package    minilessonitem_smartframe
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Smart Frame';
$string['aiinstructions'] = 'Створи віджет за допомогою ШІ';
$string['aiinstructions_copied'] = 'Інструкції для ШІ скопійовано';
$string['aiinstructions_copy'] = 'Скопіювати інструкції для ШІ';
$string['aiinstructions_download'] = 'Завантажити';
$string['aiinstructions_edit'] = 'Щоб пізніше змінити віджет, завантаж його з поля файлів нижче, додай до нового чату разом із цими інструкціями й напиши, що змінити.';
$string['aiinstructions_intro'] = 'Створи віджет у ChatGPT, Claude або Gemini, де його можна випробувати й попросити змін:';
$string['aiinstructions_show'] = 'Показати інструкції';
$string['aiinstructions_step1'] = 'Встав ці інструкції в новий чат або завантаж їх і додай файл.';
$string['aiinstructions_step2'] = 'Опиши активність. Наприклад: «Вікторина на минулий час 10 поширених дієслів з інструкціями англійською та японською, яка повідомляє бал».';
$string['aiinstructions_step3'] = 'Випробуй його в чаті й проси змін, доки він тобі не сподобається.';
$string['aiinstructions_step4'] = 'Напиши «Export it for Poodll», завантаж файл, який отримаєш, і завантаж його нижче.';
$string['error:htmlencoding'] = 'HTML-файл має бути збережений як текст UTF-8.';
$string['error:importbase64'] = 'Файл віджета {$a} не є коректним base64. Закодуй байти файлу як звичайний base64, без префікса «data:».';
$string['error:importboth'] = 'Передай віджет або як widgethtml, або як файл у customfile1, але не обидва.';
$string['error:importgradepolicy'] = 'gradepolicy має бути 0 (ігнорувати бал віджета), 1 (зберегти його) або 2 (зарахувати до оцінки уроку).';
$string['error:importnofile'] = 'В елементі немає віджета. Додай HTML віджета в його поле widgethtml.';
$string['error:importonefile'] = 'В елементі більше одного файлу в області файлів customfile1. Додай лише один файл віджета (.html або .zip).';
$string['error:manifestentry'] = '«entry» у маніфесті ({$a}) має вказувати на HTML-файл у ZIP.';
$string['error:manifestfield'] = '«{$a}» у маніфесті відсутнє або некоректне.';
$string['error:manifestjson'] = 'Маніфест віджета не є коректним JSON.';
$string['error:manifestprotocol'] = 'Цьому віджету потрібен новіший хост уроків. Цей хост використовує протокол версії {$a}.';
$string['error:manifestschema'] = '«schemaVersion» у маніфесті має бути {$a}.';
$string['error:nomanifest'] = 'У ZIP немає manifest.json на верхньому рівні.';
$string['error:nomanifesthtml'] = 'У HTML-файлі немає маніфесту віджета. Потрібен блок &lt;script type="application/json" id="poodll-widget-manifest"&gt;. Попроси інструмент ШІ: «export it for Poodll».';
$string['error:notazip'] = 'Файл не є ZIP-файлом або пошкоджений.';
$string['error:notpublished'] = 'Цей віджет не вдалося опублікувати, тому учні його не бачать. Збережи елемент ще раз, щоб повторити спробу.';
$string['error:onezip'] = 'Завантаж лише один файл віджета. Видали інші.';
$string['error:publishfailed'] = 'Не вдалося опублікувати віджет.';
$string['error:zipbadpath'] = 'ZIP містить недозволену назву файлу: {$a}';
$string['error:zipduplicate'] = 'ZIP містить два файли з однаковою назвою: {$a}';
$string['error:zipencrypted'] = 'ZIP захищено паролем. Завантаж ZIP без пароля.';
$string['error:zipfiletoobig'] = 'Файл {$a->file} завеликий. Найбільший дозволений розмір — {$a->size}.';
$string['error:zipfiletype'] = 'Такий тип файлу у віджеті не дозволено: {$a}';
$string['error:zipread'] = 'Не вдалося прочитати файл {$a} із ZIP.';
$string['error:zipsymlink'] = 'ZIP містить посилання, що не дозволено: {$a}';
$string['error:ziptoobig'] = 'ZIP завеликий. Найбільший дозволений розмір ZIP — {$a}.';
$string['error:ziptoomanyfiles'] = 'ZIP містить забагато файлів. Дозволено щонайбільше {$a}.';
$string['error:ziptotaltoobig'] = 'Загальний розмір файлів у ZIP завеликий. Дозволено щонайбільше {$a}.';
$string['error:zipunreadable'] = 'Не вдалося прочитати ZIP.';
$string['gradepolicy'] = 'Бал віджета';
$string['gradepolicy_help'] = 'Деякі віджети повідомляють бал, наприклад 3 з 4. Вибери, що урок з ним робить.

* Ігнорувати: бал не використовується.
* Зберегти, але не оцінювати: бал зберігається разом зі спробою, але не враховується в оцінці уроку.
* Використати як оцінку елемента: бал враховується в оцінці уроку, перерахований до загальної кількості балів. Учень, який переходить далі, не завершивши, отримує 0.

Бал, який надсилає віджет, обчислюється в браузері учня, тож учень може його змінити. Використовуй його для практики, а не для важливих тестів.';
$string['gradepolicy_ignore'] = 'Ігнорувати';
$string['gradepolicy_record'] = 'Зберегти, але не оцінювати';
$string['gradepolicy_use'] = 'Використати як оцінку елемента';
$string['item_desc'] = 'Smart Frame завантажує вивантажену HTML-сторінку в рамку всередині Minilesson. Він адаптивний, безпечний і може виставляти оцінку. Його можна використовувати для пояснювальних віджетів, власних інтерактивних вправ або навіть ігор.';
$string['pluginname'] = 'Smart Frame';
$string['uilanguage'] = 'Мова віджета';
$string['uilanguage_auto'] = 'Автоматично';
$string['uilanguage_help'] = 'Мова інструкцій, кнопок і відгуків віджета. Мова, яку вивчають, від цього не змінюється.

Автоматично використовується власна мова учня, якщо віджет її пропонує, потім рідна мова уроку, потім мова Moodle учня, а потім мова віджета за замовчуванням. Збережи елемент один раз, щоб вибирати з мов віджета.';
$string['warning:importuilanguage'] = 'uilanguage «{$a->lang}» не є однією з мов інтерфейсу віджета ({$a->languages}), тому учні бачитимуть його мову за замовчуванням.';
$string['widget_instructions1'] = 'Виконай активність нижче.';
$string['widgetnotavailable'] = 'Ця активність зараз недоступна.';
$string['widgetpackage'] = 'Файл віджета';
$string['widgetpackage_help'] = 'Віджет: або один HTML-файл (його найлегше створюють інструменти ШІ), або ZIP для віджета з аудіо, відео чи великою кількістю зображень.

HTML-файл має містити маніфест віджета в блоці &lt;script type="application/json" id="poodll-widget-manifest"&gt;. ZIP має містити manifest.json і HTML-сторінку на верхньому рівні. Усе, що використовує віджет, має бути у файлі або в ZIP: віджет не може завантажувати файли з інших сайтів і не може використовувати сховище браузера (наприклад, localStorage) чи файли cookie.';
$string['widgetsummary'] = 'Поточний віджет';
$string['widgetsummary_text'] = '<strong>{$a->title}</strong><br>Мови: {$a->languages}<br>Повідомляє бал: {$a->grade}';
