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
	dimensionUnits     = new Array(), //an array for dimension units combobox in batchupdate window.
	cellClicked        = false,  	  //flag to check if any cell is clicked in the editor grid.
	search_timeout_id  = 0, 		  //timeout for sending request while searching.
	colModelTimeoutId  = 0, 		  //timeout to reconfigure the grid.
	limit 			   = 100,		  //per page records limit.
	editorGrid         = '',
	showOrdersView     = '',
	showCustomersView  = '',
	weightUnitStore    = '',
	countriesStore     = '',
	regionsStore       = '',
	reloadRegionCombo  = '';

Ext.onReady(function () {
		
	try{
		//Stateful
		Ext.state.Manager.setProvider(new Ext.state.CookieProvider({
			expires: new Date(new Date().getTime()+(1000*60*60*24*30)) //30 days from now
		}));
		
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
	
	var actions            = new Array(); //an array for actions combobox in batchupdate window.
	
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

    dimensionUnits    = {'items': [{'id':0 , 'name':getText('inches'), 'value': 'in'},
                                    {'id':1 , 'name':getText('cm'), 'value': 'cm'},
                                    {'id':2 , 'name':getText('meter'), 'value': 'meter'}],
                         'totalCount': 3 };
	
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
				text   	  : getText('Add product'),
				tooltip   : getText('Add a new product'),
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
							Ext.notification.msg('Smart Manager', getText('Add product feature is available only in Pro version') );
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
							Ext.notification.msg('Smart Manager',  getText('Print Preview feature is available only in Pro version') );
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
	Ext.util.Format.comboRenderer = function(dimensionCombo){
		return function(value){
			var record = dimensionCombo.findRecord(dimensionCombo.valueField, value);
			return record ? record.get(dimensionCombo.displayField) : dimensionCombo.valueNotFoundText;
		}
	}
	
	//units combo box for product's shipping details
	var dimensionCombo = new Ext.form.ComboBox({
		typeAhead: true,
		triggerAction: 'all',
		lazyRender:true,
		editable: false,
		mode: 'local',
		store: new Ext.data.ArrayStore({
			id: 0,
			fields: ['value','name'],
			data: [['in', 'inches'], ['cm', 'cm'], ['meter', 'meter']]
		}),
		valueField: 'value',
		displayField: 'name'
	});

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
	
	//weight combo box for product's shipping details
	var weightUnitCombo = new Ext.form.ComboBox({
		typeAhead: true,
		triggerAction: 'all',
		lazyRender:true,
		editable: false,
		mode: 'local',
		store: new Ext.data.ArrayStore({
			id: 0,
			fields: ['value','name'],
			data: [['pound', getText('Pounds')], ['ounce', getText('Ounces')], ['gram', getText('Grams')], ['kilogram', getText('Kilograms')]]
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
			tooltip: getText('Product Image'),
			width: 20,
			hidden: true,
			renderer: function (value, metaData, record, rowIndex, colIndex, store) {
				return (record.data.thumbnail != 'false' ? '<img id=editUrl width=16px height=16px src="' + record.data.thumbnail + '"/>' : '');
			}
		},
		{
			header: SM.productsCols.name.name,
			id: 'name',
			sortable: true,
			dataIndex: SM.productsCols.name.colName,
			tooltip: getText('Product Name'),
			width: 300,
			editable: true,
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
			tooltip: getText('Price'),
			editable: true,
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
			tooltip: getText('Sale Price'),
			editor: new fm.NumberField({
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
				allowBlank: false,
				allowNegative: false
			})
		},{
			header: SM.productsCols.sku.name,
			id: 'sku',
			sortable: true,
			dataIndex: SM.productsCols.sku.colName,
			tooltip: getText('SKU'),
			editor: new fm.TextField({
				allowBlank: false
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
				allowBlank: false,
				allowNegative: false
			})
		},{
			header: SM.productsCols.weightUnit.name,
			id: 'weightUnit',
			sortable: true,
			hidden: true,
			dataIndex: SM.productsCols.weightUnit.colName,
			tooltip: getText('Weight Unit'),
			editor: weightUnitCombo,
			renderer: Ext.util.Format.comboRenderer(weightUnitCombo)
		},{
			header: SM.productsCols.publish.name,
			id: 'publish',
			sortable: true,
			dataIndex: SM.productsCols.publish.colName,
			tooltip: getText('Product Status'),
			renderer: Ext.util.Format.comboRenderer(productStatusCombo)
		},{
			header: SM.productsCols.disregardShipping.name,
			id: 'disregardShipping',
			hidden: true,
			sortable: true,
			dataIndex: SM.productsCols.disregardShipping.colName,
			tooltip: getText('Disregard Shipping'),
			editor: yesNoCombo,
			renderer: Ext.util.Format.comboRenderer(yesNoCombo)
		},{
			header: SM.productsCols.desc.name,
			id: 'desc',
			dataIndex: SM.productsCols.desc.colName,
			tooltip: getText('Description'), 
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
			tooltip: getText('Additional Description'),
			width: 180,
			editor: new fm.TextArea({
				autoHeight: true,
				grow: true,
				growMax: 10000
			})
		},{
	  		header: SM.productsCols.pnp.name,
	  		id: 'pnp',
	  		hidden: true,
			colSpan: 2,
			sortable: true,
			align: 'right',
			dataIndex: SM.productsCols.pnp.colName,
			tooltip: getText('Local Shipping Fee'),			
			renderer: amountRenderer,
			editor: new fm.NumberField({
				allowBlank: false,
				allowNegative: false
			})
		},{
			header: SM.productsCols.intPnp.name,
			id: 'intPnp',
			hidden: true,
			colSpan: 2,
			sortable: true,
			align: 'right',
			dataIndex: SM.productsCols.intPnp.colName,
			tooltip: getText('International Shipping Fee'),
			renderer: amountRenderer,
			editor: new fm.NumberField({
				allowBlank: false,
				allowNegative: false
			})
		},{
			header: SM.productsCols.height.name,
			id: 'height',
			colSpan: 2,
			hidden: true,
			sortable: true,
			align: 'right',
			dataIndex: SM.productsCols.height.colName,
			tooltip: getText('Height'),		
			renderer: amountRenderer,
			editor: new fm.NumberField({
				allowBlank: false,
				allowNegative: false
			})
		},{
			header: SM.productsCols.heightUnit.name,
			id: 'heightUnit',
			hidden: true,
			sortable: true,
			dataIndex: SM.productsCols.heightUnit.colName,
			tooltip: getText('Height Unit'),
			editor: dimensionCombo,
			renderer: Ext.util.Format.comboRenderer(dimensionCombo)
		},{
			header: SM.productsCols.width.name,
			id: 'width',
			colSpan: 2,
			hidden: true,
			sortable: true,
			align: 'right',
			dataIndex: SM.productsCols.width.colName,
			tooltip: getText('Width'),
			renderer: amountRenderer,
			editor: new fm.NumberField({
				allowBlank: false,
				allowNegative: false
			})
		},{
			header: SM.productsCols.widthUnit.name,
			id: 'widthUnit',
			hidden: true,
			sortable: true,
			dataIndex: SM.productsCols.widthUnit.colName,
			tooltip: getText('Width Unit'),
			editor: dimensionCombo,
			renderer: Ext.util.Format.comboRenderer(dimensionCombo)
		},{
			header: SM.productsCols.lengthCol.name,
			id: 'lengthCol',
			colSpan: 2,
			hidden: true,
			sortable: true,
			align: 'right',
			dataIndex: SM.productsCols.lengthCol.colName,
			tooltip: getText('Length'),			
			renderer: amountRenderer,
			editor: new fm.NumberField({
				allowBlank: false,
				allowNegative: false
			})
		},{
			header: SM.productsCols.lengthUnit.name,
			sortable: true,
			hidden: true,
			id: 'lengthUnit',
			dataIndex: SM.productsCols.lengthUnit.colName,
			tooltip: getText('Length Unit'),
			editor: dimensionCombo,
			renderer: Ext.util.Format.comboRenderer(dimensionCombo)
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
	
	var productsJsonReader = new Ext.data.customJsonReader({
		totalProperty: 'totalCount',
		root: 'items',
		fields: [
				{name: SM.productsCols.id.colName,                type: 'int'},
				{name: SM.productsCols.name.colName,              type: 'string'},
				{name: SM.productsCols.price.colName,             type: 'float'},
				{name: SM.productsCols.salePrice.colName,         type: 'float'},
				{name: SM.productsCols.inventory.colName,         type: 'string'},
				{name: SM.productsCols.publish.colName,           type: 'string'},
				{name: SM.productsCols.sku.colName,               type: 'string'},
				{name: SM.productsCols.group.colName,             type: 'string'},
				{name: SM.productsCols.disregardShipping.colName, type: 'string'},
				{name: SM.productsCols.desc.colName,              type: 'string'},
				{name: SM.productsCols.addDesc.colName,           type: 'string'},
				{name: SM.productsCols.pnp.colName,               type: 'float'},
				{name: SM.productsCols.intPnp.colName,            type: 'float'},
				{name: SM.productsCols.weight.colName,            type: 'float'},
				{name: SM.productsCols.weightUnit.colName,        type: 'string'},
				{name: SM.productsCols.height.colName,            type: 'float'},
				{name: SM.productsCols.heightUnit.colName,        type: 'string'},
				{name: SM.productsCols.width.colName,             type: 'float'},
				{name: SM.productsCols.widthUnit.colName,         type: 'string'},
				{name: SM.productsCols.lengthCol.colName,         type: 'float'},
				{name: SM.productsCols.lengthUnit.colName,        type: 'string'},
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
				
		for(var i=2;i<=8;i++)
		editorGrid.getTopToolbar().get(i).hide();
		editorGrid.getTopToolbar().get('incVariation').show();

		productsStore.load();
		pagingToolbar.bind(productsStore);

		editorGrid.reconfigure(productsStore,productsColumnModel);
		fieldsStore.loadData(productsFields);

		var firstToolbar       = batchUpdatePanel.items.items[0].items.items[0];
		var textfield          = firstToolbar.items.items[5];
		var weightUnitDropdown = firstToolbar.items.items[7];

		weightUnitDropdown.hide();
		weightUnitStore.loadData(weightUnits);
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
					if( selectedRecords >= pageTotalRecord && SM.searchTextField.getValue() == '' ){
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
			else
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
                    src: jsonURL+'?cmd=exportCsvWpsc&incVariation='+SM.incVariation+'&viewCols='+encodeURIComponent(Ext.encode(productsViewCols))+'&searchText='+SM.searchTextField.getValue()+'&fromDate='+fromDateTxt.getValue()+'&toDate='+toDateTxt.getValue()+'&active_module='+SM.activeModule+''
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
		var msg = getText('Are you sure you want to delete the selected record?'); 
		else
		var msg = getText('Are you sure you want to delete the selected records?');

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
	
	// Products, Customers and Orders combo box
	SM.dashboardComboBox = new Ext.form.ComboBox({
		id: 'dashboardComboBox',
		stateId : 'dashboardComboBoxWpsc',
		stateEvents : ['added','beforerender','enable','select','change','show','beforeshow'],
		stateful: true,
		getState: function(){ return { value: this.getValue()}; },
		applyState: function(state) {
			this.setValue(state.value);
			pagingToolbar.emptyMsg =  state.value + ' ' + getText(' list is empty');
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
				editorGrid.stateId = this.value.toLowerCase()+'EditorGridPanelWpsc';

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

//====== common ======

// ============ Customers ================

	countriesStore = new Ext.data.Store({
		reader: new Ext.data.JsonReader({
			idProperty: 'id',
			totalProperty: 'totalCount',
			root: 'items',
			fields: [{ name: 'id'  },
					 { name: 'name' },
					 { name: 'value'},
					 { name: 'country_id'}]
		}),
		autoDestroy: false,
		dirty: false
	});
	countriesStore.loadData(countries);
	
	reloadRegionCombo = function(curCountry) {
		var countryStoreArr = countriesStore.reader.jsonData.items;
		var countryIndex    = 0;
		//resetting the column value to empty of the current record
		if(curCountry != '') {
			for(var i=0;i<=countriesStore.reader.jsonData.totalCount;i++){
				var country = countryStoreArr[i].name; //not to include id w/o countyriID
				if(country == curCountry) {
					var curCountryId = countryStoreArr[i].country_id;
					(regions[curCountryId]!= undefined) ? regionsStore.loadData(regions[curCountryId]) : regionsStore.removeAll(true);
					break;
				}
			}
		}else {
			regionsStore.removeAll(true);
		}
	};
	
	// countries combo box
	var countriesCombo = new Ext.form.ComboBox({
		typeAhead: true,
	    triggerAction: 'all',
	    lazyRender:true,
	    editable: false,
		mode: 'local',
	    store:countriesStore,
	    value: 'value',
	    valueField: 'name',	    
	    displayField: 'name',
	    forceSelection: true,
	    listeners: {
	    	select: function() {
	    		// setting the region of current record to empty
	    		if(SM.curDataIndex == 'billingcountry')
	    			SM.activeRecord.set('billingstate','')
	    		else if(SM.curDataIndex == 'shippingcountry')
	    			SM.activeRecord.set('shippingstate','');

	    		var curCountry = this.value;
	    		reloadRegionCombo(curCountry);
	    	}
		}
	});
	
	regionsStore = new Ext.data.Store({
		reader: new Ext.data.JsonReader({
			idProperty: 'id',
			totalProperty: 'totalCount',
			root: 'items',
			fields: [{ name: 'id'  },
			{ name: 'name' },
			{ name: 'value'},
			{ name: 'region_id'}]
		}),
		autoDestroy: false,
		dirty: false
	});	

	var regionCombo = new Ext.form.ComboBox({
		typeAhead: true,
		triggerAction: 'all',
		lazyRender:true,
		editable: false,
		mode: 'local',
		store:regionsStore,
		value: 'value',
		valueField: 'name',
		displayField: 'name',
		forceSelection: true
	});
	
	if(isWPSC37 == '1'){
		regionEditor = regionCombo;
	}else if(isWPSC38 == '1'){
		var regionEditor = new fm.TextField({
			allowBlank: true,
			allowNegative: false
		});
	}
	
	var customersColumnModel = new Ext.grid.ColumnModel({	
		columns:[editorGridSelectionModel, //checkbox for
		{
			header: getText('First Name'), 
			id: 'billingfirstname',
			dataIndex: 'billingfirstname',
			tooltip: getText('Billing First Name'),
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 150
		},{
			header: getText('Last Name'),
			id: 'billinglastname',
			dataIndex: 'billinglastname',
			tooltip: getText('Billing Last Name'),
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 150
		},{
			header: getText('Email'),
			id: 'billingemail',
			dataIndex: 'billingemail',
			tooltip: getText('Email Address'),
			editor: new fm.TextField({
				allowBlank: true,
				allowNegative: false
			}),
			width: 200
		},{
			header: getText('Address'),
			id: 'billingaddress',
			dataIndex: 'billingaddress',
			tooltip: getText('Billing Address'),
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 200
		},{
			header: getText('Postal Code'), 
			id: 'billingpostcode',
			dataIndex: 'billingpostcode',
			tooltip: getText('Billing Postal Code'),
			editor: new fm.TextField({
				allowBlank: true,
				allowNegative: false
			}),
			width: 150
		},{
			header: getText('City'),
			id: 'billingcity',
			dataIndex: 'billingcity',
			tooltip: getText('Billing City'),
			align: 'left',
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 150
		},
		{
			header: getText('Region'),
			id: 'billingstate',
			dataIndex: 'billingstate',
			tooltip: getText('Billing Region'),
			width: 100
		},
		{
			header: getText('Country'),
			id: 'billingcountry',
			dataIndex: 'billingcountry',
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
			dataIndex: 'Last_Order',
			tooltip: getText('Last Order Details'),
			width: 220			
		},{   
			header: getText('Phone Number'),
			id: 'billingphone',
			dataIndex: 'billingphone',
			tooltip: getText('Phone Number'),
			editor: new fm.TextField({
				allowBlank: true,
				allowNegative: false
			}),
			width: 180		
		},{
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
		customersColumnModel.columns[customersColumnModel.findColumnIndex('Last_Order')].align = 'center';
                customersColumnModel.columns[customersColumnModel.findColumnIndex('count_orders')].align = 'center';
                customersColumnModel.columns[customersColumnModel.findColumnIndex('total_orders')].align = 'center';
	}else{
		totPurDataType = 'float';
//		customersColumnModel.setRenderer(7,amountRenderer);		
	}
	
	// Data reader class to create an Array of Records objects from a JSON packet.
	var customersJsonReader = new Ext.data.customJsonReader({
		totalProperty: 'totalCount',
		root: 'items',
		fields:
		[
		{name:'id',type:'int'},		
		{name:'billingfirstname',type:'string'},		
		{name:'billinglastname',type:'string'},				
		{name:'billingaddress',type:'string'},
		{name:'billingcity', type:'string'},		
		{name:'billingstate', type:'string'},
		{name:'billingcountry', type:'string'},		
		{name:'billingpostcode',type:'string'},
		{name:'billingemail',type:'string'},
		{name:'billingphone', type:'string'},	
		{name:'_order_total',type:totPurDataType},
		{name:'Last_Order', type:'string'},		
		{name:'Old_Email_Id', type: 'string'},
        {name:'count_orders',type:totPurDataType},
        {name:'total_orders',type:totPurDataType}
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
			var countriesDropdown = firstToolbar.items.items[7];
			textfield.show();
			countriesDropdown.hide();
			weightUnitStore.loadData(countries);
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

if(isWPSC38 == '1'){
	var orderStatusCombo = new Ext.form.ComboBox({
		typeAhead: true,
		triggerAction: 'all',
		lazyRender:true,
		editable: false,
		mode: 'local',
		store: new Ext.data.ArrayStore({
			id: 0,
			fields: ['internalname','label','value'],
			data: [
			['incomplete_sale',  'Incomplete Sale',  1],
			['order_received',   'Order Received',   2],
			['accepted_payment', 'Accepted Payment', 3],
			['job_dispatched',   'Job Dispatched',   4],
			['closed_order',     'Closed Order',     5],
			['declined_payment', 'Payment Declined', 6]
			]
		}),
		valueField: 'value',
		displayField: 'label'
	});
}else if(isWPSC37 == '1'){
	var orderStatusCombo = new Ext.form.ComboBox({
		typeAhead: true,
		triggerAction: 'all',
		lazyRender:true,
		mode: 'local',
		store: new Ext.data.ArrayStore({
			id: 0,
			fields: ['label','value'],
			data: [
			['Order Received',   1],
			['Accepted Payment', 2],
			['Job Dispatched',   3],
			['Closed Order',     4]
			]
		}),
		valueField: 'value',
		displayField: 'label'
	});
}

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
			id: 'amount',
			dataIndex: 'amount',
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
			header: getText('Track Id'), 
			id: 'track_id',
			dataIndex: 'track_id',
			tooltip: getText('Track Id'),
			align: 'left',
			editable: false,
			editor: new fm.TextField({
				allowBlank: true,
				allowNegative: false
			}),
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
			header: getText('Orders Notes'),
			id: 'notes',
			dataIndex: 'notes',
			tooltip: getText('Orders Notes'),
			width: 180,
			editable: false,
			editor: new fm.TextArea({				
				autoHeight: true
			})
		},{   
			header: getText('Shipping First Name'),
			id: 'shippingfirstname',
			dataIndex: 'shippingfirstname',
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
			id: 'shippinglastname',
			dataIndex: 'shippinglastname',
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
			id: 'shippingaddress',
			dataIndex: 'shippingaddress',
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
			id: 'shippingpostcode',
			dataIndex: 'shippingpostcode',
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
			id: 'shippingcity',
			dataIndex: 'shippingcity',
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
			id: 'shippingstate',
			dataIndex: 'shippingstate',
			tooltip: getText('Shipping Region'),
			align: 'center',
			hidden: true,
//			editor: regionEditor,
			width: 100		
		},
		{
			header: getText('Shipping Country'),
			id: 'shippingcountry',
			dataIndex: 'shippingcountry',
			tooltip: getText('Shipping Country'),
//			editor:countriesCombo,
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
		{name:'customer_id',type:'int'},
		{name:'date',type:'string'},
		{name:'name',type:'string'},
		{name:'amount', type:'float'},
		{name:'details', type:'string'},
		{name:'track_id',type:'string'},
		{name:'order_status', type:'string'},
		{name:'notes', type:'string'},
		{name:'shippingfirstname', type:'string'},
		{name:'shippinglastname', type:'string'},
		{name:'shippingaddress', type:'string'},
		{name:'shippingcity', type:'string'},
		{name:'shippingcountry', type:'string'},
		{name:'shippingstate', type:'string'},  
		{name:'shippingpostcode', type:'string'}
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
				ordersColumnModel.setEditable(6,true);
				ordersColumnModel.setEditable(8,true);
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
			
			showPrintButton();
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
			var weightUnitDropdown = firstToolbar.items.items[7];
			weightUnitDropdown.show();
			weightUnitStore.loadData(ordersStatus);
			textfield.hide();

		} catch(e) {
			var err = e.toString();
			Ext.notification.msg('Error', err);
		}
	};
	
	// ======= orders =====


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
			if(SM.activeModule == 'Orders')
			store = ordersStore;
			else if(SM.activeModule == 'Products')
			store = productsStore;
			else
			store = customersStore;		
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

//store to populate weightUnits in fifth combobox(weightUnits combobox) on selecting 'weight' from first combobox(field combobox)
//and 'set to' from second combobox(actions combobox).
	weightUnitStore = new Ext.data.Store({
	reader: new Ext.data.JsonReader({
		idProperty: 'id',
		totalProperty: 'totalCount',
		root: 'items',
		fields: [{ name: 'id'  },
				{ name: 'name' },
				{ name: 'value'},
				{ name: 'country_id'}]
	}),
	autoDestroy: false,
	dirty: false
});
weightUnitStore.loadData(weightUnits);
// countries's store

var mask = new Ext.LoadMask(Ext.getBody(), {
	msg: getText("Please wait") + "..." 
});

var batchMask = new Ext.LoadMask(Ext.getBody(), {
	msg: getText("Please wait") + "..."
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
						var actionsData = new Array();
						var toolbarParent = this.findParentByType(batchUpdateToolbarInstance, true);
						var comboCategoriesActionCmp = toolbarParent.get(4);
						var setTextfield = toolbarParent.get(5);
						var comboActionCmp = toolbarParent.get(2);
						var comboWeightUnitCmp = toolbarParent.get(7);						
						var comboRegionCmp = toolbarParent.get(9);
						objRegExp = /(^-?\d\d*\.\d*$)|(^-?\d\d*$)|(^-?\.\d\d*$)/;;
						regexError = getText('Only numbers are allowed'); 
						
							if(SM['productsCols'][this.value] != undefined ){
								var categoryActionType = SM['productsCols'][this.value].actionType;
							}							
							if (field_type == 'category' || categoryActionType == 'category_actions') {
								setTextfield.hide();
								comboWeightUnitCmp.hide();
                                                                comboCategoriesActionCmp.show();
								comboCategoriesActionCmp.reset();
							}else if (field_type == 'string') {
								setTextfield.hide();
								comboWeightUnitCmp.hide();
								comboCategoriesActionCmp.hide();
							}else if (field_name == 'Stock: Quantity Limited' || field_name == 'Publish' || field_name == 'Stock: Inform When Out Of Stock' || field_name == 'Disregard Shipping') {								
								setTextfield.hide();
								comboWeightUnitCmp.hide();
								comboCategoriesActionCmp.hide();
							}else if (field_name == 'Weight' || field_name == 'Variations: Weight' || field_name == 'Height' || field_name == 'Width' || field_name == 'Length' ) {
								comboWeightUnitCmp.hide();
								setTextfield.show();
								comboCategoriesActionCmp.hide();
							}else if(field_name == 'Orders Status' || field_name.indexOf('Country') != -1){
								if(field_name.indexOf('Country') != -1) {
									actions_index = 'bigint';
									weightUnitStore.loadData(countries);
								}else{
									weightUnitStore.loadData(ordersStatus);
									actions_index = field_type;
								}
								setTextfield.hide();
								comboWeightUnitCmp.show();
							}else if(field_type == 'YesNoActions'){
								setTextfield.hide();
							}else {
								setTextfield.show();
								if (field_type == 'blob' || field_type == 'modStrActions') {
									objRegExp = '';
									regexError = '';
								}
								comboWeightUnitCmp.hide();
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
						}else if(SM.activeModule == 'Products'){
							actionStore.loadData(actions[SM['productsCols'][this.value].actionType]);
						}
						setTextfield.reset();
						comboActionCmp.reset();
						comboWeightUnitCmp.reset();
						comboRegionCmp.hide();
						
						// @todo apply regex accordign to the req
						setTextfield.regex = objRegExp;
						setTextfield.regexText = regexError;						
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
				emptyText: getText('Select an action') + '...',
				triggerAction: 'all',
				editable: false,
				selectOnFocus: true,
				listeners: {
					focus: function () {	
							var actionsData        = new Array();
							var toolbarParent      = this.findParentByType(batchUpdateToolbarInstance, true);
							var comboFieldCmp      = toolbarParent.get(0);
							var comboActionCmp     = toolbarParent.get(2);
							var selectedValue      = comboFieldCmp.value;
							
							if(SM.activeModule == 'Orders' || SM.activeModule == 'Customers'){
								var selectedFieldIndex = comboFieldCmp.selectedIndex;
								var field_type         = comboFieldCmp.store.reader.jsonData.items[selectedFieldIndex].type;
								var field_name         = comboFieldCmp.store.reader.jsonData.items[selectedFieldIndex].name;
								var actions_index;

								actions_index = (field_type == 'category') ? field_type + '_actions' :((field_name.indexOf('Country') != -1) ? 'bigint' : field_type);
								(field_name.indexOf('Country') != -1) ? weightUnitStore.loadData(countries) : '';

								for (j = 0; j < actions[actions_index].length; j++) {
									actionsData[j] = new Array();
									actionsData[j][0] = actions[actions_index][j].id;
									actionsData[j][1] = actions[actions_index][j].name;
									actionsData[j][2] = actions[actions_index][j].value;
								}
								actionStore.loadData(actionsData);
							}else{
//								// on swapping between the toolbars	
                                actionStore.loadData( actions[SM['productsCols'][selectedValue].actionType] );
							}
						},
					beforeselect: function( combo, record, index ) {
							var toolbarParent      = this.findParentByType(batchUpdateToolbarInstance, true);
							var comboFieldCmp      = toolbarParent.get(0);
							
							if ( comboFieldCmp.value.substr( 0, 14 ) == 'groupVariation' && index == 0 ) {
								return false;
							}
						},
					select: function() {
						var toolbarParent      = this.findParentByType(batchUpdateToolbarInstance, true);
						var comboFieldCmp      = toolbarParent.get(0);
						var comboactionCmp     = toolbarParent.get(2);
						var comboWeightUnitCmp = toolbarParent.get(7);
						var selectedFieldIndex = comboFieldCmp.selectedIndex;
						var selectedValue      = comboFieldCmp.value;
						var field_name = comboFieldCmp.store.reader.jsonData.items[selectedFieldIndex].name;						

							if(wpscRunning == 1){if (field_name == 'Weight' || field_name == 'Variations: Weight'||field_name == 'Height' ||field_name == 'Width' ||field_name == 'Length') {
								if (field_name == 'Weight' || field_name == 'Variations: Weight') {
									weightUnitStore.loadData(weightUnits);
								}
								else if(field_name == 'Height' ||field_name == 'Width' ||field_name == 'Length') {
									weightUnitStore.loadData(dimensionUnits);
								}
								if(comboactionCmp.value == 'SET_TO')
									comboWeightUnitCmp.show();
								else
									comboWeightUnitCmp.hide();
							}}
					}
				}
			},'',{
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
				emptyText: getText('Select a value') + '...',
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

						if(SM.activeModule == 'Orders' || SM.activeModule == 'Customers'){
							var field_type = comboFieldCmp.store.reader.jsonData.items[selectedFieldIndex].type;
							var field_name = comboFieldCmp.store.reader.jsonData.items[selectedFieldIndex].name;
							var actions_index;
							
							(field_type == 'category') ? actions_index = field_type + '_actions' : actions_index = field_type;
							for (j = 0; j < actions[actions_index].length; j++) {
								actionsData[j] = new Array();
								actionsData[j][0] = actions[actions_index][j].id;
								actionsData[j][1] = actions[actions_index][j].name;
								actionsData[j][2] = actions[actions_index][j].value;
							}
							actionStore.loadData(actionsData);
							categoryStore.loadData(categories[comboFieldCmp.getValue()]);
						}else{
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
				regex: objRegExp,
				regexText: regexError,
				displayField: 'fullname',
				emptyText: getText('Enter the value') + '...',
				cls: 'searchPanel',
				hidden: false,
				selectOnFocus: true
			}, '',
			{
				xtype: 'combo',
				allowBlank: false,
				typeAhead: true,
				hidden: false,
				width: 170,
				align: 'center',
				store: weightUnitStore,
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
							// this combo is used for weight unit, countries
							var toolbarParent = this.findParentByType(batchUpdateToolbarInstance, true);
							var comboFieldCmp = toolbarParent.get(0);
							var selectedFieldIndex = comboFieldCmp.selectedIndex;
							var field_name = comboFieldCmp.store.reader.jsonData.items[selectedFieldIndex].name;
							var comboRegionCmp = toolbarParent.get(9);
							var textState = toolbarParent.get(11);
							comboRegionCmp.reset();
							
							if(field_name.indexOf('Country') != -1) {
								var selectCountryIndex = this.selectedIndex;
								var countryId = this.store.data.items[selectCountryIndex].data['country_id'];
								textState.reset();
								if(regions[countryId]==undefined){
									regionsStore.removeAll(false);
									comboRegionCmp.hide();
									textState.show();
								}else{
									regionsStore.loadData(regions[countryId]);
									comboRegionCmp.show();
									textState.hide();
								}
							}
					}}
			},'',{
				xtype: 'combo',
				forceSelection: false,
				typeAhead: true,
				editable: false,
				allowBlank: false,
				hidden: false,
				width: 170,
				align: 'center',
				store: regionsStore,
				style: {
					fontSize: '12px',
					paddingLeft: '2px'
				},
				hidden: true,
				valueField: 'region_id',
				displayField: 'name',
				mode: 'local',
				cls: 'searchPanel',
				emptyText: getText('Select a value') + '...', 
				triggerAction: 'all',
				selectOnFocus: true,
				listeners: {
					focus: function(){
						if(isWPSC37 == '1'){
							var toolbarParent       = this.findParentByType(batchUpdateToolbarInstance, true);
							var comboCountryCmp     = toolbarParent.get(7);
							var selectedCountryName = comboCountryCmp.lastSelectionText;
							var countryData = comboCountryCmp.store.reader.jsonData;
							//comparing the countries name selected by user with the ones from datastore reader.
							for(var i=0;i<=countryData.totalCount;i++){
								if(selectedCountryName == countryData.items[i].name)
								countryId = countryData.items[i].country_id;
							}
							regionsStore.loadData(regions[countryId]);
						}
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
				emptyText: getText('Enter State/Region') + '...',
				cls: 'searchPanel',
				hidden: true,
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
		tooltip: getText('Add a new row'),
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
batchUpdateToolbar.get(0).get(13).hide(); //hide delete row icon from first toolbar.

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
				flag = 	1;
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

			firstToolbar.items.items[5].reset();

			firstToolbar.items.items[7].reset();
			firstToolbar.items.items[7].hide();

			firstToolbar.items.items[9].reset();
			firstToolbar.items.items[9].hide();
			
			firstToolbar.items.items[11].reset();
			firstToolbar.items.items[11].hide();
			
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
		stateId : 'billingDetailsWindowWpsc',
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
		html: '<iframe src='+ ordersDetailsLink + '' + recordId +' style="width:100%;height:100%;border:none;">< p>' + getText('Your browser does not support iframes.') + '</p></iframe>' 
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
	
	clearTimeout(SM.colModelTimeoutId);
	SM.colModelTimeoutId = showCustomersView.defer(100,this,[emailId]);
	SM.searchTextField.setValue(emailId);
};

	// Grid panel for the records to display
	editorGrid = new Ext.grid.EditorGridPanel({
	stateId : SM.dashboardComboBox.value.toLowerCase()+'EditorGridPanelWpsc',
	stateEvents : ['viewready','beforerender','columnresize', 'columnmove', 'columnvisible', 'columnsort','reconfigure'],
	stateful: true,
	store: eval(SM.dashboardComboBox.value.toLowerCase()+'Store'),
	cm: eval(SM.dashboardComboBox.value.toLowerCase()+'ColumnModel'),
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
			 	boxLabel: getText('Show Variations'),
			 	listeners: {
			 		check : function(checkbox, bool) {
			 			if ( SM.activeModule == 'Products' ) {
				 			if ( isWPSC37 == true ) {
				 				Ext.notification.msg('Smart Manager', getText('Show Variations feature is available only for WPeC 3.8+') );
				 			}else if(fileExists == 1){
				 				SM.incVariation  = bool;
				 				productsStore.setBaseParam('incVariation', SM.incVariation);
				 				getVariations(productsStore.baseParams,productsColumnModel,productsStore);
				 			}else{
				 				Ext.notification.msg('Smart Manager', getText('Show Variations feature is available only in Pro version') );
				 			}
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
//								SM.dashboardComboBox.setValue(dashboardComboStore[0][1]);
//								grid.cm = eval(SM.dashboardComboBox.value.toLowerCase()+'ColumnModel');
//								grid.store = eval(SM.dashboardComboBox.value.toLowerCase()+'Store');
//								grid.stateId = SM.dashboardComboBox.value.toLowerCase()+'EditorGridPanelWpsc';
											
								SM.dashboardComboBox.store.loadData(dashboardComboStore);
//								showSelectedModule(SM.dashboardComboBox.getValue());
							}
						}
						,scope: SM.dashboardComboBox
						,params:
						{
							cmd:'getRolesDashboard'
						}};
				Ext.Ajax.request(object);
			
		},
		cellclick: function(editorGrid, rowIndex, columnIndex, e) {
			try{
				var record  = editorGrid.getStore().getAt(rowIndex);
				cellClicked = true;
				var editLinkColumnIndex   	  = productsColumnModel.findColumnIndex('edit_url'),
					editImageColumnIndex   	  = productsColumnModel.findColumnIndex(SM.productsCols.image.colName),
					prodTypeColumnIndex       = productsColumnModel.findColumnIndex('type'),
					totalPurchasedColumnIndex = customersColumnModel.findColumnIndex('_order_total'),
					lastOrderColumnIndex      = customersColumnModel.findColumnIndex('Last_Order'),
					nameLinkColumnIndex       = ordersColumnModel.findColumnIndex('name'),
					orderDetailsColumnIndex   = ordersColumnModel.findColumnIndex('details');					
					publishColumnIndex        = productsColumnModel.findColumnIndex(SM.productsCols.publish.colName);

				if(SM.activeModule == 'Orders'){
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
							stateId : 'productsDetailsWindowWpsc',
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
							html: '<iframe src='+ productsDetailsLink + record.id +'&action=edit style="width:100%;height:100%;border:none;">< p >' + getText('Your browser does not support iframes.') + '</p></iframe>' 
						});
						// To disable Product's details window for product variations
						if(record.get('post_parent') == 0){
							productsDetailsWindow.show('editLink');
						}
						
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
					} else if ( columnIndex == editImageColumnIndex ) {
						if ( isWPSC37 != 1 ) {
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
											this.setTitle(  getText('Manage your Product Images - Available only in Pro version') );
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
								html: ( fileExists == 1 ) ? '<iframe src="'+ site_url + '/wp-admin/media-upload.php?parent_page=wpsc-edit-products&post_id=' + record.id +'&type=image&tab=library&" style="width:100%;height:100%;border:none;">< p>' + getText('Your browser does not support iframes.') +'</p></iframe>' : ''
							});
							productsImageWindow.show('image');
						}
					}
				}
				else if(SM.activeModule == 'Customers'){
					if(fileExists == 1){
						if(columnIndex == totalPurchasedColumnIndex){
							checkModifiedAndshowDetails(record,rowIndex);
						}else if(columnIndex == lastOrderColumnIndex){
							billingDetailsIframe(record.json.last_order_id);
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
					var bill_country = SM.activeRecord.data['billingcountry'];
					var curCountry;
					    
						if(SM.curDataIndex == 'billingcountry' || SM.curDataIndex == 'billingstate') {
							curCountry = bill_country;
						}
						reloadRegionCombo(curCountry);
				}
			}else if(SM.activeModule == 'Orders') {
				var ship_country = SM.activeRecord.data['shippingcountry'];
				
				if(SM.curDataIndex == 'shippingcountry' || SM.curDataIndex == 'shippingstate') {
					 curCountry = ship_country;
				}
				reloadRegionCombo(curCountry);
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
//			pagingToolbar.addProductButton.disable();
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

