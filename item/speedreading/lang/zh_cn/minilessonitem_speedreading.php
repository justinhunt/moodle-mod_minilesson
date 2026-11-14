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
 * Simplified Chinese strings for minilessonitem_speedreading
 *
 * @package    minilessonitem_speedreading
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = '速读';
$string['answercorrect'] = '正确';
$string['answersneeded'] = '回答所有题目后才能提交。提交前可以修改答案。';
$string['answerwrong'] = '错误';
$string['cannotgoback'] = '不能返回短文。';
$string['charactercount'] = '{$a} 个字';
$string['charactersperminute'] = '字/分钟';
$string['correctansweris'] = '正确答案：';
$string['defaultinstructions'] = '尽可能快地读完短文，然后回答问题。';
$string['error:correctanswertoohigh'] = '正确答案的编号大于答案的数量。';
$string['error:needtwoanswers'] = '请至少输入两个答案，每行一个。';
$string['error:passmark'] = '请输入 0 到 100 之间的数字。';
$string['error:targetwpm'] = '请输入大于 0 的数字。';
$string['error:wordcount'] = '请输入 0 或更大的数。留空则自动计数。';
$string['finishedreading'] = '我读完了';
$string['formexplainer'] = '学生会看到四个界面。点击“开始阅读”时开始计时，点击“我读完了”时停止计时。学生不能返回短文。下面每个部分设置一个界面。';
$string['formexplainer_heading'] = '此项目的运作方式';
$string['formexplainer_step1'] = '标题、说明、提示和可选的图片。学生点击“开始阅读”。';
$string['formexplainer_step2'] = '短文和计时器。完成按钮在短文末尾。';
$string['formexplainer_step3'] = '最多 5 道多项选择题。在看到结果之前，学生不知道自己是否答对。';
$string['formexplainer_step4'] = '阅读速度、速度分、测验得分、带反馈的答案以及短文。';
$string['gradeitem'] = '为此项目评分';
$string['gradeitem_details'] = '成绩就是测验得分。阅读速度会显示给学生，但不计入成绩。';
$string['instructions'] = '说明';
$string['intronote'] = '点击“开始阅读”后开始计时。读完后不能再返回短文。';
$string['introtext'] = '介绍文字';
$string['introtext_details'] = '说明是标题下方的一行简短文字。介绍文字可以写一些提示（可选），图片也是可选的。系统会自动加上一条提示：学生点击“开始阅读”时开始计时，并且不能返回短文。';
$string['item_desc'] = '速读练习。学生在计时的同时阅读一篇短文，然后回答相关问题。学生可以看到自己的阅读速度（每分钟词数，中文为每分钟字数）。';
$string['minsec'] = '{$a->minutes} 分 {$a->seconds} 秒';
$string['noanswer'] = '未作答';
$string['notread'] = '你没有读完，所以没有阅读速度。';
$string['passage'] = '短文';
$string['passage_details'] = '请选择简单的短文：学生应该认识几乎所有的词。速读练的是读得快，不是学新词。';
$string['passagestatus'] = '{$a->count} 个词。按目标速度每分钟 {$a->target} 个词，大约需要 {$a->time}。';
$string['passagestatus_cpm'] = '{$a->count} 个字。按目标速度每分钟 {$a->target} 个字，大约需要 {$a->time}。';
$string['passmark'] = '速度计入的及格线';
$string['passmark_details'] = '只有答对的题目达到这个百分比时，阅读速度才计入。';
$string['passmarkstatus'] = '共 {$a->questions} 道题时，学生需要答对 {$a->needed} 道。';
$string['picture'] = '图片';
$string['pluginname'] = '速读';
$string['questionanswers'] = '答案（每行一个）';
$string['questioncount'] = '{$a} 道题';
$string['questionfeedback'] = '反馈（可选）';
$string['questionno'] = '题目 {$a}';
$string['questionofn'] = '第 {$a->number} 题，共 {$a->total} 题';
$string['questions'] = '题目';
$string['questions_details'] = '填写 1 到 5 道题目。在结果界面之前，学生看不到自己是否答对。反馈只在结果界面显示。';
$string['questiontext'] = '题目文字';
$string['readingspeed'] = '你的阅读速度';
$string['readtime'] = '{$a->minutes} 分 {$a->seconds} 秒读了 {$a->count} 个词';
$string['readtime_cpm'] = '{$a->minutes} 分 {$a->seconds} 秒读了 {$a->count} 个字';
$string['screen1'] = '界面 1：介绍';
$string['screen2'] = '界面 2：阅读短文';
$string['screen3'] = '界面 3：测验';
$string['screen4'] = '界面 4：结果与评分';
$string['showallquestions'] = '一次显示所有题目';
$string['showallquestions_details'] = '关闭：每次显示一道题，点击即进入下一题。开启：所有题目显示在同一页，并有提交按钮。';
$string['showtimer'] = '阅读时显示计时器';
$string['showtimer_details'] = '无论是否显示都会记录时间。如果计时器让学生紧张，可以隐藏它。';
$string['speedcounts'] = '你的速度有效。需要答对 {$a->total} 题中的 {$a->needed} 题，你答对了 {$a->correct} 题。';
$string['speeddoesnotcount'] = '这次你的速度不计入。需要答对 {$a->total} 题中的 {$a->needed} 题，你答对了 {$a->correct} 题。下次读慢一点，理解更多内容。';
$string['speedpoints'] = '速度分';
$string['speedreading'] = '速读';
$string['startreading'] = '开始阅读';
$string['submitanswers'] = '提交答案';
$string['tapnext'] = '点击一个答案即可进入下一题。最后会显示你的结果。';
$string['targetis'] = '目标：{$a}';
$string['targetwpm'] = '目标速度';
$string['targetwpm_details'] = '达到或超过这个速度得 10 个速度分，只有一半或更慢得 0 分。';
$string['thepassage'] = '短文';
$string['timerrunning'] = '正在计时';
$string['toofast'] = '速度太快，不像是真正的阅读，所以你的速度不计入。下次请读完整篇短文。';
$string['wordcount'] = '词数';
$string['wordcount_details'] = '留空则自动计算词数。填写可指定一个固定的词数。<br>注意：日语、中文、韩语和泰语按字数（而不是词数）计算。';
$string['wordcountshort'] = '{$a} 个词';
$string['wordsperminute'] = '词/分钟';
$string['youranswer'] = '你的答案：';
$string['youranswers'] = '你的答案';
