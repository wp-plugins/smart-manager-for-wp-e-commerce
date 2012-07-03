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
var actions            = new Array(), //an array for actions combobox in batchupdate window.
	categories         = new Array(), //an array for category combobox in batchupdate window.
	attribute          = new Array(),
	cellClicked        = false,  	  //flag to check if any cell is clicked in the editor grid.
	search_timeout_id  = 0, 		  //timeout for sending request while searching.
	colModelTimeoutId  = 0, 		  //timeout to reconfigure the grid.
	limit 			   = 100,		  //per page records limit.
	editorGrid         = '',
	weightUnitStore    = '',
	showOrdersView     = '',
	countriesStore     = '';

//creating an array of actions to be used in the actions combobox in batch update window.
actions['blob']   = [{'id': 0,'name': 'set to','value': 'SET_TO'},
				     {'id': 1,'name': 'append','value': 'APPEND'},
				     {'id': 2,'name': 'prepend','value': 'PREPEND'}];

actions['bigint'] = [{'id': 0,'name': 'set to','value': 'SET_TO'}];

actions['real']   = [{'id': 0,'name': 'set to','value': 'SET_TO'},
				     {'id': 1,'name': 'increase by %','value': 'INCREASE_BY_%'},
				     {'id': 1,'name': 'decrease by %','value': 'DECREASE_BY_%'},
				     {'id': 2,'name': 'increase by number','value': 'INCREASE_BY_NUMBER'},
				     {'id': 3,'name': 'decrease by number','value': 'DECREASE_BY_NUMBER'}];

actions['int']    = [{'id': 0,'name': 'set to','value': 'SET_TO'},
				     {'id': 1,'name': 'increase by number','value': 'INCREASE_BY_NUMBER'},
				     {'id': 2,'name': 'decrease by number','value': 'DECREASE_BY_NUMBER'}];

actions['float']  = [{'id': 0,'name': 'set to','value': 'SET_TO'},
			         {'id': 1,'name': 'increase by number','value': 'INCREASE_BY_NUMBER'},
			         {'id': 2,'name': 'decrease by number','value': 'DECREASE_BY_NUMBER'}];

actions['string'] = [{'id': 0,'name': 'Yes','value': 'YES'},
					 {'id': 1,'name': 'No','value': 'NO'}];

actions['category_actions'] = [{'id': 0,'name': 'set to','value': 'SET_TO'},
							   {'id': 1,'name': 'add to','value': 'ADD_TO'},
							   {'id': 2,'name': 'remove from','value': 'REMOVE_FROM'}];

actions['modStrActions']   = [[ 0, 'set to', 'SET_TO'],
                              [ 1, 'append', 'APPEND'],
                              [ 2, 'prepend', 'PREPEND']];

actions['setStrActions']   = [[ 0,'set to', 'SET_TO']];

actions['setAdDelActions'] = [[0, 'set to', 'SET_TO'],
                              [1, 'add to', 'ADD_TO'],
                              [2, 'remove from', 'REMOVE_FROM']];

actions['modIntPercentActions']   = [[0, 'set to', 'SET_TO'],
                                     [1, 'increase by %', 'INCREASE_BY_%'],
                                     [2, 'decrease by %', 'DECREASE_BY_%'],
                                     [3, 'increase by number','INCREASE_BY_NUMBER'],
                                     [4, 'decrease by number', 'DECREASE_BY_NUMBER']];

actions['modIntActions']   		  = [[0, 'set to', 'SET_TO'],
                              		 [1, 'increase by number','INCREASE_BY_NUMBER'],
                              		 [2, 'decrease by number', 'DECREASE_BY_NUMBER']];

actions['YesNoActions']   		  = [[0,'Yes','YES'],
                             		 [1,'No','NO']];

actions['category_actions'] 	  = [[0, 'set to','SET_TO'],
							   		 [1,'add to','ADD_TO'],
							   		 [2,'remove from','REMOVE_FROM']];

