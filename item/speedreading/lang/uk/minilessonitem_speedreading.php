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
 * Ukrainian strings for minilessonitem_speedreading
 *
 * @package    minilessonitem_speedreading
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Швидкочитання';
$string['answercorrect'] = 'Правильно';
$string['answersneeded'] = 'Дай відповідь на всі запитання, щоб надіслати. Відповідь можна змінити до надсилання.';
$string['answerwrong'] = 'Неправильно';
$string['cannotgoback'] = 'Повернутися до тексту не можна.';
$string['charactercount'] = 'Символів: {$a}';
$string['charactersperminute'] = 'символів за хвилину';
$string['correctansweris'] = 'Правильна відповідь:';
$string['defaultinstructions'] = 'Прочитай текст якомога швидше. Потім дай відповіді на запитання.';
$string['error:correctanswertoohigh'] = 'Номер правильної відповіді більший за кількість відповідей.';
$string['error:needtwoanswers'] = 'Введи щонайменше дві відповіді, по одній у рядку.';
$string['error:passmark'] = 'Введи число від 0 до 100.';
$string['error:targetwpm'] = 'Введи число, більше за 0.';
$string['error:wordcount'] = 'Введи 0 або більше. Залиш порожнім, щоб рахувати автоматично.';
$string['finishedreading'] = 'Завершити читання';
$string['formexplainer'] = 'Учні бачать чотири екрани. Таймер запускається, коли вони натискають «Почати читання», і зупиняється, коли натискають «Завершити читання». Повернутися до тексту не можна. Кожен розділ нижче налаштовує один екран.';
$string['formexplainer_heading'] = 'Як працює цей елемент';
$string['formexplainer_step1'] = 'Назва, інструкції, поради й необов’язкове зображення. Учні натискають «Почати читання».';
$string['formexplainer_step2'] = 'Текст і таймер. Кнопка завершення — наприкінці тексту.';
$string['formexplainer_step3'] = 'До 5 запитань із вибором відповіді. Учні не бачать, чи відповіли правильно, до результатів.';
$string['formexplainer_step4'] = 'Швидкість читання, бали за швидкість, результат тесту, відповіді з відгуками та текст.';
$string['gradeitem'] = 'Оцінювати цей елемент';
$string['gradeitem_details'] = 'Оцінка — це результат тесту. Швидкість читання учень бачить, але вона не входить в оцінку.';
$string['instructions'] = 'Інструкції';
$string['intronote'] = 'Таймер запуститься, коли ти натиснеш «Почати читання». Після завершення повернутися до тексту не можна.';
$string['introtext'] = 'Вступний текст';
$string['introtext_details'] = 'Інструкції — це один короткий рядок під назвою. Вступний текст може містити поради, а зображення необов’язкове. Примітка про те, що таймер запускається, коли учень натискає «Почати читання», і що повернутися до тексту не можна, додається автоматично.';
$string['item_desc'] = 'Вправа зі швидкочитання. Учень читає текст, поки працює таймер, а потім відповідає на запитання до нього. Учень бачить свою швидкість читання у словах за хвилину.';
$string['minsec'] = '{$a->minutes} хв {$a->seconds} с';
$string['noanswer'] = 'Немає відповіді';
$string['notread'] = 'Ти не дочитав текст, тому швидкості читання немає.';
$string['passage'] = 'Текст';
$string['passage_details'] = 'Обирай легкий текст: учні мають знати майже всі слова. Швидкочитання тренує швидке читання, а не вивчення нових слів.';
$string['passagestatus'] = 'Слів: {$a->count}. За цільової швидкості {$a->target} слів за хвилину це займе приблизно {$a->time}.';
$string['passagestatus_cpm'] = 'Символів: {$a->count}. За цільової швидкості {$a->target} символів за хвилину це займе приблизно {$a->time}.';
$string['passmark'] = 'Поріг зарахування швидкості';
$string['passmark_details'] = 'Швидкість читання зараховується, лише коли правильних відповідей щонайменше стільки відсотків.';
$string['passmarkstatus'] = 'Якщо запитань {$a->questions}, учням потрібно правильних відповідей: {$a->needed}.';
$string['picture'] = 'Зображення';
$string['pluginname'] = 'Швидкочитання';
$string['questionanswers'] = 'Відповіді (по одній у рядку)';
$string['questioncount'] = 'Запитань: {$a}';
$string['questionfeedback'] = 'Відгук (необов’язково)';
$string['questionno'] = 'Запитання {$a}';
$string['questionofn'] = 'Запитання {$a->number} з {$a->total}';
$string['questions'] = 'Запитання';
$string['questions_details'] = 'Заповни від 1 до 5 запитань. Учні не бачать, чи відповіли правильно, до екрана результатів. Відгуки показуються лише на екрані результатів.';
$string['questiontext'] = 'Текст запитання';
$string['readingspeed'] = 'Твоя швидкість читання';
$string['readtime'] = 'Слів: {$a->count} за {$a->minutes} хв {$a->seconds} с';
$string['readtime_cpm'] = 'Символів: {$a->count} за {$a->minutes} хв {$a->seconds} с';
$string['screen1'] = 'Екран 1: Вступ';
$string['screen2'] = 'Екран 2: Текст для читання';
$string['screen3'] = 'Екран 3: Тест';
$string['screen4'] = 'Екран 4: Результати й оцінювання';
$string['showallquestions'] = 'Показати всі запитання одночасно';
$string['showallquestions_details'] = 'Вимкнено: по одному запитанню, натискання переходить далі. Увімкнено: усі запитання на одній сторінці з кнопкою надсилання.';
$string['showtimer'] = 'Показувати таймер під час читання';
$string['showtimer_details'] = 'Час записується в будь-якому разі. Сховай таймер, якщо він хвилює учнів.';
$string['speedcounts'] = 'Твою швидкість зараховано. Потрібно було {$a->needed} з {$a->total} правильних, у тебе {$a->correct}.';
$string['speeddoesnotcount'] = 'Цього разу швидкість не зараховано. Потрібно було {$a->needed} з {$a->total} правильних, у тебе {$a->correct}. Наступного разу читай трохи повільніше, щоб краще зрозуміти.';
$string['speedpoints'] = 'Бали за швидкість';
$string['speedreading'] = 'Швидкочитання';
$string['startreading'] = 'Почати читання';
$string['submitanswers'] = 'Надіслати відповіді';
$string['tapnext'] = 'Натисни відповідь, щоб перейти до наступного запитання. Результати побачиш наприкінці.';
$string['targetis'] = 'Ціль: {$a}';
$string['targetwpm'] = 'Цільова швидкість';
$string['targetwpm_details'] = 'Ця швидкість або вища дає 10 балів за швидкість. Половина цієї швидкості або нижча дає 0.';
$string['thepassage'] = 'Текст';
$string['timerrunning'] = 'Таймер працює';
$string['toofast'] = 'Це було надто швидко для справжнього читання, тому швидкість не зараховано. Наступного разу прочитай увесь текст.';
$string['wordcount'] = 'Кількість слів';
$string['wordcount_details'] = 'Залиш порожнім, щоб слова рахувалися автоматично. Заповни, щоб задати певну кількість слів.<br>Примітка: для японської, китайської, корейської та тайської ми рахуємо символи (не слова).';
$string['wordcountshort'] = 'Слів: {$a}';
$string['wordsperminute'] = 'слів за хвилину';
$string['youranswer'] = 'Твоя відповідь:';
$string['youranswers'] = 'Твої відповіді';
