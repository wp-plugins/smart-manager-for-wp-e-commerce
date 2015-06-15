
// jQuery(function($) {
var $ = jQuery.noConflict(),
    sm = {dashboard_model:'', dashboard_key: '',dashboard_select_options: ''},
    page = 1,
    hideDialog = '',
    inline_edit_dlg = '',
    multiselect_chkbox_list = '',
    limit = 50,
    sm_dashboards_combo = '', // variable to store the dashboard names
    column_names = new Array(), // array for the column headers in jqgrid
    column_names_batch_update = new Array(), // array for storing the batch update fields
    sm_column_names_src = new Array(), // array for storing the column src for current dashboard
    sm_store_table_model = new Array(), // array for storing store table model
    lastrow = '1',
    lastcell = '1',
    grid_width = '750',
    sm_ajax_url = (ajaxurl.indexOf('?') !== -1) ? ajaxurl + '&action=sm_beta_include_file' : ajaxurl + '?action=sm_beta_include_file',
    //defining default actions for batch update
    batch_update_action_string = {set_to:'set to', prepend:'prepend', append:'append'};
    batch_update_action_number = {set_to:'set to', increase_by_per:'increase by %', decrease_by_per:'decrease by %', increase_by_num:'increase by number', decrease_by_num:'decrease by number'};
    sm_qtags_btn_init = 1,
    sm_grid_nm = 'sm_editor_grid', //name of div containing jqgrid
    sm_wp_editor_html = '', //variable for storing the html of the wp editor
    sm_last_edited_row_id = sm_last_edited_col = '';

    //function for inline edit dialog
    inline_edit_dlg = function (dialog_content, title, dlg_width, dlg_height, edited_col) {            
        modal_width = '';
        modal_height = '';

        if (dlg_width == '' || dlg_width == undefined) {
            modal_width = 350;
        } else {
            modal_width = dlg_width;
        }

        if (dlg_height == '' || dlg_height == undefined) {
            modal_height = 390;
        } else {
            modal_height = dlg_height;
        }

        var grid = $("#sm_editor_grid"),
            gID = grid[0].id,
            IDs = {
                themodal:gID+'_inlinemod',
                modalhead:gID+'_inlinehd',
                modalcontent:gID+'_inlinecnt',
                scrollelm:gID+'_inlineTbl'
            },
            dlgContent = dialog_content,
            window_width = jQuery(window).width();
            window_height = jQuery(window).height();

            hideDialog = function() {
                $(document).trigger("sm_inline_edit_dlg_hide",edited_col); //event for adding custom elements to jqgrid titlebar
                $.jgrid.hideModal("#"+IDs.themodal,{gb:"#gbox_"+gID,jqm:true, onClose: null});
                index = 0;
            }

                if ($('#'+IDs.themodal).length===0) {
                    // dialog not yet exist. we need create it.
                    $.jgrid.createModal(
                        IDs,
                        dlgContent,
                        {
                            gbox: "#gbox_"+gID,
                            caption: title,
                            jqModal: true,
                            left: ((window_width - modal_width)/2),
                            top: ((window_height - modal_height)/2),
                            overlay: 10,
                            width: modal_width,
                            height: modal_height,
                            zIndex: 950,
                            drag: true,
                            // resize: true,
                            closeOnEscape: true,
                            closeicon: [false,'left','ui-icon-close'],
                            onClose:hideDialog
                            
                        },
                        "#gview_"+gID,
                        $("#gview_"+gID)[0]);
                        $("#"+gID+"_inlinemod").css({'overflow-x': 'hidden', 'overflow-y': 'scroll'});
                } else {
                    $("#"+gID+"_inlinemod").css({'width': modal_width, 'height': modal_height, 'overflow-x': 'hidden', 'overflow-y': 'scroll', 'left': ((window_width - modal_width)/2), 'top':((window_height - modal_height)/2)});
                    // $("#edit_product_attributes").html(dialog_content);

                    $("#"+gID+"_inlinecnt").html(dialog_content);
                }

                $.jgrid.viewModal("#"+IDs.themodal,{gbox:"#gbox_"+gID,jqm:true, overlay: 10, modal:false});


                $("#"+gID+"_inlinehd").find('.ui-jqdialog-titlebar-close').hide();

    }

$(document).ready(function() {

    sm_dashboards_combo = sm_dashboards = $.parseJSON(sm_dashboards[0]);

    sm.dashboard_key = sm_dashboards['default'];

    if ( !jQuery(document.body).hasClass('folded') ) {
        grid_width  = document.documentElement.offsetWidth - 220;
    }
    else {
        grid_width  = document.documentElement.offsetWidth - 100;
    }


    $('#collapse-menu').live('click', function() {

        if ( !jQuery(document.body).hasClass('folded') ) {
            grid_width  = document.documentElement.offsetWidth - 220;
        }
        else {
            grid_width  = document.documentElement.offsetWidth - 100;
        }

        $('#sm_editor_grid').jqGrid("setGridWidth", grid_width);
        $('#sm_editor_grid').trigger( 'reloadGrid' );

    });

    sm_qtags_btn_init = 0;

    sm_wp_editor_html = $('#sm_wp_editor').html();

    jqgrid_custom_func();
    load_dashboard ();

});

