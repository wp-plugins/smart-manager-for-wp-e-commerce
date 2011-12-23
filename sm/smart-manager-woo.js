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
	cellClicked        = false,  	  //flag to check if any cell is clicked in the editor grid.
	search_timeout_id  = 0, 		  //timeout for sending request while searching.
	colModelTimeoutId  = 0, 		  //timeout to reconfigure the grid.
	limit 			   = 100,		  //per page records limit.
	editorGrid         = '',
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
	Ext.util.Format.comboRenderer = function(dimensionCombo){
		return function(value){
			var record = dimensionCombo.findRecord(dimensionCombo.valueField, value);
			return record ? record.get(dimensionCombo.displayField) : dimensionCombo.valueNotFoundText;
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
			type: 'float',
			align: 'right',
			sortable: true,
			dataIndex: SM.productsCols.price.colName,
			tooltip: 'Price',
			renderer: amountRenderer,
			editor: new fm.NumberField({
				allowBlank: false,
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
				allowBlank: false,
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
				allowBlank: false,
				allowNegative: false
			})
		},{
			header: SM.productsCols.sku.name,
			id: 'sku',
			sortable: true,
			dataIndex: SM.productsCols.sku.colName,
			tooltip: 'SKU',
			editor: new fm.TextField({
				allowBlank: false
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
				allowBlank: false,
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
				autoHeight: true
			})
		},{
			header: SM.productsCols.addDesc.name,
			id: 'addDesc',
			hidden: true,
			dataIndex: SM.productsCols.addDesc.colName,
			tooltip: 'Additional Description',
			width: 180,
			editor: new fm.TextArea({
				autoHeight: true
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
				allowBlank: false,
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
				allowBlank: false,
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
				allowBlank: false,
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
				{name: SM.productsCols.price.colName,             type: 'float'},
				{name: SM.productsCols.salePrice.colName,         type: 'int'},
				{name: SM.productsCols.salePriceFrom.colName,     type: 'date', dateFormat: 'Y-m-d'},
				{name: SM.productsCols.salePriceTo.colName,       type: 'date', dateFormat: 'Y-m-d'},
				{name: SM.productsCols.inventory.colName,         type: 'string'},
				{name: SM.productsCols.publish.colName,           type: 'string'},
				{name: SM.productsCols.salePrice.colName,         type: 'float'},
				{name: SM.productsCols.sku.colName,               type: 'string'},
				{name: SM.productsCols.group.colName,             type: 'string'},
				{name: SM.productsCols.desc.colName,              type: 'string'},
				{name: SM.productsCols.addDesc.colName,           type: 'string'},
				{name: SM.productsCols.weight.colName,            type: 'float'},
				{name: SM.productsCols.height.colName,            type: 'float'},
				{name: SM.productsCols.width.colName,             type: 'float'},
				{name: SM.productsCols.lengthCol.colName,         type: 'float'},
				{name: SM.productsCols.post_parent.colName,	      type: 'int'}
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
				var pageTotalRecord = editorGrid.getStore().getCount();		
				var selectedRecords=editorGridSelectionModel.getCount();
				if( selectedRecords >= pageTotalRecord){		
					batchRadioToolbar.setVisible(true);
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
			store = productsStore;
			saveRecords(store,pagingToolbar,jsonURL,editorGridSelectionModel);
		}}
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

						store = productsStore;

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
			SM.activeModule = 'Products';
			showProductsView();
	};
	
	// Products, Customers and Orders combo box
	SM.dashboardComboBox = new Ext.form.ComboBox({
		id: 'dashboardComboBox',
		stateId : 'dashboardComboBox',
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
			fields: ['id', 'fullname'],
			data: [
				[0, 'Products']
			]
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
			
			beforerender: function() {
				this.value = 'Products';
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
		}, 500);
	}}
});