Ext.onReady(function () {
	try{
		if(wpsc_woo != 1){
			//Stateful
			Ext.state.Manager.setProvider(new Ext.state.CookieProvider({
				expires: new Date(new Date().getTime()+(1000*60*60*24*30)), //30 days from now
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
	SM.colModelTimeoutId = '';		
	SM.activeModule      = 'Products'; //default module selected.
	SM.activeRecord      = '';
	SM.curDataIndex      = '';
	SM.incVariation      = false;
	SM.typeColIndex 	 = '';
	
	//fm used as a short form for Ext.form
	var fm 		     = Ext.form,
		toolbarCount =  1,
		cnt 		 = -1,    //for checkboxSelectionModel.
		cnt_array 	 = [];	 //for checkboxSelectionModel.
	
	//Regex to allow only numbers.
	var objRegExp = /(^-?\d\d*\.\d*$)|(^-?\d\d*$)|(^-?\.\d\d*$)/;
	var regexError = 'Only numbers are allowed';
		
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
					
					if(pagingToolbar.hasOwnProperty('deleteButton'))
					pagingToolbar.deleteButton.enable();
					
					if(pagingToolbar.hasOwnProperty('printButton'))
					pagingToolbar.printButton.enable();
				} else {					
					pagingToolbar.batchButton.disable();
					
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
				text      : 'Add Product',
				tooltip   : 'Add a new product',
				icon      : imgURL + 'add.png',
				disabled  : true,
				hidden    : false,
				id 	 	  : 'addProductButton',
				ref 	  : 'addProductButton',
				listeners : {
					click : function() {
						productsColumnModel.getColumnById('publish').editor = newProductStatusCombo;
						if(fileExists == 1){
							addProduct(productsStore, cnt_array, cnt, newCatName);
						}else{
							Ext.notification.msg('Smart Manager', 'Add product feature is available only in Pro version');
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
				text: 'Print',
				tooltip: 'Print Packing Slips',
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
							Ext.notification.msg('Smart Manager', 'Print Preview feature is available only in Pro version');
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
				text: 'Delete',
				tooltip: 'Delete the selected items',
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

	var productsColumnModel = new Ext.grid.ColumnModel({
		columns: [editorGridSelectionModel,
		{
			header: '',
			id: 'type',
			dataIndex: SM.productsCols.post_parent.colName,
			tooltip: 'Type',
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
			tooltip: 'Product Images',
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
			tooltip: 'Product Name',
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
			tooltip: 'Price',
			renderer: amountRenderer,
			editor: new fm.NumberField({
				allowBlank: true,
				allowNegative: false
			})
		},{
			header: SM.productsCols.salePrice.name,
			id: 'salePrice',
			sortable: true,
			align: 'right',
			dataIndex: SM.productsCols.salePrice.colName,
			renderer: amountRenderer,
			tooltip: 'Sale Price',
			editor: new fm.NumberField({
				allowBlank: true,
				allowNegative: false
			})
		},{
            header: SM.productsCols.salePriceFrom.name,
            id: 'salePriceFrom',
			sortable: true,
			tooltip: 'Sale Price From',
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
			tooltip: 'Sale Price To',
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
			tooltip: 'Inventory',
			editor: new fm.NumberField({
				allowBlank: true,
				allowNegative: false
			})
		},{
			header: SM.productsCols.sku.name,
			id: 'sku',
			sortable: true,
			dataIndex: SM.productsCols.sku.colName,
			tooltip: 'SKU',
			editor: new fm.TextField({
				allowBlank: true
			})
		},{
			header: SM.productsCols.group.name,
			id: 'group',
			sortable: true,
			dataIndex: SM.productsCols.group.colName,
			tooltip: 'Category'
		},{
			header: SM.productsCols.weight.name,
			id: 'weight',
			colSpan: 2,
			sortable: true,
			align: 'right',
			dataIndex: SM.productsCols.weight.colName,
			tooltip: 'Weight',
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
			tooltip: 'Product Status',
			renderer: Ext.util.Format.comboRenderer(productStatusCombo)
		},{
			header: SM.productsCols.desc.name,
			id: 'desc',
			dataIndex: SM.productsCols.desc.colName,
			tooltip: 'Description',
			width: 180,
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
			tooltip: 'Additional Description',
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
			tooltip: 'Height',			
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
			tooltip: 'Width',
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
			tooltip: 'Length',			
			renderer: amountRenderer,
			editor: new fm.NumberField({
				allowBlank: true,
				allowNegative: false
			})
		},{
			header: 'Edit',
			id: 'edit',
			sortable: true,
			tooltip: 'Product Info',
			dataIndex: 'edit_url',
			width: 50,
			id: 'editLink',
			renderer: function (value, metaData, record, rowIndex, colIndex, store) {
				return '<img id=editUrl src="' + imgURL + 'edit.gif"/>';
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
				{name: SM.productsCols.image.colName,	      	  type: 'string'}
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
				
			}
		}
	});

	var showProductsView = function(){
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
		text: 'Batch Update',
		tooltip: 'Update selected items',
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
		text: 'Save',
		tooltip: 'Save all Changes',
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
		text: 'Export CSV',
		tooltip: 'Download CSV file',
		icon: imgURL + 'export_csv.gif',
		id: 'exportCsvButton',
		ref: 'exportButton',
		scope: this,
		listeners: { 
			click: function () { 
				if ( fileExists != 1 ) {
					Ext.notification.msg('Smart Manager', '"Export CSV" feature is available only in Pro version');
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
	emptyMsg: SM.activeModule+' list is empty'
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

	// Function to delete selected records
	var deleteRecords = function () {
		var selected  = editorGrid.getSelectionModel();
		var records   = selected.selections.keys;
		var getDeletedRecords = function (btn, text) {
			if (btn == 'yes') {
				var o = {
					url: jsonURL,
					method: 'post',
					callback: function (options, success, response) {

						if(SM.activeModule == 'Products')
						store = productsStore;
						else if(SM.activeModule == 'Orders')
						store = ordersStore;

						var myJsonObj    = Ext.decode(response.responseText);
						var delcnt       = myJsonObj.delCnt;
						var totalRecords = productsJsonReader.jsonData.totalCount;
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
		if (records.length == 1)
		var msg = 'Are you sure you want to delete the selected record?';
		else
		var msg = 'Are you sure you want to delete the selected records?';

		Ext.Msg.show({
			title: 'Confirm File Delete',
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
	
	// Products, Customers and Orders combo box
	SM.dashboardComboBox = new Ext.form.ComboBox({
		id: 'dashboardComboBox',
		stateId : 'dashboardComboBoxWoo',
		stateEvents : ['added','beforerender','enable','select','change','show','beforeshow'],
		stateful: true,
		getState: function(){ return { value: this.getValue()}; },
		applyState: function(state) {
			this.setValue(state.value);
			pagingToolbar.emptyMsg =  state.value+' list is empty';
		},
		store: new Ext.data.ArrayStore({
			autoDestroy: true,
			forceSelection: true,
			fields: ['id', 'fullname']
		}),
		displayField: 'fullname',
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
				pagingToolbar.emptyMsg = this.getValue()+' list is empty';
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
						title: 'Confirm Save',
						msg: 'Do you want to save the modified records?',
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
	emptyText: 'Search...',
	enableKeyEvents: true,
	listeners: {
		keyup: function () {
			if ( fileExists != 1 ) {
				Ext.notification.msg('Smart Manager', 'Search feature is available only in Pro version');
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
					title: 'Confirm Save',
					msg: 'Do you want to save the modified records?',
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
				else if(SM.activeModule == 'Orders')
					ordersStore.loadData(myJsonObj);
				else
					customersStore.loadData(myJsonObj);
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
	msg: "Please wait..."
});

var batchMask = new Ext.LoadMask(Ext.getBody(), {
	msg: "Please wait..."
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
				emptyText: 'Select a field...',
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
						regexError = 'Only numbers are allowed';
						
							if(SM['productsCols'][this.value] != undefined ){
								var categoryActionType = SM['productsCols'][this.value].actionType;
							}							
							if (field_type == 'category' || categoryActionType == 'category_actions') {
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
								comboCategoriesActionCmp.show();
								comboCategoriesActionCmp.reset();
							} else if (field_name.indexOf('Country') != -1) {
								actions_index = 'bigint';
								setTextfield.hide();
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
				emptyText: 'Select an action...',
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
								textField1Cmp.emptyText = 'Enter Attribute Name...';
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
				emptyText: 'Select a value...',
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
				emptyText: 'Enter the value...',
				cls: 'searchPanel',
				hidden: true,
				selectOnFocus: true,
				listeners: {
					beforerender: function( cmp ) {
						cmp.emptyText = 'Enter the value...';
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
				emptyText: 'Select a category...',
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
						var selectedFieldIndex = comboFieldCmp.selectedIndex;
						var selectedValue      = comboFieldCmp.value;
						var field_name		   = toolbarParent.items.items[0].store.reader.jsonData.items[selectedFieldIndex].name;
						
						if (SM.activeModule == 'Products') {
							if ( selectedValue.substring( 0, 14 ) != 'groupAttribute' ){
								categoryStore.loadData(categories["category-"+SM['productsCols'][selectedValue].colFilter]);
							}
						} else if(SM.activeModule == 'Orders' && field_name == 'Order Status') {
							this.store = orderStatusStore;
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
				emptyText: 'Enter values...',
				cls: 'searchPanel',
				hidden: true,
				listeners: {
					render: function( cmp ) {
						Ext.QuickTips.register({
						    target: cmp.getEl(),
						    title: 'Important:',
						    text: 'For more than one values, use pipe (|) as delimiter'
						});
					}
				},
				selectOnFocus: true
			}, '->',
			{
				icon: imgURL + 'del_row.png',
				tooltip: 'Delete Row',
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
		text: 'Add Row',
		tooltip: 'Add a new row',
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
		text: 'Update',
		id: 'updateButton',
		ref: 'updateButton',
		tooltip: 'Apply all changes',
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
		    text: 'Update...'
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
	title: 'Batch Update - available only in Pro version',
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
		html: '<iframe src='+ ordersDetailsLink + '' + recordId +'&action=edit style="width:100%;height:100%;border:none;"><p>Your browser does not support iframes.</p></iframe>'
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
			title: 'Confirm Save',
			msg: 'Do you want to save the modified records?',
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
	
	clearTimeout(SM.colModelTimeoutId);
	SM.colModelTimeoutId = showCustomersView.defer(100,this,[emailId]);
	SM.searchTextField.setValue(emailId);
};

// ============ Customers ================

	var customersColumnModel = new Ext.grid.ColumnModel({	
		columns:[editorGridSelectionModel, //checkbox for
		{
			header: 'First Name',
			id: '_billing_first_name',
			dataIndex: '_billing_first_name',
			tooltip: 'Billing First Name',
			editable: false,
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 150
		},{
			header: 'Last Name',
			id: '_billing_last_name',
			dataIndex: '_billing_last_name',
			tooltip: 'Billing Last Name',
			editable: false,
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 150
		},{
			header: 'Email',
			id: '_billing_email',
			dataIndex: '_billing_email',
			tooltip: 'Email Address',
			editable: false,
			editor: new fm.TextField({
				allowBlank: true,
				allowNegative: false
			}),
			width: 200
		},{
			header: 'Address',
			id: '_billing_address',
			dataIndex: '_billing_address',
			tooltip: 'Billing Address',
			editable: false,
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 200
		},{
			header: 'Postal Code',
			id: '_billing_postcode',
			dataIndex: '_billing_postcode',
			tooltip: 'Billing Postal Code',
			editable: false,
			editor: new fm.TextField({
				allowBlank: true,
				allowNegative: false
			}),
			width: 150
		},{
			header: 'City',
			id: '_billing_city',
			dataIndex: '_billing_city',
			tooltip: 'Billing City',
			align: 'left',
			editable: false,
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 150
		},
		{
			header: 'Region',
			id: '_billing_state',
			dataIndex: '_billing_state',
			tooltip: 'Billing Region',
			align: 'center',
			width: 100
		},
		{
			header: 'Country',
			id: '_billing_country',
			dataIndex: '_billing_country',
			tooltip: 'Billing Country',
			width: 120
		},
		{
			header: 'Total Purchased',
			id: 'total_purchased', //@todo: change the id to Total_Purchased
			dataIndex: '_order_total',
			tooltip: 'Total Purchased',
			align: 'right',
			width: 150			
		},{
			header: 'Last Order',
			id: 'last_order',
			dataIndex: 'last_order',
			tooltip: 'Last Order Details',
			width: 220			
		},{   
			header: 'Phone Number',
			id: '_billing_phone',
			dataIndex: '_billing_phone',
			tooltip: 'Phone Number',
			editable: false,
			editor: new fm.TextField({
				allowBlank: true,
				allowNegative: false
			}),
			width: 180		
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
		{name:'last_order', type:'string'}
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
				Ext.notification.msg('Smart Manager', '"Filter through Date" feature is available only in Pro version');
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
				Ext.notification.msg('Smart Manager', '"Filter through Date" feature is available only in Pro version');
				return;
			}
			toDateTxt.setValue(date.format('M j Y'));
			searchLogic();
		},
		maxDate: now
	});

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

	var ordersColumnModel = new Ext.grid.ColumnModel({	
		columns:[editorGridSelectionModel, //checkbox for
		{
			header: 'Order Id',
			id: 'id',
			dataIndex: 'id',
			tooltip: 'Order Id'
		},{
			header: 'Date / Time',
			id: 'date',
			dataIndex: 'date',
			tooltip: 'Date / Time',
			width: 250
		},{
			header: 'Name',
			id: 'name',
			dataIndex: 'name',
			tooltip: 'Customer Name',
			width: 350
		},{
			header: 'Amount',
			id: '_order_total',
			dataIndex: '_order_total',
			tooltip: 'Amount',
			align: 'right',
			renderer: amountRenderer,
			width: 100
		},{
			header: 'Details',
			id: 'details',
			dataIndex: 'details',
			tooltip: 'Details',
			width: 100
		},{
			header: 'Payment Method',
			id: '_payment_method',
			dataIndex: '_payment_method',
			tooltip: 'Payment Method',
			align: 'left',
			width: 110
		},{
			header: 'Status',
			id: 'order_status',
			dataIndex: 'order_status',
			tooltip: 'Order Status',
			width: 150,
			editable: true,
			editor: orderStatusCombo,
			renderer: Ext.util.Format.comboRenderer(orderStatusCombo)
		},{
			header: 'Shipping Method',
			id: '_shipping_method',
			dataIndex: '_shipping_method',
			tooltip: 'Shipping Method',
			width: 180
		},{   
			header: 'Shipping First Name',
			id: '_shipping_first_name',
			dataIndex: '_shipping_first_name',
			tooltip: 'Shipping First Name',
			hidden: true,
			editable: false,
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 200
		},{   
			header: 'Shipping Last Name',
			id: '_shipping_last_name',
			dataIndex: '_shipping_last_name',
			tooltip: 'Shipping Last Name',
			hidden: true,
			editable: false,
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 200
		},{   
			header: 'Shipping Address',
			id: '_shipping_address',
			dataIndex: '_shipping_address',
			tooltip: 'Shipping Address',
			hidden: true,
			editable: false,
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 200		
		},{
			header: 'Shipping Postal Code',
			id: '_shipping_postcode',
			dataIndex: '_shipping_postcode',
			tooltip: 'Shipping Postal Code',
			hidden: true,
			editable: false,
			editor: new fm.TextField({
					allowBlank: true,
					allowNegative: false
			}),
			width: 200
		},{   
			header: 'Shipping City',
			id: '_shipping_city',
			dataIndex: '_shipping_city',
			tooltip: 'Shipping City',
			hidden: true,
			editable: false,
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 200
		},
		{   
			header: 'Shipping Region',
			id: '_shipping_state',
			dataIndex: '_shipping_state',
			tooltip: 'Shipping Region',
			align: 'center',
			hidden: true,
			width: 100		
		},
		{
			header: 'Shipping Country',
			id: '_shipping_country',
			dataIndex: '_shipping_country',
			tooltip: 'Shipping Country',
			hidden: true,
			width: 120
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
		{name:'_shipping_postcode', type:'string'}
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
			{xtype: 'tbspacer',width: 50, id:'afterSearchId'}
			,
			{ 
				xtype: 'checkbox',
				id:'incVariation',
				name: 'incVariation',
				stateEvents : ['added','check'],
				stateful: true,
				getState: function(){ return { value: this.getValue()}; },
				applyState: function(state) { this.setValue(state.value);},
			 	boxLabel: 'Show Variations',
			 	listeners: {
			 		check : function(checkbox, bool) {
			 			if(fileExists == 1){
			 				SM.incVariation  = bool;
			 				productsStore.setBaseParam('incVariation', SM.incVariation);
			 				getVariations(productsStore.baseParams,productsColumnModel,productsStore);
			 			}else{
			 				Ext.notification.msg('Smart Manager', 'Show Variations feature is available only in Pro version');
			 			}
			 		}
			 	}
			}],
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
									dashboardComboStore.push(new Array(i, myJsonObj[i].slice(0,9)));
									dashboardComboStore.push(new Array(i+1, myJsonObj[i].slice(10)));
								} else {
									dashboardComboStore.push(new Array(i, myJsonObj[i]));
								}
								
							}
							if ( dashboardComboStore < 1) {
								Ext.Msg.show({
									title: 'Access Denied',
									msg: 'You don\'t have sufficient permission to view this page',
									buttons: Ext.MessageBox.OK,
									fn: function() {
										location.href = 'index.php';
									},
									icon: Ext.MessageBox.WARNING
								});
							} else {
//								SM.dashboardComboBox.setValue(dashboardComboStore[0][1]);
//								grid.cm = eval(SM.dashboardComboBox.value.toLowerCase()+'ColumnModel');
//								grid.store = eval(SM.dashboardComboBox.value.toLowerCase()+'Store');
//								grid.stateId = SM.dashboardComboBox.value.toLowerCase()+'EditorGridPanelWoo';
											
								SM.dashboardComboBox.store.loadData(dashboardComboStore);
								showSelectedModule(SM.dashboardComboBox.value);	
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
					orderDetailsColumnIndex   = ordersColumnModel.findColumnIndex('details');					
					publishColumnIndex        = productsColumnModel.findColumnIndex(SM.productsCols.publish.colName);
					nameColumnIndex			  = productsColumnModel.findColumnIndex(SM.productsCols.name.colName);
					salePriceFromColumnIndex  = productsColumnModel.findColumnIndex(SM.productsCols.salePriceFrom.colName);
					salePriceToColumnIndex    = productsColumnModel.findColumnIndex(SM.productsCols.salePriceTo.colName);
					descColumnIndex        	  = productsColumnModel.findColumnIndex(SM.productsCols.desc.colName);
					addDescColumnIndex        = productsColumnModel.findColumnIndex(SM.productsCols.addDesc.colName);

				if(SM.activeModule == 'Orders'){
					if ( fileExists != 1 && ( columnIndex == orderDetailsColumnIndex || columnIndex == nameLinkColumnIndex ) ) {
						Ext.notification.msg('Smart Manager', 'This feature is available only in Pro version');
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
							title: 'Products Details',
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
							html: '<iframe src='+ productsDetailsLink + '' + record.id +' style="width:100%;height:100%;border:none;"><p>Your browser does not support iframes.</p></iframe>'
						});
						// To disable Product's details window for product variations
						if(record.get('post_parent') == 0){
							productsDetailsWindow.show('editLink');
						}
					
					// show Inherit option only for the product variations otherwise show only Published & Draft 	
					}else if(columnIndex == publishColumnIndex || columnIndex == nameColumnIndex || columnIndex == salePriceFromColumnIndex || columnIndex == salePriceToColumnIndex || columnIndex == descColumnIndex || columnIndex == addDescColumnIndex){
						if(fileExists == 1){
							if(record.get('post_parent') == 0){
								productsColumnModel.setEditable(columnIndex,true);
								productsColumnModel.getColumnById('publish').editor = newProductStatusCombo;
							}else{
								productsColumnModel.getColumnById('publish').editor = productStatusCombo;
								productsColumnModel.setEditable(columnIndex,false);
							}
						}
					} else if ( columnIndex == editImageColumnIndex ) {
						var productsImageWindow = new Ext.Window({
							collapsible:true,
							shadow : true,
							shadowOffset: 10,
							title: 'Manage your Product Images',
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
										this.setTitle( 'Manage your Product Images - Available only in Pro version' );
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
							html: ( fileExists == 1 ) ? '<iframe src="'+ site_url + '/wp-admin/media-upload.php?post_id=' + record.id +'&type=image&tab=library&" style="width:100%;height:100%;border:none;"><p>Your browser does not support iframes.</p></iframe>' : ''
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
			showSelectedModule(SM.dashboardComboBox.value);
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
	batchUpdateWindow.title = 'Batch Update';
}else{	
	batchUpdateRecords = function () {
		Ext.notification.msg('Smart Manager', 'Batch Update feature is available only in Pro version');
	};
	
	//disable inline editing for products
	var productsColumnCount = productsColumnModel.getColumnCount();
	for(var i=5; i<productsColumnCount; i++)
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