//Function to handle the loading of the dashboard
var load_dashboard = function () {


    // $('#sm_editor_grid').jqGrid('GridUnload');
    // $('#sm_editor_grid').jqGrid('gridUnload');
    $.jgrid.gridUnload('sm_editor_grid');

    column_names = new Array();
    column_names_batch_update = new Array();

    if ( typeof(sm.dashboard_model) == 'undefined' || sm.dashboard_model == '' ) {
        get_dashboard_model();    
    }

    load_grid();

    //Code for enabling the batch update button
    // $( "input[id^='jqg_sm_editor_grid_'], #jqgh_sm_editor_grid_cb input" ).live('change', function() {

    //     var selected_row_count = $("input[id^='jqg_sm_editor_grid_']:checked").length,
    //         cb_header_selected = $("#jqgh_sm_editor_grid_cb input").is(':checked');
    //     if (selected_row_count > 0 || (cb_header_selected && $(this).parent().attr('id') == 'jqgh_sm_editor_grid_cb' ) ) {
    //         $('#batch_sm_editor_grid').removeClass('ui-state-disabled');
    //     } else if(selected_row_count <= 0 ) {
    //         $('#batch_sm_editor_grid').addClass('ui-state-disabled');
    //     }

    //     if ($(this).parent().attr('id') == 'jqgh_sm_editor_grid_cb' && cb_header_selected === false) {
    //         $('#batch_sm_editor_grid').addClass('ui-state-disabled');
    //     }

    // })

    //code for inline editing dirty cell highlighting
    $('#sm_editor_grid td input, #sm_editor_grid td select').live('change',function() {
        if ($(this).parent().attr('aria-describedby') == 'sm_editor_grid_cb' ) return;
        $(this).parent().addClass('sm-jqgrid-dirty-cell');
        $(this).parent().parent().addClass('edited'); // for adding class to the td element
        $('#save_sm_editor_grid').removeClass('ui-state-disabled');

    });

    var batch_update_field_options = '<option value="" disabled selected>Select Field</option>';
    for (var key in column_names_batch_update) {
        batch_update_field_options += '<option value="'+key+'">'+ column_names_batch_update[key].name +'</option>';
    }

    //Formating options for default actions
    var batch_update_action_options_default = '<option value="" disabled selected>Select Action</option>';
        batch_update_action_options_default += '<option value="set_to">set to</option>';

    //Formating options for string actions
    var batch_update_action_options_string = '<option value="" disabled selected>Select Action</option>';
    for (var key in batch_update_action_string) {
        batch_update_action_options_string += '<option value="'+key+'">'+ batch_update_action_string[key] +'</option>';
    }

    //Formating options for string actions
    var batch_update_action_options_number = '<option value="" disabled selected>Select Action</option>';
    for (var key in batch_update_action_number) {
        batch_update_action_options_number += '<option value="'+key+'">'+ batch_update_action_number[key] +'</option>';
    }

    var grid = $("#sm_editor_grid"),
        gID = grid[0].id,
        IDs = {
            themodal:'batchmod'+gID,
            modalhead:'batchhd'+gID,
            modalcontent:'batchcnt'+gID,
            scrollelm:'BatchTbl_'+gID
        },
        hideDialog = function() {
            $.jgrid.hideModal("#"+IDs.themodal,{gb:"#gbox_"+gID,jqm:true, onClose: null});
        },
        rowId,
        createBatchUpdateDialog = function() {
            var dlgContent =
                "<div id='"+IDs.scrollelm+"' class='formdata' style='width: 100%; overflow: auto; position: relative; height: auto;'>"+
                    "<table class='batch_update_table'>"+
                        "<tbody>"+
                            "<tr>"+
                                "<td style='white-space: pre;'><select id='batch_update_field' style='min-width:130px !important;'>"+batch_update_field_options+"</select></td>"+
                                "<td style='white-space: pre;'><select id='batch_update_action' style='min-width:130px !important;'>"+batch_update_action_options_default+"</select></td>"+
                                "<td id='batch_update_value_td' style='white-space: pre;'><input type='text' id='batch_update_value' placeholder='Enter a value...' class='FormElement ui-widget-content' style='height:28px;min-width:130px !important;'></td>"+
                            "</tr>"+
                            "<tr>"+
                                "<td>&#160;</td>"+
                            "</tr>"+
                        "</tbody>"+
                    "</table>"+
                "</div>"+
                "<table cellspacing='0' cellpadding='0' border='0' class='EditTable' id='"+IDs.scrollelm+"_2'>"+
                    "<tbody>"+
                        "<tr>"+
                            "<td>"+
                                "<hr class='ui-widget-content' style='margin: 1px' />"+
                            "</td>"+
                        "</tr>"+
                        "<tr>"+
                            "<td class='DelButton EditButton'>"+
                                "<a href='javascript:void(0)' id='btn_batch_update' class='fm-button ui-state-default ui-corner-all'>Update</a>"+
                            "</td>"+
                        "</tr>"+
                    "</tbody>"+
                "</table>";

            if ($('#'+IDs.themodal).length===0) {
                // dialog not yet exist. we need create it.



                // $.jgrid.createModal(
                //     IDs,
                //     dlgContent,
                //     {
                //         gbox: "#gbox_"+gID,
                //         caption: 'Batch Update',
                //         jqModal: true,
                //         left: 200,
                //         top: 300,
                //         overlay: 10,
                //         width: 540,
                //         height: 'auto',
                //         zIndex: 950,
                //         drag: true,
                //         resize: true,
                //         closeOnEscape: true,
                //         onClose: null
                //     },
                //     "#gview_"+gID,
                //     $("#gview_"+gID)[0]);
            }

            $.jgrid.viewModal("#"+IDs.themodal,{gbox:"#gbox_"+gID,jqm:true, overlay: 10, modal:false});

            $("#batch_update_field").on('change',function(){
                var selected_field = $( "#batch_update_field option:selected" ).val(),
                    type = column_names_batch_update[selected_field].type,
                    col_val = column_names_batch_update[selected_field].values;

                if (type == 'number') {
                    $("#batch_update_action").empty().append(batch_update_action_options_number);
                } else if (type == 'string') {
                    $("#batch_update_action").empty().append(batch_update_action_options_string);
                } else {
                    $("#batch_update_action").empty().append(batch_update_action_options_default);
                }

                $("#batch_update_value").val('');

                $("#batch_update_value_td").empty().append('<input type="text" id="batch_update_value" placeholder="Enter a value..." class="FormElement ui-widget-content" style="height:28px;min-width:130px !important;">');

                if (type == 'date' || type == 'datetime') {
                    $("#batch_update_value").attr('placeholder','Enter Date');
                    $("#batch_update_value").datepicker();
                } else {
                    $("#batch_update_value").attr('placeholder','Enter a value...');
                    $("#batch_update_value").datepicker('destroy');
                }

                if(type == 'toggle') {
                    $("#batch_update_value_td").empty().append('<select id="batch_update_value" style="min-width:130px !important;">'+
                                                                '<option value="yes"> Yes </option>'+
                                                                '<option value="no"> No </option>'+
                                                            '</select>');
                } else if (col_val != '' && type == 'list') {
                    
                    var batch_update_value_options = '<select id="batch_update_value" style="min-width:130px !important;">';

                    for (var key in col_val) {
                        batch_update_value_options += '<option value="'+key+'">'+ col_val[key] + '</option>';
                    }

                    batch_update_value_options += '</select>';

                    $("#batch_update_value_td").empty().append(batch_update_value_options);

                } else if (type == 'longtext') {
                    $("#batch_update_value_td").empty().append('<`a id="batch_update_value" placeholder="Enter a value..." class="FormElement ui-widget-content" style="height:28px;min-width:130px;margin-top:5px; !important;"> </textarea>');
                }

            });

        };

    //Code to create the dashboard combobox
    var selected = '';
    sm.dashboard_select_options = '';


    for (var key in sm_dashboards) {

        if (key == 'default') continue;

        selected = '';

        if (key == sm.dashboard_key) {
            selected = "selected";
        }
        sm.dashboard_select_options += '<option value="'+key+'" '+selected+'>'+sm_dashboards[key]+'</option>'
    }

    var sm_top_bar = "<div id='sm_top_bar' style='font-weight:400 !important;'>"+
                        "<div id='sm_top_bar_left'>"+
                            "<label id=sm_dashboard_select_lbl> <select id='sm_dashboard_select' style='height:20px!important;'> </select> </label>"+
                        "</div>"+
                        "<div id='sm_top_bar_right'>"+
                            "<span id='add_sm_editor_grid' title='Add Row' class='dashicons dashicons-plus' style='margin-top: 2px;margin-right: 2px;font-size: 23px;'></span>"+
                            "<span id='save_sm_editor_grid' title='Save' class='ui-icon' style='margin-top:5px;padding:0px !important;'></span>"+
                            "<span id='del_sm_editor_grid' title='Delete Selected Row' class='dashicons dashicons-trash sm_error_icon' style='margin-top:1px;'></span>"+
                            "<span id='sm_top_bar_right_separator' class='ui-separator' style='width=4px;padding:0px;margin-top:4px;margin-right:1px;margin-left:0px;'></span>"+
                            "<span id='refresh_sm_editor_grid' title='Refresh' class='dashicons dashicons-update' style='font-size: 23px;margin-right: 1px;'></span>"+
                            "<span id='show_hide_cols_sm_editor_grid' title='Show / Hide Columns' class='dashicons dashicons-admin-generic'></span>"+
                        "</div>"+
                    "</div>";

    $(".ui-jqgrid-titlebar").append(sm_top_bar);
    $('#sm_dashboard_select').append(sm.dashboard_select_options);

    $('#sm_dashboard_select').width($('#sm_dashboard_select').width()+16); //Code for dynamically increasing the width of the select-box

    // Code for handling all the click events

    // Code for handling the delete row functionality
    $("#del_sm_editor_grid").click(function(){
        // "Delete" button is clicked
        // var rowId = grid.jqGrid('getGridParam', 'selrow');
        var row_ids = grid.jqGrid('getGridParam', 'selarrrow');

        if (row_ids.length == 0) {
            inline_edit_dlg('Please, select row','Warning',150,50);
            return;
        }

        $.ajax({
                type : 'POST',
                url : sm_ajax_url,
                dataType:"text",
                async: false,
                data: {
                            cmd: 'delete',
                            active_module: sm.dashboard_key,
                            ids: JSON.stringify(row_ids)
                },
                success: function(response) { 
                    if ( response != 0 ) {
                        inline_edit_dlg(response,'Success',150,50);    
                        $('#sm_editor_grid').trigger( 'reloadGrid' );
                    }
                }
        });
        hideDialog();
    });

    // Code for handling the add row functionality
    $("#add_sm_editor_grid").click(function(){
        var add_row_data = {},
        length = $( "tr[id^='jqg_sm_add_row_']" ).length;

          $("#sm_editor_grid").jqGrid('addRowData','jqg_sm_add_row_'+length ,add_row_data,'first');
            
          // Code for aligning the checkbox
          var chkbox = $("#jqg_sm_add_row_"+length).find('[aria-describedby="sm_editor_grid_cb"]').html();

          var updated_html = '<div class="tree-wrap tree-wrap-ltr" style="width:18px;"></div>'+
                                '<span class="cell-wrapperleaf">'+chkbox+'</span>';


          $("#jqg_sm_add_row_"+length).find('[aria-describedby="sm_editor_grid_cb"]').html(updated_html);
          
          // Code for udpating the post type
          $("#jqg_sm_add_row_"+length).find('[aria-describedby="sm_editor_grid_posts_post_type"]').html(sm.dashboard_key);
          $("#jqg_sm_add_row_"+length).find('[aria-describedby="sm_editor_grid_posts_post_type"]').addClass('sm-jqgrid-dirty-cell').addClass('dirty-cell');

          $(document).trigger("sm_add_row",['jqg_sm_add_row_'+length]);

          $(this).attr("disabled",false);
    });

    // Code for handling the save row functionality
    $("#save_sm_editor_grid").click(function(){
        $("#sm_editor_grid").jqGrid("saveCell",lastrow,lastcell);

        var edited_ids = jQuery('.edited').toArray(),
            rowdata = {},
            edited_item_ids = [],
            children = '';

        for (var edited_id in edited_ids) {

            id = edited_ids[edited_id].id;
            children = jQuery('#'+id).children(".sm-jqgrid-dirty-cell");

            //Code to get the edited item ids
            $(children).each(function(index, item){
                item_id = $(item).attr('aria-describedby');
                edited_field_nm = item_id.substr(15);
                if ( edited_item_ids.indexOf(edited_field_nm) == '-1' ) {
                    edited_item_ids.push(edited_field_nm); //strlen() for 'sm_editor_grid' is 15    
                }
                
            });
        }

        //Code for making the final edited data array
        for (var edited_id in edited_ids) {

            var formatted_row_data = {};

            id = edited_ids[edited_id].id;
            edited_rowData = $('#sm_editor_grid').jqGrid('getRowData', id);

            for (var row_data_key in edited_rowData) {

                if ( row_data_key == 'posts_id' ) {
                    id = edited_rowData[row_data_key];
                }

                if ( edited_item_ids.indexOf(row_data_key) == '-1')
                    continue;

                key = sm_column_names_src[row_data_key];
                formatted_row_data [key] = edited_rowData[row_data_key];
            }

            rowdata[id] = formatted_row_data;
        }

        //Ajax request to save the edited data
        $.ajax({
                type : 'POST',
                url : sm_ajax_url,
                dataType:"text",
                async: false,
                data: {
                            cmd: 'inline_update',
                            active_module: sm.dashboard_key,
                            edited_data: JSON.stringify(rowdata),
                            table_model: JSON.stringify(sm_store_table_model)
                },
                success: function(response) {
                    inline_edit_dlg(response,'Success',150,50);
                    $('#sm_editor_grid').trigger( 'reloadGrid' );
                    $('#save_sm_editor_grid').addClass('ui-state-disabled');
                }
            });
    });

    // Code for handling the refresh grid functionality
    $("#refresh_sm_editor_grid").click(function(){
        $('#sm_editor_grid').trigger( 'reloadGrid' );
    });

    // Code for handling the show/hide columns functionality
    $("#show_hide_cols_sm_editor_grid").click(function(){
        $('#sm_editor_grid').jqGrid('columnChooser', {
                                                        modal: true,
                                                        done : function (perm) {
                                                            if (perm) {
                                                                $("#sm_editor_grid").jqGrid("remapColumns", perm, true);
                                                                
                                                                setTimeout(function() {
                                                                    $("#sm_editor_grid").jqGrid("setGridWidth", grid_width);
                                                                    $("#sm_editor_grid").clearGridData().trigger("reloadGrid");    
                                                                },100);
                                                            }
                                                        }
        });
    });

    // Column Chooser CSS
    $('body').live('DOMNodeInserted', '#colchooser_sm_editor_grid', function(e) {
        if ( $(e.target).attr('id') == 'colchooser_sm_editor_grid' ) {
            setTimeout(function(){
                var count  = $('#colchooser_sm_editor_grid').find('.ui-multiselect').find('.selected').find('.count').text();
                $('#colchooser_sm_editor_grid').parent().find('.ui-dialog-titlebar').text('Show / Hide Columns ['+count+']');
                $('#colchooser_sm_editor_grid').find('.ui-multiselect').find('.selected').find('.count').text();
                $('#colchooser_sm_editor_grid').find('.ui-multiselect').css({"max-height":"250px","overflow-y":"scroll"});
                $('#colchooser_sm_editor_grid').find('.ui-multiselect').find('.selected').css('width','50%');
                $('#colchooser_sm_editor_grid').find('.ui-multiselect').find('.available').css('width','50%');
                $('#colchooser_sm_editor_grid').find('.ui-multiselect').find('ul').css('width','100%');
                $('#colchooser_sm_editor_grid').find('.ui-multiselect').find('input.search').attr('placeholder','Search...');
                $('#colchooser_sm_editor_grid').find('.ui-multiselect').find('input.search').css({"height":"20px","opacity":"1","margin":"6px","width":"150px","font-weight":"400"});
                $('#colchooser_sm_editor_grid').find('.ui-multiselect').find('li').css({"color":"#444","font-weight":"400"});
                $('#colchooser_sm_editor_grid').find('.ui-multiselect').find('input.search').on('focus',function(){
                    $(this).css({"border":"1px solid #0073ea","background":"transparent","color":"#444"});
                });                
            },100);
        }
    })
    
    // Code for handling the batch update functionality : TODO
    // $("#batch_update_sm_editor_grid").click(function(){
    //     rowId = grid.jqGrid('getGridParam', 'selrow');

    //     if (rowId === null) {
    //         var alertIDs = {themodal: 'alertmod_' + this.p.id, modalhead: 'alerthd_' + this.p.id,modalcontent: 'alertcnt_' + this.p.id},
    //         $t = this, twd, tdw;
    //         $.jgrid.viewModal("#"+alertIDs.themodal,{gbox:"#gbox_"+$.jgrid.jqID($t.p.id),jqm:true});$("#jqg_alrt").focus();

    //     } else {
    //         createBatchUpdateDialog();
    //     }

    //     return false;
    // });

    $(document).trigger("sm_jqgrid_titlebar_load"); //event for adding custom elements to jqgrid titlebar

     $("#sm_editor_grid").jqGrid('navGrid','#sm_pagging_bar',{
                          edit:false,
                          add:false,
                          search:false,
                          addParams: {
                            position: "last",
                          },
                          del:true,
                          refresh:true});

    $("#sm_dashboard_select").on('change',function(){
        sm.dashboard_key = $( "#sm_dashboard_select" ).val();
        sm.dashboard_model = '';
        load_dashboard ();
        $('#sm_editor_grid').trigger( 'reloadGrid' );
    });

    $('#save_sm_editor_grid,#batch_sm_editor_grid').addClass('ui-state-disabled');

    $('#add_sm_editor_grid div, #save_sm_editor_grid div, #batch_sm_editor_grid div').css('padding-right','5px');

    $('#gbox_sm_editor_grid').css({border:'1px solid #3892D3'}); // for main grid

    //Code for handling the cell edit view part
    $(document).on('focus','td[role="gridcell"]', function(){
        var parent = $(this).parent(),
            parent_id = parent[0].id,
            attr_val = $(this).attr('aria-describedby'),
            cell_nm_const = 'sm_editor_grid_';
            cell_nm = attr_val.substr(cell_nm_const.length,attr_val.length);
            grid_cell = $("#"+parent_id+" td[aria-describedby='"+attr_val+"']"),
            input_el = grid_cell[0].children[0],
            input_el_id = grid_cell[0].children[0].id,
            columns = sm.dashboard_model[sm.dashboard_key].columns,
            field_datetime = false;

        for (var i in columns) {
            if (columns[i].hasOwnProperty('name') === false) continue;
            if (columns[i].name == cell_nm) {
                if (columns[i].hasOwnProperty('type') !== false && columns[i].type == 'datetime') {
                    field_datetime = true;
                }
            }
        }

        if ( attr_val != 'sm_editor_grid_cb' && field_datetime !== true ) {
            $(document).on('focusout','#'+input_el_id, function(){
                var grid_cell = $(this).parent();
                $(this).hide();
                $(this).parent().attr('tabindex','-1');
                $(this).parent().removeClass('edit-cell ui-state-highlight');
                $(this).parent().html(grid_cell[0].children[0]).append($(this).val());
            });
        }

        if (input_el.style.display == 'none') { 
            
            $(this).attr('tabindex','0');
            $(this).addClass('edit-cell ui-state-highlight');

            if ( field_datetime === true ) {
                $(this).children().each(function() {
                    $(this).show();
                });
            } else {
                $(this).html(input_el);
                $('#'+input_el_id).show().focus();
            }
        }
    });
}

