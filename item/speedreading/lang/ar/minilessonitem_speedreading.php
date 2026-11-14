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
 * Arabic strings for minilessonitem_speedreading
 *
 * @package    minilessonitem_speedreading
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'القراءة السريعة';
$string['answercorrect'] = 'صحيح';
$string['answersneeded'] = 'أجب عن جميع الأسئلة لتتمكن من الإرسال. يمكنك تغيير أي إجابة قبل الإرسال.';
$string['answerwrong'] = 'خطأ';
$string['cannotgoback'] = 'لا يمكنك العودة إلى النص.';
$string['charactercount'] = 'عدد الأحرف: {$a}';
$string['charactersperminute'] = 'حرف في الدقيقة';
$string['correctansweris'] = 'الإجابة الصحيحة:';
$string['defaultinstructions'] = 'اقرأ النص بأسرع ما يمكنك، ثم أجب عن الأسئلة.';
$string['error:correctanswertoohigh'] = 'رقم الإجابة الصحيحة أكبر من عدد الإجابات.';
$string['error:needtwoanswers'] = 'أدخل إجابتين على الأقل، كل إجابة في سطر.';
$string['error:passmark'] = 'أدخل رقمًا من 0 إلى 100.';
$string['error:targetwpm'] = 'أدخل رقمًا أكبر من 0.';
$string['error:wordcount'] = 'أدخل 0 أو أكثر. اتركه فارغًا ليُحسب تلقائيًا.';
$string['finishedreading'] = 'انتهيت من القراءة';
$string['formexplainer'] = 'يرى الطلاب أربع شاشات. يبدأ المؤقت عندما ينقرون على «ابدأ القراءة» ويتوقف عندما ينقرون على «انتهيت من القراءة». لا يمكنهم العودة إلى النص. يُعِدّ كل قسم أدناه شاشة واحدة.';
$string['formexplainer_heading'] = 'كيف يعمل هذا العنصر';
$string['formexplainer_step1'] = 'العنوان والتعليمات والنصائح وصورة اختيارية. ينقر الطلاب على «ابدأ القراءة».';
$string['formexplainer_step2'] = 'النص والمؤقت. زر الإنهاء في نهاية النص.';
$string['formexplainer_step3'] = 'حتى 5 أسئلة اختيار من متعدد. لا يعرف الطلاب إن كانت إجاباتهم صحيحة حتى تظهر النتائج.';
$string['formexplainer_step4'] = 'سرعة القراءة ونقاط السرعة ونتيجة الاختبار والإجابات مع الملاحظات والنص.';
$string['gradeitem'] = 'منح درجة لهذا العنصر';
$string['gradeitem_details'] = 'الدرجة هي نتيجة الاختبار. تظهر سرعة القراءة للطالب، لكنها لا تدخل في الدرجة.';
$string['instructions'] = 'التعليمات';
$string['intronote'] = 'يبدأ المؤقت عندما تنقر على «ابدأ القراءة». لا يمكنك العودة إلى النص بعد أن تنتهي.';
$string['introtext'] = 'نص المقدمة';
$string['introtext_details'] = 'التعليمات سطر قصير تحت العنوان. يمكن أن يتضمن نص المقدمة نصائح اختيارية، والصورة اختيارية. تُضاف تلقائيًا ملاحظة بأن المؤقت يبدأ عندما ينقر الطالب على «ابدأ القراءة» وأنه لا يمكنه العودة إلى النص.';
$string['item_desc'] = 'تدريب على القراءة السريعة. يقرأ الطالب نصًا بينما يعمل المؤقت، ثم يجيب عن أسئلة حوله. ويرى سرعة قراءته بعدد الكلمات في الدقيقة.';
$string['minsec'] = '{$a->minutes} د {$a->seconds} ث';
$string['noanswer'] = 'لا توجد إجابة';
$string['notread'] = 'لم تُنهِ القراءة، لذلك لا توجد سرعة قراءة.';
$string['passage'] = 'النص';
$string['passage_details'] = 'اختر نصًا سهلًا: يجب أن يعرف الطلاب جميع الكلمات تقريبًا. القراءة السريعة تدريب على القراءة بسرعة، لا على تعلّم كلمات جديدة.';
$string['passagestatus'] = 'عدد الكلمات: {$a->count}. بالسرعة المستهدفة ({$a->target} كلمة في الدقيقة) يستغرق ذلك نحو {$a->time}.';
$string['passagestatus_cpm'] = 'عدد الأحرف: {$a->count}. بالسرعة المستهدفة ({$a->target} حرف في الدقيقة) يستغرق ذلك نحو {$a->time}.';
$string['passmark'] = 'حدّ احتساب السرعة';
$string['passmark_details'] = 'تُحتسب سرعة القراءة فقط عندما تكون هذه النسبة المئوية على الأقل من الأسئلة صحيحة.';
$string['passmarkstatus'] = 'مع {$a->questions} من الأسئلة، يحتاج الطلاب إلى {$a->needed} إجابات صحيحة.';
$string['picture'] = 'الصورة';
$string['pluginname'] = 'القراءة السريعة';
$string['questionanswers'] = 'الإجابات (واحدة في كل سطر)';
$string['questioncount'] = 'عدد الأسئلة: {$a}';
$string['questionfeedback'] = 'الملاحظات (اختياري)';
$string['questionno'] = 'السؤال {$a}';
$string['questionofn'] = 'السؤال {$a->number} من {$a->total}';
$string['questions'] = 'الأسئلة';
$string['questions_details'] = 'املأ من سؤال واحد إلى 5 أسئلة. لا يعرف الطلاب إن كانت إجاباتهم صحيحة حتى شاشة النتائج. تظهر الملاحظات في شاشة النتائج فقط.';
$string['questiontext'] = 'نص السؤال';
$string['readingspeed'] = 'سرعة قراءتك';
$string['readtime'] = '{$a->count} كلمة في {$a->minutes} د {$a->seconds} ث';
$string['readtime_cpm'] = '{$a->count} حرف في {$a->minutes} د {$a->seconds} ث';
$string['screen1'] = 'الشاشة 1: المقدمة';
$string['screen2'] = 'الشاشة 2: نص القراءة';
$string['screen3'] = 'الشاشة 3: الاختبار';
$string['screen4'] = 'الشاشة 4: النتائج والدرجات';
$string['showallquestions'] = 'عرض جميع الأسئلة دفعة واحدة';
$string['showallquestions_details'] = 'إيقاف: سؤال واحد في كل مرة، والنقر ينتقل إلى التالي. تشغيل: كل الأسئلة في صفحة واحدة مع زر للإرسال.';
$string['showtimer'] = 'إظهار المؤقت أثناء القراءة';
$string['showtimer_details'] = 'يُسجَّل الوقت في الحالتين. أخفِ المؤقت إذا كان يسبب القلق للطلاب.';
$string['speedcounts'] = 'سرعتك محتسبة. كنت تحتاج إلى {$a->needed} من {$a->total} إجابات صحيحة وحصلت على {$a->correct}.';
$string['speeddoesnotcount'] = 'لن تُحتسب سرعتك هذه المرة. كنت تحتاج إلى {$a->needed} من {$a->total} إجابات صحيحة وحصلت على {$a->correct}. اقرأ ببطء أكثر قليلًا في المرة القادمة لتفهم أكثر.';
$string['speedpoints'] = 'نقاط السرعة';
$string['speedreading'] = 'القراءة السريعة';
$string['startreading'] = 'ابدأ القراءة';
$string['submitanswers'] = 'إرسال الإجابات';
$string['tapnext'] = 'انقر على إجابة للانتقال إلى السؤال التالي. سترى نتائجك في النهاية.';
$string['targetis'] = 'الهدف: {$a}';
$string['targetwpm'] = 'السرعة المستهدفة';
$string['targetwpm_details'] = 'هذه السرعة أو أسرع تمنح 10 نقاط سرعة. نصف هذه السرعة أو أبطأ يمنح 0.';
$string['thepassage'] = 'النص';
$string['timerrunning'] = 'المؤقت يعمل';
$string['toofast'] = 'كان ذلك أسرع من أن يكون قراءة حقيقية، لذلك لن تُحتسب سرعتك. اقرأ النص كاملًا في المرة القادمة.';
$string['wordcount'] = 'عدد الكلمات';
$string['wordcount_details'] = 'اتركه فارغًا لعدّ الكلمات تلقائيًا. املأه لفرض عدد محدد من الكلمات.<br>ملاحظة: في اليابانية والصينية والكورية والتايلاندية نعدّ الأحرف (لا الكلمات).';
$string['wordcountshort'] = 'عدد الكلمات: {$a}';
$string['wordsperminute'] = 'كلمة في الدقيقة';
$string['youranswer'] = 'إجابتك:';
$string['youranswers'] = 'إجاباتك';
