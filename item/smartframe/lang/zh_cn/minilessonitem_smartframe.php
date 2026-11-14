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
 * Simplified Chinese strings for minilessonitem_smartframe
 *
 * @package    minilessonitem_smartframe
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Smart Frame';
$string['aiinstructions'] = '用 AI 制作小组件';
$string['aiinstructions_copied'] = '已复制 AI 指令';
$string['aiinstructions_copy'] = '复制 AI 指令';
$string['aiinstructions_download'] = '下载';
$string['aiinstructions_edit'] = '以后要修改小组件，请从下方的文件框下载它，连同这些指令一起附加到新的聊天中，并说明要改什么。';
$string['aiinstructions_intro'] = '在 ChatGPT、Claude 或 Gemini 中制作小组件，你可以在那里试用并要求修改：';
$string['aiinstructions_show'] = '显示指令';
$string['aiinstructions_step1'] = '把这些指令粘贴到新的聊天中，或下载后附加该文件。';
$string['aiinstructions_step2'] = '描述这个活动。例如：“一个关于 10 个常用动词过去式的测验，用英语和日语给出说明，并报告分数。”';
$string['aiinstructions_step3'] = '在聊天中试用，并不断要求修改，直到你满意为止。';
$string['aiinstructions_step4'] = '输入 “Export it for Poodll”，下载它给你的文件，然后在下方上传。';
$string['error:htmlencoding'] = 'HTML 文件必须保存为 UTF-8 文本。';
$string['error:importbase64'] = '小组件文件 {$a} 不是有效的 base64。请将文件字节编码为纯 base64，不要带“data:”前缀。';
$string['error:importboth'] = '请以 widgethtml 或 customfile1 中的文件之一提供小组件，不能同时提供。';
$string['error:importgradepolicy'] = 'gradepolicy 必须是 0（忽略小组件的分数）、1（记录分数）或 2（计入课程成绩）。';
$string['error:importnofile'] = '该项目没有小组件。请将小组件的 HTML 放入其 widgethtml 字段。';
$string['error:importonefile'] = '该项目的 customfile1 文件区中有多个文件。请只添加一个小组件文件（.html 或 .zip）。';
$string['error:manifestentry'] = '清单的“entry”（{$a}）必须指向 ZIP 中的一个 HTML 文件。';
$string['error:manifestfield'] = '清单中的“{$a}”缺失或无效。';
$string['error:manifestjson'] = '小组件的清单不是有效的 JSON。';
$string['error:manifestprotocol'] = '此小组件需要更新的课程宿主。此宿主使用的协议版本为 {$a}。';
$string['error:manifestschema'] = '清单的“schemaVersion”必须为 {$a}。';
$string['error:nomanifest'] = 'ZIP 的顶层没有 manifest.json。';
$string['error:nomanifesthtml'] = 'HTML 文件没有小组件清单。它需要一个 &lt;script type="application/json" id="poodll-widget-manifest"&gt; 块。请让 AI 工具执行 “export it for Poodll”。';
$string['error:notazip'] = '该文件不是 ZIP 文件，或已损坏。';
$string['error:notpublished'] = '此小组件无法发布，因此学习者看不到它。请再次保存该项目以重试。';
$string['error:onezip'] = '只上传一个小组件文件，删除其他文件。';
$string['error:publishfailed'] = '无法发布小组件。';
$string['error:zipbadpath'] = 'ZIP 中包含不允许的文件名：{$a}';
$string['error:zipduplicate'] = 'ZIP 中有两个同名文件：{$a}';
$string['error:zipencrypted'] = 'ZIP 受密码保护。请上传没有密码的 ZIP。';
$string['error:zipfiletoobig'] = '文件 {$a->file} 太大。允许的最大文件为 {$a->size}。';
$string['error:zipfiletype'] = '小组件中不允许此类文件：{$a}';
$string['error:zipread'] = '无法从 ZIP 中读取文件 {$a}。';
$string['error:zipsymlink'] = 'ZIP 中包含链接，这是不允许的：{$a}';
$string['error:ziptoobig'] = 'ZIP 太大。允许的最大 ZIP 为 {$a}。';
$string['error:ziptoomanyfiles'] = 'ZIP 中的文件太多。最多允许 {$a} 个。';
$string['error:ziptotaltoobig'] = 'ZIP 中文件的总大小太大。最多允许 {$a}。';
$string['error:zipunreadable'] = '无法读取 ZIP。';
$string['gradepolicy'] = '小组件分数';
$string['gradepolicy_help'] = '有些小组件会报告分数，例如 4 题中答对 3 题。选择课程如何处理这个分数。

* 忽略：不使用该分数。
* 保存但不评分：分数随本次尝试保存，但不计入课程成绩。
* 用作项目成绩：分数按总分折算后计入课程成绩。学习者在完成前就继续前进，得 0 分。

小组件发送的分数是在学习者的浏览器中计算的，因此学习者可能会修改它。请用于练习，不要用于重要的测试。';
$string['gradepolicy_ignore'] = '忽略';
$string['gradepolicy_record'] = '保存但不评分';
$string['gradepolicy_use'] = '用作项目成绩';
$string['item_desc'] = 'Smart Frame 将上传的 HTML 页面加载到 Minilesson 内的框架中。它支持响应式布局，安全可靠，并能生成成绩。可用于讲解小组件、自定义互动活动，甚至游戏。';
$string['pluginname'] = 'Smart Frame';
$string['uilanguage'] = '小组件语言';
$string['uilanguage_auto'] = '自动';
$string['uilanguage_help'] = '小组件的说明、按钮和反馈所用的语言。这不会改变所学的语言。

“自动”会优先使用学习者自己的语言（如果小组件提供），其次是课程的母语，然后是学习者的 Moodle 语言，最后是小组件的默认语言。保存一次该项目后，即可从小组件的语言中选择。';
$string['warning:importuilanguage'] = 'uilanguage“{$a->lang}”不是小组件的界面语言之一（{$a->languages}），因此学习者将看到其默认语言。';
$string['widget_instructions1'] = '完成下面的活动。';
$string['widgetnotavailable'] = '此活动目前不可用。';
$string['widgetpackage'] = '小组件文件';
$string['widgetpackage_help'] = '小组件本身：可以是一个 HTML 文件（AI 工具最容易生成的形式），也可以是 ZIP（用于包含音频、视频或大量图片的小组件）。

HTML 文件必须在 &lt;script type="application/json" id="poodll-widget-manifest"&gt; 块中包含小组件的清单。ZIP 的顶层必须有 manifest.json 和一个 HTML 页面。小组件用到的一切都必须在该文件或 ZIP 中：小组件不能从其他网站加载文件，也不能使用浏览器存储（如 localStorage）或 Cookie。';
$string['widgetsummary'] = '当前小组件';
$string['widgetsummary_text'] = '<strong>{$a->title}</strong><br>语言：{$a->languages}<br>报告分数：{$a->grade}';