var smInitDateWithButton = function (elem) {

        $(elem).datepicker({
            dateFormat: 'yy-mm-dd',
            showOn: 'button',
            changeYear: true,
            changeMonth: true,
            showWeek: true,
            showButtonPanel: true,
            onSelect: function(datetext){

            },
            onClose: function (dateText, inst) {

                var d = new Date(); // for now
                dateText=dateText+" "+d.getHours()+":"+d.getMinutes()+":"+d.getSeconds();
                
                inst.input.text(dateText);
                inst.input[0].value = dateText;
                inst.input.focus();

                var grid_cell = $(this).parent(),
                    id = $(this).attr('id');

                $(this).hide();
                $(this).parent().find('button').hide();
                $(this).parent().attr('tabindex','-1');
                $(this).parent().removeClass('edit-cell ui-state-highlight');
                $(this).parent().find('#'+id+'_span').remove();
                
                $(this).parent().addClass('sm-jqgrid-dirty-cell');
                $(this).parent().parent().addClass('edited'); // for adding class to the td element
                $('#save_sm_editor_grid').removeClass('ui-state-disabled');

                $(this).parent().append('<span id="'+id+'_span">'+$(this).text()+'</span>');
                
            }
        });
        $(elem).next('button.ui-datepicker-trigger').button({
            text: false,
            icons: {primary: 'ui-icon-calculator'}
        }).find('span.ui-button-text').css('padding', '0.1em');
};

