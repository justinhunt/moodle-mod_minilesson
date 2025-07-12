define(
    ['jquery','core/log','core/templates','core/fragment','core/modal_factory','core/str','core/config','core/modal_events'],
    function($,log,templates,Fragment,Modalfactory,Str,Config,ModalEvents) {
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
            self.set_data();
        },

        init_controls: function(uniqid){
            //set up the controls
            var self = this;
            self.controls.selectgenerate = $('#' + uniqid + ' select[name="generatemethod"]');
            self.controls.aigenmakebtn = $('#' + uniqid + '_aigen_make_btn');
            self.controls.fieldmappings = $('#' + uniqid + ' #id_fieldmappings');
            self.controls.aigenmake_textarea = $('#' + uniqid + '_aigen_make_textarea');
        },

        register_events: function(uniqid){
            //register events
            var self = this;

            //On clicking the make aigen button
             self.controls.aigenmakebtn.on('click', function(e) {
                var items = [];
                var itemcontrols =$('.ml_aigen_item');
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

                     //get the file areas
                    var generateFileareasCheckboxes = $(itemcontrol).find('.aigen_fileareas-to-generate input[type="checkbox"]');
                    var generateFileareas = [];
                    generateFileareasCheckboxes.each(function() {
                        var generateFilearea = {};
                        generateFilearea.name = $(this).attr('name');
                        generateFilearea.generate  = $(this).is(':checked') ? 1 : 0;
                        if(generateFilearea.generate) {
                            generateFilearea.mapping = $(itemcontrol).find('select[name="' + generateFilearea.name + '_mapping"]').val();
                        }
                        generateFileareas.push(generateFilearea);
                    });
                    itemdata.generatefileareas = generateFileareas;

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

                if (self.controls.fieldmappings.length) {
                    alldata[self.controls.fieldmappings.attr('name')] = JSON.parse(self.controls.fieldmappings.val());
                }

                self.controls.aigenmake_textarea.val(JSON.stringify(alldata, null, 2));
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

                //Update the files areas div
                var fileareasDiv = $(this).closest('.ml_aigen_item').find('.ml_aigen_filearea_mappings');
                var fileareasData = {
                    methodreuse: selectedValue=='reuse',
                    aigenplaceholders: self.splitDataField(fileareasDiv.data('aigenplaceholders')),
                    contextfileareas: self.splitDataField(fileareasDiv.data('contextfileareas')),
                    aigenfileareas: self.splitDataField(fileareasDiv.data('aigenfileareas'))
                };
                templates.render('mod_minilesson/aigenfilemappings',fileareasData).then(
                    function(html,js){
                        log.debug('redoing fileareadata: ');
                        fileareasDiv.html(html);
                    }
                );// End of templates


            });

        },  // end of register_events

        set_data: function(){
            //set up the controls
            var self = this;
            try {
                var textareavalue = self.controls.aigenmake_textarea.val();
                var jsonvalue = JSON.parse(textareavalue);
                jsonvalue.items.forEach(function(itemdata, index) {
                    var itemcontrol = $('.ml_aigen_item[data-itemnumber="'+index+'"]');

                    //set the prompt
                    var promptTextArea = $(itemcontrol).find('textarea[name="aigenprompt"]');
                    promptTextArea.val(itemdata.prompt);

                    var updateTheFields = function() {
                                            
                        //get the generate fields
                        itemdata.generatefields.forEach(function(generateField) {
                            var $generateCheckbox = $(itemcontrol).find('.aigen_fields-to-generate input[type="checkbox"][name="'+generateField.name+'"]');
                            $generateCheckbox.prop('checked', generateField.generate);
                            if (generateField.generate) {
                                if (itemdata.generatemethod=="reuse") {
                                    var $mappingSelect = $(itemcontrol).find('select[name="' + generateField.name + '_mapping"]');
                                    $mappingSelect.val(generateField.mapping);
                                }
                            }
                        });

                        //set the file areas
                        itemdata.generatefileareas.forEach(function(generateFilearea) {
                            var $generateFileareasCheckbox = $(itemcontrol).find('.aigen_fileareas-to-generate input[type="checkbox"][name="'+generateFilearea.name+'"]');
                            $generateFileareasCheckbox.prop('checked', generateFilearea.generate);
                            if(generateFilearea.generate) {
                                var $mappingSelect = $(itemcontrol).find('select[name="' + generateFilearea.name + '_mapping"]');
                                $mappingSelect.val(generateFilearea.mapping);
                            }
                        });

                        //set the prompt field mappings div
                        itemdata.promptfields.forEach(function(promptField) {
                            var $mappingsSelect= $(itemcontrol).find('.aigen_promptfield-mappings select[data-name="'+promptField.name+'"]');
                            $mappingsSelect.val(promptField.mapping);
                        });
                    }

                    //set the generate method
                    var generateMethodSelect = $(itemcontrol).find('select[name="generatemethod"]');
                    generateMethodSelect.val(itemdata.generatemethod);
                    log.debug('Setting generate method to: ' + itemdata.generatemethod);
                    // We need to do this to make sure the correct fields are on the page, before we set data to them.
                    if (itemdata.generatemethod === 'reuse') {
                        log.debug('triggering');
                        generateMethodSelect.trigger('change');
                        //wait a second for the change to take effect
                        setTimeout(function() {
                            log.debug('updating after triggering');
                            updateTheFields();
                        }, 1000);
                    }else{
                        log.debug('updating without triggering');
                        updateTheFields();
                    }

                });
            } catch (error) {
                log.debug('Invalid JSON');
                log.debug(error);
            }
        },

        splitDataField: function(datafield) {
            if(!datafield || datafield.trim() === '') {
                return [];
            }else{
                return datafield.split(',').filter(element => element.trim() !== "")
            }
        },

        extractFieldsFromString: function(input) {
            const regex = new RegExp('\\{(\\w+)\\}', 'g'); // fresh regex every time
            return Array.from(input.matchAll(regex)).reduce(function(a,i) {
                if (a.indexOf(i[1]) === -1) {
                    a.push(i[1]);
                }
                return a;
            }, []);
        },

        registerAiGenerateAction: function(wrapperselector) {
            var self = this;
            var wrapper = document.querySelector(wrapperselector);
            if (wrapper) {
                wrapper.addEventListener('submit', function(e) {
                    if (e.target.elements.hasOwnProperty('keyname')) {
                        e.preventDefault();
                        Modalfactory.create({
                            type: Modalfactory.types.SAVE_CANCEL,
                            title: Str.get_string('aigenmodaltitle', 'mod_minilesson'),
                            body: self.callAiGenerateContextFormApi(e.target),
                            large: true,
                            removeOnClose: true
                        }).then(function(modal) {
                            modal.getRoot().on('submit ' + ModalEvents.save, function(e) {
                                var form = this.querySelector('form');
                                if (form.getAttribute('data-submitted')) {
                                    return;
                                }
                                e.preventDefault();
                                modal.setBody(self.callAiGenerateContextFormApi(form).then(function(html, js) {
                                    if (html === 'submitted') {
                                        form.setAttribute('data-submitted', 1);
                                        js = '<script>document.getElementById("'+form.getAttribute('id')+'").submit()</script>';
                                    }
                                    return [form, js];
                                }));
                            });
                            modal.show();
                        });
                    }
                });
            }
        },

        callAiGenerateContextFormApi: function(form) {
            return Fragment.loadFragment(
                'mod_minilesson', 'aigen_contextform',
                Config.contextid, {
                    url: form.getAttribute('action'),
                    params: new URLSearchParams(new FormData(form)).toString()
                }
            );
        }
    };//end of return value
});