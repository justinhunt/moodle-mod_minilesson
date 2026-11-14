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
 * Arabic strings for minilessonitem_smartframe
 *
 * @package    minilessonitem_smartframe
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Smart Frame';
$string['aiinstructions'] = 'أنشئ ودجت بالذكاء الاصطناعي';
$string['aiinstructions_copied'] = 'تم نسخ تعليمات الذكاء الاصطناعي';
$string['aiinstructions_copy'] = 'نسخ تعليمات الذكاء الاصطناعي';
$string['aiinstructions_download'] = 'تنزيل';
$string['aiinstructions_edit'] = 'لتغيير الودجت لاحقًا، نزّله من مربع الملفات أدناه، وأرفقه في محادثة جديدة مع هذه التعليمات، واذكر ما تريد تغييره.';
$string['aiinstructions_intro'] = 'أنشئ الودجت في ChatGPT أو Claude أو Gemini، حيث يمكنك تجربته وطلب التعديلات:';
$string['aiinstructions_show'] = 'عرض التعليمات';
$string['aiinstructions_step1'] = 'الصق هذه التعليمات في محادثة جديدة، أو نزّلها وأرفق الملف.';
$string['aiinstructions_step2'] = 'صِف النشاط. مثلًا: «اختبار عن الماضي لعشرة أفعال شائعة، بتعليمات بالإنجليزية واليابانية، يُبلغ عن الدرجة».';
$string['aiinstructions_step3'] = 'جرّبه في المحادثة، واطلب التعديلات حتى ترضى عنه.';
$string['aiinstructions_step4'] = 'اكتب «Export it for Poodll»، ونزّل الملف الذي يعطيك إياه، ثم ارفعه أدناه.';
$string['error:htmlencoding'] = 'يجب حفظ ملف HTML كنص UTF-8.';
$string['error:importbase64'] = 'ملف الودجت {$a} ليس base64 صالحًا. رمّز بايتات الملف كـ base64 عادي، بدون البادئة «data:».';
$string['error:importboth'] = 'قدّم الودجت إما في widgethtml أو كملف في customfile1، وليس كليهما.';
$string['error:importgradepolicy'] = 'يجب أن تكون قيمة gradepolicy إما 0 (تجاهل درجة الودجت) أو 1 (تسجيلها) أو 2 (احتسابها في درجة الدرس).';
$string['error:importnofile'] = 'لا يحتوي العنصر على ودجت. ضع HTML الخاص بالودجت في الحقل widgethtml.';
$string['error:importonefile'] = 'يحتوي العنصر على أكثر من ملف في منطقة الملفات customfile1. أضف ملف ودجت واحدًا فقط (‎.html أو ‎.zip).';
$string['error:manifestentry'] = 'يجب أن يشير «entry» في ملف البيان ({$a}) إلى ملف HTML داخل ZIP.';
$string['error:manifestfield'] = '«{$a}» في ملف البيان مفقود أو غير صالح.';
$string['error:manifestjson'] = 'ملف البيان الخاص بالودجت ليس JSON صالحًا.';
$string['error:manifestprotocol'] = 'يحتاج هذا الودجت إلى مضيف دروس أحدث. يستخدم هذا المضيف الإصدار {$a} من البروتوكول.';
$string['error:manifestschema'] = 'يجب أن تكون قيمة «schemaVersion» في ملف البيان {$a}.';
$string['error:nomanifest'] = 'لا يحتوي ملف ZIP على manifest.json في مستواه الأعلى.';
$string['error:nomanifesthtml'] = 'لا يحتوي ملف HTML على ملف بيان للودجت. يحتاج إلى كتلة &lt;script type="application/json" id="poodll-widget-manifest"&gt;. اطلب من أداة الذكاء الاصطناعي: «export it for Poodll».';
$string['error:notazip'] = 'الملف ليس ملف ZIP، أو أنه تالف.';
$string['error:notpublished'] = 'تعذّر نشر هذا الودجت، لذلك لا يمكن للمتعلّمين رؤيته. احفظ العنصر مرة أخرى لإعادة المحاولة.';
$string['error:onezip'] = 'ارفع ملف ودجت واحدًا فقط، واحذف الملفات الأخرى.';
$string['error:publishfailed'] = 'تعذّر نشر الودجت.';
$string['error:zipbadpath'] = 'يحتوي ملف ZIP على اسم ملف غير مسموح به: {$a}';
$string['error:zipduplicate'] = 'يحتوي ملف ZIP على ملفين بالاسم نفسه: {$a}';
$string['error:zipencrypted'] = 'ملف ZIP محمي بكلمة مرور. ارفع ملف ZIP بدون كلمة مرور.';
$string['error:zipfiletoobig'] = 'الملف {$a->file} كبير جدًا. أكبر حجم مسموح به هو {$a->size}.';
$string['error:zipfiletype'] = 'هذا النوع من الملفات غير مسموح به في الودجت: {$a}';
$string['error:zipread'] = 'تعذّرت قراءة الملف {$a} من ملف ZIP.';
$string['error:zipsymlink'] = 'يحتوي ملف ZIP على رابط، وهذا غير مسموح به: {$a}';
$string['error:ziptoobig'] = 'ملف ZIP كبير جدًا. أكبر حجم مسموح به هو {$a}.';
$string['error:ziptoomanyfiles'] = 'يحتوي ملف ZIP على ملفات كثيرة جدًا. الحد الأقصى المسموح به هو {$a}.';
$string['error:ziptotaltoobig'] = 'الحجم الإجمالي للملفات داخل ZIP كبير جدًا. الحد الأقصى المسموح به هو {$a}.';
$string['error:zipunreadable'] = 'تعذّرت قراءة ملف ZIP.';
$string['gradepolicy'] = 'درجة الودجت';
$string['gradepolicy_help'] = 'تُبلغ بعض الودجت عن درجة، مثل 3 من 4. اختر ما يفعله الدرس بها.