//function to format the column model
var format_dashboard_column_model = function (column_model) {

    if (column_model == '') return;


    // for (i = 0; i < column_model.length; i++) {
    for (i = 0; i < column_model.length; i++) {

            column_values = (typeof(column_model[i].values) != 'undefined') ? column_model[i].values : '';

            column_names[i] = column_model[i].name.trim(); //Array for column headers
            sm_column_names_src[column_model[i].index] = column_model[i].src;

            var batch_enabled_flag = 'true';

            if (column_model[i].hasOwnProperty('batch_editable')) {
                batch_enabled_flag = column_model[i].batch_editable;
            }

            if (batch_enabled_flag == 'true') {
                column_names_batch_update[column_model[i].index] = {name: column_model[i].name.trim(), type:column_model[i].type, values:column_values, src:column_model[i].src};
            }

            if ( typeof(column_model[i].allow_showhide) != 'undefined' && column_model[i].allow_showhide === false ) {
                column_model[i].hidedlg = true;
            }
            
            column_model[i].name = column_model[i].index;

             //setting the default width
            if (typeof(column_model[i].width) == 'undefined') {
                column_model[i].width = 80;
            }

            //setting the edtiting options
            if ( typeof(column_model[i].type) != 'undefined' ) {
                if (column_model[i].type == 'toggle') {
                    column_model[i].edittype = 'checkbox';
                    column_model[i].editoptions = {value:'Yes:No'}; 
                } else if (column_model[i].type == 'longstring') {
                    column_model[i].editable = false;
                    // column_model[i].edittype = 'textarea';
                    // column_model[i].editoptions = {rows:"2",cols:"10"};

                    column_model[i].formatter = function(v) {
                        v = (typeof(v) != 'undefined') ? v : '';
                        return '<div id="sm_formatter" style="max-height: 20px !important; overflow:hidden;">' + v + '</div>';
                    }

                    column_model[i].unformat = function(cellvalue, options, rowObject) {
                        var edited_val = $(rowObject).find('#sm_formatter').html();
                        return edited_val;
                    }

                } else if (column_model[i].type == 'datetime') {
                    // column_model[i].editoptions= { dataInit: function(el) { $(el).datepicker(); } };
                    column_model[i].editoptions= { dataInit: smInitDateWithButton, size: 11};
                } else if ( column_model[i].type == 'serialized' ) {
                    column_model[i].formatter = function(v) {
                        v = (typeof(v) != 'undefined') ? v : '';
                        return '<div style="max-height: 20px">' + v + '</div>';
                    }
                    column_model[i].unformat = function(v) {
                        return v;
                    }
                } else if ( column_model[i].type == 'list' ) { 
                    
                } else if ( column_model[i].type == 'multilist' ) {
                    column_model[i].formatter = function(v) {
                        v = (typeof(v) != 'undefined') ? v : '';
                        return '<div style="max-height: 20px">' + v + '</div>';
                    }
                    column_model[i].unformat = function(v) {
                        return v;
                    }
                }
            }

            //Code for formatting the values
            var formatted_values = '';

            if (column_values && Object.keys(column_model[i].values).length > 0) {
                var values = column_model[i].values;
                for (var key in values) {
                  if (values.hasOwnProperty(key)) {
                    formatted_values += key + ":" + values[key] + ";";
                  }
                }

                formatted_values = formatted_values.substr(0,(formatted_values.length-1));

                column_model[i].edittype = 'select';
                column_model[i].editoptions = {'value':formatted_values};

                //for displaying selected text instead of values
                // column_model[i].formatter = SelectFormatter;
            }



    };
    return column_model;
}

