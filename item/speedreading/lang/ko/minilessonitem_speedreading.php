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
 * Korean strings for minilessonitem_speedreading
 *
 * @package    minilessonitem_speedreading
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = '속독';
$string['answercorrect'] = '정답';
$string['answersneeded'] = '모든 문제에 답해야 제출할 수 있어요. 제출하기 전까지는 답을 바꿀 수 있어요.';
$string['answerwrong'] = '오답';
$string['cannotgoback'] = '지문으로 돌아갈 수 없어요.';
$string['charactercount'] = '{$a}자';
$string['charactersperminute'] = '자/분';
$string['correctansweris'] = '정답:';
$string['defaultinstructions'] = '지문을 최대한 빨리 읽으세요. 그다음 문제에 답하세요.';
$string['error:correctanswertoohigh'] = '정답 번호가 보기 개수보다 커요.';
$string['error:needtwoanswers'] = '보기를 두 개 이상, 한 줄에 하나씩 입력하세요.';
$string['error:passmark'] = '0에서 100 사이의 숫자를 입력하세요.';
$string['error:targetwpm'] = '0보다 큰 숫자를 입력하세요.';
$string['error:wordcount'] = '0 이상을 입력하세요. 비워 두면 자동으로 세요.';
$string['finishedreading'] = '다 읽었어요';
$string['formexplainer'] = '학생은 네 개의 화면을 봐요. 타이머는 ‘읽기 시작’ 버튼을 누르면 시작되고 ‘다 읽었어요’ 버튼을 누르면 멈춰요. 지문으로 돌아갈 수 없어요. 아래 각 섹션에서 화면을 하나씩 설정해요.';
$string['formexplainer_heading'] = '이 항목의 진행 방식';
$string['formexplainer_step1'] = '제목, 안내, 팁, 선택 이미지가 나와요. 학생은 ‘읽기 시작’ 버튼을 눌러요.';
$string['formexplainer_step2'] = '지문과 타이머가 나와요. 완료 버튼은 지문 끝에 있어요.';
$string['formexplainer_step3'] = '객관식 문제가 최대 5개 나와요. 결과 화면 전까지는 맞았는지 알 수 없어요.';
$string['formexplainer_step4'] = '읽기 속도, 속도 점수, 퀴즈 점수, 피드백이 포함된 답, 그리고 지문이 나와요.';
$string['gradeitem'] = '이 항목 채점';
$string['gradeitem_details'] = '성적은 퀴즈 점수예요. 읽기 속도는 학생에게 보이지만 성적에는 들어가지 않아요.';
$string['instructions'] = '안내';
$string['intronote'] = '‘읽기 시작’ 버튼을 누르면 타이머가 시작돼요. 다 읽은 뒤에는 지문으로 돌아갈 수 없어요.';
$string['introtext'] = '소개 글';
$string['introtext_details'] = '안내는 제목 아래의 짧은 한 줄이에요. 소개 글에는 팁을 넣을 수 있고 이미지는 선택이에요. 학생이 ‘읽기 시작’ 버튼을 누르면 타이머가 시작되고 지문으로 돌아갈 수 없다는 안내는 자동으로 추가돼요.';
$string['item_desc'] = '속독 연습이에요. 학생은 타이머가 도는 동안 지문을 읽고 지문에 관한 문제에 답해요. 읽기 속도는 분당 단어 수(한국어는 분당 글자 수)로 보여요.';
$string['minsec'] = '{$a->minutes}분 {$a->seconds}초';
$string['noanswer'] = '답 없음';
$string['notread'] = '끝까지 읽지 않아서 읽기 속도가 없어요.';
$string['passage'] = '지문';
$string['passage_details'] = '쉬운 글을 고르세요. 학생이 거의 모든 단어를 알아야 해요. 속독은 빨리 읽는 연습이지 새 단어를 배우는 연습이 아니에요.';
$string['passagestatus'] = '{$a->count}단어. 목표 속도(분당 {$a->target}단어)로 읽으면 약 {$a->time} 걸려요.';
$string['passagestatus_cpm'] = '{$a->count}자. 목표 속도(분당 {$a->target}자)로 읽으면 약 {$a->time} 걸려요.';
$string['passmark'] = '속도 인정 기준';
$string['passmark_details'] = '정답 비율이 이 값 이상일 때만 읽기 속도가 인정돼요.';
$string['passmarkstatus'] = '문제가 {$a->questions}개이면 {$a->needed}개를 맞혀야 해요.';
$string['picture'] = '이미지';
$string['pluginname'] = '속독';
$string['questionanswers'] = '보기(한 줄에 하나)';
$string['questioncount'] = '{$a}문제';
$string['questionfeedback'] = '피드백(선택)';
$string['questionno'] = '문제 {$a}';
$string['questionofn'] = '문제 {$a->number}/{$a->total}';
$string['questions'] = '문제';
$string['questions_details'] = '문제를 1~5개 입력하세요. 학생은 결과 화면 전까지 맞았는지 알 수 없어요. 피드백은 결과 화면에만 보여요.';
$string['questiontext'] = '문제 내용';
$string['readingspeed'] = '내 읽기 속도';
$string['readtime'] = '{$a->minutes}분 {$a->seconds}초에 {$a->count}단어';
$string['readtime_cpm'] = '{$a->minutes}분 {$a->seconds}초에 {$a->count}자';
$string['screen1'] = '화면 1: 소개';
$string['screen2'] = '화면 2: 읽기 지문';
$string['screen3'] = '화면 3: 퀴즈';
$string['screen4'] = '화면 4: 결과와 채점';
$string['showallquestions'] = '모든 문제를 한꺼번에 표시';
$string['showallquestions_details'] = '끄기: 한 번에 한 문제씩 나오고 누르면 다음으로 넘어가요. 켜기: 모든 문제가 한 페이지에 나오고 제출 버튼이 있어요.';
$string['showtimer'] = '읽는 동안 타이머 표시';
$string['showtimer_details'] = '시간은 어느 쪽이든 기록돼요. 학생이 불안해하면 타이머를 숨기세요.';
$string['speedcounts'] = '내 속도가 인정돼요. {$a->total}문제 중 {$a->needed}개를 맞혀야 했고 {$a->correct}개를 맞혔어요.';
$string['speeddoesnotcount'] = '이번에는 속도가 인정되지 않아요. {$a->total}문제 중 {$a->needed}개를 맞혀야 했는데 {$a->correct}개를 맞혔어요. 다음에는 조금 더 천천히 읽고 더 많이 이해해 보세요.';
$string['speedpoints'] = '속도 점수';
$string['speedreading'] = '속독';
$string['startreading'] = '읽기 시작';
$string['submitanswers'] = '답안 제출';
$string['tapnext'] = '답을 누르면 다음 문제로 넘어가요. 결과는 마지막에 볼 수 있어요.';
$string['targetis'] = '목표: {$a}';
$string['targetwpm'] = '목표 속도';
$string['targetwpm_details'] = '이 속도 이상이면 속도 점수 10점, 이 속도의 절반 이하이면 0점이에요.';
$string['thepassage'] = '지문';
$string['timerrunning'] = '타이머 작동 중';
$string['toofast'] = '너무 빨라서 제대로 읽었다고 볼 수 없어 속도가 인정되지 않아요. 다음에는 지문을 끝까지 읽어 보세요.';
$string['wordcount'] = '단어 수';
$string['wordcount_details'] = '비워 두면 단어 수를 자동으로 세요. 특정 단어 수를 쓰려면 입력하세요.<br>참고: 일본어, 중국어, 한국어, 태국어는 단어가 아니라 글자 수를 세요.';
$string['wordcountshort'] = '{$a}단어';
$string['wordsperminute'] = '단어/분';
$string['youranswer'] = '내 답:';
$string['youranswers'] = '내 답';
