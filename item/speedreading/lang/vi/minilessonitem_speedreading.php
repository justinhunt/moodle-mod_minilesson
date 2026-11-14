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
 * Vietnamese strings for minilessonitem_speedreading
 *
 * @package    minilessonitem_speedreading
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Đọc nhanh';
$string['answercorrect'] = 'Đúng';
$string['answersneeded'] = 'Trả lời tất cả câu hỏi để nộp bài. Bạn có thể đổi câu trả lời cho đến khi nộp.';
$string['answerwrong'] = 'Sai';
$string['cannotgoback'] = 'Bạn không thể quay lại đoạn văn.';
$string['charactercount'] = '{$a} ký tự';
$string['charactersperminute'] = 'ký tự mỗi phút';
$string['correctansweris'] = 'Đáp án đúng:';
$string['defaultinstructions'] = 'Đọc đoạn văn nhanh nhất có thể. Sau đó trả lời các câu hỏi.';
$string['error:correctanswertoohigh'] = 'Số thứ tự đáp án đúng lớn hơn số đáp án.';
$string['error:needtwoanswers'] = 'Nhập ít nhất hai đáp án, mỗi dòng một đáp án.';
$string['error:passmark'] = 'Nhập một số từ 0 đến 100.';
$string['error:targetwpm'] = 'Nhập một số lớn hơn 0.';
$string['error:wordcount'] = 'Nhập 0 trở lên. Để trống để tự động đếm.';
$string['finishedreading'] = 'Tôi đã đọc xong';
$string['formexplainer'] = 'Học viên thấy bốn màn hình. Đồng hồ bắt đầu khi bấm “Bắt đầu đọc” và dừng khi bấm “Tôi đã đọc xong”. Học viên không thể quay lại đoạn văn. Mỗi phần bên dưới thiết lập một màn hình.';
$string['formexplainer_heading'] = 'Cách hoạt động của mục này';
$string['formexplainer_step1'] = 'Tiêu đề, hướng dẫn, mẹo và một hình ảnh tùy chọn. Học viên bấm “Bắt đầu đọc”.';
$string['formexplainer_step2'] = 'Đoạn văn và đồng hồ. Nút hoàn thành nằm ở cuối đoạn văn.';
$string['formexplainer_step3'] = 'Tối đa 5 câu hỏi trắc nghiệm. Học viên không biết mình đúng hay sai cho đến khi xem kết quả.';
$string['formexplainer_step4'] = 'Tốc độ đọc, điểm tốc độ, điểm bài trắc nghiệm, câu trả lời kèm phản hồi và đoạn văn.';
$string['gradeitem'] = 'Chấm điểm mục này';
$string['gradeitem_details'] = 'Điểm là điểm bài trắc nghiệm. Tốc độ đọc được hiển thị cho học viên nhưng không tính vào điểm.';
$string['instructions'] = 'Hướng dẫn';
$string['intronote'] = 'Đồng hồ bắt đầu khi bạn bấm “Bắt đầu đọc”. Bạn không thể quay lại đoạn văn sau khi đọc xong.';
$string['introtext'] = 'Văn bản giới thiệu';
$string['introtext_details'] = 'Hướng dẫn là một dòng ngắn dưới tiêu đề. Văn bản giới thiệu có thể chứa mẹo (tùy chọn), hình ảnh cũng tùy chọn. Ghi chú rằng đồng hồ bắt đầu khi học viên bấm “Bắt đầu đọc” và không thể quay lại đoạn văn sẽ được tự động thêm vào.';
$string['item_desc'] = 'Luyện đọc nhanh. Học viên đọc một đoạn văn trong khi đồng hồ chạy, rồi trả lời các câu hỏi về đoạn văn. Học viên thấy tốc độ đọc của mình theo số từ mỗi phút.';
$string['minsec'] = '{$a->minutes} phút {$a->seconds} giây';
$string['noanswer'] = 'Chưa trả lời';
$string['notread'] = 'Bạn chưa đọc xong nên không có tốc độ đọc.';
$string['passage'] = 'Đoạn văn';
$string['passage_details'] = 'Hãy chọn một đoạn văn dễ: học viên nên biết gần như tất cả các từ. Đọc nhanh là luyện đọc nhanh, không phải học từ mới.';
$string['passagestatus'] = '{$a->count} từ. Với tốc độ mục tiêu {$a->target} từ mỗi phút, sẽ mất khoảng {$a->time}.';
$string['passagestatus_cpm'] = '{$a->count} ký tự. Với tốc độ mục tiêu {$a->target} ký tự mỗi phút, sẽ mất khoảng {$a->time}.';
$string['passmark'] = 'Ngưỡng tính tốc độ';
$string['passmark_details'] = 'Tốc độ đọc chỉ được tính khi số câu đúng đạt ít nhất tỷ lệ phần trăm này.';
$string['passmarkstatus'] = 'Với {$a->questions} câu hỏi, học viên cần đúng {$a->needed} câu.';
$string['picture'] = 'Hình ảnh';
$string['pluginname'] = 'Đọc nhanh';
$string['questionanswers'] = 'Đáp án (mỗi dòng một đáp án)';
$string['questioncount'] = '{$a} câu hỏi';
$string['questionfeedback'] = 'Phản hồi (tùy chọn)';
$string['questionno'] = 'Câu hỏi {$a}';
$string['questionofn'] = 'Câu {$a->number} / {$a->total}';
$string['questions'] = 'Câu hỏi';
$string['questions_details'] = 'Điền từ 1 đến 5 câu hỏi. Học viên không biết mình đúng hay sai cho đến màn hình kết quả. Phản hồi chỉ hiển thị trên màn hình kết quả.';
$string['questiontext'] = 'Nội dung câu hỏi';
$string['readingspeed'] = 'Tốc độ đọc của bạn';
$string['readtime'] = '{$a->count} từ trong {$a->minutes} phút {$a->seconds} giây';
$string['readtime_cpm'] = '{$a->count} ký tự trong {$a->minutes} phút {$a->seconds} giây';
$string['screen1'] = 'Màn hình 1: Giới thiệu';
$string['screen2'] = 'Màn hình 2: Đoạn văn đọc';
$string['screen3'] = 'Màn hình 3: Bài trắc nghiệm';
$string['screen4'] = 'Màn hình 4: Kết quả và chấm điểm';
$string['showallquestions'] = 'Hiển thị tất cả câu hỏi cùng lúc';
$string['showallquestions_details'] = 'Tắt: mỗi lần một câu hỏi, bấm để sang câu tiếp. Bật: tất cả câu hỏi trên một trang, có nút nộp bài.';
$string['showtimer'] = 'Hiển thị đồng hồ khi đọc';
$string['showtimer_details'] = 'Thời gian vẫn được ghi lại dù hiển thị hay không. Hãy ẩn đồng hồ nếu nó khiến học viên lo lắng.';
$string['speedcounts'] = 'Tốc độ của bạn được tính. Bạn cần đúng {$a->needed}/{$a->total} câu và bạn đã đúng {$a->correct} câu.';
$string['speeddoesnotcount'] = 'Lần này tốc độ của bạn không được tính. Bạn cần đúng {$a->needed}/{$a->total} câu và bạn đã đúng {$a->correct} câu. Lần sau hãy đọc chậm hơn một chút để hiểu nhiều hơn.';
$string['speedpoints'] = 'Điểm tốc độ';
$string['speedreading'] = 'Đọc nhanh';
$string['startreading'] = 'Bắt đầu đọc';
$string['submitanswers'] = 'Nộp bài';
$string['tapnext'] = 'Bấm vào một đáp án để sang câu hỏi tiếp theo. Bạn sẽ xem kết quả ở cuối.';
$string['targetis'] = 'Mục tiêu: {$a}';
$string['targetwpm'] = 'Tốc độ mục tiêu';
$string['targetwpm_details'] = 'Tốc độ này hoặc nhanh hơn được 10 điểm tốc độ. Một nửa tốc độ này hoặc chậm hơn được 0 điểm.';
$string['thepassage'] = 'Đoạn văn';
$string['timerrunning'] = 'Đồng hồ đang chạy';
$string['toofast'] = 'Tốc độ quá nhanh để là đọc thật nên không được tính. Lần sau hãy đọc hết đoạn văn.';
$string['wordcount'] = 'Số từ';
$string['wordcount_details'] = 'Để trống để tự động đếm số từ. Điền vào để đặt một số từ cụ thể.<br>Lưu ý: với tiếng Nhật, tiếng Trung, tiếng Hàn và tiếng Thái, chúng tôi đếm ký tự (không đếm từ).';
$string['wordcountshort'] = '{$a} từ';
$string['wordsperminute'] = 'từ mỗi phút';
$string['youranswer'] = 'Câu trả lời của bạn:';
$string['youranswers'] = 'Câu trả lời của bạn';