//function to get the dashboard model for the selected dashboard
var get_dashboard_model = function () {

    sm.dashboard_model = '';

    //Ajax request to get the dashboard model
    $.ajax({
            type : 'POST',
            url : sm_ajax_url,
            dataType:"json",
            async: false,
            data: {
                        cmd: 'get_dashboard_model',
                        active_module: sm.dashboard_key
                },
            success: function(response) {
                if (response != '') {

                    sm_store_table_model = response[sm.dashboard_key].tables;
                    col_model = format_dashboard_column_model(response[sm.dashboard_key].columns);
                    response[sm.dashboard_key].columns = col_model;
                }
                sm.dashboard_model = response;
            }
        });
}

var load_grid = function () {

    var jqgrid_params = { 
                            url:sm_ajax_url,
                            // editurl:sm_ajax_url,
                            datatype: "json",
                            mtype: 'POST',

                            // ajaxGridOptions: {
                            //   type    : 'post',
                            //   async   : false,
                            postData: {
                                      cmd: 'get_data_model',
                                      active_module: sm.dashboard_key,
                                      start: 0,
                                      page: page,
                                      limit: limit,
                                      sort_params: sm.dashboard_model[sm.dashboard_key].sort_params,
                                      table_model: sm.dashboard_model[sm.dashboard_key].tables
                                  },
                              jsonReader: {
                                      root: "items",
                                      page: "page",
                                      start: "start",
                                      records: "total_count",
                                      total: "total_pages",
                                      repeatitems: false,
                                      // id: "5"  ,
                                      // cell: ""  ,
                                      // userdata: "userdata"
                                  },
                            // }, 
                            colNames:column_names,
                            colModel:sm.dashboard_model[sm.dashboard_key].columns,
                            rowNum:limit,
                            // rowList:[10,20,30],
                            pager: '#sm_pagging_bar', // for rendering the paging bottom bar
                            multiselect: true, // for left checkbox column and multi-selection
                            height: 500,
                            width: grid_width,
                            hidegrid: false, //option for removing the grid show/hide option
                            // autowidth: true,
                            // forceFit: true,
                            shrinkToFit: false, // for remap columns
                            scroll:1, // for infinite scrolling
                            viewrecords: true, // for viewing the total no. of records
                            // sortorder: "desc",
                            // sortname: 'invid',
                            // sortorder: 'desc',
                            sortable: true, // for enabling sorting of columns
                            // onselectrow: true,
                            // multiSort: true, // for multiple sorting
                            // ExpandColumn : 'post_title',
                            'cellEdit': true, // for cell editing
                            'cellsubmit' : 'clientArray',
                            onSelectCell: function (rowid, celname, value, iRow, iCol) {
                                $(document).trigger("sm_on_cell_click",[rowid, celname, value, iRow, iCol]);
                            },
                            beforeEditCell:function(rowid,cellname,v,iRow,iCol){
                                lastrow = iRow;
                                lastcell = iCol;
                            },
                            afterEditCell : function(rowid, cellname, value, iRow, iCol) {
                                $(document).trigger("sm_after_edit_cell",[rowid, celname, value, iRow, iCol]);
                            },
                            gridComplete: function() {

                                //Code for changing the tree grid icons
                                $('.ui-icon.ui-icon-radio-off.tree-leaf.treeclick').replaceWith('<div style="margin-left: 20px;height: 18px;width: 18px;color: #469BDD;font-size: 1em;" class="">•••</div>');

                                if ( sm.dashboard_model[sm.dashboard_key].treegrid === true ) {
                                    $('#cb_sm_editor_grid').css('margin-right','8px');
                                } else {
                                    $('#cb_sm_editor_grid').css('margin-right','0px');
                                }

                                $('#sm_pagging_bar').hide();
                                var records_view = $('#sm_pagging_bar_right').find('.ui-paging-info').html();

                                var records_view_html = '<div id="sm_records_view" style="float:right;color:#3892D3;margin-right:3.3em;font-weight:bold;font-style:italic;">'+records_view+'</div>';

                                $('#sm_records_view').remove(); //Code for refreshing the sm_records_view
                                $("#gbox_sm_editor_grid").after(records_view_html);


                                $("#gbox_sm_editor_grid").find('input[type=checkbox]').each(function() {
                                    $(this).live('change',function(){

                                        if ( $(this).attr('id') == 'cb_sm_editor_grid' ) {
                                            if( $(this).is(':checked')) {
                                                setTimeout(function() {
                                                    $('input[id^=jqg_sm_editor_grid_]').each(function() {
                                                        $(this).parents('tr:last').removeClass('ui-state-highlight').addClass('selected-row').addClass('ui-state-hover').css({"color":"#444","font-weight":"400"});
                                                    });
                                                });
                                            } else {
                                                setTimeout(function() {
                                                    $('input[id^=jqg_sm_editor_grid_]').each(function() {
                                                        $(this).parents('tr:last').removeClass('selected-row').removeClass('ui-state-hover');
                                                    });
                                                });
                                            }
                                            
                                            return;
                                        }

                                        var colid = $(this).parents('tr:last').attr('id');

                                        $("#sm_editor_grid").jqGrid('setSelection', colid );

                                        if( $(this).is(':checked')) {
                                           $(this).prop('checked',true);
                                           setTimeout(function() {
                                                $('#'+colid).removeClass('ui-state-highlight').addClass('selected-row').addClass('ui-state-hover').css({"color":"#444","font-weight":"400"}); 
                                           },10);
                                        } else {                                            
                                           $(this).prop('checked',false);
                                           setTimeout(function() {
                                                $('#'+colid).removeClass('selected-row').removeClass('ui-state-hover');
                                           },10);
                                        }
                                        return true;
                                    });
                                });


                                $(document).trigger("sm_grid_complete");

                            },
                            // recordpos: 'left', // for position of the view records label ... left, center, right
                            // footerrow:true, // for insertng blnk row in footer
                            // toppager: true, // for having the same pager bar at top
                            // toolbar: [true,"top"],
                            // headertitles: true,
                            caption:" "
                      };

    //Code for adding tree-grid params
    if ( sm.dashboard_model[sm.dashboard_key].treegrid === true ) {
        jqgrid_params = $.extend(jqgrid_params, {
                                                    treeGrid: true,
                                                    treeGridModel: 'adjacency',
                                                    treedatatype: 'json',
                                                    ExpandColumn: 'tree_grid_col',
                                                });
    }
    $("#sm_editor_grid").jqGrid(jqgrid_params);
}

