define(['jquery','core/log','core/templates'], function($,log,templates) {
    "use strict"; // jshint ;_;

/*
This file contains class and ID definitions.
 */

    log.debug('MiniLesson AiGen Config Maker: initialising');

    return{
        controls: {},
        //pass in config, amd set up table
        init: function(uniqid){
            //pick up opts from html
            var self = this;
            self.init_controls(uniqid);
            self.register_events(uniqid);
        },

        init_controls: function(uniqid){
            //set up the controls
            var self = this;
            self.controls.selectgenerate = $('#' + uniqid + ' select[name="generatemethod"]');
            self.controls.aigenmakebtn = $('#' + uniqid + '_aigen_make_btn');
        },

        register_events: function(uniqid){
            //register events
            var self = this;

            //On clicking the make aigen button
             self.controls.aigenmakebtn.on('click', function(e) {
                e.preventDefault();
                var items = [];
                var itemcontrols =$('.ml_aigen_item');
                var aigenmake_textarea = $('#' + uniqid + '_aigen_make_textarea');
                // Get the lesson title and description
                var lessonTitle = $('#' + uniqid + '_ml_aigen_lesson_title input').val();
                var lessonDescription = $('#' + uniqid + '_ml_aigen_lesson_description textarea').val();

                //loop through each item
                itemcontrols.each(function(index, itemcontrol) {
                    var itemdata = {};

                   //set the itemnumber
                    itemdata.itemnumber = Number($(itemcontrol).data('itemnumber'));

                    //get the prompt
                    var promptTextArea = $(itemcontrol).find('textarea[name="aigenprompt"]');
                    itemdata.prompt = promptTextArea.val();

                    //get the generate method
                    var generateMethodSelect = $(itemcontrol).find('select[name="generatemethod"]');
                    itemdata.generatemethod = generateMethodSelect.val();

                    //get the generate fields
                    var generateFieldsCheckboxes = $(itemcontrol).find('.aigen_fields-to-generate input[type="checkbox"]');
                    var generateFields = [];
                    generateFieldsCheckboxes.each(function() {
                        var generateField = {};
                        generateField.name = $(this).attr('name');
                        generateField.generate  = $(this).is(':checked') ? 1 : 0;
                        if(itemdata.generatemethod=="reuse" && generateField.generate) {
                            generateField.mapping = $(itemcontrol).find('select[name="' + generateField.name + '_mapping"]').val();
                        }else {
                            generateField.mapping = '';
                        }
                        generateFields.push(generateField);
                    });
                    itemdata.generatefields = generateFields;

                    //get the prompt field mappings div
                    var promptFields=[];
                    var mappingsSelects= $(itemcontrol).find('.aigen_promptfield-mappings select');
                    mappingsSelects.each(function() {
                        var promptField = {};
                        promptField.name = $(this).data('name');
                        promptField.mapping = $(this).val();
                        promptFields.push(promptField);
                    });
                    itemdata.promptfields = promptFields;
                    // Add the current itemdata to the items array
                    items.push(itemdata);
                    
                });// End of itemcontrols.each
                
                var alldata = {};
                alldata.lessonTitle = lessonTitle;
                alldata.lessonDescription = lessonDescription;
                alldata.items = items;
                aigenmake_textarea.val(JSON.stringify(alldata, null, 2));
                log.debug('AiGen make button clicked, items: ' + JSON.stringify(alldata, null,2));
             });

            //On reload of the prompt field to contextdata mapping
            $(document).on('click','.aigen-reload-button', function(e, data) {
                e.preventDefault();

                //get the prompt text area
                var promptTextArea = $(this).closest('.ml_aigen_item').find('textarea[name="aigenprompt"]');
                var newprompt = promptTextArea.val();

                //update the mappings div
                var mappingsDiv = $(this).closest('.ml_aigen_item').find('.ml_aigen_mappings');
                mappingsDiv.data('promptfields',self.extractFieldsFromString(newprompt).join(','));
                
                //update the prompt fields mappings div
                var mappingsdata = {
                    availablecontext: mappingsDiv.data('availablecontext').split(',').filter(element => element.trim() !== ""),
                    aigenpromptfields: mappingsDiv.data('promptfields').split(',').filter(element => element.trim() !== ""),
                };
                var promptfieldmappingsDiv = $(this).closest('.ml_aigen_item').find('.aigen_promptfield-mappings'); 
                templates.render('mod_minilesson/aigenpromptfieldmappings',mappingsdata).then(
                    function(html,js){
                        promptfieldmappingsDiv.html(html);
                    }
                );// End of templates
            });

            //On change of the select, update the config
            self.controls.selectgenerate.on('change', function() {
                //get the value of the select
                var selectedValue = $(this).val();
                //log the value
                log.debug('Selected generate method: ' + selectedValue);
                //the prompt text area
                var promptTextArea = $(this).closest('.ml_aigen_item').find('textarea[name="aigenprompt"]');
                var newprompt = promptTextArea.data(selectedValue + 'prompt');
                promptTextArea.val(newprompt);
                //If this is a reuse generate method the text area should be readonly
                if (selectedValue === 'reuse') {
                    promptTextArea.attr('readonly', true);
                } else {
                    promptTextArea.removeAttr('readonly');
                }

                //update the mappings div
                var mappingsDiv = $(this).closest('.ml_aigen_item').find('.ml_aigen_mappings');
                mappingsDiv.data('promptfields',self.extractFieldsFromString(newprompt).join(','));
                var mappingsdata = {
                    methodreuse: selectedValue=='reuse',
                    aigenplaceholders: mappingsDiv.data('aigenplaceholders').split(',').filter(element => element.trim() !== ""),
                    availablecontext: mappingsDiv.data('availablecontext').split(',').filter(element => element.trim() !== ""),
                    aigenpromptfields: mappingsDiv.data('promptfields').split(',').filter(element => element.trim() !== ""),
                };
                templates.render('mod_minilesson/aigenmappings',mappingsdata).then(
                    function(html,js){
                        log.debug('redoing mappingsdiv: ');
                        mappingsDiv.html(html);
                    }
                );// End of templates
            });
           
        },  // end of register_events

        extractFieldsFromString: function(input) {
            const regex = /\{(\w+)\}/g; // Matches fields inside curly brackets
            const matches = [];
            let match;

            while ((match = regex.exec(input)) !== null) {
                matches.push(match[1]); // Add the captured group to the array
            }

            // Remove duplicates using a Set
            return [...new Set(matches)];
        }
    };//end of return value
});