define(['jquery', 'core/fragment'], function($, Fragment) {
    return {
        init: function() {
            $('select[data-name="instructionsaiprompt"]').on('change', function() {
                const val = $(this).val();
                const itemtype = $(this).data('type') || '';
                const textarea = $('textarea[data-name="aigrade_instructions"]');
                if (parseInt(val) === 0) {
                    return;
                }
                Fragment.loadFragment(
                    'mod_minilesson',
                    'ai_prompt',
                    M.cfg.contextid,
                    { promptid: val, prompttype: 'instructions', itemtype: itemtype }
                ).done(function(text) {
                    textarea.val(text);
                });
            });

            $('select[data-name="gradingaiprompt"]').on('change', function() {
                const val = $(this).val();
                const itemtype = $(this).data('type') || '';
                const textarea = $('textarea[data-name="aigrade_grade"]');
                if (parseInt(val) === 0) {
                    return;
                }
                Fragment.loadFragment(
                    'mod_minilesson',
                    'ai_prompt',
                    M.cfg.contextid,
                    { promptid: val, prompttype: 'grading', itemtype: itemtype }
                ).done(function(text) {
                    textarea.val(text);
                });
            });

            $('select[data-name="feedbackaiprompt"]').on('change', function() {
                const val = $(this).val();
                const itemtype = $(this).data('type') || '';
                const textarea = $('textarea[data-name="aigrade_feedback"]');
                if (parseInt(val) === 0) {
                    return;
                }
                Fragment.loadFragment(
                    'mod_minilesson',
                    'ai_prompt',
                    M.cfg.contextid,
                    { promptid: val, prompttype: 'feedback', itemtype: itemtype }
                ).done(function(text) {
                    textarea.val(text);
                });
            });
        }
    };
});