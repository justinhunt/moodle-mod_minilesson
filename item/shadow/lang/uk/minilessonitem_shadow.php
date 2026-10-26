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
 * Ukrainian strings for minilessonitem_shadow
 *
 * @package    minilessonitem_shadow
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Відео shadowing';
$string['aihelper_placeholder_shadow'] = 'наприклад: Додай розділові знаки та виправ усі орфографічні помилки й помилки з великими літерами';
$string['autoforward'] = 'Автоматичний перехід';
$string['autoforward_details'] = 'Після останнього повторення рядка автоматично переходить до наступного рядка замість очікування «ОК / Далі». У цьому режимі немає кнопки «Повторити».';
$string['enablesubtitlefetch'] = 'Увімкнути кнопку отримання субтитрів';
$string['enablesubtitlefetch_details'] = 'Показує на формі елемента кнопку «Отримати субтитри», яка завантажує субтитри відео YouTube до редактора субтитрів. Примітка: отримання субтитрів працює не завжди й може припинити працювати будь-коли. Це допоміжний інструмент, доступність якого Poodll не гарантує.';
$string['error:badshadowlines'] = 'Рядки для shadowing мають бути * (усі рядки) або списком номерів рядків через кому, наприклад 1,4,5,6.';
$string['error:badtimestamp'] = 'Час початку й кінця кліпу має бути у форматі hh:mm:ss, наприклад 00:01:30.';
$string['error:badvtt'] = 'Не вдалося обробити субтитри. Введи дійсний WebVTT щонайменше з одним фрагментом із часом.';
$string['error:nocuesinclip'] = 'Жоден рядок субтитрів не потрапляє повністю між часом початку й кінця кліпу. Змінь час або субтитри.';
$string['error:noshadowlines'] = 'Жоден із вибраних номерів рядків не відповідає рядку субтитрів між часом початку й кінця кліпу.';
$string['error:novideoid'] = 'Потрібен ID відео YouTube або URL-адреса.';
$string['error:startafterend'] = 'Час кінця кліпу має бути пізнішим за час початку.';
$string['error:subtitlefetchdisabled'] = 'Отримання субтитрів вимкнено на цьому сайті.';
$string['fetchvtt'] = 'Отримати субтитри';
$string['fetchvtt_disabled'] = 'Автоматичне отримання субтитрів наразі вимкнено';
$string['fetchvtt_failed'] = 'Не вдалося отримати субтитри з YouTube.';
$string['fetchvtt_fetching'] = 'Отримання...';
$string['fetchvtt_invalidurl'] = 'Спочатку введи дійсну URL-адресу YouTube або ID відео з 11 символів.';
$string['fetchvtt_overwrite'] = 'Це замінить субтитри, які зараз є в редакторі. Продовжити?';
$string['fetchvtt_overwrite_title'] = 'Замінити субтитри?';
$string['item_desc'] = 'Елемент «Відео shadowing» відтворює кліп YouTube рядок за рядком. Учні виконують shadowing кожного рядка субтитрів, промовляючи слова разом із відео, поки вони підсвічуються.';
$string['loopcount'] = 'Кількість shadowing на рядок';
$string['loopcount_desc'] = 'Скільки разів повторюється кожен рядок, щоб учень виконав shadowing.';
$string['loopindicator'] = 'Shadowing: {$a->current} / {$a->total}';
$string['oknext'] = 'ОК / Далі';
$string['paste_empty'] = 'Спочатку встав транскрипт, скопійований з YouTube.';
$string['paste_failed'] = 'Не вдалося перетворити транскрипт.';
$string['paste_label'] = 'Транскрипт, скопійований з YouTube';
$string['paste_placeholder'] = '0:00
перший рядок транскрипту
0:03
другий рядок транскрипту';
$string['paste_step_copy'] = 'Виділи весь транскрипт, скопіюй його й встав у поле нижче';
$string['paste_step_open_novideo'] = 'Відкрий відео на YouTube (спочатку введи ID відео у формі, щоб отримати тут посилання)';
$string['paste_step_showtranscript'] = 'Під відео розгорни опис і натисни «Показати транскрипт»';
$string['paste_step_timestamps'] = 'У меню ⋮ панелі транскрипту переконайся, що позначки часу ввімкнено';
$string['paste_success'] = 'Додано рядків субтитрів: {$a}';
$string['paste_wordhighlightoff'] = 'Підсвічування окремих слів вимкнено, бо вставлений транскрипт містить час лише для цілих рядків. Кліп підсвічуватиме по одному рядку.';
$string['pastevtt'] = 'Вставити транскрипт';
$string['pastevtt_convert'] = 'Перетворити на субтитри';
$string['pastevtt_title'] = 'Вставити транскрипт YouTube';
$string['pluginname'] = 'Відео shadowing';
$string['retry'] = 'Повторити';
$string['rotatedevice'] = 'Щоб продовжити, поверни пристрій у вертикальний режим.';
$string['shadow_instructions1'] = 'Подивись відео. Потім виконуй shadowing кожного рядка: слухай і говори разом із відео, коли воно відтворюється знову.';
$string['shadowlines'] = 'Рядки для shadowing';
$string['shadowlines_desc'] = 'Номери рядків субтитрів для shadowing, що відлічуються від 1 у субтитрах нижче, наприклад 1,4,5,6. Використай *, щоб виконувати shadowing усіх рядків. Інші рядки все одно показуються під час перегляду.';
$string['shadowpause'] = 'Пауза між shadowing (секунди)';
$string['shadowvtt'] = 'Субтитри (WebVTT)';
$string['shadowvtt_desc'] = 'Встав або відредагуй субтитри WebVTT для кліпу в області нижче.';
$string['startshadowing'] = 'Почати shadowing';
$string['watchhint'] = 'Натисни відтворення й подивись кліп. Коли він закінчиться, натисни «Почати shadowing».';
$string['wordhighlight'] = 'Увімкнути підсвічування по словах';
$string['wordhighlight_details'] = 'Підсвічувати кожне слово під час вимови за допомогою часових міток слів у субтитрах. Часові мітки слів у YouTube на деяких відео ненадійні — вимкни цей параметр, щоб підсвічувати цілі рядки. Коли параметр вимкнено, під час отримання субтитрів також не отримуються часові мітки слів.';
$string['ytclipdetails'] = 'Кліп YouTube (ID/URL, початок і кінець у секундах)';
