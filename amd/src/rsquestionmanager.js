/* jshint ignore:start */
define(['jquery','jqueryui', 'core/log','core/templates','mod_poodlltime/definitions','mod_poodlltime/modalformhelper',
        'mod_poodlltime/modaldeletehelper','mod_poodlltime/moveitemhelper','mod_poodlltime/datatables'],
    function($, jqui, log, templates, def, mfh, mdh, mih, datatables) {

    "use strict"; // jshint ;_;

    log.debug('RSQuestion manager: initialising');

    return {

        cmid: null,
        controls: null,


        //pass in config
        init: function(props){
            var dd = this;
            dd.contextid=props.contextid;
            dd.tableid = props.tableid;

            dd.register_events();
            dd.process_html();
        },

        process_html: function(){
            this.controls = [];
            this.controls.questionstable = datatables.getDataTable(this.tableid);
            this.controls.questionscontainer = $('#' + def.mod_poodlltime_items_cont);
            this.controls.noquestionscontainer = $('#' + def.mod_poodlltime_noitems_cont);
            this.controls.movearrows=$('#' + def.movearrow);
        },
        moveRow:function(row, direction) {
            console.log("moved");
          
            var index = this.controls.questionstable.row(row).index();
            
            console.log(index);
          
            var order = -1;
            if (direction === 'down') {
              order = 1;
            }

            console.log(order);
          
            var data1 = this.controls.questionstable.row(index).data();
            data1.order += order;
          
            console.log(data1);

            var data2 = this.controls.questionstable.row(index + order).data();
            data2.order += -order;
          
            console.log(data2);

            this.controls.questionstable.row(index).data(data2);
            this.controls.questionstable.row(index + order).data(data1);

            this.controls.questionstable.page(0).draw(false);
            
        },
        register_events: function() {
          
            var dd = this;
          
            var qtypes =[def.qtype_dictation,def.qtype_dictationchat,def.qtype_page,
                def.qtype_speechcards,def.qtype_listenrepeat, def.qtype_multichoice];

            var after_questionmove= function(itemid, direction) {
              
                var therow = '#' + def.itemrow + '_' + itemid;
                var index = dd.controls.questionstable.row(therow).index();
              
                var targetindex;
              
                if(direction=="up"){
                  targetindex=index-1;
                } else if(direction=="down"){
                  targetindex=index+1;
                }
                
                var targetrow=$(".mod_poodlltime_item_row").eq(targetindex);
              
                var from = dd.controls.questionstable.cell({row:index, column:0}).data();
                var to = dd.controls.questionstable.cell({row:targetindex, column:0}).data();
                dd.controls.questionstable.cell({row:index, column:0}).data(to);
                dd.controls.questionstable.cell({row:targetindex, column:0}).data(from);

                dd.controls.questionstable.draw();
            };

            var after_questionedit= function(item, itemid) {
                var therow = '#' + def.itemrow + '_' + itemid;
                dd.controls.questionstable.cell($(therow + ' .c1')).data(item.name);
            };
            var after_questionadd= function(item, itemid) {
                item.id = itemid;
                item.typelabel = item.type;
                templates.render('mod_poodlltime/itemlistitem',item).then(
                    function(html,js){
                        dd.controls.questionstable.row.add($(html)[0]).draw();
                    }
                );
                dd.controls.noquestionscontainer.hide();
                dd.controls.questionscontainer.show();
            };
            var after_questiondelete= function(itemid) {
                log.debug('after question delete');
                dd.controls.questionstable.row('#' + def.itemrow + '_' + itemid).remove().draw();
                var itemcount = dd.controls.questionstable.rows().count();
                if(!itemcount){
                    dd.controls.noquestionscontainer.show();
                    dd.controls.questionscontainer.hide();
                }
            };

            //register ajax modal handler
            var editcallback=function(item, itemid){console.log(item);};
            var deletecallback=function(itemid){console.log(itemid);};
            var addcallback=function(itemid){console.log(itemid);};
            mfh.init('.' + def.component + '_addlink', dd.contextid,after_questionadd);
            //edit form helper
            mfh.init('.' + def.itemrow + '_editlink', dd.contextid,after_questionedit);
            //delete helpser
            mdh.init('.' + def.itemrow + '_deletelink', dd.contextid, 'deleteitem',after_questiondelete);
            //move helper
            mih.init('.' + def.movearrow , dd.contextid, after_questionmove);
        }

    };//end of returned object
});//total end