//Code for handling cell click for multiselect
$(document).on('sm_on_cell_click',function(e,rowid, celname, value, iRow, iCol){
    var columns = sm.dashboard_model[sm.dashboard_key].columns,
        multiselect_edit_html = '',
        current_value = '',
        actual_value = '',
        grid_rowid = rowid;

    multiselect_chkbox_list = '';

    if (value != '') {
        current_value = value.split('<br>');
    }

    for (var i in columns) {

        if (columns[i].hasOwnProperty('name') === false) continue;

        if (columns[i].name == celname) {

            if (columns[i].hasOwnProperty('type') !== false && columns[i].type == 'longstring') {

                //Code for unformatting the 'longstring' type values
                var unformatted_val = $('#'+sm_grid_nm).find('#'+rowid).find('[aria-describedby="sm_editor_grid_'+celname+'"]').find('#sm_formatter').html();

                if ( sm_last_edited_row_id != rowid || sm_last_edited_col != iCol ) {

                    // $('#sm_wp_editor').html(sm_wp_editor_html);
                    // sm_qtags_btn_init = 0;
                }

                $('#sm_wp_editor').find('.quicktags-toolbar').hide(); 

                if (unformatted_val != '') {
                    $('#sm_wp_editor').find('.wp-editor-area').text(unformatted_val);    
                } else {
                    $('#sm_wp_editor').find('.wp-editor-area').text('');
                }

                if ( $('#sm_wp_editor').find('#sm_inline_wp_editor_ifr').length != 0 ) {
                    $('#sm_wp_editor').find('#sm_inline_wp_editor_ifr').contents().find('body').html(unformatted_val);
                }

                var wp_editor_html = $('#sm_wp_editor').html();

                if ( sm_last_edited_row_id == '' && sm_last_edited_col == '' ) {
                   wp_editor_html += '<span id="edit_attributes_toolbar">'+
                                        '<button type="button" id="inline_edit_longstring_ok" class="button button-primary" style="float:right;">OK</button>'+
                                    '</span>';
                }

                    
                inline_edit_dlg(wp_editor_html, column_names[iCol-1],500,300,columns[i]);

                sm_last_edited_row_id = rowid;
                sm_last_edited_col = iCol;


                if ( $('#sm_wp_editor').find('#sm_inline_wp_editor_ifr').length != 0 ) {
                    $('#sm_inline_wp_editor-tmce').on('click',function() {
                        $('#sm_editor_grid_inlinecnt').find('#mceu_36').show();
                    });

                    $('#sm_inline_wp_editor-html').on('click',function() {
                        $('#sm_editor_grid_inlinecnt').find('#mceu_36').hide();
                    });

                    $('#sm_editor_grid_inlinecnt').find('#sm_inline_wp_editor_ifr').contents().find('head').html( $('#sm_wp_editor').find('#sm_inline_wp_editor_ifr').contents().find('head').html() );
                    $('#sm_editor_grid_inlinecnt').find('#sm_inline_wp_editor_ifr').contents().find('body').html( $('#sm_wp_editor').find('#sm_inline_wp_editor_ifr').contents().find('body').html() );
                }

                tinyMCE.init({ id : tinyMCEPreInit.mceInit[ 'sm_inline_wp_editor' ]});
                quicktags({id : 'sm_inline_wp_editor'});
                QTags._buttonsInit();
                sm_qtags_btn_init = 1;
                
                $(document).on("sm_inline_edit_dlg_hide", function(e,edited_col) {
                    
                    if (edited_col.hasOwnProperty('type') !== false && edited_col.type == 'longstring')
                        return;
                    
                    $('#sm_wp_editor').html( $('#sm_editor_grid_inlinecnt').html() );
                    $('#sm_wp_editor').find('#sm_inline_wp_editor_ifr').contents().find('head').html( $('#sm_editor_grid_inlinecnt').find('#sm_inline_wp_editor_ifr').contents().find('head').html() );
                    $('#sm_wp_editor').find('#sm_inline_wp_editor_ifr').contents().find('body').html( $('#sm_editor_grid_inlinecnt').find('#sm_inline_wp_editor_ifr').contents().find('body').html() );
                });
                
                //Code for click event of 'ok' btn
                $("#inline_edit_longstring_ok").on('click',function(){

                    var edit_val,
                        longstring_col_index = '',
                        columns = sm.dashboard_model[sm.dashboard_key].columns;

                    for (var i in columns) {
                        if (columns[i].name == celname) {
                            longstring_col_index = columns[i].index;
                        }
                    }

                    tinyMCE.triggerSave();
                    
                    var rowData = $('#sm_editor_grid').jqGrid('getRowData', grid_rowid);

                    // rowData[longstring_col_index] = '<div id="sm_formatter" style="max-height: 20px"> ' + jQuery('#sm_inline_wp_editor').val() + ' </div>';
                    rowData[longstring_col_index] = jQuery('#sm_inline_wp_editor').val();

                    $('#sm_editor_grid').jqGrid('smsetCell',grid_rowid, longstring_col_index, '', 'sm-jqgrid-dirty-cell', false, true, true);
                    $('#sm_editor_grid').jqGrid('setRowData', grid_rowid, rowData);

                    hideDialog();

                });

                return;
            }

            if (columns[i].hasOwnProperty('type') === false || columns[i].type != 'multilist' || columns[i].hasOwnProperty('values') === false) return;

            actual_value = columns[i].values;
            var multiselect_data = [];

            for (var index in actual_value) {
                if (actual_value[index].parent == "0") {
                    multiselect_data[index] = {'term' : actual_value[index].term};
                } else {
                    if (multiselect_data[actual_value[index].parent].hasOwnProperty('child') === false) {
                        multiselect_data[actual_value[index].parent].child = {};
                    }
                    multiselect_data[actual_value[index].parent].child[index] = actual_value[index].term;
                }
            }

            multiselect_chkbox_list += '<ul>';

            for (var index in multiselect_data) {

                var checked = '';

                if (current_value != '' && current_value.indexOf(multiselect_data[index].term) != -1) {
                    checked = 'checked';                        
                } 

                multiselect_chkbox_list += '<li> <input type="checkbox" name="chk_multiselect" value="'+ index +'" '+ checked +'>  '+ multiselect_data[index].term +'</li>';
                
                if (multiselect_data[index].hasOwnProperty('child') === false) continue;

                var child_val = multiselect_data[index].child;
                multiselect_chkbox_list += '<ul class="children">';

                for (var child_id in child_val) {

                    var child_checked = '';

                    if (current_value != '' && current_value.indexOf(child_val[child_id]) != -1) {
                        child_checked = 'checked';                        
                    } 

                    multiselect_chkbox_list += '<li> <input type="checkbox" name="chk_multiselect" value="'+ child_id +'" '+ child_checked +'>  '+ child_val[child_id] +'</li>';
                }
                multiselect_chkbox_list += '</ul>';
            }               

            multiselect_chkbox_list += '</ul>';
        }
    }

    // multiselect_chkbox_list = $(document).trigger("oncell_multiselect_click",[actual_value, current_value, multiselect_chkbox_list ,dashboard_model]);
    $(document).trigger("oncell_multiselect_click",[multiselect_chkbox_list]);

    multiselect_edit_html = '<div id="edit_product_attributes">'+ multiselect_chkbox_list + 
                            '<span id="edit_attributes_toolbar">'+
                            '<button type="button" id="inline_edit_multiselect_ok" class="button button-primary">OK</button>'+
                            '</span> </div>';

    //Code for creating the edit dialog for multiselect columns
    inline_edit_dlg(multiselect_edit_html, column_names[iCol-1]);

    //Code for click event of 'ok' btn
    $("#inline_edit_multiselect_ok").on('click',function(){

            var mutiselect_edited_text = '',
                mutiselect_col_val = '',
                mutiselect_col_index = '',
                columns = sm.dashboard_model[sm.dashboard_key].columns;

            for (var i in columns) {
                if (columns[i].name == celname) {
                    mutiselect_col_val = columns[i].values;
                    mutiselect_col_index = columns[i].index;
                }
            }

            selected_val = $("input[name='chk_multiselect']:checked" ).map(function () {
                                    return $(this).val();
                                }).get();

            for (var index in mutiselect_col_val) {
                if (selected_val.indexOf(index) != -1) {
                    if (mutiselect_edited_text != '') {
                        mutiselect_edited_text += '<br>';
                    }
                    mutiselect_edited_text += mutiselect_col_val[index]['term'];
                }
            }

            var rowData = $('#sm_editor_grid').jqGrid('getRowData', grid_rowid);

            rowData[mutiselect_col_index] = mutiselect_edited_text;

            $('#sm_editor_grid').jqGrid('smsetCell',grid_rowid, mutiselect_col_index, '', 'sm-jqgrid-dirty-cell', false, true, true);
            $('#sm_editor_grid').jqGrid('setRowData', grid_rowid, rowData);

            hideDialog();

    });


});