var searchLogic = function () {
	//START setting the params to store if search fields are with values (refresh event)
		productsStore.setBaseParam('searchText', SM.searchTextField.getValue());

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
					productsStore.loadData(myJsonObj)
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
						var comboCategoriesActionCmp = toolbarParent.get(4);
						var setTextfield = toolbarParent.get(5);
						var comboActionCmp = toolbarParent.get(2);
						objRegExp = /(^-?\d\d*\.\d*$)|(^-?\d\d*$)|(^-?\.\d\d*$)/;;
						regexError = 'Only numbers are allowed';
						
							if(SM['productsCols'][this.value] != undefined ){
								var categoryActionType = SM['productsCols'][this.value].actionType;
							}							
							if (field_type == 'category' || categoryActionType == 'category_actions') {
								setTextfield.hide();
								comboCategoriesActionCmp.show();
								comboCategoriesActionCmp.reset();
							}else if (field_type == 'string') {
								setTextfield.hide();
								comboCategoriesActionCmp.hide();
							} else if (field_name == 'Stock: Quantity Limited' || field_name == 'Publish' || field_name == 'Stock: Inform When Out Of Stock' || field_name == 'Disregard Shipping') {								
								setTextfield.hide();
								comboCategoriesActionCmp.hide();
							}else if (field_name == 'Weight' || field_name == 'Variations: Weight'||field_name == 'Height' ||field_name == 'Width' ||field_name == 'Length') {
								setTextfield.show();
								comboCategoriesActionCmp.hide();
							}
							else if(field_name == 'Orders Status' || field_name.indexOf('Country') != -1){
								if(field_name.indexOf('Country') != -1) {
									actions_index = 'bigint';
								}else{
									actions_index = field_type;
								}
								setTextfield.hide();
							} else {
								setTextfield.show();
								if (field_type == 'blob' || field_type == 'modStrActions') {
									objRegExp = '';
									regexError = '';
								}
								comboCategoriesActionCmp.hide();
								actions_index = field_type;
							}
						if(SM.activeModule == 'Products'){
							actionStore.loadData(actions[SM['productsCols'][this.value].actionType]);
						}
						setTextfield.reset();
						comboActionCmp.reset();
						
						// @todo apply regex accordign to the req
						setTextfield.regex = objRegExp;
						setTextfield.regexText = regexError;						
					}
				}
			}, '',
			{
				xtype: 'combo',
				width: 180,
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
							
								// on swapping between the toolbars	
							actionStore.loadData(actions[SM['productsCols'][selectedValue].actionType]);
						},					
					select: function() {
						var toolbarParent      = this.findParentByType(batchUpdateToolbarInstance, true);
						var comboFieldCmp      = toolbarParent.get(0);
						var comboactionCmp     = toolbarParent.get(2);
						var selectedFieldIndex = comboFieldCmp.selectedIndex;
						var selectedValue      = comboFieldCmp.value;
						var field_name = comboFieldCmp.store.reader.jsonData.items[selectedFieldIndex].name;						
					}
				}
			},'',{
				xtype: 'combo',
				width: 180,
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
							categoryStore.loadData(categories["category-"+SM['productsCols'][selectedValue].colFilter]);
				    }
				}
			},{
				xtype: 'textfield',
				width: 180,
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
				hidden: false,
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
batchUpdateToolbar.get(0).get(7).hide(); //hide delete row icon from first toolbar.

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
					
				store = productsStore;
				cm = productsColumnModel;
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
		    width: 250,
			height: 20,
		    items: [
		    	
		        {boxLabel: 'Selected items', name: 'rb-batch', inputValue: 1, checked: true},
		        {boxLabel: 'All items in store', name: 'rb-batch', inputValue: 2}
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

			firstToolbar.items.items[5].reset();

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


var checkModifiedAndshowDetails = function(record,rowIndex){
	//set a store depending on the active Module
	
	var modifiedRecords = store.getModifiedRecords();
	if(!modifiedRecords.length) {
		
	}else{
		
		var saveModification = function (btn, text) {
			if (btn == 'yes')
			saveRecords(store,pagingToolbar,jsonURL,editorGridSelectionModel);
			store.load();
			
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
			SM.searchTextField,{ icon: imgURL + 'search.png', id:'searchIconId' },
			{xtype: 'tbspacer',width: 50, id:'afterSearchId'}
	],
	scrollOffset: 50,
	listeners: {
		cellclick: function(editorGrid,rowIndex, columnIndex, e) {
			try{
				var record  = editorGrid.getStore().getAt(rowIndex);
				cellClicked = true;
				var editLinkColumnIndex   	  = productsColumnModel.findColumnIndex('edit_url'),
					prodTypeColumnIndex       = productsColumnModel.findColumnIndex('type'),
					publishColumnIndex        = productsColumnModel.findColumnIndex(SM.productsCols.publish.colName);

				if(SM.activeModule == 'Products'){
					if(columnIndex == editLinkColumnIndex) {
						var productsDetailsWindow = new Ext.Window({
							stateId : 'productsDetailsWindow',
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
							listeners: { show: function(t){ storeDetailsWindowState(t,t.stateId); }	},
							html: '<iframe src='+ productsDetailsLink + '' + record.id +' style="width:100%;height:100%;border:none;"><p>Your browser does not support iframes.</p></iframe>'
						});
				
					productsDetailsWindow.show('editLink');
					
					// show Inherit option only for the product variations otherwise show only Published & Draft 	
					}else if(columnIndex == publishColumnIndex){						
						if(fileExists == 1){
							if(record.get('post_parent') == 0){
								productsColumnModel.setEditable(columnIndex,true);
								productsColumnModel.getColumnById('publish').editor = newProductStatusCombo;
							}else{
								productsColumnModel.getColumnById('publish').editor = productStatusCombo;
								productsColumnModel.setEditable(columnIndex,false);
							}
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
	for(var i=3; i<productsColumnCount; i++)
	productsColumnModel.setEditable(i,false);

}

	}catch(e){
		var err = e.toString();
		Ext.notification.msg('Error', err);
		return;
	}
});