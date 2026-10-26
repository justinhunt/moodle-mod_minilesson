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
 * Arabic strings for minilessonitem_shadow
 *
 * @package    minilessonitem_shadow
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'محاكاة الفيديو';
$string['aihelper_placeholder_shadow'] = 'مثال: أضف علامات الترقيم وصحّح أي أخطاء إملائية وأخطاء في حالة الأحرف';
$string['autoforward'] = 'الانتقال التلقائي';
$string['autoforward_details'] = 'بعد آخر محاكاة للسطر، يُنتقل إلى السطر التالي تلقائيًا بدلًا من انتظار «موافق / التالي». لا يوجد زر «إعادة المحاولة» في هذا الوضع.';
$string['enablesubtitlefetch'] = 'تفعيل زر جلب الترجمات';
$string['enablesubtitlefetch_details'] = 'يعرض زر «جلب الترجمات» في نموذج العنصر الذي ينزّل ترجمات فيديو YouTube إلى محرر الترجمات. ملاحظة: جلب الترجمات لا يعمل دائمًا وقد يتوقف في أي وقت. إنها أداة مساعدة لا تضمن Poodll توفرها دائمًا.';
$string['error:badshadowlines'] = 'يجب أن تكون الأسطر المراد محاكاتها * (جميع الأسطر) أو قائمة أرقام أسطر مفصولة بفواصل، مثل 1,4,5,6.';
$string['error:badtimestamp'] = 'يجب أن تكون أوقات بداية المقطع ونهايته بتنسيق hh:mm:ss، مثل 00:01:30.';
$string['error:badvtt'] = 'تعذّر تحليل الترجمات. أدخل WebVTT صالحًا يحتوي على إشارة توقيت واحدة على الأقل.';
$string['error:nocuesinclip'] = 'لا يقع أي سطر ترجمة بالكامل ضمن وقتي بداية المقطع ونهايته. اضبط الأوقات أو الترجمات.';
$string['error:noshadowlines'] = 'لا يطابق أي من أرقام الأسطر المحددة سطر ترجمة ضمن وقتي بداية المقطع ونهايته.';
$string['error:novideoid'] = 'مطلوب معرّف فيديو YouTube أو عنوان URL.';
$string['error:startafterend'] = 'يجب أن يكون وقت نهاية المقطع بعد وقت البداية.';
$string['error:subtitlefetchdisabled'] = 'جلب الترجمات معطّل على هذا الموقع.';
$string['fetchvtt'] = 'جلب الترجمات';
$string['fetchvtt_disabled'] = 'الجلب التلقائي للترجمات معطّل حاليًا';
$string['fetchvtt_failed'] = 'تعذّر جلب الترجمات من YouTube.';
$string['fetchvtt_fetching'] = 'جارٍ الجلب...';
$string['fetchvtt_invalidurl'] = 'أدخل أولاً عنوان URL صالحًا لـ YouTube أو معرّف فيديو من 11 حرفًا.';
$string['fetchvtt_overwrite'] = 'سيؤدي هذا إلى استبدال الترجمات الموجودة حاليًا في المحرر. هل تريد المتابعة؟';
$string['fetchvtt_overwrite_title'] = 'استبدال الترجمات؟';
$string['item_desc'] = 'يشغّل عنصر محاكاة الفيديو مقطعًا من YouTube سطرًا بسطر. يقوم الطلاب بـ«محاكاة» كل سطر من الترجمة، متحدثين مع الفيديو بينما تُميَّز الكلمات.';
$string['loopcount'] = 'عدد مرات المحاكاة لكل سطر';
$string['loopcount_desc'] = 'عدد مرات إعادة تشغيل كل سطر ليحاكيه الطالب.';
$string['loopindicator'] = 'المحاكاة: {$a->current} / {$a->total}';
$string['oknext'] = 'موافق / التالي';
$string['paste_empty'] = 'الصق أولاً النص المفرّغ الذي نسخته من YouTube.';
$string['paste_failed'] = 'تعذّر تحويل النص المفرّغ.';
$string['paste_label'] = 'النص المفرّغ المنسوخ من YouTube';
$string['paste_placeholder'] = '0:00
السطر الأول من النص المفرّغ
0:03
السطر الثاني من النص المفرّغ';
$string['paste_step_copy'] = 'حدّد النص المفرّغ كاملاً، وانسخه، ثم الصقه في المربع أدناه';
$string['paste_step_open_novideo'] = 'افتح الفيديو على YouTube (أدخل معرّف الفيديو في النموذج أولاً لتحصل على رابط هنا)';
$string['paste_step_showtranscript'] = 'أسفل الفيديو، وسّع الوصف وانقر على «إظهار النص المفرّغ»';
$string['paste_step_timestamps'] = 'في قائمة ⋮ بلوحة النص المفرّغ، تأكد من تفعيل الطوابع الزمنية';
$string['paste_success'] = 'عدد أسطر الترجمة المضافة: {$a}';
$string['paste_wordhighlightoff'] = 'تم إيقاف تمييز الكلمات واحدة تلو الأخرى، لأن النص المفرّغ الملصوق يحتوي على توقيت للأسطر الكاملة فقط. سيقوم المقطع بتمييز سطر واحد في كل مرة.';
$string['pastevtt'] = 'لصق النص المفرّغ';
$string['pastevtt_convert'] = 'تحويل إلى ترجمات';
$string['pastevtt_title'] = 'لصق النص المفرّغ من YouTube';
$string['pluginname'] = 'محاكاة الفيديو';
$string['retry'] = 'إعادة المحاولة';
$string['rotatedevice'] = 'يُرجى تدوير جهازك إلى الوضع الرأسي للمتابعة.';
$string['shadow_instructions1'] = 'شاهد الفيديو. ثم حاكِ كل سطر: استمع، وتحدّث مع الفيديو أثناء إعادة تشغيله.';
$string['shadowlines'] = 'الأسطر المراد محاكاتها';
$string['shadowlines_desc'] = 'أرقام أسطر الترجمة المراد محاكاتها، تُحسب من 1 في الترجمات أدناه، مثل 1,4,5,6. استخدم * لمحاكاة جميع الأسطر. تظل الأسطر الأخرى معروضة أثناء المشاهدة.';
$string['shadowpause'] = 'الإيقاف بين المحاكاة (بالثواني)';
$string['shadowvtt'] = 'الترجمات (WebVTT)';
$string['shadowvtt_desc'] = 'الصق ترجمات WebVTT الخاصة بالمقطع أو عدّلها في المنطقة أدناه.';
$string['startshadowing'] = 'ابدأ المحاكاة';
$string['watchhint'] = 'اضغط على تشغيل وشاهد المقطع. عند انتهائه، اضغط على «ابدأ المحاكاة».';
$string['wordhighlight'] = 'تفعيل تمييز كل كلمة';
$string['wordhighlight_details'] = 'تمييز كل كلمة أثناء نطقها، باستخدام الطوابع الزمنية للكلمات في الترجمات. توقيتات الكلمات في YouTube غير موثوقة في بعض مقاطع الفيديو - أوقف هذا الخيار لتمييز الأسطر الكاملة بدلاً من ذلك. عند إيقافه، يتخطى جلب الترجمات أيضًا جلب الطوابع الزمنية للكلمات.';
$string['ytclipdetails'] = 'مقطع YouTube (المعرّف/الرابط، وثواني البداية والنهاية)';
