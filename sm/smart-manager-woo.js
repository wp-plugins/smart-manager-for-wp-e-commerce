// Floating notification start
Ext.notification = function(){
    var msgCt;
    function createBox(t, s){
        return ['<div class="msg">',
                '<div class="x-box-tl"><div class="x-box-tr"><div class="x-box-tc"></div></div></div>',
                '<div class="x-box-ml"><div class="x-box-mr"><div class="x-box-mc"><h3>', t, '</h3>', s, '</div></div></div>',
                '<div class="x-box-bl"><div class="x-box-br"><div class="x-box-bc"></div></div></div>',
                '</div>'].join('');
    }
    return {
        msg : function(title, format){
            try{
	        	if(!msgCt){
	                msgCt = Ext.DomHelper.insertFirst(document.body, {id:'msg-div'}, true);
	            }
	            msgCt.alignTo(document, 't-t');
	            Ext.DomHelper.applyStyles(msgCt, 'left: 33%; top: 30px;');
	            var s = String.format.apply(String, Array.prototype.slice.call(arguments, 1));
	            var m = Ext.DomHelper.append(msgCt, {html:createBox(title, s)}, true);
	            m.slideIn('t').pause(2).ghost("t", {remove:true});
            }catch(e){
				return;
			}
        },

        init : function(){
            var lb = Ext.get('lib-bar');
            if(lb){
                lb.show();
            }
        }
    };
}();// Floating notification end

// global Variables and array declaration.
var	categories         = new Array(), //an array for category combobox in batchupdate window.
	attribute          = new Array(),
	cellClicked        = false,  	  //flag to check if any cell is clicked in the editor grid.
	search_timeout_id  = 0, 		  //timeout for sending request while searching.
	colModelTimeoutId  = 0, 		  //timeout to reconfigure the grid.
	limit 		   = 100,		  //per page records limit.
	editorGrid         = '',
	weightUnitStore    = '',
	showOrdersView     = '',
	countriesStore     = '';