* تجاهل: لا تُستخدم الدرجة.
* حفظ دون تقييم: تُحفظ الدرجة مع المحاولة، لكنها لا تُحتسب في درجة الدرس.
* استخدامها كدرجة للعنصر: تُحتسب الدرجة في درجة الدرس بعد تحويلها إلى إجمالي الدرجات. المتعلّم الذي ينتقل قبل أن ينتهي يحصل على 0.

تُحسب الدرجة التي يرسلها الودجت في متصفح المتعلّم، لذا يمكن للمتعلّم تغييرها. استخدمها للتدريب، لا للاختبارات المهمة.';
$string['gradepolicy_ignore'] = 'تجاهل';
$string['gradepolicy_record'] = 'حفظ دون تقييم';
$string['gradepolicy_use'] = 'استخدامها كدرجة للعنصر';
$string['item_desc'] = 'يعرض Smart Frame صفحة HTML مرفوعة داخل إطار في Minilesson. وهو متجاوب وآمن، ويمكنه إنشاء درجة. ويمكن استخدامه لأدوات الشرح التفاعلية، أو للأنشطة التفاعلية المخصصة، أو حتى للألعاب.';
$string['pluginname'] = 'Smart Frame';
$string['uilanguage'] = 'لغة الودجت';
$string['uilanguage_auto'] = 'تلقائي';
$string['uilanguage_help'] = 'لغة تعليمات الودجت وأزراره وملاحظاته. لا يغيّر هذا اللغة التي يتعلمها المتعلّم.

يستخدم الخيار التلقائي لغة المتعلّم الخاصة إذا كان الودجت يوفرها، ثم اللغة الأم للدرس، ثم لغة Moodle الخاصة بالمتعلّم، ثم اللغة الافتراضية للودجت. احفظ العنصر مرة واحدة لتتمكن من الاختيار من لغات الودجت.';
$string['warning:importuilanguage'] = 'قيمة uilanguage «{$a->lang}» ليست من لغات واجهة الودجت ({$a->languages})، لذلك سيرى المتعلّمون لغته الافتراضية.';
$string['widget_instructions1'] = 'أكمل النشاط أدناه.';
$string['widgetnotavailable'] = 'هذا النشاط غير متاح الآن.';
$string['widgetpackage'] = 'ملف الودجت';
$string['widgetpackage_help'] = 'الودجت: إما ملف HTML واحد (وهو أسهل ما تصنعه أدوات الذكاء الاصطناعي)، أو ملف ZIP لودجت يحتوي على صوت أو فيديو أو صور كثيرة.

يجب أن يحتوي ملف HTML على ملف بيان الودجت في كتلة &lt;script type="application/json" id="poodll-widget-manifest"&gt;. ويجب أن يحتوي ملف ZIP على manifest.json وصفحة HTML في مستواه الأعلى. يجب أن يكون كل ما يستخدمه الودجت داخل الملف أو ملف ZIP: لا يمكن للودجت تحميل ملفات من مواقع أخرى، ولا استخدام تخزين المتصفح (مثل localStorage) أو ملفات تعريف الارتباط.';
$string['widgetsummary'] = 'الودجت الحالي';
$string['widgetsummary_text'] = '<strong>{$a->title}</strong><br>اللغات: {$a->languages}<br>يُبلغ عن درجة: {$a->grade}';
