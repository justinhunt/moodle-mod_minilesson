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
 * Arabic strings for minilessonitem_audiochat
 *
 * @package    minilessonitem_audiochat
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'محادثة صوتية';
$string['audiochat_silenceduration'] = 'المهلة قبل رد الذكاء الاصطناعي';
$string['audiochat_silenceduration_details'] = 'المدة التي يجب أن يتوقف فيها الطالب عن الكلام، بالمللي ثانية، قبل أن يعتبر الذكاء الاصطناعي دوره منتهيًا ويرد. ارفعها لمنح المتعلمين وقتًا أطول للتوقف والتفكير في منتصف الجملة. اخفضها للحصول على حوار أسرع وأكثر محادثة. القيمة الافتراضية 3500 (3.5 ثانية). القيم خارج النطاق من 500 إلى 30000 تعود إلى القيمة الافتراضية. ينطبق هذا فقط بعد أن يتحدث الطالب، وفقط عندما يكون «إرسال تلقائي» مفعّلًا للعنصر.';
$string['cantchat_ningxia'] = 'الدردشة غير متاحة. لاستخدام هذا النوع من العناصر يجب تعيين مفتاح {$a} صالح في إعدادات المسؤول لنشاط MiniLesson. الدردشة غير متاحة أيضًا في الصين (منطقة نينغشيا)';
$string['cloudpoodll'] = 'Cloud Poodll';
$string['gemini'] = 'Gemini';
$string['geminilivemodel'] = 'نموذج Gemini Live';
$string['geminilivemodel_details'] = 'نموذج Gemini Live المستخدم عندما يكون المزوّد Gemini. Gemini 3.8 Live هو الإعداد الافتراضي الحالي من Google للصوت منخفض زمن الاستجابة. النسخة التجريبية 3.1 هي النموذج الأقدم وتوصي Google بالانتقال منها. ينطبق هذا على مزوّدَي Gemini وCloud Poodll وليس على OpenAI.';
$string['gradingfailed'] = 'تعذّر على الذكاء الاصطناعي تقييم هذه المحادثة، لذلك حصلت على 0. تم حفظ النص المفرّغ.';
$string['item_desc'] = 'يُنشئ عنصر المحادثة الصوتية محادثة صوتية مع شريك من الذكاء الاصطناعي. يمكن استخدامه لمناقشة موضوع أو طرح أسئلة حول صورة. التعليمات الموجّهة إلى روبوت الذكاء الاصطناعي نصوص حرة وقابلة للتعديل.';
$string['openai'] = 'OpenAI';
$string['pluginname'] = 'محادثة صوتية';
$string['provider'] = 'المزوّد';
$string['provider_details'] = 'اختر مزوّد ذكاء اصطناعي لعنصر المحادثة الصوتية. يعمل Cloud Poodll مباشرة، لكن ليس في منطقة نينغشيا. بالنسبة إلى المزوّدين الآخرين، تحتاج إلى إدخال مفتاح API في إعدادات المسؤول الخاصة بـ BYOK في minilesson.';
$string['unknown'] = 'غير معروف';
