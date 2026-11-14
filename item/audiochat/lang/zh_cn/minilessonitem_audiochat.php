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
 * Simplified Chinese strings for minilessonitem_audiochat
 *
 * @package    minilessonitem_audiochat
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = '语音聊天';
$string['aiactions_instructions'] = '当你的 AI 指令要求时，AI 可以结束聊天并显示图片。例如：“当学生付完苹果的钱后，显示 apple.png，说再见并结束聊天。”';
$string['aiactions_unsupported'] = '管理设置中选择的 Gemini Live 模型（Extended Thinking）尚不支持这些操作，因此 AI 不会结束聊天或显示图片。';
$string['aiactionsheading'] = 'AI 操作';
$string['aicanend'] = 'AI 可以结束聊天';
$string['aicanend_desc'] = '聊天结束时让 AI 结束聊天';
$string['aicanend_help'] = '勾选后，AI 可以自行结束聊天，就像学生按下了结束按钮一样。请在 AI 指令中说明何时结束聊天。AI 会先说再见。学生仍可随时用结束按钮结束聊天。';
$string['aichat_endedbyai'] = 'AI 已结束聊天。';
$string['aipictures'] = 'AI 可以显示的图片';
$string['aipictures_help'] = '聊天过程中 AI 可以向学生显示的图片。AI 看不到图片，只能通过文件名识别它们，所以请给每张图片起一个能说明内容的名字，例如 redapple.png 或 blackkitten.jpg。请在 AI 指令中说明何时显示每张图片。';
$string['aipictureshown'] = '图片：{$a}';
$string['audiochat_silenceduration'] = 'AI 回复前的停顿';
$string['audiochat_silenceduration_details'] = 'AI 判定学生本轮发言结束并开始回复之前，学生需要停止说话的时长（毫秒）。调大可让学习者在句子中间有更多停顿思考的空间；调小则对话来回更快、更像聊天。默认值为 3500（3.5 秒）。超出 500 到 30000 范围的值会回到默认值。仅在学生说话之后生效，且仅当该项目开启“自动发送”时适用。';
$string['cantchat_ningxia'] = '聊天不可用。要使用此项目类型，必须在 MiniLesson 活动的管理设置中设置有效的 {$a} 密钥。中国（宁夏区域）也无法使用聊天。';
$string['cloudpoodll'] = 'Cloud Poodll';
$string['gemini'] = 'Gemini';
$string['geminilivemodel'] = 'Gemini Live 模型';
$string['geminilivemodel_details'] = '提供方为 Gemini 时使用的 Gemini Live 模型。Gemini 3.8 Live 是 Google 当前面向低延迟语音的默认模型。3.1 预览版是旧模型，Google 建议不再使用。此设置适用于 Gemini 和 Cloud Poodll 提供方，不适用于 OpenAI。';
$string['gradingfailed'] = 'AI 无法为这次聊天评分，因此得分为 0。转写文本已保存。';
$string['item_desc'] = '语音聊天项目会设置与 AI 伙伴的语音对话。可用于讨论某个话题，或就一张图片提问。给 AI 机器人的提示为自由文本，可编辑。';
$string['openai'] = 'OpenAI';
$string['pluginname'] = '语音聊天';
$string['provider'] = '提供方';
$string['provider_details'] = '为语音聊天项目选择一个 AI 提供方。Cloud Poodll 通常开箱即用，但在宁夏区域不可用。使用其他提供方时，需在 MiniLesson 管理端的 BYOK 设置中提供 API 密钥。';
$string['unknown'] = '未知';
