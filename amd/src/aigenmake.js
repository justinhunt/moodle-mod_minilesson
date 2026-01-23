define(
    ['jquery','core/log','core/templates','core/fragment','core/modal_factory',
        'core/str','core/config','core/modal_events', 'core_table/dynamic', 'core/ajax'],
    function ($,log,templates,Fragment,Modalfactory,Str,Config,ModalEvents, DyanamicTable, Ajax) {
        "use strict"; // jshint ;_;

    /*
    This file contains class and ID definitions.
    */

        log.debug('MiniLesson AiGen Config Maker: initialising');

        return{
            controls: {},
            itemdatas: [],
            itemcontrols: [],
            //pass in config, amd set up table
            init: function (uniqid) {
                //pick up opts from html
                var self = this;
                self.init_controls(uniqid);
                self.register_events(uniqid);
                self.set_data();
            },

            init_controls: function (uniqid) {
                //set up the controls
                var self = this;
                self.controls.selectgenerate = $('#' + uniqid + ' select[name="generatemethod"]');
                self.controls.aigenmakebtn = $('#' + uniqid + '_aigen_make_btn');
                self.controls.fieldmappings = $('#' + uniqid + ' #id_fieldmappings');
                self.controls.aigenmake_textarea = $('#' + uniqid + '_aigen_make_textarea');
            },

            register_events: function (uniqid) {
                //register events
                var self = this;

                //On clicking the make aigen button
                 self.controls.aigenmakebtn.on('click', function (e) {
                    var items = [];
                    var itemcontrols = $('.ml_aigen_item');
                    // Get the lesson title and description
                    var lessonTitle = $('#' + uniqid + '_ml_aigen_lesson_title input').val();
                    var lessonDescription = $('#' + uniqid + '_ml_aigen_lesson_description textarea').val();

                    //loop through each item
                    itemcontrols.each(function (index, itemcontrol) {
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
                        generateFieldsCheckboxes.each(function () {
                            var generateField = {};
                            generateField.name = $(this).attr('name');
                            generateField.generate  = $(this).is(':checked') ? 1 : 0;
                            if (itemdata.generatemethod == "reuse" && generateField.generate) {
                                generateField.mapping = $(itemcontrol).find('select[name="' + generateField.name + '_mapping"]').val();
                            } else {
                                generateField.mapping = '';
                            }
                            generateFields.push(generateField);
                        });
                        itemdata.generatefields = generateFields;

                         //get the file areas
                        var generateFileareasCheckboxes = $(itemcontrol).find('.aigen_fileareas-to-generate input[type="checkbox"]');
                        var generateFileareas = [];
                        generateFileareasCheckboxes.each(function () {
                            var generateFilearea = {};
                            generateFilearea.name = $(this).attr('name');
                            generateFilearea.generate  = $(this).is(':checked') ? 1 : 0;
                            if (generateFilearea.generate) {
                                generateFilearea.mapping = $(itemcontrol).find('select[name="' + generateFilearea.name + '_mapping"]').val();
                            }
                            generateFileareas.push(generateFilearea);
                        });
                        itemdata.generatefileareas = generateFileareas;

                        //Get the overall image context for the item (if any) e.g "user_topic" - "A man and a boy are walking in a park"
                        var overallimagecontext = $(itemcontrol).find('select[name="overall_image_context"]').val();
                        itemdata.overallimagecontext = overallimagecontext;

                        //get the prompt field mappings div
                        var promptFields = [];
                        var mappingsSelects = $(itemcontrol).find('.aigen_promptfield-mappings select');
                        mappingsSelects.each(function () {
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
                $(document).on('click','.aigen-reload-button', function (e, data) {
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
                        function (html,js) {
                            promptfieldmappingsDiv.html(html);
                        }
                    );// End of templates
                });

                //On change of the select, update the config
                self.controls.selectgenerate.on('change', function () {
                    self.regenerate_item_form(this);
                });

            },  // end of register_events

            regenerate_item_form: function (selectgenerateelement, itemdata = null, itemcontrol = null) {
                var self = this;
                //get the value of the select
                var selectedValue = $(selectgenerateelement).val();
                //log the value
                log.debug('Selected generate method: ' + selectedValue);
                //the prompt text area
                var promptTextArea = $(selectgenerateelement).closest('.ml_aigen_item').find('textarea[name="aigenprompt"]');
                var newprompt = promptTextArea.data(selectedValue + 'prompt');
                promptTextArea.val(newprompt);
                //If this is a reuse generate method the text area should be readonly
                if (selectedValue === 'reuse') {
                    promptTextArea.attr('readonly', true);
                } else {
                    promptTextArea.removeAttr('readonly');
                }

                //prepare the mappings div data
                var mappingsDiv = $(selectgenerateelement).closest('.ml_aigen_item').find('.ml_aigen_mappings');
                mappingsDiv.data('promptfields',self.extractFieldsFromString(newprompt).join(','));
                var mappingsdata = {
                    methodreuse: selectedValue === 'reuse',
                    aigenplaceholders: mappingsDiv.data('aigenplaceholders').split(',').filter(element => element.trim() !== ""),
                    availablecontext: mappingsDiv.data('availablecontext').split(',').filter(element => element.trim() !== ""),
                    aigenpromptfields: mappingsDiv.data('promptfields').split(',').filter(element => element.trim() !== ""),
                };

                //prepare the files areas div data
                var fileareasDiv = $(selectgenerateelement).closest('.ml_aigen_item').find('.ml_aigen_filearea_mappings');
                var fileareasData = {
                    methodreuse: selectedValue === 'reuse',
                    aigenplaceholders: self.splitDataField(fileareasDiv.data('aigenplaceholders')),
                    contextfileareas: self.splitDataField(fileareasDiv.data('contextfileareas')),
                    aigenfileareas: self.splitDataField(fileareasDiv.data('aigenfileareas')),
                    availablecontext: self.splitDataField(fileareasDiv.data('availablecontext')),
                };

                // Render mappings first, then render file areas after mappings has finished.
                templates.render('mod_minilesson/aigenmappings', mappingsdata)
                .then(function (html, js) {
                    log.debug('redoing mappingsdiv: ');
                    mappingsDiv.html(html);
                    // Chain the second render so it runs after the first is done
                    return templates.render('mod_minilesson/aigenfilemappings', fileareasData);
                })
                .then(function (html, js) {
                    log.debug('redoing fileareadata: ');
                    fileareasDiv.html(html);
                }).then(function () {
                // If we have itemdata, set the fields accordingly
                    if (itemdata && itemcontrol) {
                        log.debug('updating after regenerating form');
                        self.updateTheFields(itemdata, itemcontrol);
                    }
                });
            },

            updateTheFields: function (theitemdata, theitemcontrol) {

                //get the generate fields
                theitemdata.generatefields.forEach(function (generateField) {
                    var $generateCheckbox = $(theitemcontrol).find('.aigen_fields-to-generate input[type="checkbox"][name="' + generateField.name + '"]');
                    $generateCheckbox.prop('checked', generateField.generate);
                    if (generateField.generate) {
                        if (theitemdata.generatemethod == "reuse") {
                            var $mappingSelect = $(theitemcontrol).find('select[name="' + generateField.name + '_mapping"]');
                            $mappingSelect.val(generateField.mapping);
                        }
                    }
                });

                //set the file areas
                theitemdata.generatefileareas.forEach(function (generateFilearea) {
                    var $generateFileareasCheckbox = $(theitemcontrol).find('.aigen_fileareas-to-generate input[type="checkbox"][name="' + generateFilearea.name + '"]');
                    $generateFileareasCheckbox.prop('checked', generateFilearea.generate);
                    if (generateFilearea.generate) {
                        var $mappingSelect = $(theitemcontrol).find('select[name="' + generateFilearea.name + '_mapping"]');
                        $mappingSelect.val(generateFilearea.mapping);
                    }
                });

                //set the overall image context
                var overallimagecontext = $(theitemcontrol).find('select[name="overall_image_context"]');
                overallimagecontext.val(theitemdata.overallimagecontext);

                //set the prompt field mappings div
                theitemdata.promptfields.forEach(function (promptField) {
                    var $mappingsSelect = $(theitemcontrol).find('.aigen_promptfield-mappings select[data-name="' + promptField.name + '"]');
                    $mappingsSelect.val(promptField.mapping);
                });
            },

            set_data: function () {
                //set up the controls
                var self = this;
                try {
                    var textareavalue = self.controls.aigenmake_textarea.val();
                    var jsonvalue = JSON.parse(textareavalue);
                    jsonvalue.items.forEach(function (itemdata, index) {
                        var itemcontrol = $('.ml_aigen_item[data-itemnumber="' + index + '"]');

                        //set the prompt
                        var promptTextArea = $(itemcontrol).find('textarea[name="aigenprompt"]');
                        promptTextArea.val(itemdata.prompt);

                        //set the generate method
                        var generateMethodSelect = $(itemcontrol).find('select[name="generatemethod"]');
                        generateMethodSelect.val(itemdata.generatemethod);
                        promptTextArea.data(itemdata.generatemethod + 'prompt', itemdata.prompt);
                        log.debug('Setting generate method to: ' + itemdata.generatemethod);
                        // We need to do this to make sure the correct fields are on the page, before we set data to them.
                        log.debug('triggering');

                        // Regenerate the form and, once both renders finish, set the values for this item
                        self.regenerate_item_form(generateMethodSelect[0], itemdata, itemcontrol);

                    });
                } catch (error) {
                    log.debug('Invalid JSON');
                    log.debug(error);
                }
            },

            splitDataField: function (datafield) {
                if (!datafield || datafield.trim() === '') {
                    return [];
                } else {
                    return datafield.split(',').filter(element => element.trim() !== "")
                }
            },

            extractFieldsFromString: function (input) {
                const regex = new RegExp('\\{(\\w+)\\}', 'g'); // fresh regex every time
                return Array.from(input.matchAll(regex)).reduce(function (a,i) {
                    if (a.indexOf(i[1]) === -1) {
                        a.push(i[1]);
                    }
                    return a;
                }, []);
            },

            registerAiGenerateAction: function (wrapperselector) {
                var self = this;
                var wrapper = document.querySelector(wrapperselector);
                if (wrapper) {
                    wrapper.addEventListener('submit', function (e) {
                        if (e.target.elements.hasOwnProperty('templateid')) {
                            e.preventDefault();
                            Modalfactory.create({
                                type: Modalfactory.types.SAVE_CANCEL,
                                title: Str.get_string('aigenmodaltitle', 'mod_minilesson'),
                                body: self.callAiGenerateContextFormApi(e.target),
                                large: true,
                                removeOnClose: true
                            }).then(function (modal) {
                                modal.getRoot().on('submit ' + ModalEvents.save, function (e) {
                                    e.preventDefault();
                                    var form = this.querySelector('form');
                                    modal.setBody(self.callAiGenerateContextFormApi(form).then(function (html, js) {
                                        if (html === 'submitted') {
                                            modal.destroy();
                                            self.refreshTable(wrapper.dataset.tableuniqueid);
                                            document.querySelector('#page') ? .scrollTo({ top : 0, behavior : 'smooth' });
                                            return ['', ''];
                                        }
                                        return [html, js];
                                    }));
                                });
                                modal.show();
                            });
                        }
                    });
                    self.checkProgressBar(wrapper.dataset.tableuniqueid);
                }
            },

            callAiGenerateContextFormApi: function (form) {
                return Fragment.loadFragment(
                    'mod_minilesson',
                    'aigen_contextform',
                    Config.contextid,
                    {
                        url: form.getAttribute('action'),
                        params: new URLSearchParams(new FormData(form)).toString()
                    }
                );
            },

            refreshTable: function (tableuniqueid) {
                var self = this;
                try {
                    var tableRoot = DyanamicTable.getTableFromId(tableuniqueid);
                    DyanamicTable.refreshTableContent(tableRoot).then(function () {
                        self.checkProgressBar(tableuniqueid);
                    });
                } catch (e) {
                    log.debug(e);
                }
            },

            checkProgressBar: function (tableuniqueid, pinginterval = 1) {
                var self = this;
                try {
                    var tableRoot = DyanamicTable.getTableFromId(tableuniqueid);
                } catch (e) {
                    log.debug(e);
                }
                var table = tableRoot.querySelector('table');
                if (!table) {
                    return;
                }
                var updateinterval = parseInt(table.getAttribute('data-updateinterval'));
                if (updateinterval > 0) {
                    pinginterval = updateinterval;
                }
                if (tableRoot) {
                    var idmap = {};
                    tableRoot.querySelectorAll('[data-region="progressbar"]')
                    .forEach(function (progressbar) {
                        var id = parseInt(progressbar.getAttribute('data-id'));
                        if (id > 0) {
                            idmap[id] = progressbar;
                        }
                    });
                    var ids = Object.keys(idmap).map(parseInt);
                    if (Object.keys(ids).length > 0) {
                        Ajax.call([{
                            methodname: 'mod_minilesson_update_progressbars',
                            args: {
                                contextid: Config.contextid,
                                ids: ids
                            }
                        }])[0].then(function (response) {
                            response.forEach(function (line) {
                                if (ids.indexOf(line.id) > -1) {
                                    var progressbarrow = idmap[line.id].closest('tr');
                                    line.columns.forEach(function (coldata, index) {
                                        if (coldata.update) {
                                            var columntd = progressbarrow.querySelector('td.c' + index);
                                            if (columntd) {
                                                columntd.innerHTML = coldata.data;
                                                columntd.dataset.column = coldata.column;
                                            }
                                        }
                                    });
                                }
                            });
                            setTimeout(function () {
                                self.checkProgressBar(tableuniqueid);
                            }, pinginterval * 1000);
                        });
                    }
                }
            },

            manageFilter: function () {
                var self = this;
                var allcheckbox = document.querySelector('.minilesson_template_tag_all');
                var othercheckbox = document.querySelectorAll('.minilesson_template_tag_others');
                var allChecks = [].concat(allcheckbox, Array.from(othercheckbox));

                allcheckbox.addEventListener('change', function () {
                    othercheckbox.forEach(function (cb) {
                        cb.disabled = allcheckbox.checked;
                    });
                });

                document.addEventListener('change', e => {
                    if (allChecks.includes(e.target)) {
                        const filters = allChecks.filter(function (cb) {
                            return cb.checked && !cb.disabled;
                        }).map(function (cb) {
                            return cb.value;
                        });
                        self.loadContents(filters);
                    }
                });
            },

            loadContents: function (filters) {
                var rootelement = document.querySelector('[data-region="mod_minilesson_aigentemplates"]');
                Fragment.loadFragment(
                    'mod_minilesson',
                    'templates',
                    M.cfg.contextid,
                    {
                        filters: JSON.stringify(filters),
                    }
                ).then(function (html, js) {
                    templates.replaceNodeContents(rootelement, html, js);
                });
            }
        };//end of return value
    }
);