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
 * Simplified Chinese strings for minilessonitem_shadow
 *
 * @package    minilessonitem_shadow
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = '视频跟读';
$string['aihelper_placeholder_shadow'] = '例如：添加标点，并修正拼写和大小写错误';
$string['enablesubtitlefetch'] = '启用字幕获取按钮';
$string['enablesubtitlefetch_details'] = '在项目表单上显示一个“获取字幕”按钮，可将 YouTube 视频的字幕下载到字幕编辑器中。注意：字幕获取并非总能成功，并可能随时失效。这是一个 Poodll 不保证始终可用的实用工具。';
$string['error:badshadowlines'] = '要跟读的行必须为 *（所有行）或以逗号分隔的行号列表，例如 1,4,5,6。';
$string['error:badtimestamp'] = '片段的开始和结束时间必须为 hh:mm:ss 格式，例如 00:01:30。';
$string['error:badvtt'] = '无法解析字幕。请输入有效的 WebVTT，且至少包含一条带时间的字幕。';
$string['error:nocuesinclip'] = '没有字幕行完全落在片段的开始和结束时间之内。请调整时间或字幕。';
$string['error:noshadowlines'] = '所选行号均与片段开始和结束时间之内的字幕行不匹配。';
$string['error:novideoid'] = '需要提供 YouTube 视频 ID 或网址。';
$string['error:startafterend'] = '片段的结束时间必须晚于开始时间。';
$string['error:subtitlefetchdisabled'] = '本站点已停用字幕获取。';
$string['fetchvtt'] = '获取字幕';
$string['fetchvtt_disabled'] = '字幕自动获取当前已停用';
$string['fetchvtt_failed'] = '无法从 YouTube 获取字幕。';
$string['fetchvtt_fetching'] = '正在获取…';
$string['fetchvtt_invalidurl'] = '请先输入有效的 YouTube 网址或 11 位视频 ID。';
$string['fetchvtt_overwrite'] = '这将替换编辑器中当前的字幕。是否继续？';
$string['fetchvtt_overwrite_title'] = '替换字幕？';
$string['item_desc'] = '视频跟读项目会逐行播放 YouTube 片段。学生在单词高亮时跟着视频一起说，从而“跟读”每一行字幕。';
$string['loopcount'] = '每行跟读次数';
$string['loopcount_desc'] = '每行重放多少次，供学生跟读。';
$string['loopindicator'] = '跟读：{$a->current} / {$a->total}';
$string['oknext'] = '确定 / 下一个';
$string['paste_empty'] = '请先粘贴你从 YouTube 复制的字幕文本。';
$string['paste_failed'] = '无法转换该字幕文本。';
$string['paste_label'] = '从 YouTube 复制的字幕文本';
$string['paste_placeholder'] = '0:00
字幕文本的第一行
0:03
字幕文本的第二行';
$string['paste_step_copy'] = '选中整段字幕文本，复制后粘贴到下面的框中';
$string['paste_step_open_novideo'] = '在 YouTube 上打开视频（先在表单中输入视频 ID，即可在此处获得链接）';
$string['paste_step_showtranscript'] = '在视频下方展开简介，点击“显示文字记录”';
$string['paste_step_timestamps'] = '在文字记录面板的 ⋮ 菜单中，确保已开启时间戳';
$string['paste_success'] = '已添加 {$a} 行字幕。';
$string['paste_wordhighlightoff'] = '逐词高亮已关闭，因为粘贴的字幕文本只包含整行的时间信息。片段将逐行高亮。';
$string['pastevtt'] = '粘贴字幕文本';
$string['pastevtt_convert'] = '转换为字幕';
$string['pastevtt_title'] = '粘贴 YouTube 字幕文本';
$string['pluginname'] = '视频跟读';
$string['retry'] = '重试';
$string['rotatedevice'] = '请将设备旋转为竖屏以继续。';
$string['shadow_instructions1'] = '观看视频。然后逐行跟读：一边听，一边在视频重播时跟着说。';
$string['shadowlines'] = '要跟读的行';
$string['shadowlines_desc'] = '要跟读的字幕行号，从下面字幕的第 1 行开始计数，例如 1,4,5,6。使用 * 跟读所有行。观看时其他行仍会显示。';
$string['shadowpause'] = '每次跟读之间的暂停（秒）';
$string['shadowvtt'] = '字幕 (WebVTT)';
$string['shadowvtt_desc'] = '在下方区域粘贴或编辑该片段的 WebVTT 字幕。';
$string['startshadowing'] = '开始跟读';
$string['watchhint'] = '点击播放并观看片段。结束后，点击“开始跟读”。';
$string['wordhighlight'] = '启用逐词高亮';
$string['wordhighlight_details'] = '利用字幕中的逐词时间戳，在每个词被说出时高亮它。YouTube 的逐词计时在部分视频中不可靠——关闭此项可改为高亮整行。关闭时，获取字幕也会跳过获取逐词时间戳。';
$string['ytclipdetails'] = 'YouTube 片段（ID/网址、开始和结束秒数）';
