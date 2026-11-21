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
 * Simplified Chinese strings for minilessonitem_conversation
 *
 * @package    minilessonitem_conversation
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = '对话';
$string['check_auto'] = '自动检查（语音识别）';
$string['check_self'] = '自我检查（仅录音）';
$string['checking'] = '正在检查…';
$string['checkmode'] = '检查方式';
$string['checkmode_help'] = '自动检查：每句台词都必须通过语音识别，对话才会继续。自我检查：每句台词只录音，并且总会被接受。学生可以回放并重录。两种方式的评分相同：按学生完成了多少句自己的台词评分，而不是按语音识别的结果。';
$string['choosepart'] = '你想扮演哪个角色？';
$string['complete'] = '对话完成';
$string['conversation'] = '对话';
$string['defaultinstructions'] = '你的台词出现时，把它说出来。注意听对方说话。';
$string['dialog'] = '对话';
$string['dialog_help'] = '每行写一句台词。在每行开头写 A) 或 B)，表示是谁说的，例如：

A) 你好！今天想喝点什么？
B) 请给我一杯大杯拿铁。';
$string['error:dialogspeakers'] = '对话必须同时包含 A) 和 B) 的台词。';
$string['great'] = '做得好！';
$string['hearit'] = '听一听';
$string['item_desc'] = '学生说一段固定对话中的一个角色，另一个角色由电脑语音来说。';
$string['labela'] = 'A 的名称';
$string['labela_help'] = '两个角色的名称（可选），例如“服务员”和“顾客”。名称会显示在聊天和开始界面中。如果留空，则使用 A 和 B。';
$string['labelb'] = 'B 的名称';
$string['listen'] = '听对方说…';
$string['look_dotted'] = '虚线框中的灰色文字';
$string['look_grey'] = '仅灰色文字';
$string['movedon'] = '已跳过';
$string['moveon'] = '跳过';
$string['myrecording'] = '我的录音';
$string['playall'] = '播放整段对话';
$string['playingback'] = '正在播放 {$a->num}/{$a->total}';
$string['pluginname'] = '对话';
$string['prompt_full'] = '整句台词';
$string['prompt_hidden'] = '隐藏';
$string['prompt_initials'] = '仅首字母';
$string['prompthelp'] = '提示';
$string['prompthelp_help'] = '显示学生下一句台词的多少。选择“仅首字母”或“隐藏”时，学生必须记住台词。说出台词后会显示完整的单词。';
$string['promptlook'] = '提示样式';
$string['recorded'] = '已录音';
$string['recording'] = '正在录音…按麦克风停止。';
$string['recordingreplaced'] = '录音已替换。';
$string['redo'] = '重录';
$string['redohere'] = '按麦克风重录这句台词。';
$string['redowait'] = '等对方说完，再按“重录”。';
$string['replay'] = '重播';
$string['role_a'] = 'A';
$string['role_b'] = 'B';
$string['role_choose'] = '由学生选择';
$string['saidfirsttry'] = '第一次就说对了';
$string['saidintries'] = '尝试 {$a} 次后说对了';
$string['scene'] = '场景图片';
$string['scene_help'] = '显示在聊天背后的图片（可选），用于设定场景：咖啡馆、教室、办公室。只会使用一张图片。如果有两张，则使用最先添加的那张。';
$string['startagain'] = '重新开始';
$string['startconversation'] = '开始';
$string['stopplayback'] = '停止';
$string['studentrole'] = '学生的角色';
$string['studentrole_help'] = '学生要说的角色。另一个角色由电脑语音来说。';
$string['summaryauto'] = '{$a->total} 句台词中说对了 {$a->correct} 句';
$string['summaryself'] = '已录音台词：{$a} 句。你可以重录任何一句。';
$string['tryagain'] = '差一点。再说一遍。';
$string['voicea'] = 'A 的语音';
$string['voiceb'] = 'B 的语音';
$string['youare'] = '你的角色：{$a}';
$string['yourline'] = '你的台词';
$string['yourturn'] = '轮到你了。按麦克风，说出你的台词。';