Ext.onReady(function () {
	try{
		if(wpsc_woo != 1){
			//Stateful
			Ext.state.Manager.setProvider(new Ext.state.CookieProvider({
				expires: new Date(new Date().getTime()+(1000*60*60*24*30)) //30 days from now
			}));
		}
		
	// Tooltips
	Ext.QuickTips.init();
	Ext.apply(Ext.QuickTips.getQuickTip(), {
		maxWidth: 150,
		minWidth: 100,
		dismissDelay: 9999999,
		trackMouse: true
	});
	
	// Global object SM....declared in manager-console.php
	SM.searchTextField   = '';
	SM.dashboardComboBox = '';
        SM.duplicateButton   = '';
	SM.colModelTimeoutId = '';		
	SM.activeModule      = 'Products'; //default module selected.
	SM.activeRecord      = '';
	SM.curDataIndex      = '';
	SM.incVariation      = false;
	SM.typeColIndex 	 = '';
	
	var actions = new Array();
	
	//creating an array of actions to be used in the actions combobox in batch update window.
	actions['blob']   = [{'id': 0,'name': getText('set to'), 'value':'SET_TO'},
					     {'id': 1,'name': getText('append'),'value': 'APPEND'},
					     {'id': 2,'name': getText('prepend'),'value': 'PREPEND'}];

	actions['bigint'] = [{'id': 0,'name': getText('set to'),'value': 'SET_TO'}];

	actions['real']   = [{'id': 0,'name': getText('set to'),'value': 'SET_TO'},
					     {'id': 1,'name': getText('increase by %'),'value': 'INCREASE_BY_%'},
					     {'id': 1,'name': getText('decrease by %'),'value': 'DECREASE_BY_%'},
					     {'id': 2,'name': getText('increase by number'),'value': 'INCREASE_BY_NUMBER'},
					     {'id': 3,'name': getText('decrease by number'),'value': 'DECREASE_BY_NUMBER'}];

	actions['int']    = [{'id': 0,'name': getText('set to'),'value': 'SET_TO'},
					     {'id': 1,'name': getText('increase by number'),'value': 'INCREASE_BY_NUMBER'},
					     {'id': 2,'name': getText('decrease by number'),'value': 'DECREASE_BY_NUMBER'}];

	actions['float']  = [{'id': 0,'name': getText('set to'),'value': 'SET_TO'},
				         {'id': 1,'name': getText('increase by number'),'value': 'INCREASE_BY_NUMBER'},
				         {'id': 2,'name': getText('decrease by number'),'value': 'DECREASE_BY_NUMBER'}];

	actions['string'] = [{'id': 0,'name': getText('Yes'),'value': 'YES'},
						 {'id': 1,'name': getText('No'),'value': 'NO'}];

	actions['category_actions'] = [{'id': 0,'name': getText('set to'),'value': 'SET_TO'},
								   {'id': 1,'name': getText('add to'),'value': 'ADD_TO'},
								   {'id': 2,'name': getText('remove from'),'value': 'REMOVE_FROM'}];


    actions['modStrActions']   = [[ 0, getText('set to'), 'SET_TO'],
	                              [ 1, getText('append'), 'APPEND'],
	                              [ 2, getText('prepend'), 'PREPEND']];

	actions['setStrActions']   = [[ 0,getText('set to'), 'SET_TO']];

	actions['setAdDelActions'] = [[0, getText('set to'), 'SET_TO'],
	                              [1, getText('add to'), 'ADD_TO'],
	                              [2, getText('remove from'), 'REMOVE_FROM']];

	actions['modIntPercentActions']   = [[0, getText('set to'), 'SET_TO'],
	                                     [1, getText('increase by %'), 'INCREASE_BY_%'],
	                                     [2, getText('decrease by %'), 'DECREASE_BY_%'],
	                                     [3, getText('increase by number'),'INCREASE_BY_NUMBER'],
	                                     [4, getText('decrease by number'), 'DECREASE_BY_NUMBER']];

	actions['modIntActions']   		  = [[0, getText('set to'), 'SET_TO'],
	                              		 [1, getText('increase by number'),'INCREASE_BY_NUMBER'],
	                              		 [2, getText('decrease by number'), 'DECREASE_BY_NUMBER']];

	actions['YesNoActions']   		  = [[0,getText('Yes'),'YES'],
	                             		 [1,getText('No'),'NO']];

	actions['category_actions'] 	  = [[0, getText('set to'),'SET_TO'],
								   		 [1,getText('add to'),'ADD_TO'],
								   		 [2,getText('remove from'),'REMOVE_FROM']];
	
	//fm used as a short form for Ext.form
	var fm 		     = Ext.form,
		toolbarCount =  1,
		cnt 		 = -1,    //for checkboxSelectionModel.
		cnt_array 	 = [];	 //for checkboxSelectionModel.
	
	//Regex to allow only numbers.
	var objRegExp = /(^-?\d\d*\.\d*$)|(^-?\d\d*$)|(^-?\.\d\d*$)/;
	var regexError = getText('Only numbers are allowed'); 
		
	//number format in which the amounts in the grid will be displayed.
	var amountRenderer = Ext.util.Format.numberRenderer('0,0.00'),
		
		//setting Date fields.
		fromDateTxt    = new Ext.form.TextField({emptyText:'From Date',readOnly: true,width: 80, id:'fromDateTxtId'}),
		toDateTxt      = new Ext.form.TextField({emptyText:'To Date',readOnly: true,width: 80, id:'toDateTxtId'}),
		now            = new Date(),
		initDate       = new Date(0),
		lastMonDate    = new Date(now.getFullYear(), now.getMonth()-1, now.getDate()+1);
	
	fromDateTxt.setValue(lastMonDate.format('M j Y'));
	toDateTxt.setValue(now.format('M j Y'));
	
	//CheckBoxes for EditorGrid Panel for selecting rows.
	var editorGridSelectionModel = new Ext.grid.CheckboxSelectionModel({
		checkOnly: true,
		listeners: {
			selectionchange: function (sm) {
				if (sm.getCount()) {					
					pagingToolbar.batchButton.enable();
					
                                        editorGrid.getTopToolbar().get('duplicateButton').enable();
                                        
					if(pagingToolbar.hasOwnProperty('deleteButton'))
					pagingToolbar.deleteButton.enable();
					
					if(pagingToolbar.hasOwnProperty('printButton'))
					pagingToolbar.printButton.enable();
				} else {					
					pagingToolbar.batchButton.disable();
					
                                        editorGrid.getTopToolbar().get('duplicateButton').disable();
                                        
					if(pagingToolbar.hasOwnProperty('deleteButton'))
					pagingToolbar.deleteButton.disable();
					
					if(pagingToolbar.hasOwnProperty('printButton'))
					pagingToolbar.printButton.disable();
				}
			}
		}
	});

	//save the columns state (size, visibility..) of all the three Dashboard
	var storeColState = function(){
		var editorGridStateId = editorGrid.getStateId();
		var state = Ext.state.Manager.get(editorGridStateId);

		if(state != undefined){
			state = editorGrid.getState();
			Ext.state.Manager.set(editorGridStateId,state);
		}
	};	
	
	//Function to escape white space characters	in customJsonReader
	String.prototype.trim = function() {
		return this.replace(/^\s+|\s+$/g,"");
	}
	String.prototype.ltrim = function() {
		return this.replace(/^\s+/g,"");
	}
	String.prototype.rtrim = function() {
		return this.replace(/\s+$/g,"");
	}

	// To escape new line characters.
	SM.escapeCharacters = function(result){
		// The "g" at the end of the regex statement signifies that the replacement should take place more than once (g).
		patternF = /\f/g;
		patternN = /\n/g;
		patternR = /\r/g;
		patternT = /\t/g;
		return result = result.replace(patternF,'\\f').replace(patternN,'\\n').replace(patternR,'\\r').replace(patternT,'\\t');
	};
	
	//creates new 'Add Product' Button & a vertical Separator and is added to the pagingtoolbar.
	var showAddProductButton = function(){
		if(typeof pagingToolbar.addProductButton == 'undefined' && typeof Ext.getCmp('addProductSeparator') == 'undefined'){
			var addProductSeparator = new Ext.Toolbar.Separator({
				id: 'addProductSeparator'
			});

			var addProductButton = new Ext.Button({
				text      : getText('Add product'),
				tooltip   : getText('Add a new product'),
				icon      : imgURL + 'add.png',
				disabled  : true,
				hidden    : false,
				id 	 	  : 'addProductButton',
				ref 	  : 'addProductButton',
				listeners : {
					click : function() {
						productsColumnModel.getColumnById('publish').editor = newProductStatusCombo;
                        productsColumnModel.getColumnById('visibility').editor = visibilityCombo;
                        productsColumnModel.getColumnById('taxStatus').editor = taxStatusCombo;
						if(fileExists == 1){
							addProduct(productsStore, cnt_array, cnt, newCatName);
						}else{
							Ext.notification.msg('Smart Manager', getText('Add product feature is available only in Pro version')); 
						}
					}
				}
			});
			pagingToolbar.add(addProductSeparator);
			pagingToolbar.add(addProductButton);
		}
		if(fileExists == 1){
			pagingToolbar.addProductButton.enable();
		}
	};

	// removed 'Add Product' Button & the vertical Separator from the pagingtoolbar.
	var hideAddProductButton = function(){
		if(typeof pagingToolbar.addProductButton != 'undefined' && typeof Ext.getCmp('addProductSeparator') != 'undefined'){
			pagingToolbar.remove(pagingToolbar.addProductButton);
			pagingToolbar.remove(Ext.getCmp('addProductSeparator'));
		}
	};

	//creates new 'Print' Button & a vertical Separator and is added to the pagingtoolbar.
	var showPrintButton = function(){
		if(typeof pagingToolbar.printButton == 'undefined' && typeof Ext.getCmp('printSeparator') == 'undefined'){
			var printSeparator = new Ext.Toolbar.Separator({
				id: 'printSeparator'
			});

			var printButton = new Ext.Button({
				text: getText('Print'),
				tooltip: getText('Print Packing Slips'),
				disabled: true,
				ref: 'printButton',
				id: 'printButton',
				icon: imgURL + 'print.png',
				scope: this,
				listeners: {
					click: function () {
						if(fileExists == 1){
							showPrintWindow(editorGrid);
						}else{
							Ext.notification.msg('Smart Manager', getText('Print Preview feature is available only in Pro version') );
						}
					}
				}
			});

			pagingToolbar.add(printSeparator);
			pagingToolbar.add(printButton);
		}
	};

	//removed 'Print' Button & the vertical Separator from the pagingtoolbar.
	var hidePrintButton = function(){
		if(typeof pagingToolbar.printButton != 'undefined' && typeof Ext.getCmp('printSeparator') != 'undefined'){
			pagingToolbar.remove(Ext.getCmp('printSeparator'));
			pagingToolbar.remove(pagingToolbar.printButton);
		}
	};
	
	var showDeleteButton = function(){
		if(typeof pagingToolbar.deleteButton == 'undefined' && typeof Ext.getCmp('deleteSeparator') == 'undefined'){
			var deleteSeparator = new Ext.Toolbar.Separator({
				id: 'deleteSeparator'
			});

			var deleteButton = new Ext.Button({
				text: getText('Delete'),
				tooltip: getText('Delete the selected items'),
				disabled: true,
				ref: 'deleteButton',
				id: 'deleteButton',
				icon: imgURL + 'delete.png',
				scope: this,
				listeners: { click: function () { deleteRecords(); }}
			});

			pagingToolbar.add(deleteSeparator);
			pagingToolbar.add(deleteButton);
		}
	}
	
	//remove 'Delete' Button & its vertical Separator from the pagingtoolbar.
	var hideDeleteButton = function(){
		if(typeof pagingToolbar.deleteButton != 'undefined' && typeof Ext.getCmp('deleteSeparator') != 'undefined'){
			pagingToolbar.remove(Ext.getCmp('deleteSeparator'));
			pagingToolbar.remove(pagingToolbar.deleteButton);
		}
	};
	
	/* ====================== Products ==================== */
	
	//Renderer for dimension units
	Ext.util.Format.comboRenderer = function(productStatusCombo){
        return function(value){
			var record = productStatusCombo.findRecord(productStatusCombo.valueField, value);
			return record ? record.get(productStatusCombo.displayField) : productStatusCombo.valueNotFoundText;
		}
	}
	
	function formatDate(value){
        return value ? value.dateFormat('M d, Y') : '';
    }
	
	//combo box consisting of yes and no values.
	var yesNoCombo = new Ext.form.ComboBox({
		typeAhead: true,
		triggerAction: 'all',
		lazyRender:true,
		editable: false,
		mode: 'local',
		store: new Ext.data.ArrayStore({
			id: 0,
			fields: ['value','name'],
			data: [[1, 'Yes'], [0, 'No']]
		}),
		valueField: 'value',
		displayField: 'name'
	});	
	
	// product status combo box
	var productStatusCombo = new Ext.form.ComboBox({
		typeAhead: true,
		id: 'productStatusCombo',
		triggerAction: 'all',
		lazyRender:true,
		editable: false,
		mode: 'local',		
		store: new Ext.data.ArrayStore({
			id: 0,
			fields: ['value','name'],
			data: [['publish', 'Published'], ['draft', 'Draft'],['inherit', 'Inherit']]
		}),
		valueField: 'value',
		displayField: 'name'
	});
	
	// product status combo box when new record is added to grid
	var newProductStatusCombo = new Ext.form.ComboBox({
		typeAhead: true,
		id: 'newProductStatusCombo',
		triggerAction: 'all',
		lazyRender:true,
		editable: false,
		mode: 'local',
		store: new Ext.data.ArrayStore({
			id: 0,
			fields: ['value','name'],
			data: [['publish', 'Published'], ['draft', 'Draft']]			
		}),
		valueField: 'value',
		displayField: 'name'
	});

    // Visibility combo box
    var visibilityCombo = new Ext.form.ComboBox({
        typeAhead: true,
        id: 'visibilityCombo',
        triggerAction: 'all',
        lazyRender:true,
        editable: false,
        mode: 'local',
        store: new Ext.data.ArrayStore({
            id: 0,
            fields: ['value','name'],
            data: [
                    ['visible', 'Catalog & Search'],
                    ['catalog', 'Catalog'],
                    ['search', 'Search'],
                    ['hidden', 'Hidden']
                  ]
        }),
        valueField: 'value',
        displayField: 'name'
    });

	// Visibility combo box
    var taxStatusCombo = new Ext.form.ComboBox({
        typeAhead: true,
        id: 'taxStatusCombo',
        triggerAction: 'all',
        lazyRender:true,
        editable: false,
        mode: 'local',
        store: new Ext.data.ArrayStore({
            id: 0,
            fields: ['value','name'],
            data: [
                    [ 'taxable', getText('Taxable') ],
                    [ 'shipping', getText('Shipping only') ],
                    [ 'none', getText('None') ]
                  ]
        }),
        valueField: 'value',
        displayField: 'name'
    });

	var productsColumnModel = new Ext.grid.ColumnModel({
		columns: [editorGridSelectionModel,
		{
			header: '',
			id: 'type',
			dataIndex: SM.productsCols.post_parent.colName,
			tooltip: getText('Type'),
			width: 20,
			hidden: true,
			renderer: function (value, metaData, record, rowIndex, colIndex, store) {
				return (value == 0 ? '<img id=editUrl src="' + imgURL + 'fav.gif"/>' : '');
			}
		},
		{
			header: SM.productsCols.image.name,
			id: 'image',
			dataIndex: SM.productsCols.image.colName,
			tooltip: getText('Product Images'),
			width: 20,
			hidden: true,
			renderer: function (value, metaData, record, rowIndex, colIndex, store) {
				return (record.data.thumbnail != 'false' ? '<img width=16px height=16px src="' + record.data.thumbnail + '"/>' : '');
			}
		},
		{
			header: SM.productsCols.name.name,
			id: 'name',
			sortable: true,
			dataIndex: SM.productsCols.name.colName,
			tooltip: getText('Product Name'),
			width: 300,
			editor: new fm.TextField({
				allowBlank: false
			})
		},
		{
			header: SM.productsCols.price.name,
			id: 'price',
			align: 'right',
			sortable: true,
			dataIndex: SM.productsCols.price.colName,
			tooltip: getText('Price'),
			renderer: amountRenderer,
			editor: new fm.NumberField({
				allowBlank: true,
				allowNegative: true
			})
		},{
			header: SM.productsCols.salePrice.name,
			id: 'salePrice',
			sortable: true,
			align: 'right',
			dataIndex: SM.productsCols.salePrice.colName,
			renderer: amountRenderer,
			tooltip: getText('Sale Price'),
			editor: new fm.NumberField({
				allowBlank: true,
				allowNegative: true
			})
		},{
            header: SM.productsCols.salePriceFrom.name,
            id: 'salePriceFrom',
			sortable: true,
            hidden: true,
			tooltip: getText('Sale Price From'),
            dataIndex: SM.productsCols.salePriceFrom.colName,
            renderer: formatDate,
            editor: new fm.DateField({
                format: 'm/d/y',
                editable: false,
                allowBlank: false,
				allowNegative: false
            })
        },{
            header: SM.productsCols.salePriceTo.name,
            id: 'salePriceTo',
			sortable: true,
            hidden: true,
            tooltip: getText('Sale Price To'),
            dataIndex: SM.productsCols.salePriceTo.colName,
            renderer: formatDate,
            editor: new fm.DateField({
                format: 'm/d/y',
                editable: false,
                allowBlank: false,
				allowNegative: false
            })
        },{
			header: SM.productsCols.inventory.name,
			id: 'inventory',
			sortable: true,
			align: 'right',
			dataIndex: SM.productsCols.inventory.colName,
			tooltip: getText('Inventory'),
			editor: new fm.NumberField({
				allowBlank: true,
				allowNegative: false
			})
		},{
			header: SM.productsCols.sku.name,
			id: 'sku',
			sortable: true,
			dataIndex: SM.productsCols.sku.colName,
			tooltip: getText('SKU'),
			editor: new fm.TextField({
				allowBlank: true
			})
		},{
			header: SM.productsCols.group.name,
			id: 'group',
			sortable: true,
			dataIndex: SM.productsCols.group.colName,
			tooltip: getText('Category')
		},{
			header: SM.productsCols.weight.name,
			id: 'weight',
			colSpan: 2,
			sortable: true,
			align: 'right',
			dataIndex: SM.productsCols.weight.colName,
			tooltip: getText('Weight'),
			renderer: amountRenderer,
			editor: new fm.NumberField({
				allowBlank: true,
				allowNegative: false
			})
		},{
			header: SM.productsCols.publish.name,
			id: 'publish',
			sortable: true,
			dataIndex: SM.productsCols.publish.colName,
			tooltip: getText('Product Status'),
			renderer: Ext.util.Format.comboRenderer(productStatusCombo)
		},{
			header: SM.productsCols.desc.name,
			id: 'desc',
			dataIndex: SM.productsCols.desc.colName,
			tooltip: getText('Description'),
			width: 180,
                        hidden: true,
			editor: new fm.TextArea({				
				autoHeight: true,
				grow: true,
				growMax: 10000
			})
		},{
			header: SM.productsCols.addDesc.name,
			id: 'addDesc',
			hidden: true,
			dataIndex: SM.productsCols.addDesc.colName,
			tooltip: getText('Additional Description'),
			width: 180,
			editor: new fm.TextArea({
				autoHeight: true,
				grow: true,
				growMax: 10000
			})
		},{
			header: SM.productsCols.height.name,
			id: 'height',
			hidden: true,
			colSpan: 2,
			sortable: true,
			align: 'right',
			dataIndex: SM.productsCols.height.colName,
			tooltip: getText('Height'),		
			renderer: amountRenderer,
			editor: new fm.NumberField({
				allowBlank: true,
				allowNegative: false
			})
		},{
			header: SM.productsCols.width.name,
			id: 'width',
			hidden: true,
			colSpan: 2,
			sortable: true,
			align: 'right',
			dataIndex: SM.productsCols.width.colName,
			tooltip: getText('Width'),
			renderer: amountRenderer,
			editor: new fm.NumberField({
				allowBlank: true,
				allowNegative: false
			})
		},{
			header: SM.productsCols.lengthCol.name,
			id: 'lengthCol',
			hidden: true,
			colSpan: 2,
			sortable: true,
			align: 'right',
			dataIndex: SM.productsCols.lengthCol.colName,
			tooltip: getText('Length'),		
			renderer: amountRenderer,
			editor: new fm.NumberField({
				allowBlank: true,
				allowNegative: false
			})
		},{
            header: SM.productsCols.visibility.name,
            id: 'visibility',
            sortable: true,
            hidden: true,
            dataIndex: SM.productsCols.visibility.colName,
            tooltip: getText('Product Visibility'),
            renderer: Ext.util.Format.comboRenderer(visibilityCombo)
        },{
			header: SM.productsCols.taxStatus.name,
			id: 'taxStatus',
			hidden: true,
			sortable: true,
			dataIndex: SM.productsCols.taxStatus.colName,
			tooltip: getText('Tax Status'),
            renderer: Ext.util.Format.comboRenderer(taxStatusCombo)
		},{
			header: getText('Edit'),
			id: 'edit',
			sortable: true,
			tooltip: getText('Product Info'),
			dataIndex: 'edit_url',
			width: 50,
			id: 'editLink',
			renderer: function (value, metaData, record, rowIndex, colIndex, store) {
                if(record.get('post_parent') == 0) {
                    return '<img id=editUrl src="' + imgURL + 'edit.gif"/>';
                }
			}
		}],
		listeners: {
			hiddenchange: function( ColumnModel,columnIndex, hidden ){
				storeColState();
			}
		},
		defaultSortable: true
	});	
			
	// created a custom jsonreader by extending JsonReader and overridding read function 
	// to escape invisible/white space characters from the responseText
	Ext.data.customJsonReader = Ext.extend(Ext.data.JsonReader,{
		read : function(response){
			var responseData = response.responseText;
				responseData = responseData.trim();

			var json = SM.escapeCharacters(responseData),
				   o = Ext.decode(json);
			if(!o) {
				throw {message: 'JsonReader.read: Json object not found'};
			}
			return this.readRecords(o);
		}
	});

	var productsJsonReader = new Ext.data.JsonReader({
		totalProperty: 'totalCount',
		root: 'items',
		fields: [
				{name: SM.productsCols.id.colName,                type: 'int'},
				{name: SM.productsCols.name.colName,              type: 'string'},
				{name: SM.productsCols.price.colName,             type: 'string'},
				{name: SM.productsCols.salePrice.colName,         type: 'string'},
				{name: SM.productsCols.salePriceFrom.colName,     type: 'date', dateFormat: 'Y-m-d'},
				{name: SM.productsCols.salePriceTo.colName,       type: 'date', dateFormat: 'Y-m-d'},
				{name: SM.productsCols.inventory.colName,         type: 'string'},
				{name: SM.productsCols.publish.colName,           type: 'string'},
				{name: SM.productsCols.sku.colName,               type: 'string'},
				{name: SM.productsCols.group.colName,             type: 'string'},
				{name: SM.productsCols.desc.colName,              type: 'string'},
				{name: SM.productsCols.addDesc.colName,           type: 'string'},
				{name: SM.productsCols.weight.colName,            type: 'float'},
				{name: SM.productsCols.height.colName,            type: 'float'},
				{name: SM.productsCols.width.colName,             type: 'float'},
				{name: SM.productsCols.lengthCol.colName,         type: 'float'},
				{name: SM.productsCols.post_parent.colName,	      type: 'int'},
				{name: SM.productsCols.image.colName,	      	  type: 'string'},
				{name: SM.productsCols.taxStatus.colName,	      type: 'string'},
                {name: SM.productsCols.visibility.colName,        type: 'string'}
                ]
		
	});	

	var productsStore = new Ext.data.Store({
		reader: productsJsonReader,
		proxy: new Ext.data.HttpProxy({
			url: jsonURL
		}),
		baseParams: {
			cmd: 'getData',
			active_module: SM.activeModule,
			start: 0,
			limit: limit,
			viewCols: Ext.encode(productsViewCols),
			incVariation: SM.incVariation
		},
		dirty: false,
		pruneModifiedRecords: true,
		listeners: {
			//Products Store onload function.
			load: function (store,records,obj) {
				cnt = -1;
				cnt_array = [];
				editorGridSelectionModel.clearSelections();
				pagingToolbar.saveButton.disable();
				productsColumnModel.getColumnById('publish').editor = productStatusCombo;
                productsColumnModel.getColumnById('visibility').editor = visibilityCombo;   // Tarun
                productsColumnModel.getColumnById('taxStatus').editor = taxStatusCombo;
			}
		}
	});

	var showProductsView = function(){
                batchUpdateWindow.loadMask.show();
                
		productsStore.baseParams.searchText = ''; //clear the baseParams for productsStore
		SM.searchTextField.reset(); 			  //to reset the searchTextField
		
		hidePrintButton();
		hideDeleteButton();
		showAddProductButton();
		showDeleteButton();
		pagingToolbar.doLayout(true,true);
		batchUpdateToolbar.items.items[2].show();		
		
		for(var i=2;i<=8;i++)
		editorGrid.getTopToolbar().get(i).hide();
		editorGrid.getTopToolbar().get('incVariation').show();
                editorGrid.getTopToolbar().get('duplicateButton').show();

		productsStore.load();
		pagingToolbar.bind(productsStore);

		editorGrid.reconfigure(productsStore,productsColumnModel);
		fieldsStore.loadData(productsFields);

		var firstToolbar       = batchUpdatePanel.items.items[0].items.items[0];
		var textfield          = firstToolbar.items.items[5];
		textfield.show();
	};

	/* ====================== Products ==================== */

	
//	==== common ====

var pagingToolbar = new Ext.PagingToolbar({
	id: 'pagingToolbar',
	items: ['->', {xtype:'tbseparator', id:'beforeBatchSeparator'},
	{
		text: getText('Batch Update'), 
		tooltip: getText('Update selected items'), 
		icon: imgURL + 'batch_update.png',
		id: 'batchUpdateButton',
		disabled: true,
		ref: 'batchButton',
		scope: this,
		listeners: { 
			click: function () { 
				if(SM.activeModule == 'Products') {
					var pageTotalRecord = editorGrid.getStore().getCount();		
					var selectedRecords=editorGridSelectionModel.getCount();
					if( selectedRecords >= pageTotalRecord){		
						batchRadioToolbar.setVisible(true);
					} else {	
						batchRadioToolbar.setVisible(false);						
					}
				} else {
					batchRadioToolbar.setVisible(false);
				}
				batchUpdateWindow.show();	
			}
		}
	},{xtype:'tbseparator', id:'beforeSaveSeparator'},{
		text: getText('Save'),
		tooltip: getText('Save all Changes'),
		icon: imgURL + 'save.png',
		disabled: true,
		scope: this,
		ref: 'saveButton',
		id: 'saveButton',
		listeners:{ click : function () {
			if(SM.activeModule == 'Orders')
			store = ordersStore;
			else if(SM.activeModule == 'Products')
			store = productsStore;
			else if(SM.activeModule == 'Customers')
			store = customersStore;
			saveRecords(store,pagingToolbar,jsonURL,editorGridSelectionModel);
		}}
	},{xtype:'tbseparator', id:'beforeExportSeparator'},
	{
		text: getText('Export CSV'),
		tooltip: getText('Download CSV file'),
		icon: imgURL + 'export_csv.gif',
		id: 'exportCsvButton',
		ref: 'exportButton',
		scope: this,
		listeners: { 
			click: function () { 
				if ( fileExists != 1 ) {
					Ext.notification.msg('Smart Manager', getText('Export CSV feature is available only in Pro version') ); 
					return;
				}
				Ext.DomHelper.append(Ext.getBody(), { 
                    tag: 'iframe', 
                    id:'downloadIframe', 
                    frameBorder: 0, 
                    width: 0, 
                    height: 0, 
                    css: 'display:none;visibility:hidden;height:0px;', 
                    src: jsonURL+'?cmd=exportCsvWoo&incVariation='+SM.incVariation+'&searchText='+SM.searchTextField.getValue()+'&fromDate='+fromDateTxt.getValue()+'&toDate='+toDateTxt.getValue()+'&active_module='+SM.activeModule+''
                }); 
			}
		}
	}],
	pageSize: limit,
	store: productsStore,
	displayInfo: true,
	style: { width: '100%' },
	hideBorders: true,
	align: 'center',
	displayMsg: 'Displaying {0} - {1} of {2}',
	emptyMsg: SM.activeModule + ' ' + getText('list is empty') 
});

var pagingActivePage = pagingToolbar.getPageData().activePage;
	
	// Function to save modified records
	var saveRecords = function(store,pagingToolbar,jsonURL,editorGridSelectionModel){
		// Gets all records modified since the last commit.
		// Modified records are persisted across load operations like pagination or store reload.
		
		var modifiedRecords = store.getModifiedRecords();		
		if(!modifiedRecords.length) {
			return;
		}
		var edited  = [];
		Ext.each(modifiedRecords, function(r, i){
			if(r.get('id') == ''){
				r.data.category = newCatId;
			}
			edited.push(r.data);
		});

		var o = {
			url:jsonURL
			,method:'post'
			,callback: function(options, success, response)	{
				var myJsonObj = Ext.decode(response.responseText);
				if(true !== success){
					Ext.notification.msg('Failed',response.responseText);
					return;
				}try{
					store.commitChanges();					
					pagingToolbar.saveButton.disable();
					Ext.notification.msg('Success', myJsonObj.msg);
					pagingToolbar.doRefresh(); // to refresh the current page.
					return;
				}catch(e){
					var err = e.toString();
					Ext.notification.msg('Error', err);					
					return;
				}
			}
			,scope:this
			,params:
			{
				cmd:'saveData',
				active_module: SM.activeModule,
				incVariation: SM.incVariation,
				edited:Ext.encode(edited)				
			}};
			Ext.Ajax.request(o);
	};


        // Function to duplicate the Selected Products
        var duplicateRecords = function (menu) {
		var selected  = editorGrid.getSelectionModel();
		var records   = selected.selections.keys;
		var getDuplicateRecords = function (btn, text) {
                    if (btn == 'yes') {

                        //Code to create a extjs Messagebox with a progressbar
                        var progress = Ext.MessageBox.show({
                           title: 'Please wait',
                           msg: 'Duplicating Products...',
                           progressText: 'Initializing...',
                           width: 300,
                           progress: true,
                           closable: false
                           });

                        var count = 0;

                        function dup_prod(count, total_records, dup_data) {
                        var arr = new Array();

                        dupcnt = 0;
                        if (total_records > 20) {
                            fdupcnt = 20;
                        }
                        else{
                            fdupcnt = total_records;
                        }

                        //Code to delay the progressbar hide task and then load the store
                        var task = new Ext.util.DelayedTask(function(){
                            progress.hide();
                            store.load();
                        });

                        //Code to create multiple AJAX request based on the count received from the first AJAX Request
                        for (i=0;i<count;i++) {

                                arr[i] = {
                                            url: jsonURL,
                                            method: 'post',
                                            callback: function (options, success, response) {

                                                store = productsStore;

                                                try {
                                                    var myJsonObj    = Ext.decode(response.responseText);
                                                    var nxtreq       = myJsonObj.nxtreq;
                                                    var dupcnt       = myJsonObj.dupCnt;
                                                    var per          = myJsonObj.per;
                                                    var val          = myJsonObj.val;

                                                    if (true !== success) {
                                                            Ext.notification.msg('Failed',myJsonObj.msg);
                                                            return;
                                                    }
                                                    else{
                                                        progress.updateProgress(val,per+"% Completed");

                                                        if (nxtreq < count) {
                                                            Ext.Ajax.request(arr[nxtreq]);
                                                        }
                                                        else{
                                                            task.delay(2500);
                                                        }

                                                        if (dupcnt == 0) {
                                                            Ext.notification.msg('Warning', myJsonObj.msg);
                                                        }
                                                        else{
                                                             if (per == 100) {
                                                                Ext.notification.msg('Success', myJsonObj.msg);
                                                            }
                                                        }

                                                    }

                                                }
                                                catch (e) {
                                                            Ext.notification.msg('Warning','Duplication of the Product Not Successful');							
                                                            return;
                                                }
                                            },
                                            scope: this,
                                            params: {
                                                    cmd: 'dupData',
                                                    part: i+1,
                                                    dupcnt : dupcnt,
                                                    fdupcnt : fdupcnt,
                                                    count : count,
                                                    total_records : total_records,
                                                    dup_data : dup_data,
                                                    menu : menu,
                                                    active_module: SM.activeModule,
                                                    incvariation: SM.incVariation
                                            }
                                    };

                                    dupcnt = fdupcnt;
                                    if ((fdupcnt+20) <= total_records) {
                                          fdupcnt = fdupcnt +20;
                                    }
                                     else{
                                        fdupcnt = total_records;
                                    }


                         }

                            Ext.Ajax.request(arr[0]);
                        }   

                        //Initial AJAX request to get the number of AJAX request to be made based on the number of products selected for duplication
                        var o = {
                            url: jsonURL,
                            method: 'post',
                            callback: function (options, success, response) {
                                try {
                                    var myJsonObj    = Ext.decode(response.responseText);
                                    var count        = myJsonObj.count;
                                    var dupcnt       = myJsonObj.dupCnt;
                                    var dup_data     = myJsonObj.data_dup;
                                    dup_prod(count, dupcnt, dup_data);
                                }
                                catch (e) {
                                    Ext.notification.msg('Warning','Duplication of the Product Not Successful');							
                                    return;
                                }
                            },
                            scope: this,
                            params: {
                                    cmd: 'dupData',
                                    part: 'initial',
                                    menu : menu,
                                    active_module: SM.activeModule,
                                    incvariation: SM.incVariation,
                                    data: Ext.encode(records)
                            }
                    };
                    Ext.Ajax.request(o);
                }
            }

            var msg;
            if (menu == 'selected') {
                if (records.length == 1) {
                    msg = getText('Are you sure you want to duplicate the selected product?');
                }
                else{
                    msg = getText('Are you sure you want to duplicate the selected products?');
                }
            }
            else{
                msg = getText('Are you sure you want to duplicate the entire store?');
            }

            Ext.Msg.show({
                    title: getText('Confirm Product Duplication'),
                    msg: msg,
                    width: 400,
                    buttons: Ext.MessageBox.YESNO,
                    fn: getDuplicateRecords,
                    animEl: 'dup',
                    closable: false,
                    icon: Ext.MessageBox.QUESTION
            })
	};

	// Function to delete selected records
	var deleteRecords = function () {
		var selected  = editorGrid.getSelectionModel();
		var records   = selected.selections.keys;
		var getDeletedRecords = function (btn, text) {
			if (btn == 'yes') {
                                batchUpdateWindow.loadMask.show();
				var o = {
					url: jsonURL,
					method: 'post',
					callback: function (options, success, response) {

						if (SM.activeModule == 'Products') {
						store = productsStore;
                                                }
						else if (SM.activeModule == 'Orders') {
						store = ordersStore;
                                                }

						var myJsonObj    = Ext.decode(response.responseText);
						var delcnt       = myJsonObj.delCnt;
                                                var totalRecords = 0;
						if (SM.activeModule == 'Products') {
                                                    totalRecords = productsJsonReader.jsonData.totalCount;
                                                }
                                                else if (SM.activeModule == 'Orders') {
                                                    totalRecords = ordersJsonReader.jsonData.totalCount;
                                                }
						var lastPage     = Math.ceil(totalRecords / limit);
						var totalPages   = Math.ceil(totalRecords / limit);
						var currentPage  = pagingToolbar.readPage();
						var lastPageTotalRecords = store.data.length;

						if (true !== success) {
							Ext.notification.msg('Failed',response.responseText);
							return;
						}try {							
							var afterDeletePageCount = lastPageTotalRecords - delcnt;

							//if all the records on the first page are deleted & there are no more records to populate in the grid.
							if (currentPage == 1 && afterDeletePageCount == 0 && totalPages == 1){							
									myJsonObj.items = '';
									store.loadData(myJsonObj);
							}else if (currentPage == lastPage && afterDeletePageCount == 0) { //if all the records on the last page are deleted
								pagingToolbar.movePrevious();
						    }else {						    	
						    	pagingToolbar.doRefresh();
						    }
							
							Ext.notification.msg('Success', myJsonObj.msg);							
						} catch (e) {
							var err = e.toString();
							Ext.notification.msg('Error', err);							
							return;
						}
					},
					scope: this,
					params: {
						cmd: 'delData',
						active_module: SM.activeModule,
						data: Ext.encode(records)
					}
				};
				Ext.Ajax.request(o);
			}
		}
		var msg = getText('Are you sure you want to delete the selected record' + (records.length == 1) ? '': 's' + '?');

		Ext.Msg.show({
			title: getText('Confirm File Delete'),
			msg: msg,
			width: 400,
			buttons: Ext.MessageBox.YESNO,
			fn: getDeletedRecords,
			animEl: 'del',
			closable: false,
			icon: Ext.MessageBox.QUESTION
		})
	};

	var showSelectedModule = function(clickedActiveModule){
		if(clickedActiveModule == 'Customers'){
			SM.activeModule = 'Customers';
			showCustomersView();
		}else if (clickedActiveModule == 'Orders'){
			SM.activeModule = 'Orders';
			showOrdersView();
		}else{
			SM.activeModule = 'Products';
			showProductsView();
		}
	};
	
        //Code to create a new button for dulicating product
        SM.duplicateButton = new Ext.SplitButton({
                        id          : 'duplicateButton',
                        menu: [{
                                text: getText('Selected Products'),
                                handler: function(){
                                    if ( fileExists != 1 ) {
					Ext.notification.msg('Smart Manager', getText('Duplicate Product feature is available only in Pro version') ); 
					return;
                                    }
                                    else{
                                        duplicateRecords('selected');
                                    }
                                }
                                },{
                                text: getText('Duplicate Store'),
                                handler: function(){
                                    if ( fileExists != 1 ) {
					Ext.notification.msg('Smart Manager', getText('Duplicate Store feature is available only in Pro version') ); 
					return;
                                    }
                                    else{
                                        duplicateRecords('store');
                                    }
                                }
                                }],
                        text        : getText('Duplicate Product'),
                        tooltip     : getText('Duplicate Product'),
                        icon        : imgURL + 'batch_update.png',
                        scope       : this,
                        width       : 100,
                        disabled    : true,
                        hidden      : false,
                        ref         : 'SM.duplicateButton',
                        listeners: {
                                click: function () {
                                    if(this.pressed == true){
                                        this.hideMenu();
                                        this.pressed = false;
                                    }
                                    else{
                                        this.showMenu();
                                        this.menu.visible = true;
                                        this.pressed = true;
                                    }
                                   
                                }}
                        });
        
	// Products, Customers and Orders combo box
	SM.dashboardComboBox = new Ext.form.ComboBox({
		id: 'dashboardComboBox',
		stateId : 'dashboardComboBoxWoo',
		stateEvents : ['added','beforerender','enable','select','change','show','beforeshow'],
		stateful: true,
		getState: function(){ return { value: this.getValue()}; },
		applyState: function(state) {
			this.setValue(state.value);
			pagingToolbar.emptyMsg =  state.value + ' ' + getText('list is empty');
		},
		store: new Ext.data.ArrayStore({
			autoDestroy: true,
			forceSelection: true,
			fields: ['id', 'fullname', 'display']
		}),
		displayField: 'display',
		valueField: 'fullname',
		cls: 'searchPanel',
		mode: 'local',
		triggerAction: 'all',
		editable: false,
		value: 'Products',
		style: {
			fontSize: '14px',
			paddingLeft: '2px'
		},
		forceSelection: true,
		width: 135,
		listeners: {
			select: function () {
				pagingToolbar.emptyMsg = this.getValue() + ' ' + getText('list is empty');
				editorGrid.stateId = this.value.toLowerCase()+'EditorGridPanelWoo';

				cellClicked = false;
				if(batchUpdateWindow.isVisible())
				batchUpdateWindow.hide();

				//set a store depending on the active Module
				if(SM.activeModule == 'Orders')
					store = ordersStore;
				else if(SM.activeModule == 'Products')
					store = productsStore;
				else 
					store = customersStore;

				//storing the value of clicked module name
				if (this.value == 'Customers')
					clickedActiveModule = 'Customers';
				else if (this.value == 'Orders')
					clickedActiveModule = 'Orders';
				else
					clickedActiveModule = 'Products';

				var modifiedRecords = store.getModifiedRecords();
				if(!modifiedRecords.length) {
					showSelectedModule(clickedActiveModule);
				}else{
					var saveModification = function (btn, text) {
						if (btn == 'yes')
						saveRecords(store,pagingToolbar,jsonURL,editorGridSelectionModel);
						showSelectedModule(clickedActiveModule);
					};
					Ext.Msg.show({
						title: getText('Confirm Save'),
						msg: getText('Do you want to save the modified records?'),
						width: 400,
						buttons: Ext.MessageBox.YESNO,
						fn: saveModification,
						animEl: 'del',
						closable: false,
						icon: Ext.MessageBox.QUESTION
					});
				}
			}
		}
	});


	// ==== common ====
SM.searchTextField = new Ext.form.TextField({
	id: 'searchTextField',
	width: 400,
	cls: 'searchPanel',
	style: {
		fontSize: '14px',
		paddingLeft: '2px',
		width: '100%'
	},
	params: {
		cmd: 'searchText'
	},
	emptyText: getText('Search') + '...', 
	enableKeyEvents: true,
	listeners: {
		keyup: function () {
			if ( fileExists != 1 ) {
				Ext.notification.msg('Smart Manager', getText('Search feature is available only in Pro version') );
				return;
			}			
			//set a store depending on the active Module
			store = productsStore;
			var modifiedRecords = store.getModifiedRecords();
			
			// make server request after some time - let people finish typing their keyword
			clearTimeout(search_timeout_id);
			search_timeout_id = setTimeout(function () {
			if(!modifiedRecords.length) {				
				 searchLogic();
			}else{
				var saveModification = function (btn, text) {
					if (btn == 'yes')
					saveRecords(store,pagingToolbar,jsonURL,editorGridSelectionModel);
					searchLogic();
				}
				Ext.Msg.show({
					title: getText('Confirm Save'),
					msg: getText('Do you want to save the modified records?'),
					width: 400,
					buttons: Ext.MessageBox.YESNO,
					fn: saveModification,
					animEl: 'del',
					closable: false,
					icon: Ext.MessageBox.QUESTION
				})
			}
		}, 1000);
	}}
});

var searchLogic = function () {
	//START setting the params to store if search fields are with values (refresh event)
	switch(SM.activeModule) {
		case 'Products':
		productsStore.setBaseParam('searchText', SM.searchTextField.getValue());
		break;
		case 'Orders':
		ordersStore.setBaseParam('searchText', SM.searchTextField.getValue());		
		ordersStore.setBaseParam('fromDate', fromDateTxt.getValue());
		ordersStore.setBaseParam('toDate', toDateTxt.getValue());
		break;
		default :
		customersStore.setBaseParam('searchText',SM.searchTextField.getValue());
	};
	//END setting the params to store if search fields are with values (refresh event)
	mask.show();
	var o = {
		url: jsonURL,
		method: 'post',
		callback: function (options, success, response) {
			
			var result = response.responseText;
				result = result.trim();
				result = SM.escapeCharacters(result);
			var myJsonObj = Ext.decode(result);
			
			if (true !== success) {
				Ext.notification.msg('Failed',response.responseText);
				return;
			}
			try {
				var records_cnt = myJsonObj.totalCount;
				if (records_cnt == 0) myJsonObj.items = '';
				if(SM.activeModule == 'Products')
					productsStore.loadData(myJsonObj);
				else if(SM.activeModule == 'Orders'){
					ordersStore.loadData(myJsonObj);
                                } else {
					customersStore.loadData(myJsonObj);
                                }
					
			} catch (e) {
				return;
			}
			mask.hide();
		},
		scope: this,
		params: {
			cmd: 'getData',
			active_module: SM.activeModule,
			searchText: SM.searchTextField.getValue(),
			fromDate: fromDateTxt.getValue(),
			toDate: toDateTxt.getValue(),
			incVariation:SM.incVariation,
			start: 0,
			limit: limit,
			viewCols: Ext.encode(productsViewCols)
		}
	};
	Ext.Ajax.request(o);
};
	
//store for first combobox(field combobox) of BatchUpdate window.
var fieldsStore = new Ext.data.Store({
	reader: new Ext.data.JsonReader({
		idProperty: 'id',
		totalProperty: 'totalCount',
		root: 'items',
		fields: [{ name: 'id' },
				 { name: 'name'	},
				 { name: 'type'	},
				 { name: 'value'}]
	}),
	autoDestroy: false,
	dirty: false
});
fieldsStore.loadData(productsFields);

//store for second combobox(actions combobox) of BatchUpdate window.
var actionStore = new Ext.data.ArrayStore({
	fields: ['id', 'name', 'value'],
	autoDestroy: false
});
actionStore.loadData(actions);

//store to populate category in the third combobox(category combobox) on selecting a category from first combobox(field combobox).
var categoryStore = new Ext.data.ArrayStore({
	fields: ['id', 'name'],
	autoDestroy: false
});

var taxStatusStoreData = new Array();
	taxStatusStoreData = [
							[ 'taxable', getText('Taxable') ],
							[ 'shipping', getText('Shipping only') ],
							[ 'none', getText('None') ]
						 ];

var visibilityStoreData = new Array();
    visibilityStoreData = [
                            ['visible', getText('Catalog & Search')],
                            ['catalog', getText('Catalog')],
                            ['search', getText('Search')],
                            ['hidden', getText('Hidden')]
                          ];

//Store for 'set to' from second combobox(actions combobox).
var countriesStore = new Ext.data.Store({
	reader: new Ext.data.JsonReader({
		idProperty: 'id',
		totalProperty: 'totalCount',
		root: 'items',
		fields: [{ name: 'id'  },
				{ name: 'name' },
				{ name: 'value'}]
	}),
	autoDestroy: false,
	dirty: false
});
countriesStore.loadData(countries);

var countriesStoreCombo = new Ext.form.ComboBox({
		store: countriesStore,
		valueField: 'value',
		displayField: 'name',
		mode: 'local',
		typeAhead: true,
		triggerAction: 'all',
		lazyRender: true,
		editable: false
});

var mask = new Ext.LoadMask(Ext.getBody(), {
	msg: getText('Please wait') + "..."
});

var batchMask = new Ext.LoadMask(Ext.getBody(), {
	msg: getText('Please wait') + "..."
});

var orderStatusStoreData = new Array();
    orderStatusStoreData = [
                            ['pending', getText('Pending')],
                            ['failed', getText('Failed')],
                            ['on-hold', getText('On Hold')],
                            ['processing',getText('Processing')],
                            ['completed', getText('Completed')],
                            ['refunded', getText('Refunded')],
                            ['cancelled', getText('Cancelled')]
                          ];

var orderStatusStore = new Ext.data.ArrayStore({
			id: 0,
			fields: ['id','name'],
			data: [
			['pending',  	'Pending'],
			['failed',  	'Failed'],
			['on-hold', 	'On Hold'],
			['processing',  'Processing'],
			['completed',   'Completed'],
			['refunded',    'Refunded'],
			['cancelled', 	'Cancelled']
			]
	});
	
	
	var orderStatusCombo = new Ext.form.ComboBox({
		typeAhead: true,
		triggerAction: 'all',
		lazyRender:true,
		editable: false,
		mode: 'local',
		store: orderStatusStore,
		valueField: 'id',
		displayField: 'name'
	});

//batch update window
var batchUpdateToolbarInstance = Ext.extend(Ext.Toolbar, {
	cls: 'batchtoolbar',
	constructor: function (config) {
		config = Ext.apply({
			items: [{
				xtype: 'combo',
				width: 170,
				allowBlank: false,
				align: 'center',				
				store: fieldsStore,
				typeAhead: true,
				style: {
					fontSize: '12px',
					paddingLeft: '2px',
					verticalAlign: 'middle'
				},
				displayField: 'name',
				valueField: 'value',
				mode: 'local',
				cls: 'searchPanel',
				emptyText: getText('Select a field') + '...',
				triggerAction: 'all',
				editable: false,				
				selectOnFocus: true,
				listeners: {
					select: function () {
						var actions_index;
						var selectedFieldIndex = this.selectedIndex;
						
						if(SM.activeModule == 'Products')
							var field_type = SM['productsCols'][this.value].actionType;
						else
							var field_type = this.store.reader.jsonData.items[selectedFieldIndex].type;
						var field_name = this.store.reader.jsonData.items[selectedFieldIndex].name;
						var colName = this.store.reader.jsonData.items[selectedFieldIndex].colName;
						var actionsData = new Array();
						var toolbarParent = this.findParentByType(batchUpdateToolbarInstance, true);
						var comboCategoriesActionCmp = toolbarParent.get(7);
						var setTextfield = toolbarParent.get(6);
						var comboActionCmp = toolbarParent.get(2);
						var comboCountriesCmp = toolbarParent.get(4);
						var selectedActionvalue = comboActionCmp.value;
						var textField2Cmp      = toolbarParent.get(8);
						
						toolbarParent.get(5).hide();			//to hide extra space on batchUpdateToolbar
						
						objRegExp = /(^-?\d\d*\.\d*$)|(^-?\d\d*$)|(^-?\.\d\d*$)/;;
						regexError = getText('Only numbers are allowed');
						
							if(SM['productsCols'][this.value] != undefined ){
								var categoryActionType = SM['productsCols'][this.value].actionType;
							}							
                            setTextfield.emptyText="Enter the Value...";
							if (field_type == 'category' || categoryActionType == 'category_actions') {
								setTextfield.hide();
								textField2Cmp.hide();
								comboCategoriesActionCmp.show();
								comboCategoriesActionCmp.reset();
							} else if(colName == '_tax_status' || colName == '_visibility'){
								setTextfield.hide();
								textField2Cmp.hide();
								comboCategoriesActionCmp.show();
								comboCategoriesActionCmp.reset();
							} else if(field_type == 'attribute_action'){
								setTextfield.hide();
								textField2Cmp.hide();
								comboCategoriesActionCmp.hide();
							} else if (field_type == 'string') {
								setTextfield.hide();
								textField2Cmp.hide();
								comboCategoriesActionCmp.hide();
							} else if (field_name == 'Stock: Quantity Limited' || field_name == 'Publish' || field_name == 'Stock: Inform When Out Of Stock' || field_name == 'Disregard Shipping') {								
								setTextfield.hide();
								textField2Cmp.hide();
								comboCategoriesActionCmp.hide();
							} else if (field_name == 'Weight' || field_name == 'Variations: Weight'||field_name == 'Height' ||field_name == 'Width' ||field_name == 'Length') {
								setTextfield.show();
								textField2Cmp.hide();
								comboCategoriesActionCmp.hide();
							} else if(field_name == 'Order Status'){
								actions_index = field_type;
								setTextfield.hide();
								textField2Cmp.hide();
                                                                comboCountriesCmp.hide();
								comboCategoriesActionCmp.show();
								comboCategoriesActionCmp.reset();
							} else if (field_name.indexOf('Country') != -1) {
								actions_index = 'bigint';
								setTextfield.hide();
                                                                setTextfield.emptyText="Enter State/Region...";
								textField2Cmp.hide();
								comboCategoriesActionCmp.hide();
								comboCountriesCmp.show();
								comboCountriesCmp.reset();
							} else {
								setTextfield.show();
								textField2Cmp.hide();
								if (field_type == 'blob' || field_type == 'modStrActions') {
									objRegExp = '';
									regexError = '';
								}
								comboCountriesCmp.hide();
								comboCategoriesActionCmp.hide();
								actions_index = field_type;
							}
						
						if(SM.activeModule == 'Orders' || SM.activeModule == 'Customers'){
							for (j = 0; j < actions[actions_index].length; j++) {
								actionsData[j] = new Array();
								actionsData[j][0] = actions[actions_index][j].id;
								actionsData[j][1] = actions[actions_index][j].name;
								actionsData[j][2] = actions[actions_index][j].value;
							}
							actionStore.loadData(actionsData); // @todo: check whether used only for products or is it used for any other module?
							
							// @todo apply regex accordign to the req
							setTextfield.regex = '';
							setTextfield.regexText = '';	
						}else if(SM.activeModule == 'Products'){
							if ( this.value.substring( 0, 14 ) != 'groupAttribute'){
								actionStore.loadData(actions[SM['productsCols'][this.value].actionType]);
							}
							// @todo apply regex accordign to the req
							setTextfield.regex = objRegExp;
							setTextfield.regexText = regexError;	
						}
						setTextfield.reset();
						comboActionCmp.reset();
						
											
					}
				}
			}, '',
			{
				xtype: 'combo',
				width: 170,
				allowBlank: false,
				store: actionStore,
				style: {
					fontSize: '12px',
					paddingLeft: '2px'
				},
				displayField: 'name',
				valueField: 'value',
				mode: 'local',
				cls: 'searchPanel',
				emptyText: getText('Select an action')+ '...',
				triggerAction: 'all',
				editable: false,
				selectOnFocus: true,
				listeners: {
					focus: function () {	
							var actionsData        = new Array();
							var toolbarParent      = this.findParentByType(batchUpdateToolbarInstance, true);
							var comboFieldCmp      = toolbarParent.get(0);
							var selectedValue      = comboFieldCmp.value;
							
							if(SM.activeModule == 'Orders' || SM.activeModule == 'Customers'){
								var selectedFieldIndex = comboFieldCmp.selectedIndex;
								var field_type         = comboFieldCmp.store.reader.jsonData.items[selectedFieldIndex].type;
								var field_name         = comboFieldCmp.store.reader.jsonData.items[selectedFieldIndex].name;
								var actions_index;

								actions_index = (field_type == 'category') ? field_type + '_actions' :((field_name.indexOf('Country') != -1) ? 'bigint' : field_type);

								for (j = 0; j < actions[actions_index].length; j++) {
									actionsData[j] = new Array();
									actionsData[j][0] = actions[actions_index][j].id;
									actionsData[j][1] = actions[actions_index][j].name;
									actionsData[j][2] = actions[actions_index][j].value;
								}
								actionStore.loadData(actionsData);
							}else{
								if ( selectedValue.substring( 0, 14 ) == 'groupAttribute' ) {
									var attributeArray = Ext.decode(attribute);
									if(selectedValue == 'groupAttributeChange' || selectedValue == 'groupAttributeRemove'){
										attributeArray.splice(0,1);
										actionStore.loadData(attributeArray);
									} else {
										actionStore.loadData(attributeArray);
									}
								} else {
									// on swapping between the toolbars	
									actionStore.loadData(actions[SM['productsCols'][selectedValue].actionType]);
								}
							}
						},					
					select: function() {
						var toolbarParent      = this.findParentByType(batchUpdateToolbarInstance, true);
						var comboFieldCmp      = toolbarParent.get(0);
						var comboactionCmp     = toolbarParent.get(2);
						var comboCountriesCmp  = toolbarParent.get(4);
						var textField1Cmp      = toolbarParent.get(6);
						var selectedFieldIndex = comboFieldCmp.selectedIndex;
						var selectedValue      = comboFieldCmp.value;
						var field_name = comboFieldCmp.store.reader.jsonData.items[selectedFieldIndex].name;
						var selectedActionvalue = comboactionCmp.value;
						var comboCategoriesActionCmp = toolbarParent.get(7);
						var textField2Cmp      = toolbarParent.get(8);
						
						if ( selectedValue.substring( 0, 14 ) == 'groupAttribute' ){
							if( selectedActionvalue == 'custom'){
								comboCategoriesActionCmp.hide();
								comboCategoriesActionCmp.reset();
								textField1Cmp.emptyText = getText('Enter Attribute Name') + '...';
								textField1Cmp.regex = null;
								textField1Cmp.show();
								textField2Cmp.show();
								textField1Cmp.reset();
								textField2Cmp.reset();
							} else {
								comboCategoriesActionCmp.show();
								comboCategoriesActionCmp.reset();
								textField1Cmp.hide();
								textField2Cmp.hide();
								var object = {
												url:jsonURL
												,method:'post'
												,callback: function(options, success, response)	{
													var myJsonObj = Ext.decode(response.responseText);
													
													if(true !== success){
														Ext.notification.msg('Failed',response.responseText) ;
														return;
													} try{
														if ( myJsonObj != '' ) {
															categoryStore.loadData(myJsonObj);
														}
														return;
													} catch(e){
														var err = e.toString();
														Ext.notification.msg('Error', err);
														return;
													}
												}
												,scope:this
												,params:
												{
													cmd: 'getTerms',
											 		active_module: SM.activeModule,
											 		action_name: selectedValue,
											 		attribute_name: selectedActionvalue
												}
											};
								Ext.Ajax.request(object);
							}
						}
					}
				}
			},'',{
				xtype: 'combo',
				allowBlank: false,
				typeAhead: true,
				hidden: false,
				width: 170,
				align: 'center',
				store: countriesStore,
				style: {
					fontSize: '12px',
					paddingLeft: '2px'
				},
				hidden: true,
				valueField: 'value',
				displayField: 'name',
				mode: 'local',
				cls: 'searchPanel',
				emptyText: getText('Select a value') + '...',
				triggerAction: 'all',
				editable: false,
				forceSelection: true,
				selectOnFocus: true,
				listeners: {
					select: function(){
						var regions			   = new Array();
						var toolbarParent      = this.findParentByType(batchUpdateToolbarInstance, true);
						var comboRegionCmp     = toolbarParent.get(7);
						var comboCountriesCmp  = toolbarParent.get(4);
						var selectedValue      = comboCountriesCmp.value;
						
						var object = {
							url:jsonURL
							,method:'post'
							,callback: function(options, success, response)	{
								var myJsonObj = Ext.decode(response.responseText);
								if(true !== success){
									Ext.notification.msg('Failed',response.responseText);
									return;
								}try{
									if ( myJsonObj != '' ) {	
										for ( var i = 0; i < myJsonObj.items.length; i++ ) {
											regions[i] = new Array();
											regions[i][0] = myJsonObj.items[i].id;
											regions[i][1] = myJsonObj.items[i].name;
										}
										comboRegionCmp.store.loadData(regions);
										
										comboRegionCmp.show();
										comboRegionCmp.reset();
										toolbarParent.get(6).hide();
									} else {
										comboRegionCmp.hide();
										toolbarParent.get(5).show();
										toolbarParent.get(6).show();
									}
									return;
								}catch(e){
									var err = e.toString();
									Ext.notification.msg('Error', err);					
									return;
								}
							}
							,scope:this
							,params:
							{
								cmd: 'getRegion',
								active_module: SM.activeModule,
								country_id: selectedValue				
							}
						};
						Ext.Ajax.request(object);
					}
				}
			},'',{
				xtype: 'textfield',
				width: 170,
				allowBlank: false,
				style: {
					fontSize: '12px',
					paddingLeft: '2px'
				},
				enableKeyEvents: true,
				regex: objRegExp,
				regexText: regexError,
				displayField: 'fullname',
				emptyText: getText('Enter the value') + '...',
				cls: 'searchPanel',
				hidden: true,
				selectOnFocus: true,
				listeners: {
					beforerender: function( cmp ) {
						cmp.emptyText = getText('Enter the value') + '...'; 
					}
				}
			},{
				xtype: 'combo',
				width: 170,
				allowBlank: false,
				store: categoryStore,
				style: {
					fontSize: '12px',
					paddingLeft: '2px'
				},
				displayField: 'name',
				valueField: 'id',
				mode: 'local',
				cls: 'searchPanel',
				emptyText: getText('Select a Value') + '...',
				triggerAction: 'all',
				editable: false,
				forceSelection: false,
				hidden: true,
				selectOnFocus: true,
				listeners: {
					focus: function () {
						var actionsData = new Array();
						var toolbarParent = this.findParentByType(batchUpdateToolbarInstance, true);
						var comboFieldCmp = toolbarParent.get(0);
						var comboRegionCmp     = toolbarParent.get(7);
						var selectedFieldIndex = comboFieldCmp.selectedIndex;
						var selectedValue      = comboFieldCmp.value;
						var field_name		   = toolbarParent.items.items[0].store.reader.jsonData.items[selectedFieldIndex].name;
						var colName			   = toolbarParent.items.items[0].store.reader.jsonData.items[selectedFieldIndex].colName;
						
						if (SM.activeModule == 'Products') {
							if ( selectedValue.substring( 0, 14 ) != 'groupAttribute' ){
								if ( colName == '_tax_status' ) {
                                    categoryStore.loadData( taxStatusStoreData );
                                } else if ( field_name == 'Visibility' ) {
                                    categoryStore.loadData( visibilityStoreData );
                                } else {
                                    var category = categories["category-"+SM['productsCols'][selectedValue].colFilter];
                                    if ( category instanceof Object ) {
                                        var categoryData = [];
                                        for ( var catId in category  ) {
                                            if ( category[catId] != undefined ) {
                                                categoryData.push(category[catId]);
                                            }
                                        }
                                        categoryStore.loadData(categoryData);
                                    } else {
                                        categoryStore.loadData(category);
                                    }
                                }
							}
						} else if(SM.activeModule == 'Orders' && field_name == 'Order Status') {
							
                                                        var temp =new Array();
                                                        temp[0] = 'Pending';
                                                        temp[1] = 'Failed';
                                                        temp[2] = 'On Hold';
                                                        temp[3] = 'Processing';
                                                        temp[4] = 'Completed';
                                                        temp[5] = 'Refunded';
                                                        temp[6] = 'Cancelled';
                                                        
                                                        
							this.store = orderStatusStore;
                                                        comboRegionCmp.store.loadData(orderStatusStoreData);
                                                        comboRegionCmp.show();
							comboRegionCmp.reset();
                                                        
						}
				    }
				}
			},{
				xtype: 'textfield',
				width: 170,
				allowBlank: false,
				style: {
					fontSize: '12px',
					paddingLeft: '2px'
				},
				enableKeyEvents: true,
				displayField: 'fullname',
				emptyText: getText('Enter values') + '...',
				cls: 'searchPanel',
				hidden: true,
				listeners: {
					render: function( cmp ) {
						Ext.QuickTips.register({
						    target: cmp.getEl(),
						    title: getText('Important:'),
						    text: getText('For more than one values, use pipe (|) as delimiter')
						});
					}
				},
				selectOnFocus: true
			}, '->',
			{
				icon: imgURL + 'del_row.png',
				tooltip: getText('Delete Row'),
				handler: function () {
					toolbarCount--;
					var toolbarParent = this.findParentByType(batchUpdateToolbarInstance, true);
					batchUpdatePanel.remove(toolbarParent);
				}
			}]
		}, config);
		batchUpdateToolbarInstance.superclass.constructor.call(this, config);
	}
});

var batchUpdateToolbar = new Ext.Toolbar({
	id: 'tl',
	cls: 'batchtoolbar',
	items: [new batchUpdateToolbarInstance(), '->',
	{
		text: getText('Add Row'),
		tooltip: getText('Add a new row') ,
		ref: 'addRowButton',
		icon: imgURL + 'add_row.png',
		handler: function () {
			var newBatchUpdateToolbar = new batchUpdateToolbarInstance();
			toolbarCount++;
			batchUpdatePanel.add(newBatchUpdateToolbar);
			batchUpdatePanel.doLayout();
		}
	}]
});
batchUpdateToolbar.get(0).get(10).hide(); //hide delete row icon from first toolbar.


var batchUpdatePanel = new Ext.Panel({
	animCollapse: true,
	autoScroll: true,
	Height: 500,
	width: 900,
	bbar: ['->',
	{
		text: getText('Update'),
		id: 'updateButton',
		ref: 'updateButton',
		tooltip: getText('Apply all changes'),
		icon: imgURL + 'batch_update.png',
		disabled: false,
		listeners: { click: function () {
			var clickRadio = Ext.getCmp('updateItemsOrStore').getValue();
			var radioValue = clickRadio.inputValue;					
			if(batchRadioToolbar.isVisible()){
				flag = 1;
			} else {
				flag = 0;
			}
					
			if(SM.activeModule == 'Orders'){
				store = ordersStore;
				cm = ordersColumnModel;
			}else if(SM.activeModule == 'Customers'){
				store = customersStore;
				cm = customersColumnModel;
			}else{
				store = productsStore;
				cm = productsColumnModel;
			}
			batchUpdateRecords(batchUpdatePanel,toolbarCount,cnt_array,store,jsonURL,batchUpdateWindow,radioValue,flag);
		}}
	}]
});
batchUpdatePanel.add(batchUpdateToolbar);
batchUpdatePanel.items.items[0].items.items[0].cls = 'firsttoolbar';

var batchRadioToolbar = new Ext.Toolbar({
	height: 35,
	items: [
		{
			xtype: 'tbtext',
		    width: 90,
		    text: getText('Update') + '...'
		},new Ext.form.RadioGroup({
			id: 'updateItemsOrStore' ,
		    width: 400,
			height: 20,
		    items: [
		    	
		        {boxLabel: 'Selected items', name: 'rb-batch', inputValue: 1, checked: true},
		        {boxLabel: 'All items in store (including Variations)', name: 'rb-batch', inputValue: 2}
		    ]
		})        
	]
});

batchUpdateWindow = new Ext.Window({
	title: getText('Batch Update - available only in Pro version'),
	animEl: 'BU',
	collapsible:true,
	shadow : true,
	loadMask: batchMask,
	shadowOffset: 10,
	tbar: batchRadioToolbar,
	items: batchUpdatePanel,
	layout: 'fit',
	width: 810,
	height: 300,
	plain: true,
	closeAction: 'hide',
	listeners: {
		hide: function (e) {
			for (sb = toolbarCount; sb >= 1; sb--){
				if(batchUpdatePanel.get(sb) != undefined)
				batchUpdatePanel.remove(batchUpdatePanel.get(sb));
			}
			var firstToolbar = batchUpdatePanel.items.items[0].items.items[0];
			firstToolbar.items.items[0].reset();
			firstToolbar.items.items[2].reset();

			firstToolbar.items.items[4].reset();
			firstToolbar.items.items[4].hide();

			firstToolbar.items.items[6].reset();
			firstToolbar.items.items[6].hide();
			
			firstToolbar.items.items[7].reset();
			firstToolbar.items.items[7].hide();

			firstToolbar.items.items[8].reset();
			firstToolbar.items.items[8].hide();

			values = '';
			ids = '';
			batchUpdateWindow.hide();
		}
	}
});

var storeDetailsWindowState = function(obj,stateId){
	var q            = new Ext.state.CookieProvider();
	var thisObjState =  q.get(stateId);

	if(thisObjState != undefined){
		obj.setSize(thisObjState.width, thisObjState.height);
		obj.setPagePosition(thisObjState.x,thisObjState.y);
	}
};

// Order's billing details window
var billingDetailsIframe = function(recordId){
	var billingDetailsWindow = new Ext.Window({
		stateId : 'billingDetailsWindowWoo',
		stateEvents : ['show','bodyresize','maximize'],
		stateful: true,
		title: 'Order Details',
		collapsible:true,
		shadow : true,
		shadowOffset: 10,
		width:500,
		height: 500,
		minimizable: false,
		maximizable: true,
		maximized: false,
		resizeable: true,
		listeners: { 
			maximize: function () {
				this.setPosition( 0, 30 );
			},
			show: function () {
				this.setPosition( 250, 30 );
			}
		},
		html: '<iframe src='+ ordersDetailsLink + '' + recordId +'&action=edit style="width:100%;height:100%;border:none;"><p> ' + getText('Your browser does not support iframes.') + '</p></iframe>'
	});
	billingDetailsWindow.show();
};

var checkModifiedAndshowDetails = function(record,rowIndex){
	//set a store depending on the active Module
	if(SM.activeModule == 'Orders')
	store = ordersStore;
	else if(SM.activeModule == 'Products')
	store = productsStore;
	else
	store = customersStore;
	
	var modifiedRecords = store.getModifiedRecords();
	if(!modifiedRecords.length) {
		
		if(SM.activeModule == 'Customers')
			showOrderDetails(record,rowIndex);
		else if(SM.activeModule == 'Orders')
			showCustomerDetails(record,rowIndex);
		
	}else{
		
		var saveModification = function (btn, text) {
			if (btn == 'yes')
			saveRecords(store,pagingToolbar,jsonURL,editorGridSelectionModel);
			store.load();
			
			if(SM.activeModule == 'Customers')
				showOrderDetails(record,rowIndex);
			else if(SM.activeModule == 'Orders')
				showCustomerDetails(record,rowIndex);
		};
		Ext.Msg.show({
			title: getText('Confirm Save'),
			msg: getText('Do you want to save the modified records?'),
			width: 400,
			buttons: Ext.MessageBox.YESNO,
			fn: saveModification,
			animEl: 'del',
			closable: false,
			icon: Ext.MessageBox.QUESTION
		});
	}
};

//extracting the email address from the records and show customer details of the passed email address.
//Its done by just setting the search textfield value to the extracted email address.
var showCustomerDetails = function(record,rowIndex){
	//START extracting emailId
	var name_emailid     = record.json.name;
	var name_emailid_arr = name_emailid.split(' ');
	var mix_emailId      = Ext.util.Format.stripTags(name_emailid_arr[name_emailid_arr.length -1]);
	var emailId          = mix_emailId.substring(1,mix_emailId.length-1);
	// END
	batchUpdateWindow.loadMask.show();
	clearTimeout(SM.colModelTimeoutId);
	SM.colModelTimeoutId = showCustomersView.defer(100,this,[emailId]);
	SM.searchTextField.setValue(emailId);
};

// ============ Customers ================

	var customersColumnModel = new Ext.grid.ColumnModel({	
		columns:[editorGridSelectionModel, //checkbox for
		{
			header: getText('First Name'),
			id: '_billing_first_name',
			dataIndex: '_billing_first_name',
			tooltip: getText('Billing First Name'),
			editable: false,
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 150
		},{
			header: getText('Last Name'),
			id: '_billing_last_name',
			dataIndex: '_billing_last_name',
			tooltip: getText('Billing Last Name'),
			editable: false,
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 150
		},{
			header: getText('Email'),
			id: '_billing_email',
			dataIndex: '_billing_email',
			tooltip: getText('Email Address'),
			editable: false,
			editor: new fm.TextField({
				allowBlank: true,
				allowNegative: false
			}),
			width: 200
		},{
			header: getText('Address'),
			id: '_billing_address',
			dataIndex: '_billing_address',
			tooltip: getText('Billing Address'),
			editable: false,
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 200
		},{
			header: getText('Postal Code'),
			id: '_billing_postcode',
			dataIndex: '_billing_postcode',
			tooltip: getText('Billing Postal Code'),
			editable: false,
			editor: new fm.TextField({
				allowBlank: true,
				allowNegative: false
			}),
			width: 150
		},{
			header: getText('City'),
			id: '_billing_city',
			dataIndex: '_billing_city',
			tooltip: getText('Billing City'),
			align: 'left',
			editable: false,
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 150
		},
		{
			header: getText('Region'),
			id: '_billing_state',
			dataIndex: '_billing_state',
			tooltip: getText('Billing Region'), 
			align: 'center',
			width: 100
		},
		{
			header: getText('Country'),
			id: '_billing_country',
			dataIndex: '_billing_country',
			tooltip: getText('Billing Country'),
			width: 120
		},
		{
			header: getText('Last Order Total'),
			id: 'total_purchased', //@todo: change the id to Total_Purchased
			dataIndex: '_order_total',
			tooltip: getText('Last Order Total'),
			align: 'right',
			width: 150			
		},{
			header: getText('Last Order'),
			id: 'last_order',
			dataIndex: 'last_order',
			tooltip: getText('Last Order Details'), 
			width: 220			
		},{   
			header: getText('Phone Number'),
			id: '_billing_phone',
			dataIndex: '_billing_phone',
			tooltip: getText('Phone Number'), 
			editable: false,
			editor: new fm.TextField({
				allowBlank: true,
				allowNegative: false
			}),
			width: 180		
        },{     // Tarun
            header: getText('Total Number Of Orders'),
            id: 'total_count',
            dataIndex: 'count_orders',
            tooltip: getText('Total Number Of Orders'),
            editable: false,
            align: 'left',
            //flex:0.25,
            width: 100
        },{
            header: getText('Total Purchased'),
            id: 'sum_orders',
            dataIndex: 'total_orders',
            tooltip: getText('Sum Total Of All Orders'),
            editable: false,
            align: 'left',
            //flex:0.25,
            width: 100
		}],
		listeners: {
			hiddenchange: function( ColumnModel,columnIndex, hidden ){
				storeColState();
			}
		},
		defaultSortable: true
	});
	
	var totPurDataType = '';	
	if (fileExists != 1) { 
		totPurDataType = 'string';
		customersColumnModel.columns[customersColumnModel.findColumnIndex('_order_total')].align = 'center';
		customersColumnModel.columns[customersColumnModel.findColumnIndex('last_order')].align = 'center';
        customersColumnModel.columns[customersColumnModel.findColumnIndex('count_orders')].align = 'center';    // Tarun
        customersColumnModel.columns[customersColumnModel.findColumnIndex('total_orders')].align = 'center';    // Tarun
	}else{
		totPurDataType = 'float';
	}
	
	// Data reader class to create an Array of Records objects from a JSON packet.
	var customersJsonReader = new Ext.data.customJsonReader({
		totalProperty: 'totalCount',
		root: 'items',
		fields:
		[
		{name:'id',type:'int'},		
		{name:'_billing_first_name',type:'string'},		
		{name:'_billing_last_name',type:'string'},				
		{name:'_billing_address',type:'string'},
		{name:'_billing_city', type:'string'},		
		{name:'_billing_state', type:'string'},
		{name:'_billing_country', type:'string'},		
		{name:'_billing_postcode',type:'string'},
		{name:'_billing_email',type:'string'},
		{name:'_billing_phone', type:'string'},	
		{name:'_order_total',type:totPurDataType},		
		{name:'last_order', type:'string'},
        {name:'count_orders',type:totPurDataType},  // Tarun
        {name:'total_orders',type:totPurDataType}   // Tarun

		]
	});
	
	// create the Customers Data Store
	var customersStore = new Ext.data.Store({
		reader: customersJsonReader,
		proxy:new Ext.data.HttpProxy({url:jsonURL}),
		baseParams:{
			cmd: 'getData',
			active_module: 'Customers',
			start: 0,
			limit: limit			
		},
		dirty:false,
		pruneModifiedRecords: true
	});
	
	customersStore.on('load', function () {
		editorGridSelectionModel.clearSelections();
		pagingToolbar.saveButton.disable();
	});
	
	showCustomersView = function(emailId){
		try{
			//initial steps when store: customers is loaded
			SM.activeModule = 'Customers';
			SM.dashboardComboBox.setValue(SM.activeModule);
			
			if(fileExists == 1)	{
				customersColumnModel.setEditable(1,true);
				customersColumnModel.setEditable(2,true);
				customersColumnModel.setEditable(3,true);
				customersColumnModel.setEditable(4,true);
				customersColumnModel.setEditable(5,true);
				customersColumnModel.setEditable(6,true);
				customersColumnModel.setEditable(11,true);
			}
			
			if(cellClicked == false){
				ordersStore.baseParams.searchText = ''; //clear the baseParams for ordersStore
				SM.searchTextField.reset(); 			//to reset the searchTextField
			}

			hidePrintButton();
			hideDeleteButton();
			hideAddProductButton();
			pagingToolbar.doLayout(true,true);

			for(var i=2;i<=8;i++)
			editorGrid.getTopToolbar().get(i).hide();
			editorGrid.getTopToolbar().get('incVariation').hide();
                        editorGrid.getTopToolbar().get('duplicateButton').hide();

			if(customersFields != 0)
			fieldsStore.loadData(customersFields);

			customersStore.setBaseParam('searchText',emailId);
			customersStore.load();
			pagingToolbar.bind(customersStore);

			editorGrid.reconfigure(customersStore,customersColumnModel);

			var firstToolbar 	  = batchUpdatePanel.items.items[0].items.items[0];
			var textfield    	  = firstToolbar.items.items[5];

			textfield.show();
		}catch(e){
			var err = e.toString();
			Ext.notification.msg('Error', err);
		}
	};
	
//	 ====== customers ======

// ======= orders ======
	var fromDateMenu = new Ext.menu.DateMenu({
		handler: function(dp, date){
			if ( fileExists != 1 ) {
				Ext.notification.msg('Smart Manager', getText('Filter through Date feature is available only in Pro version') );
				return;
			}
			fromDateTxt.setValue(date.format('M j Y'));
			searchLogic();
		},
		maxDate: now
	});

	var toDateMenu = new Ext.menu.DateMenu({
		handler: function(dp, date){
			if ( fileExists != 1 ) {
				Ext.notification.msg('Smart Manager', getText('Filter through Date feature is available only in Pro version') );
				return;
			}
			toDateTxt.setValue(date.format('M j Y'));
			searchLogic();
		},
		maxDate: now
	});

	
	
	var ordersColumnModel = new Ext.grid.ColumnModel({	
		columns:[editorGridSelectionModel, //checkbox for
		{
			header: getText('Order Id'),
			id: 'id',
			dataIndex: 'id',
			tooltip: getText('Order Id') 
		},{
			header: getText('Date / Time'),
			id: 'date',
			dataIndex: 'date',
			tooltip: getText('Date / Time'),
			width: 250
		},{
			header: getText('Name'), 
			id: 'name',
			dataIndex: 'name',
			tooltip: getText('Customer Name'),
			width: 350
		},{
			header: getText('Amount'),
			id: '_order_total',
			dataIndex: '_order_total',
			tooltip: getText('Amount'),
			align: 'right',
			renderer: amountRenderer,
			width: 100
		},{
			header: getText('Details'),
			id: 'details',
			dataIndex: 'details',
			tooltip: getText('Details'),
			width: 100
		},{
			header: getText('Payment Method'),
			id: '_payment_method',
			dataIndex: '_payment_method',
			tooltip: getText('Payment Method'),
			align: 'left',
			width: 110
		},{
			header: getText('Status'),
			id: 'order_status',
			dataIndex: 'order_status',
			tooltip: getText('Order Status'),
			width: 150,
			editable: true,
			editor: orderStatusCombo,
			renderer: Ext.util.Format.comboRenderer(orderStatusCombo)
		},{
			header: getText('Shipping Method'),
			id: '_shipping_method',
			dataIndex: '_shipping_method',
			tooltip: getText('Shipping Method'),
			width: 180
		},{   
			header: getText('Shipping First Name'),
			id: '_shipping_first_name',
			dataIndex: '_shipping_first_name',
			tooltip: getText('Shipping First Name'),
			hidden: true,
			editable: false,
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 200
		},{   
			header: getText('Shipping Last Name'),
			id: '_shipping_last_name',
			dataIndex: '_shipping_last_name',
			tooltip: getText('Shipping Last Name'),
			hidden: true,
			editable: false,
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 200
		},{   
			header: getText('Shipping Address'),
			id: '_shipping_address',
			dataIndex: '_shipping_address',
			tooltip: getText('Shipping Address'),
			hidden: true,
			editable: false,
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 200		
		},{
			header: getText('Shipping Postal Code'),
			id: '_shipping_postcode',
			dataIndex: '_shipping_postcode',
			tooltip: getText('Shipping Postal Code'),
			hidden: true,
			editable: false,
			editor: new fm.TextField({
					allowBlank: true,
					allowNegative: false
			}),
			width: 200
		},{   
			header: getText('Shipping City'),
			id: '_shipping_city',
			dataIndex: '_shipping_city',
			tooltip: getText('Shipping City'),
			hidden: true,
			editable: false,
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 200
		},
		{   
			header: getText('Shipping Region'),
			id: '_shipping_state',
			dataIndex: '_shipping_state',
			tooltip: getText('Shipping Region'),
			align: 'center',
			hidden: true,
			width: 100		
		},
		{
			header: getText('Shipping Country'), 
			id: '_shipping_country',
			dataIndex: '_shipping_country',
			tooltip: getText('Shipping Country'),
			hidden: true,
			width: 120
		},
                {   
			header: getText('Phone Number'),
			id: '_billing_phone',
			dataIndex: '_billing_phone',
			tooltip: getText('Customer Phone Number'),
			align: 'left',
			hidden: true,
			width: 100		
		}
		],
		listeners: {
			hiddenchange: function( ColumnModel,columnIndex, hidden ){
				storeColState();
			}
		},
		defaultSortable: true
	});

	// Data reader class to create an Array of Records objects from a JSON packet.
	var ordersJsonReader = new Ext.data.customJsonReader({
		totalProperty: 'totalCount',
		root: 'items',
		fields:
		[
		{name:'id',type:'int'},
		{name:'date',type:'string'},
		{name:'name',type:'string'},
		{name:'_order_total', type:'float'},
		{name:'details', type:'string'},
		{name:'_payment_method',type:'string'},
		{name:'order_status', type:'string'},
		{name:'_shipping_method', type:'string'},
		{name:'_shipping_first_name', type:'string'},
		{name:'_shipping_last_name', type:'string'},
		{name:'_shipping_address', type:'string'},
		{name:'_shipping_city', type:'string'},
		{name:'_shipping_country', type:'string'},
		{name:'_shipping_state', type:'string'},  
		{name:'_shipping_postcode', type:'string'},
                {name:'_billing_phone', type:'string'}
		]
	});
	
	// create the Orders Data Store
	var ordersStore = new Ext.data.Store({
		reader: ordersJsonReader,
		proxy:new Ext.data.HttpProxy({url:jsonURL}),
		baseParams:{
			cmd: 'getData',
			active_module: 'Orders',
			start: 0,
			limit: limit
		},
		dirty:false,
		pruneModifiedRecords: true
	});

	ordersStore.on('load', function () {
		editorGridSelectionModel.clearSelections();
		pagingToolbar.saveButton.disable();
	});	

	
	showOrdersView = function(emailid){
		try{
			//initial steps when store: orders is loaded
			SM.activeModule = 'Orders';
			SM.dashboardComboBox.setValue(SM.activeModule);

			if(fileExists == 1)	{
				ordersColumnModel.setEditable(7,true);
				ordersColumnModel.setEditable(9,true);
				ordersColumnModel.setEditable(10,true);
				ordersColumnModel.setEditable(11,true);
				ordersColumnModel.setEditable(12,true);
				ordersColumnModel.setEditable(13,true);
			}
			if(cellClicked == false){
				SM.searchTextField.reset(); //to reset the searchTextField
				fromDateTxt.setValue(lastMonDate.format('M j Y'));
				toDateTxt.setValue(now.format('M j Y'));

				ordersStore.baseParams.searchText = ''; //clear the baseParams for ordersStore
				ordersStore.baseParams.fromDate  = lastMonDate.format('M j Y');
				ordersStore.baseParams.toDate = now.format('M j Y');
			}else{
				fromDateTxt.setValue(initDate.format('M j Y'));
				ordersStore.setBaseParam('searchText',emailid);
				SM.searchTextField.setValue(emailid);

				ordersStore.setBaseParam('searchText', SM.searchTextField.getValue());
				ordersStore.setBaseParam('fromDate', fromDateTxt.getValue());
				ordersStore.setBaseParam('toDate', toDateTxt.getValue());
			}

			if(ordersFields != 0)
			fieldsStore.loadData(ordersFields);
			
			hideAddProductButton();
			hideDeleteButton();
			
			showDeleteButton();
			pagingToolbar.doLayout(true,true);
						
			for(var i=2;i<=8;i++)
			editorGrid.getTopToolbar().get(i).show();
			editorGrid.getTopToolbar().get('incVariation').hide();
                        editorGrid.getTopToolbar().get('duplicateButton').hide();

			ordersStore.load();
			editorGrid.reconfigure(ordersStore,ordersColumnModel);
			pagingToolbar.bind(ordersStore);

			var firstToolbar 	   = batchUpdatePanel.items.items[0].items.items[0];
			var textfield 	 	   = firstToolbar.items.items[5];
			textfield.hide();

		} catch(e) {
			var err = e.toString();
			Ext.notification.msg('Error', err);
		}
	};
	
	// ======= orders =====

	
	
	
	// Grid panel for the records to display
	editorGrid = new Ext.grid.EditorGridPanel({
	stateId : 'productsEditorGridPanelWoo',
	stateEvents : ['viewready','beforerender','columnresize', 'columnmove', 'columnvisible', 'columnsort','reconfigure'],
	stateful: true,
	store: productsStore,
	cm: productsColumnModel,
	renderTo: 'editor-grid',
	height: 700,
	stripeRows: true,
	frame: true,
	loadMask: mask,
	columnLines: true,
	clicksToEdit: 1,
	forceLayout: true,
	bbar: [pagingToolbar],
	viewConfig: { forceFit: true },
	sm: editorGridSelectionModel,
	tbar: [ SM.dashboardComboBox,
			{xtype: 'tbspacer',id:'afterComboTbspacer', width: 15},
		   {text:'From:', id: 'fromTextId'},fromDateTxt,{icon: imgURL + 'calendar.gif', menu: fromDateMenu, id:'fromDateMenuId'},
			{text:'To:', id:'toTextId'},toDateTxt,{icon: imgURL + 'calendar.gif', menu: toDateMenu, id:'toDateMenuId'},
			{xtype: 'tbspacer', id:'afterDateMenuTbspacer', width: 15},
			SM.searchTextField,{ icon: imgURL + 'search.png', id:'searchIconId' },
//			{xtype: 'tbspacer',width: 10, id:'afterSearchId'}
			'->',
			{ 
				xtype: 'checkbox',
				id:'incVariation',
				name: 'incVariation',
				stateEvents : ['added','check'],
				stateful: true,
				getState: function(){ return { value: this.getValue()}; },
				applyState: function(state) { this.setValue(state.value);},
			 	boxLabel: getText('Show Variations'),
			 	listeners: {
			 		check : function(checkbox, bool) {
			 			if ( SM.activeModule == 'Products' ) {
				 			if(fileExists == 1){
				 				SM.incVariation  = bool;
				 				productsStore.setBaseParam('incVariation', SM.incVariation);
				 				getVariations(productsStore.baseParams,productsColumnModel,productsStore);
				 			}else{
				 				Ext.notification.msg('Smart Manager', getText('Show Variations feature is available only in Pro version'));
				 			}
			 			}
			 		}
			 	}
			},
                         {xtype: 'tbspacer',width: 10, id:'afterShowVariation'},
                         SM.duplicateButton
                        ],
	scrollOffset: 50,
	listeners: {
		beforerender: function(grid) {
			var object = {
						url:jsonURL
						,method:'post'
						,callback: function(options, success, response)	{
							var myJsonObj = Ext.decode(response.responseText);
							var dashboardComboStore = new Array();
							for ( var i = 0; i < myJsonObj.length; i++) {
								if ( myJsonObj[i].indexOf("_") != -1) {
									dashboardComboStore.push( new Array( i, myJsonObj[i].slice( 0,9 ), getText( myJsonObj[i].slice( 0,9 ) ) ) );
									dashboardComboStore.push( new Array( i+1, myJsonObj[i].slice( 10 ), getText( myJsonObj[i].slice( 10 ) ) ) );
								} else {
									dashboardComboStore.push( new Array( i, myJsonObj[i], getText( myJsonObj[i] ) ) );
								}
								
							}
							if ( dashboardComboStore < 1) {
								Ext.Msg.show({
									title: getText('Access Denied'),
									msg: getText('You don\'t have sufficient permission to view this page'),
									buttons: Ext.MessageBox.OK,
									fn: function() {
										location.href = 'index.php';
									},
									icon: Ext.MessageBox.WARNING
								});
							} else {
								SM.dashboardComboBox.store.loadData(dashboardComboStore);
							}
						}
						,scope: SM.dashboardComboBox
						,params:
						{
							cmd:'getRolesDashboard'
						}};
				Ext.Ajax.request(object);
		},

		cellclick: function(editorGrid,rowIndex, columnIndex, e) {
			try{
				var record  = editorGrid.getStore().getAt(rowIndex);
				cellClicked = true;
				var editLinkColumnIndex   	  = productsColumnModel.findColumnIndex('edit_url'),
					editImageColumnIndex   	  = productsColumnModel.findColumnIndex(SM.productsCols.image.colName),
					prodTypeColumnIndex       = productsColumnModel.findColumnIndex('type'),
					totalPurchasedColumnIndex = customersColumnModel.findColumnIndex('_order_total'),
					lastOrderColumnIndex      = customersColumnModel.findColumnIndex('last_order'),
					nameLinkColumnIndex       = ordersColumnModel.findColumnIndex('name'),
					orderDetailsColumnIndex   = ordersColumnModel.findColumnIndex('details'),					
					publishColumnIndex        = productsColumnModel.findColumnIndex(SM.productsCols.publish.colName),
					nameColumnIndex           = productsColumnModel.findColumnIndex(SM.productsCols.name.colName),
					salePriceFromColumnIndex  = productsColumnModel.findColumnIndex(SM.productsCols.salePriceFrom.colName),
					salePriceToColumnIndex    = productsColumnModel.findColumnIndex(SM.productsCols.salePriceTo.colName),
					descColumnIndex        	  = productsColumnModel.findColumnIndex(SM.productsCols.desc.colName),
					addDescColumnIndex        = productsColumnModel.findColumnIndex(SM.productsCols.addDesc.colName),
                                        visibilityColumnIndex     = productsColumnModel.findColumnIndex(SM.productsCols.visibility.colName),    // Tarun
                    taxStatusColumnIndex      = productsColumnModel.findColumnIndex(SM.productsCols.taxStatus.colName);

				if(SM.activeModule == 'Orders'){
					if ( fileExists != 1 && ( columnIndex == orderDetailsColumnIndex || columnIndex == nameLinkColumnIndex ) ) {
						Ext.notification.msg('Smart Manager', getText('This feature is available only in Pro version')); 
						return;
					}
					if(columnIndex == orderDetailsColumnIndex){
					// showing order details of selected id by loading the web page in a Ext window instance.
						billingDetailsIframe(record.id);
					}else if(columnIndex == nameLinkColumnIndex){
					// check for any unsaved data and show details of the respective id sent as argument.
						checkModifiedAndshowDetails(record,rowIndex);
					}
					
				// Show WPeC's product edit page in a Ext window instance.
				}else if(SM.activeModule == 'Products'){
					if(columnIndex == editLinkColumnIndex) {
						var productsDetailsWindow = new Ext.Window({
							stateId : 'productsDetailsWindowWoo',
							collapsible:true,
							shadow : true,
							shadowOffset: 10,
							stateEvents : ['show','bodyresize','maximize'],
							stateful: true,
							title: getText('Products Details'),
							width:500,
							height: 600,						
							minimizable: false,
							maximizable: true,
							maximized: false,
							resizeable: true,
							shadow : true,
							shadowOffset : 10,
							animateTarget:'editLink',
							listeners: { 
								show: function(t){
									storeDetailsWindowState(t,t.stateId); 
								},
								maximize: function () {
									this.setPosition( 0, 30 );
								},
								show: function () {
									this.setPosition( 250, 30 );
								}
							},
							html: '<iframe src='+ productsDetailsLink + '' + record.id +' style="width:100%;height:100%;border:none;"><p> ' + getText('Your browser does not support iframes.') + '</p></iframe>'
						});
						// To disable Product's details window for product variations
						if(record.get('post_parent') == 0){
							productsDetailsWindow.show('editLink');
						}
					
					// show Inherit option only for the product variations otherwise show only Published & Draft 	
					}else if(columnIndex == publishColumnIndex || columnIndex == nameColumnIndex || columnIndex == salePriceFromColumnIndex || columnIndex == salePriceToColumnIndex || columnIndex == descColumnIndex || columnIndex == addDescColumnIndex || columnIndex == visibilityColumnIndex || columnIndex == taxStatusColumnIndex){
						if(fileExists == 1){
							if(record.get('post_parent') == 0){
								productsColumnModel.setEditable(columnIndex,true);
								productsColumnModel.getColumnById('publish').editor = newProductStatusCombo;
							}else{
								productsColumnModel.getColumnById('publish').editor = productStatusCombo;
								productsColumnModel.setEditable(columnIndex,false);
							}
						}
					} else if (columnIndex == visibilityColumnIndex){
						if(fileExists == 1){
							if(record.get('post_parent') == 0){
								productsColumnModel.setEditable(columnIndex,true);
                                productsColumnModel.getColumnById('visibility').editor = visibilityCombo;   // Tarun
							}else{
                                productsColumnModel.getColumnById('visibility').editor = visibilityCombo;   // Tarun
								productsColumnModel.setEditable(columnIndex,false);
							}
						}
					} else if (columnIndex == taxStatusColumnIndex){
						if(fileExists == 1){
							if(record.get('post_parent') == 0){
								productsColumnModel.setEditable(columnIndex,true);
                                productsColumnModel.getColumnById('taxStatus').editor = taxStatusCombo;
							}else{
                                productsColumnModel.getColumnById('taxStatus').editor = taxStatusCombo;
								productsColumnModel.setEditable(columnIndex,false);
							}
						}
					} else if ( columnIndex == editImageColumnIndex ) {
						var productsImageWindow = new Ext.Window({
							collapsible:true,
							shadow : true,
							shadowOffset: 10,
							title: getText('Manage your Product Images'),
							width: 700,
							height: 400,
							minimizable: false,
							maximizable: true,
							maximized: false,
							resizeable: true,
							animateTarget: 'image',
							listeners: {
								beforeshow: function() {
									if ( fileExists != 1 )
										this.setTitle( getText('Manage your Product Images - Available only in Pro version') );
								},
								maximize: function () {
									this.setPosition( 0, 30 );
								},
								show: function () {
									this.setPosition( 250, 30 );
								},
								close: function() {
									var object = {
										url:jsonURL
										,method:'post'
										,callback: function(options, success, response)	{
											var myJsonObj = Ext.decode(response.responseText);
											record.set("thumbnail", myJsonObj);
											record.commit();
										}
										,scope:this
										,params:
										{
											cmd:'editImage',
											id: record.id,
											incVariation: SM.incVariation
										}
									};
									Ext.Ajax.request(object);
								}
							},
							html: ( fileExists == 1 ) ? '<iframe src="'+ site_url + '/wp-admin/media-upload.php?post_id=' + record.id +'&type=image&tab=library&" style="width:100%;height:100%;border:none;"><p> ' + getText('Your browser does not support iframes.') + '</p></iframe>' : ''
						});
						productsImageWindow.show('image');
					}
				}
				else if(SM.activeModule == 'Customers'){
					if(fileExists == 1){
						if(columnIndex == totalPurchasedColumnIndex){
							checkModifiedAndshowDetails(record,rowIndex);
						}else if(columnIndex == lastOrderColumnIndex){
							billingDetailsIframe(record.json.id);
						}
					}
				}
			}catch(e) {
				var err = e.toString();
				Ext.notification.msg('Error', err);
			}
		},
		// Fires before a cell is clicked
		// depending on the selected country load the corresponding regions in the region combo box
		cellmousedown : function(editorGrid,rowIndex, columnIndex, e) {
			SM.activeRecord = editorGrid.getStore().getAt(rowIndex);
			// Get field name for the column
			SM.curDataIndex = editorGrid.getColumnModel().getDataIndex(columnIndex);
			var curCountry;
			
			if(SM.activeModule == 'Customers'){
				if(fileExists == 1){
					var bill_country = SM.activeRecord.data['_billing_country'];
					var curCountry;
					    
						if(SM.curDataIndex == '_billing_country' || SM.curDataIndex == '_billing_state') {
							curCountry = bill_country;
						}
				}
			}else if(SM.activeModule == 'Orders') {
				var ship_country = SM.activeRecord.data['_shipping_country'];
				
				if(SM.curDataIndex == '_shipping_country' || SM.curDataIndex == '_shipping_state') {
					 curCountry = ship_country;
				}
			}
		},
		// Fires when the grid view is available.
		// This happens only for the first time when the page is rendered with the editorgrid panel.
		// From here the flow of the code starts.
		viewready: function(grid){
            showSelectedModule( SM.dashboardComboBox.getValue() );
			SM.dashboardComboBox.setValue( getText( SM.dashboardComboBox.getValue() ) );

		},
		// Fires when the grid is reconfigured with a new store and/or column model.
		// state of the editor grid is captured and applied to back to the grid.
		reconfigure : function(grid,store,colModel ){
			var editorGridStateId = grid.getStateId();
			var state = Ext.state.Manager.get(editorGridStateId);
			
			grid.fireEvent('beforestaterestore', editorGrid, state);
			grid.applyState(Ext.apply({}, state));
			grid.fireEvent('staterestore', editorGrid, state);
		},
		// after each edit record enable the save button.
		afteredit: function(e) {
			pagingToolbar.saveButton.enable();
		}
	}
});

for(var i=2;i<=8;i++)
editorGrid.getTopToolbar().get(i).hide();
SM.typeColIndex   = productsColumnModel.findColumnIndex(SM.productsCols.post_parent.colName);

//For pro version check if the required file exists
if(fileExists == 1){
	batchUpdateWindow.title = getText('Batch Update'); 
}else{	
	batchUpdateRecords = function () {
		Ext.notification.msg('Smart Manager', getText('Batch Update feature is available only in Pro version') );
	};
	
	//disable inline editing for products
	var productsColumnCount = productsColumnModel.getColumnCount();
	for(var i=6; i<productsColumnCount; i++)
	productsColumnModel.setEditable(i,false);
	
	//disable inline editing for customers
	var customersColumnCount = customersColumnModel.getColumnCount();
	for(var i=1; i<customersColumnCount; i++)
		customersColumnModel.setEditable(i,false);	
}

	}catch(e){
		var err = e.toString();
		Ext.notification.msg('Error', err);
		return;
	}
});