//Code for adding custom functions for the jqgrid
var jqgrid_custom_func = function() {

    $.jgrid.extend({
        smsetCell : function(rowid,colname,nData,cssp,attrp, forceupd,removeClass) {
            return this.each(function(){
                var $t = this, pos =-1,v, title;
                if(!$t.grid) {return;}
                if(isNaN(colname)) {
                    $($t.p.colModel).each(function(i){
                        if (this.name === colname) {
                            pos = i;return false;
                        }
                    });
                } else {pos = parseInt(colname,10);}
                if(pos>=0) {
                    var ind = $($t).jqGrid('getGridRowById', rowid); 
                    if (ind){
                        var tcell = $("td:eq("+pos+")",ind);
                        if(nData !== "" || forceupd === true) {
                            v = $t.formatter(rowid, nData, pos,ind,'edit');

                            title = $t.p.colModel[pos].title ? {"title":$.jgrid.stripHtml(v)} : {};

                            if($t.p.treeGrid && $(".tree-wrap",$(tcell)).length>0) {
                                $("span",$(tcell)).html(v).attr(title);
                            } else {
                                $(tcell).html(v).attr(title);
                            }
                            if($t.p.datatype === "local") {
                                var cm = $t.p.colModel[pos], index;
                                nData = cm.formatter && typeof cm.formatter === 'string' && cm.formatter === 'date' ? $.unformat.date.call($t,nData,cm) : nData;
                                index = $t.p._index[$.jgrid.stripPref($t.p.idPrefix, rowid)];
                                if(index !== undefined) {
                                    $t.p.data[index][cm.name] = nData;
                                }
                            }
                        }
                        if(typeof cssp === 'string'){
                            if (removeClass === true) {
                                $(tcell).removeClass('edit-cell ui-state-highlight').addClass(cssp);
                            } else {
                                $(tcell).addClass(cssp);
                            }
                            
                        } else if(cssp) {
                            $(tcell).css(cssp);
                        }

                        $(tcell).parent().addClass('edited'); // for adding class to the td element
                        $('#save_sm_editor_grid').removeClass('ui-state-disabled');

                        if(typeof attrp === 'object') {$(tcell).attr(attrp);}
                    }
                }
            });
        },

        // columnChooser : function(opts) {
        //     var self = this;
        //     if($("#colchooser_"+$.jgrid.jqID(self[0].p.id)).length ) { return; }
        //     var selector = $('<div id="colchooser_'+self[0].p.id+'" style="position:relative;overflow:hidden"><div><select multiple="multiple"></select></div></div>');
        //     var select = $('select', selector);

        //     function insert(perm,i,v) {
        //         if(i>=0){
        //             var a = perm.slice();
        //             var b = a.splice(i,Math.max(perm.length-i,i));
        //             if(i>perm.length) { i = perm.length; }
        //             a[i] = v;
        //             return a.concat(b);
        //         }
        //     }
        //     opts = $.extend({
        //         "width" : 420,
        //         "height" : 240,
        //         "classname" : null,
        //         "done" : function(perm) { if (perm) { self.jqGrid("remapColumns", perm, true); } },
        //         /* msel is either the name of a ui widget class that
        //            extends a multiselect, or a function that supports
        //            creating a multiselect object (with no argument,
        //            or when passed an object), and destroying it (when
        //            passed the string "destroy"). */
        //         "msel" : "multiselect",
        //         /* "msel_opts" : {}, */

        //         /* dlog is either the name of a ui widget class that 
        //            behaves in a dialog-like way, or a function, that
        //            supports creating a dialog (when passed dlog_opts)
        //            or destroying a dialog (when passed the string
        //            "destroy")
        //            */
        //         "dlog" : "dialog",

        //         /* dlog_opts is either an option object to be passed 
        //            to "dlog", or (more likely) a function that creates
        //            the options object.
        //            The default produces a suitable options object for
        //            ui.dialog */
        //         "dlog_opts" : function(opts) {
        //             var buttons = {};
        //             buttons[opts.bSubmit] = function() {
        //                 opts.apply_perm();
        //                 opts.cleanup(false);
        //             };
        //             buttons[opts.bCancel] = function() {
        //                 opts.cleanup(true);
        //             };
        //             return $.extend(true, {
        //                 "buttons": buttons,
        //                 "close": function() {
        //                     opts.cleanup(true);
        //                 },
        //                 "modal" : opts.modal ? opts.modal : false,
        //                 "resizable": opts.resizable ? opts.resizable : true,
        //                 "width": opts.width+20,
        //                 resize: function (e, ui) {
        //                     var $container = $(this).find('>div>div.ui-multiselect'),
        //                         containerWidth = $container.width(),
        //                         containerHeight = $container.height(),
        //                         $selectedContainer = $container.find('>div.selected'),
        //                         $availableContainer = $container.find('>div.available'),
        //                         $selectedActions = $selectedContainer.find('>div.actions'),
        //                         $availableActions = $availableContainer.find('>div.actions'),
        //                         $selectedList = $selectedContainer.find('>ul.connected-list'),
        //                         $availableList = $availableContainer.find('>ul.connected-list'),
        //                         dividerLocation = opts.msel_opts.dividerLocation || $.ui.multiselect.defaults.dividerLocation;

        //                     $container.width(containerWidth); // to fix width like 398.96px                     
        //                     $availableContainer.width(Math.floor(containerWidth*(1-dividerLocation)));
        //                     $selectedContainer.width(containerWidth - $availableContainer.outerWidth() - ($.browser.webkit ? 1: 0));

        //                     $availableContainer.height(containerHeight);
        //                     $selectedContainer.height(containerHeight);
        //                     $selectedList.height(Math.max(containerHeight-$selectedActions.outerHeight()-1,1));
        //                     $availableList.height(Math.max(containerHeight-$availableActions.outerHeight()-1,1));
        //                 }
        //             }, opts.dialog_opts || {});
        //         },
        //         /* Function to get the permutation array, and pass it to the
        //            "done" function */
        //         "apply_perm" : function() {
        //             $('option',select).each(function(i) {
        //                 if (this.selected) {
        //                     self.jqGrid("showCol", colModel[this.value].name);
        //                 } else {
        //                     self.jqGrid("hideCol", colModel[this.value].name);
        //                 }
        //             });

        //             var perm = [];
        //             //fixedCols.slice(0);
        //             $('option:selected',select).each(function() { perm.push(parseInt(this.value,10)); });
        //             $.each(perm, function() { delete colMap[colModel[parseInt(this,10)].name]; });
        //             $.each(colMap, function() {
        //                 var ti = parseInt(this,10);
        //                 perm = insert(perm,ti,ti);
        //             });
        //             if (opts.done) {
        //                 opts.done.call(self, perm);
        //             }
        //         },
        //         /* Function to cleanup the dialog, and select. Also calls the
        //            done function with no permutation (to indicate that the
        //            columnChooser was aborted */
        //         "cleanup" : function(calldone) {
        //             call(opts.dlog, selector, 'destroy');
        //             call(opts.msel, select, 'destroy');
        //             selector.remove();
        //             if (calldone && opts.done) {
        //                 opts.done.call(self);
        //             }
        //         },
        //         "msel_opts" : {}
        //     }, $.jgrid.col, opts || {});
        //     if($.ui) {
        //         if ($.ui.multiselect ) {
        //             if(opts.msel == "multiselect") {
        //                 if(!$.jgrid._multiselect) {
        //                     // should be in language file
        //                     alert("Multiselect plugin loaded after jqGrid. Please load the plugin before the jqGrid!");
        //                     return;
        //                 }
        //                 opts.msel_opts = $.extend($.ui.multiselect.defaults,opts.msel_opts);
        //             }
        //         }
        //     }
        //     if (opts.caption) {
        //         selector.attr("title", opts.caption);
        //     }
        //     if (opts.classname) {
        //         selector.addClass(opts.classname);
        //         select.addClass(opts.classname);
        //     }
        //     if (opts.width) {
        //         $(">div",selector).css({"width": opts.width,"margin":"0 auto"});
        //         select.css("width", opts.width);
        //     }
        //     if (opts.height) {
        //         $(">div",selector).css("height", opts.height);
        //         select.css("height", opts.height - 10);
        //     }
        //     var colModel = self.jqGrid("getGridParam", "colModel");
        //     var colNames = self.jqGrid("getGridParam", "colNames");
        //     var colMap = {}, fixedCols = [];

        //     select.empty();
        //     $.each(colModel, function(i) {
        //         colMap[this.name] = i;
        //         if (this.hidedlg) {
        //             if (!this.hidden) {
        //                 fixedCols.push(i);
        //             }
        //             return;
        //         }

        //         select.append("<option value='"+i+"' "+
        //                       (this.hidden?"":"selected='selected'")+">"+colNames[i]+"</option>");
        //     });
        //     function call(fn, obj) {
        //         if (!fn) { return; }
        //         if (typeof fn == 'string') {
        //             if ($.fn[fn]) {
        //                 $.fn[fn].apply(obj, $.makeArray(arguments).slice(2));
        //             }
        //         } else if ($.isFunction(fn)) {
        //             fn.apply(obj, $.makeArray(arguments).slice(2));
        //         }
        //     }

        //     var dopts = $.isFunction(opts.dlog_opts) ? opts.dlog_opts.call(self, opts) : opts.dlog_opts;
        //     call(opts.dlog, selector, dopts);
        //     var mopts = $.isFunction(opts.msel_opts) ? opts.msel_opts.call(self, opts) : opts.msel_opts;
        //     call(opts.msel, select, mopts);
        //     // fix height of elements of the multiselect widget
        //     var resizeSel = "#colchooser_"+$.jgrid.jqID(self[0].p.id),
        //         $container = $(resizeSel + '>div>div.ui-multiselect'),
        //         $selectedContainer = $(resizeSel + '>div>div.ui-multiselect>div.selected'),
        //         $availableContainer = $(resizeSel + '>div>div.ui-multiselect>div.available'),
        //         containerHeight,
        //         $selectedActions = $selectedContainer.find('>div.actions'),
        //         $availableActions = $availableContainer.find('>div.actions'),
        //         $selectedList = $selectedContainer.find('>ul.connected-list'),
        //         $availableList = $availableContainer.find('>ul.connected-list');
        //     $container.height($container.parent().height()); // increase the container height
        //     containerHeight = $container.height();
        //     $selectedContainer.height(containerHeight);
        //     $availableContainer.height(containerHeight);
        //     $selectedList.height(Math.max(containerHeight-$selectedActions.outerHeight()-1,1));
        //     $availableList.height(Math.max(containerHeight-$availableActions.outerHeight()-1,1));
        //     // extend the list of components which will be also-resized
        //     selector.data('dialog').uiDialog.resizable("option", "alsoResize",
        //         resizeSel + ',' + resizeSel +'>div' + ',' + resizeSel + '>div>div.ui-multiselect');
        // }
    });
}
