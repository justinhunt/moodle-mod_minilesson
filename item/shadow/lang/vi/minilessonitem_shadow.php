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
 * Vietnamese strings for minilessonitem_shadow
 *
 * @package    minilessonitem_shadow
 * @category   string
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['additem'] = 'Shadowing qua video';
$string['aihelper_placeholder_shadow'] = 'ví dụ: Thêm dấu câu và sửa mọi lỗi chính tả và lỗi viết hoa';
$string['autoforward'] = 'Tự động chuyển tiếp';
$string['autoforward_details'] = 'Sau lần shadowing cuối của một dòng, tự động chuyển sang dòng tiếp theo thay vì chờ nút OK / Tiếp theo. Chế độ này không có nút Thử lại.';
$string['enablesubtitlefetch'] = 'Bật nút lấy phụ đề';
$string['enablesubtitlefetch_details'] = 'Hiển thị nút “Lấy phụ đề” trên biểu mẫu mục, tải phụ đề của video YouTube vào trình chỉnh sửa phụ đề. Lưu ý: việc lấy phụ đề không phải lúc nào cũng hoạt động và có thể ngừng hoạt động bất cứ lúc nào. Đây là công cụ tiện ích mà Poodll không đảm bảo luôn khả dụng.';
$string['error:badshadowlines'] = 'Các dòng cần shadow phải là * (tất cả các dòng) hoặc danh sách số dòng cách nhau bằng dấu phẩy, ví dụ 1,4,5,6.';
$string['error:badtimestamp'] = 'Thời gian bắt đầu và kết thúc của clip phải có định dạng hh:mm:ss, ví dụ 00:01:30.';
$string['error:badvtt'] = 'Không phân tích được phụ đề. Hãy nhập WebVTT hợp lệ có ít nhất một đoạn có mốc thời gian.';
$string['error:nocuesinclip'] = 'Không có dòng phụ đề nào nằm hoàn toàn trong khoảng thời gian bắt đầu và kết thúc của clip. Hãy điều chỉnh thời gian hoặc phụ đề.';
$string['error:noshadowlines'] = 'Không số dòng nào đã chọn khớp với một dòng phụ đề nằm trong khoảng thời gian bắt đầu và kết thúc của clip.';
$string['error:novideoid'] = 'Cần có ID video YouTube hoặc URL.';
$string['error:startafterend'] = 'Thời gian kết thúc của clip phải sau thời gian bắt đầu.';
$string['error:subtitlefetchdisabled'] = 'Tính năng lấy phụ đề đã bị tắt trên trang web này.';
$string['fetchvtt'] = 'Lấy phụ đề';
$string['fetchvtt_disabled'] = 'Hiện tính năng tự động lấy phụ đề đang bị tắt';
$string['fetchvtt_failed'] = 'Không lấy được phụ đề từ YouTube.';
$string['fetchvtt_fetching'] = 'Đang lấy...';
$string['fetchvtt_invalidurl'] = 'Hãy nhập URL YouTube hợp lệ hoặc ID video gồm 11 ký tự trước.';
$string['fetchvtt_overwrite'] = 'Thao tác này sẽ thay thế phụ đề hiện có trong trình soạn thảo. Tiếp tục?';
$string['fetchvtt_overwrite_title'] = 'Thay thế phụ đề?';
$string['item_desc'] = 'Mục Shadowing qua video phát một clip YouTube từng dòng một. Học viên “shadow” từng dòng phụ đề, nói theo video khi các từ được tô sáng.';
$string['loopcount'] = 'Số lần shadow mỗi dòng';
$string['loopcount_desc'] = 'Số lần mỗi dòng được phát lại để học viên shadow.';
$string['loopindicator'] = 'Shadowing: {$a->current} / {$a->total}';
$string['oknext'] = 'OK / Tiếp theo';
$string['paste_empty'] = 'Hãy dán bản ghi bạn đã sao chép từ YouTube trước.';
$string['paste_failed'] = 'Không chuyển đổi được bản ghi.';
$string['paste_label'] = 'Bản ghi được sao chép từ YouTube';
$string['paste_placeholder'] = '0:00
dòng đầu tiên của bản ghi
0:03
dòng thứ hai của bản ghi';
$string['paste_step_copy'] = 'Chọn toàn bộ bản ghi, sao chép rồi dán vào ô bên dưới';
$string['paste_step_open_novideo'] = 'Mở video trên YouTube (hãy nhập ID video vào biểu mẫu trước để có liên kết ở đây)';
$string['paste_step_showtranscript'] = 'Bên dưới video, mở rộng phần mô tả và nhấp vào “Hiển thị bản ghi”';
$string['paste_step_timestamps'] = 'Trong menu ⋮ của bảng bản ghi, hãy chắc chắn rằng dấu thời gian đã được bật';
$string['paste_success'] = 'Đã thêm {$a} dòng phụ đề.';
$string['paste_wordhighlightoff'] = 'Tính năng tô sáng từng từ đã được tắt, vì bản ghi dán vào chỉ có thời gian cho cả dòng. Clip sẽ tô sáng từng dòng một.';
$string['pastevtt'] = 'Dán bản ghi';
$string['pastevtt_convert'] = 'Chuyển thành phụ đề';
$string['pastevtt_title'] = 'Dán bản ghi YouTube';
$string['pluginname'] = 'Shadowing qua video';
$string['retry'] = 'Thử lại';
$string['rotatedevice'] = 'Hãy xoay thiết bị sang chế độ dọc để tiếp tục.';
$string['shadow_instructions1'] = 'Hãy xem video. Sau đó “shadow” từng dòng: nghe và nói theo video khi nó phát lại.';
$string['shadowlines'] = 'Các dòng cần shadow';
$string['shadowlines_desc'] = 'Số dòng phụ đề cần shadow, đếm từ 1 trong phần phụ đề bên dưới, ví dụ 1,4,5,6. Dùng * để shadow tất cả các dòng. Các dòng khác vẫn hiển thị khi xem.';
$string['shadowpause'] = 'Khoảng nghỉ giữa các lần shadow (giây)';
$string['shadowvtt'] = 'Phụ đề (WebVTT)';
$string['shadowvtt_desc'] = 'Dán hoặc chỉnh sửa phụ đề WebVTT của clip trong vùng bên dưới.';
$string['startshadowing'] = 'Bắt đầu shadowing';
$string['watchhint'] = 'Nhấn phát và xem đoạn clip. Khi xong, nhấn “Bắt đầu shadowing”.';
$string['wordhighlight'] = 'Bật tô sáng từng từ';
$string['wordhighlight_details'] = 'Tô sáng từng từ khi nó được nói, dựa trên dấu thời gian của từng từ trong phụ đề. Thời gian từng từ của YouTube không đáng tin ở một số video - hãy tắt tùy chọn này để tô sáng cả dòng. Khi tắt, việc lấy phụ đề cũng bỏ qua việc lấy dấu thời gian của từng từ.';
$string['ytclipdetails'] = 'Clip YouTube (ID/URL, giây bắt đầu và kết thúc)';
