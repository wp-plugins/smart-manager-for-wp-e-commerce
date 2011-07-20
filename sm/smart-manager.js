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
            if(!msgCt){
                msgCt = Ext.DomHelper.insertFirst(document.body, {id:'msg-div'}, true);
            }
            msgCt.alignTo(document, 't-t');
            var s = String.format.apply(String, Array.prototype.slice.call(arguments, 1));
            var m = Ext.DomHelper.append(msgCt, {html:createBox(title, s)}, true);
            m.slideIn('t').pause(2).ghost("t", {remove:true});
        },

        init : function(){
            var lb = Ext.get('lib-bar');
            if(lb){
                lb.show();
            }
        }
    };
}();// Floating notification end

var actions           = new Array(); //an array for actions combobox in batchupdate window.
var categories        = new Array(); //an array for category combobox in batchupdate window.
var dimensionUnits    = new Array();
var cellClicked       = false; //flag to check if any cell is clicked in the editor grid.
var search_timeout_id = 0; //timeout for sending request while searching.
var colModelTimeoutId = 0; //timeout to reconfigure the grid.
var limit = 100;//per page records limit.

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


dimensionUnits    = {'items': [{'id':0 , 'name':'inches', 'value': 'in'},
					  	       {'id':1 , 'name':'cm', 'value': 'cm'},
							   {'id':2 , 'name':'meter', 'value': 'meter'}],
	                 'totalCount': 3 };
	                 
//creating array of actions
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

actions['modIntActions']   = [[0, 'set to', 'SET_TO'],
                              [1, 'increase by number','INCREASE_BY_NUMBER'],
                              [2, 'decrease by number', 'DECREASE_BY_NUMBER']];

actions['YesNoActions']   = [[0,'Yes','YES'],
                             [1,'No','NO']];

actions['category_actions'] = [[0, 'set to','SET_TO'],
							    [1,'add to','ADD_TO'],
							    [2,'remove from','REMOVE_FROM']];
// BOF global components & function
var editorGrid         = '';
var showOrdersView     = '';
var showCustomersView  = '';
var weightUnitStore    = '';	
var countriesStore     = '';
var regionsStore       = '';
var reloadRegionCombo  = '';
// EOF

Ext.onReady(function () {
		
	try{
	
		//Stateful
		Ext.state.Manager.setProvider(new Ext.state.CookieProvider({
			expires: new Date(new Date().getTime()+(1000*60*60*24*30)), //30 days from now
			listeners: { 
					statechange : function( Provider, key, value ){
						
					}
			}
		}));
		//Stateful
		
		
	//START: Tooltips
	Ext.QuickTips.init();
	Ext.apply(Ext.QuickTips.getQuickTip(), {
		maxWidth: 150,
		minWidth: 100,
		dismissDelay: 9999999,
		trackMouse: true
	});
	//END: Tooltips
	
	SM.searchTextField   = '';
	SM.dashboardComboBox = '';
	SM.colModelTimeoutId = '';		
	SM.activeModule      = 'Products'; //default module selected.
	SM.activeRecord      = '';
	SM.curDataIndex      = '';	
	
	var fm = Ext.form;
	var toolbarCount = 1;
	
	//for checkboxSelectionModel.
	var cnt = -1;
	var cnt_array = [];
	
	//Regex
	var objRegExp = /(^-?\d\d*\.\d*$)|(^-?\d\d*$)|(^-?\.\d\d*$)/;
	var regexError = 'Only numbers are allowed';
	
	//START: setting fromDate  & lastDate
	var amountRenderer = Ext.util.Format.numberRenderer('0,0.00');	
	var fromDateTxt    = new Ext.form.TextField({emptyText:'From Date',readOnly: true,width: 80});
	var toDateTxt      = new Ext.form.TextField({emptyText:'To Date',readOnly: true,width: 80});
	var now            = new Date();
	var initDate       = new Date(0);
	var lastMonDate    = new Date(now.getFullYear(), now.getMonth()-1, now.getDate()+1);
	
	fromDateTxt.setValue(lastMonDate.format('M j Y'));
	toDateTxt.setValue(now.format('M j Y'));
	//END: setting fromDate  & lastDate
	
	//START: CheckBoxes for Grid.
	var mySelectionModel = new Ext.grid.CheckboxSelectionModel({
		checkOnly: true,
		listeners: {
			selectionchange: function (sm) {
				if (sm.getCount()) {
					pagingToolbar.deleteButton.enable();
					pagingToolbar.batchButton.enable();
				} else {
					pagingToolbar.deleteButton.disable();
					pagingToolbar.batchButton.disable();
				}
			}
		}
	});//END: CheckBoxes for Grid.

	//Renderer for dimension units
	Ext.util.Format.comboRenderer = function(dimensionCombo){
		return function(value){
			var record = dimensionCombo.findRecord(dimensionCombo.valueField, value);
			return record ? record.get(dimensionCombo.displayField) : dimensionCombo.valueNotFoundText;
		}
	}
	
	//combo box for dimension units
	var dimensionCombo = new Ext.form.ComboBox({
		typeAhead: true,
		triggerAction: 'all',
		lazyRender:true,
		mode: 'local',
		store: new Ext.data.ArrayStore({
			id: 0,
			fields: ['value','name'],
			data: [['in', 'inches'], ['cm', 'cm'], ['meter', 'meter']]
		}),
		valueField: 'value',
		displayField: 'name'
	});
	
	var yesNoCombo = new Ext.form.ComboBox({
		typeAhead: true,
		triggerAction: 'all',
		lazyRender:true,
		mode: 'local',
		store: new Ext.data.ArrayStore({
			id: 0,
			fields: ['value','name'],
			data: [[1, 'Yes'], [0, 'No']]
		}),
		valueField: 'value',
		displayField: 'name'
	});	
	
	var weightUnitCombo = new Ext.form.ComboBox({
		typeAhead: true,
		triggerAction: 'all',
		lazyRender:true,
		mode: 'local',
		store: new Ext.data.ArrayStore({
			id: 0,
			fields: ['value','name'],
			data: [['pound', 'Pounds'], ['ounce', 'Ounces'], ['gram', 'Grams'], ['kilogram', 'Kilograms']]
		}),
		valueField: 'value',
		displayField: 'name'
	});
	
	var productStatusCombo = new Ext.form.ComboBox({
		typeAhead: true,
		triggerAction: 'all',
		lazyRender:true,
		mode: 'local',
		store: new Ext.data.ArrayStore({
			id: 0,
			fields: ['value','name'],
			data: [['publish', 'Published'], ['draft', 'Draft']]			
		}),
		valueField: 'value',
		displayField: 'name'
	});	
	
//START: Products ColumnModel.
	var storeColState = function(){
		var editorGridStateId = editorGrid.getStateId();
		var state = Ext.state.Manager.get(editorGridStateId);
		
		if(state != undefined){
			state = editorGrid.getState();
			Ext.state.Manager.set(editorGridStateId,state);
		}
	};
	
	var productsColumnModel = new Ext.grid.ColumnModel({
		columns: [mySelectionModel,
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
		},{
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
			header: SM.productsCols.inventory.name,
			id: 'inventory',
			sortable: true,
			align: 'right',
			dataIndex: SM.productsCols.inventory.colName,
			tooltip: 'Inventory',
			editor: new fm.TextField({
				allowBlank: true
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
			editor: new fm.NumberField({
				allowBlank: false,
				allowNegative: false
			})
		},{
			header: SM.productsCols.weightUnit.name,
			id: 'weightUnit',
			sortable: true,
			dataIndex: SM.productsCols.weightUnit.colName,
			tooltip: 'Weight Unit',
			editor: weightUnitCombo,
			renderer: Ext.util.Format.comboRenderer(weightUnitCombo)
		},{
			header: SM.productsCols.publish.name,
			id: 'publish',
			sortable: true,
			dataIndex: SM.productsCols.publish.colName,
			tooltip: 'Product Status',
			editor: productStatusCombo,
			renderer: Ext.util.Format.comboRenderer(productStatusCombo)
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
		},{
			header: SM.productsCols.disregardShipping.name,
			id: 'disregardShipping',
			hidden: true,
			sortable: true,
			dataIndex: SM.productsCols.disregardShipping.colName,
			tooltip: 'Disregard Shipping',
			editor: yesNoCombo,
			renderer: Ext.util.Format.comboRenderer(yesNoCombo)
		},{
			header: SM.productsCols.desc.name,
			id: 'desc',
			hidden: true,
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
	  		header: SM.productsCols.pnp.name,
	  		id: 'pnp',
	  		hidden: true,
			colSpan: 2,
			sortable: true,
			align: 'right',
			dataIndex: SM.productsCols.pnp.colName,
//			width: 50,
			tooltip: 'Local Shipping Fee',			
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
//			width: 50,
			dataIndex: SM.productsCols.intPnp.colName,
			tooltip: 'International Shipping Fee',
			editor: new fm.NumberField({
				allowBlank: false,
				allowNegative: false
			})
		},{
			header: SM.productsCols.height.name,
			id: 'height',
			hidden: true,
			colSpan: 2,
			sortable: true,
			align: 'right',
//			width: 50,
			dataIndex: SM.productsCols.height.colName,
			tooltip: 'Height',			
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
			tooltip: 'Height Unit',
//			width: 50,
			editor: dimensionCombo,
			renderer: Ext.util.Format.comboRenderer(dimensionCombo)
		},{
			header: SM.productsCols.width.name,
			id: 'width',
			hidden: true,
			colSpan: 2,
			sortable: true,
			align: 'right',
			dataIndex: SM.productsCols.width.colName,
			tooltip: 'Width',
//			width: 50,
			editor: new fm.NumberField({
				allowBlank: false,
				allowNegative: false
			})
		},{
			header: SM.productsCols.widthUnit.name,
			id: 'widthUnit',
			hidden: true,
			sortable: true,
//			width: 50,
			dataIndex: SM.productsCols.widthUnit.colName,
			tooltip: 'Width Unit',
			editor: dimensionCombo,
			renderer: Ext.util.Format.comboRenderer(dimensionCombo)
		},{
			header: SM.productsCols.lengthCol.name,
			id: 'lengthCol',
			hidden: true,
			colSpan: 2,
			sortable: true,
//			width: 50,
			align: 'right',
			dataIndex: SM.productsCols.lengthCol.colName,
			tooltip: 'Length',			
			editor: new fm.NumberField({
				allowBlank: false,
				allowNegative: false
			})
		},{
			header: SM.productsCols.lengthUnit.name,
			sortable: true,
			hidden: true,
			id: 'lengthUnit',
//			width: 50,
			dataIndex: SM.productsCols.lengthUnit.colName,
			tooltip: 'Length Unit',
			editor: dimensionCombo,
			renderer: Ext.util.Format.comboRenderer(dimensionCombo)
		}],
		listeners: {
			hiddenchange: function( ColumnModel,columnIndex, hidden ){
				storeColState();
			}
		},
		defaultSortable: true
	});		
	//END: Products ColumnModel.
		
	//START: Products JsonReader.
	var productsJsonReader = new Ext.data.JsonReader({
		totalProperty: 'totalCount',
		root: 'items',
		fields: [{name: SM.productsCols.id.colName,                type: 'int'},
				 {name: SM.productsCols.name.colName,              type: 'string'},
				 {name: SM.productsCols.price.colName,             type: 'float'},
				 {name: SM.productsCols.salePrice.colName,         type: 'int'},
				 {name: SM.productsCols.inventory.colName,         type: 'string'},
				 {name: SM.productsCols.publish.colName,           type: 'string'},
				 {name: SM.productsCols.salePrice.colName,         type: 'float'},
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
				 {name: SM.productsCols.lengthUnit.colName,        type: 'string'}
				 ]
	});
	//END: Products JsonReader.
	
	//START: Products Store.
	var productsStore = new Ext.data.Store({
		reader: productsJsonReader,
		proxy: new Ext.data.HttpProxy({	url: jsonURL }),
		baseParams: {
			cmd: 'getData',
			active_module: SM.activeModule,
			start: 0,
			limit: limit,
			viewCols: Ext.encode(productsViewCols)
		},
		dirty: false,
		pruneModifiedRecords: true
	});
	//END: Products Store.
	
	var showProductsView = function(){
		console.debug('showProductsView');
		productsStore.baseParams.searchText = ''; //clear the baseParams for productsStore
		SM.searchTextField.reset(); //to reset the searchTextField

		//show all the hidden in the customers view
		for(var i=13;i<=21;i++)
		pagingToolbar.get(i).show();

		for(var i=2;i<=8;i++)
		editorGrid.getTopToolbar().get(i).hide();

		pagingToolbar.addProductButton.enable();
		pagingToolbar.addProductButton.show();
		pagingToolbar.batchButton.show();
		pagingToolbar.get(14).show();

		productsStore.load();
		pagingToolbar.bind(productsStore);
		
		editorGrid.reconfigure(productsStore,productsColumnModel);
		productFieldStore.loadData(productsFields);

		var firstToolbar = batchUpdatePanel.items.items[0].items.items[0];
		var textfield    = firstToolbar.items.items[5];
		var weightUnitDropdown = firstToolbar.items.items[7];

		weightUnitDropdown.hide();
		weightUnitStore.loadData(weightUnits);
		textfield.show();
	};	
//END: Products.
	var pagingToolbar = new Ext.PagingToolbar({
		id: 'pagingToolbar',
		items: ['->', '-',
		{
			text: 'Add Product',
			tooltip: 'Add a new product',
			icon: imgURL + 'add.png',
			disabled: true,			
			hidden: false,
			id : 'addProductButton',
			ref : 'addProductButton',
			listeners: {click: function () { addProduct(productsStore,cnt_array,cnt,newCatName); }}
		},'-',{
			text: 'Batch Update',
			tooltip: 'Update selected items',
			icon: imgURL + 'batch_update.png',
			id: 'BU',
			disabled: true,
			ref: 'batchButton',
			scope: this,
			listeners: { click: function () { batchUpdateWindow.show();	}}
		},'-',{
			text: 'Delete',
			icon: imgURL + 'delete.png',
			disabled: false,
			id: 'del',
			tooltip: 'Delete the selected items',
			ref: 'deleteButton',
			disabled: true,
			listeners: { click: function () { deleteRecords(); }}
		}, '-',	{
			text: 'Save',
			tooltip: 'Save all Changes',
			icon: imgURL + 'save.png',
			disabled: true,
			scope: this,
			ref: 'saveButton',
			id: 'save',
			listeners:{ click : function () {
				if(SM.activeModule == 'Orders')
					store = ordersStore;
				else if(SM.activeModule == 'Products')
					store = productsStore;
				else
					store = customersStore;
				saveRecords(store,pagingToolbar,jsonURL,mySelectionModel);
//				store.load();
			}}
		}],
		pageSize: limit,
		store: productsStore,
		displayInfo: true,
		style: { width: '100%' },
		hideBorders: true,
		align: 'center',
		displayMsg: 'Displaying {0} - {1} of {2}',
		emptyMsg: 'Product list is empty'
	});
	var pagingActivePage = pagingToolbar.getPageData().activePage;

//START: Functions.
	// Function to save modified records
	var saveRecords = function(store,pagingToolbar,jsonURL,mySelectionModel){
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
					store.load();
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

SM.dashboardComboBox = new Ext.form.ComboBox({	
	stateId : 'dashboardComboBox',
	stateEvents : ['added','beforerender','enable','select','change','show','beforeshow'],
	stateful: true,
	getState: function(){ return { value: this.getValue()}; },
	applyState: function(state) { this.setValue(state.value);},
	store: new Ext.data.ArrayStore({
		autoDestroy: true,
		forceSelection: true,
		fields: ['id', 'fullname'],
		data: [
		[0, 'Products'],
		[1, 'Customers'],
		[2, 'Orders']
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
		select: function () {
			
			editorGrid.stateId = this.value.toLowerCase()+'EditorGridPanel';
			
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
					saveRecords(store,pagingToolbar,jsonURL,mySelectionModel);
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
	

//START: Customers
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
	
	var countriesCombo = new Ext.form.ComboBox({
		typeAhead: true,
	    triggerAction: 'all',
	    lazyRender:true,
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
		mode: 'local',
		store:regionsStore,
		value: 'value',
		valueField: 'name',
		value: 'value',
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
		columns:[mySelectionModel, //checkbox for
		{
			header: 'First Name',
			id: 'billingfirstname',
			dataIndex: 'billingfirstname',
			tooltip: 'Billing First Name',
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 150
		},{
			header: 'Last Name',
			id: 'billinglastname',
			dataIndex: 'billinglastname',
			tooltip: 'Billing Last Name',
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 150
		},{
			header: 'Email',
			id: 'billingemail',
			dataIndex: 'billingemail',
			tooltip: 'Email Address',
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			editor: new fm.TextField({
				allowBlank: true,
				allowNegative: false
			}),
			width: 200
		},{
			header: 'Address',
			id: 'billingaddress',
			dataIndex: 'billingaddress',
			tooltip: 'Billing Address',
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 200
		},{
			header: 'Postal Code',
			id: 'billingpostcode',
			dataIndex: 'billingpostcode',
			tooltip: 'Billing Postal Code',
			editor: new fm.TextField({
				allowBlank: true,
				allowNegative: false
			}),
			width: 150
		},{
			header: 'City',
			id: 'billingcity',
			dataIndex: 'billingcity',
			tooltip: 'Billing City',
			align: 'left',
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 150
		},{
			header: 'Region',
			id: 'billingstate',
			dataIndex: 'billingstate',
			tooltip: 'Billing Region',
			align: 'center',
			editor: regionEditor,
			width: 100
		},{
			header: 'Country',
			id: 'billingcountry',
			dataIndex: 'billingcountry',
			tooltip: 'Billing Country',
			editor:countriesCombo,
			width: 120
		},{
			header: 'Total Purchased',
			id: 'total_purchased', //@todo: change the id to Total_Purchased
			dataIndex: 'Total_Purchased',
			tooltip: 'Total Purchased',
			align: 'right',
			width: 150			
		},{
			header: 'Last Order',
			id: 'last_order',
			dataIndex: 'Last_Order',
			tooltip: 'Last Order Details',
			width: 220			
		},{   
			header: 'Phone Number',
			id: 'billingphone',
			dataIndex: 'billingphone',
			tooltip: 'Phone Number',
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
		customersColumnModel.columns[9].align = 'center';
		customersColumnModel.columns[10].align = 'center';
	}else{
		totPurDataType = 'float';
		customersColumnModel.setRenderer(9,amountRenderer);		
	}
	
	// Data reader class to create an Array of Records objects from a JSON packet.
	var customersJsonReader = new Ext.data.JsonReader ({
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
		{name:'Total_Purchased',type:totPurDataType},		
		{name:'Last_Order', type:'string'},		
		{name:'Old_Email_Id', type: 'string'}
		]
	});
	
	// create the Customers Data Store
	var customersStore = new Ext.data.Store({
		reader: customersJsonReader,
		proxy:new Ext.data.HttpProxy({url:jsonURL}),
		baseParams:{cmd:'getData',
					active_module: 'Customers',
					start: 0, limit: limit},
		dirty:false,
		pruneModifiedRecords: true
	});
	
	customersStore.on('load', function () {
		mySelectionModel.clearSelections();
		pagingToolbar.saveButton.disable();
	});
	
	showCustomersView = function(emailId){
		try{
			//initial steps when store: customers is laoded
			SM.activeModule = 'Customers';
			SM.dashboardComboBox.setValue(SM.activeModule);

			if(cellClicked == false){
				ordersStore.baseParams.searchText = ''; //clear the baseParams for ordersStore
				SM.searchTextField.reset(); //to reset the searchTextField
			}

			pagingToolbar.addProductButton.disable();			
			for(var i=13;i<=17;i++){
				pagingToolbar.get(i).hide();
			}
			pagingToolbar.get(11).show();
			pagingToolbar.get(15).show();
			pagingToolbar.get(18).show();
			pagingToolbar.get(19).show();			

			for(var i=2;i<=8;i++)
			editorGrid.getTopToolbar().get(i).hide();
						
			if(customersFields != 0)
			productFieldStore.loadData(customersFields); //@todo: use a common name fieldStore and load respective fields in it.
			
			customersStore.setBaseParam('searchText',emailId);
			customersStore.load();
			pagingToolbar.bind(customersStore);	
			
			editorGrid.reconfigure(customersStore,customersColumnModel);

			var firstToolbar = batchUpdatePanel.items.items[0].items.items[0];
			var textfield    = firstToolbar.items.items[5];
			var countriesDropdown = firstToolbar.items.items[7];
			textfield.show();
			countriesDropdown.hide();			
			weightUnitStore.loadData(countries);
		}catch(e){
			var err = e.toString();
			Ext.notification.msg('Error', err);
		}
	};
	
	var fromDateMenu = new Ext.menu.DateMenu({
		handler: function(dp, date){
			fromDateTxt.setValue(date.format('M j Y'));
			searchLogic();
		},
		maxDate: now
	});

	var toDateMenu = new Ext.menu.DateMenu({
		handler: function(dp, date){
			toDateTxt.setValue(date.format('M j Y'));
			searchLogic();
		},
		maxDate: now
	});
//END: Customers	

//START: Orders settings
if(isWPSC38 == '1'){
	var orderStatusCombo = new Ext.form.ComboBox({
		typeAhead: true,
		triggerAction: 'all',
		lazyRender:true,
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
		columns:[mySelectionModel, //checkbox for
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
			id: 'amount',
			dataIndex: 'amount',
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
			header: 'Track Id',
			id: 'track_id',
			dataIndex: 'track_id',
			tooltip: 'Track Id',
			align: 'left',
			editor: new fm.TextField({
				allowBlank: true,
				allowNegative: false
			}),
			width: 110
		},{
			header: 'Status',
			id: 'order_status',
			dataIndex: 'order_status',
			tooltip: 'Order Status',
			width: 150,
			editor: orderStatusCombo,
			renderer: Ext.util.Format.comboRenderer(orderStatusCombo)
		},{
			header: 'Orders Notes',
			id: 'notes',
			dataIndex: 'notes',
			tooltip: 'Orders Notes',
			width: 180,
			editor: new fm.TextArea({				
				autoHeight: true
			})
		},{   
			header: 'Shipping First Name',
			id: 'shippingfirstname',
			dataIndex: 'shippingfirstname',
			tooltip: 'Shipping First Name',
			hidden: true,
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 200
		},{   
			header: 'Shipping Last Name',
			id: 'shippinglastname',
			dataIndex: 'shippinglastname',
			tooltip: 'Shipping Last Name',
			hidden: true,
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 200
		},{   
			header: 'Shipping Address',
			id: 'shippingaddress',
			dataIndex: 'shippingaddress',
			tooltip: 'Shipping Address',
			hidden: true,
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 200		
		},{
			header: 'Shipping Postal Code',
			id: 'shippingpostcode',
			dataIndex: 'shippingpostcode',
			tooltip: 'Shipping Postal Code',
			hidden: true,
			editor: new fm.TextField({
					allowBlank: true,
					allowNegative: false
			}),
				width: 200
		},{   
			header: 'Shipping City',
			id: 'shippingcity',
			dataIndex: 'shippingcity',
			tooltip: 'Shipping City',
			hidden: true,
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 200		
		},{   
			header: 'Shipping Region',
			id: 'shippingstate',
			dataIndex: 'shippingstate',
			tooltip: 'Shipping Region',
			align: 'center',
			hidden: true,
			editor: regionEditor,
			width: 100		
		},{
			header: 'Shipping Country',
			id: 'shippingcountry',
			dataIndex: 'shippingcountry',
			tooltip: 'Shipping Country',
			editor:countriesCombo,
			hidden: true,
			width: 120
		}],
		listeners: {
			hiddenchange: function( ColumnModel,columnIndex, hidden ){
				storeColState();
			}
		},
		defaultSortable: true
	});

	// Data reader class to create an Array of Records objects from a JSON packet.
	var ordersJsonReader = new Ext.data.JsonReader({
		totalProperty: 'totalCount',
		root: 'items',
		fields:
		[
		{name:'id',type:'int'},
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
		baseParams:{cmd:'getData',
					active_module: 'Orders',
					fromDate: fromDateTxt.getValue(),
					toDate: toDateTxt.getValue(),
					start: 0, limit: limit},
		dirty:false,
		pruneModifiedRecords: true
	});

	ordersStore.on('load', function () {
		mySelectionModel.clearSelections();
		pagingToolbar.saveButton.disable();
	});	
	
	showOrdersView = function(emailid){
		try{
			//initial steps when store: orders is loaded
			SM.activeModule = 'Orders';
			SM.dashboardComboBox.setValue(SM.activeModule);

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
						
			if(ordersFields != 0) {				
				productFieldStore.loadData(ordersFields); //@todo: use a common name fieldStore and load respective fields in it.			
			}

			for(var i=13;i<=21;i++)
			pagingToolbar.get(i).show();

			for(var i=2;i<=8;i++)
			editorGrid.getTopToolbar().get(i).show();

			pagingToolbar.addProductButton.disable();
			pagingToolbar.addProductButton.hide();
			
			pagingToolbar.get(14).hide();
			ordersStore.load();
			editorGrid.reconfigure(ordersStore,ordersColumnModel);
			pagingToolbar.bind(ordersStore);

			var firstToolbar = batchUpdatePanel.items.items[0].items.items[0];
			var textfield = firstToolbar.items.items[5];
			var weightUnitDropdown = firstToolbar.items.items[7];
			weightUnitDropdown.show();			
			weightUnitStore.loadData(ordersStatus);
			textfield.hide();
		}catch(e) {
			var err = e.toString();
			Ext.notification.msg('Error', err);			
		}
	};
//END: Orders.

	 //START: Component
 SM.searchTextField = new Ext.form.TextField({
	id: 'tf',
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
					saveRecords(store,pagingToolbar,jsonURL,mySelectionModel);
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
//END: Component

//START: Functions.
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

	var o = {
		url: jsonURL,
		method: 'post',
		callback: function (options, success, response) {
			var myJsonObj = Ext.decode(response.responseText);
			if (true !== success) {
				Ext.notification.msg('Failed',response.responseText);
				return;
			}
			try {
				var records_cnt = myJsonObj.totalCount;
				if (records_cnt == 0) myJsonObj.items = '';
				if(SM.activeModule == 'Products')
					productsStore.loadData(myJsonObj)
				if(SM.activeModule == 'Orders')
					ordersStore.loadData(myJsonObj);
				else
					customersStore.loadData(myJsonObj);
			} catch (e) {
				return;
			}
		},
		scope: this,
		params: {
			cmd: 'getData',
			active_module: SM.activeModule,
			searchText: SM.searchTextField.getValue(),
			fromDate: fromDateTxt.getValue(),
			toDate: toDateTxt.getValue(),
			start: 0,
			limit: limit,
			viewCols: Ext.encode(productsViewCols)
		}
	};
	Ext.Ajax.request(o);
};
//END: Functions.
	
//START: BatchUpdate
//store for first combobox(field combobox) of BatchUpdate window.
var productFieldStore = new Ext.data.Store({
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
productFieldStore.loadData(productsFields);

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
		{ name: 'country_id'}
		]
	}),
	autoDestroy: false,
	dirty: false
});
weightUnitStore.loadData(weightUnits);
// countries's store

var mask = new Ext.LoadMask(Ext.getBody(), {
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
				store: productFieldStore,
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
						var comboWeightUnitCmp = toolbarParent.get(7);						
						var comboRegionCmp = toolbarParent.get(9);
						objRegExp = /(^-?\d\d*\.\d*$)|(^-?\d\d*$)|(^-?\.\d\d*$)/;;
						regexError = 'Only numbers are allowed';
						
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
							} else if (field_name == 'Stock: Quantity Limited' || field_name == 'Publish' || field_name == 'Stock: Inform When Out Of Stock' || field_name == 'Disregard Shipping') {								
								setTextfield.hide();
								comboWeightUnitCmp.hide();
								comboCategoriesActionCmp.hide();
							}else if (field_name == 'Weight' || field_name == 'Variations: Weight'||field_name == 'Height' ||field_name == 'Width' ||field_name == 'Length') {
								comboWeightUnitCmp.hide();
								setTextfield.show();
								comboCategoriesActionCmp.hide();
							}
							else if(field_name == 'Orders Status' || field_name.indexOf('Country') != -1){
								if(field_name.indexOf('Country') != -1) {
									actions_index = 'bigint';
									weightUnitStore.loadData(countries);
								}else{
									weightUnitStore.loadData(ordersStatus);
									actions_index = field_type;
								}
								setTextfield.hide();
								comboWeightUnitCmp.show();
							} else {
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
								// on swapping between the toolbars	
							actionStore.loadData(actions[SM['productsCols'][selectedValue].actionType]);
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

							if (field_name == 'Weight' || field_name == 'Variations: Weight'||field_name == 'Height' ||field_name == 'Width' ||field_name == 'Length') {
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
							}
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
							categoryStore.loadData(categories["category-"+SM['productsCols'][selectedValue].colFilter]);
					    }
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
			}, '',
			{
				xtype: 'combo',
				allowBlank: false,
				typeAhead: true,
				hidden: false,
				width: 180,
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
				emptyText: 'Select a value...',
				triggerAction: 'all',
				editable: true,
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
							comboRegionCmp.reset();
							
							if(field_name.indexOf('Country') != -1) {
								var selectCountryIndex = this.selectedIndex;
								var countryId = this.store.data.items[selectCountryIndex].data['country_id'];

								if(regions[countryId]==undefined){
									regionsStore.removeAll(false);
									comboRegionCmp.hide();
								}else{
									regionsStore.loadData(regions[countryId]);
									comboRegionCmp.show();
								}
							}
					}}
			},'',{
				xtype: 'combo',
				forceSelection: false,
				typeAhead: true,
				editable: true,
				allowBlank: false,
				hidden: false,
				width: 180,
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
				emptyText: 'Select a value...',
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
batchUpdateToolbar.get(0).get(11).hide(); //hide delete row icon from first toolbar.

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
			batchUpdateRecords(batchUpdatePanel,toolbarCount,cnt_array,store,jsonURL,batchUpdateWindow);
		}}
	}]
});
batchUpdatePanel.add(batchUpdateToolbar);
batchUpdatePanel.items.items[0].items.items[0].cls = 'firsttoolbar';

batchUpdateWindow = new Ext.Window({
	title: 'Batch Update - available only in Pro version',
	animEl: 'BU',
	items: batchUpdatePanel,
	layout: 'fit',
	width: 810,
	height: 300,
	plain: true,
	closeAction: 'hide'
});

function afterClose(e) {
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
	values = '';
	ids = '';
	batchUpdateWindow.hide();
};
batchUpdateWindow.on('hide', afterClose, this);

batchUpdateWindow = new Ext.Window({
	title: 'Batch Update - available only in Pro version',
	animEl: 'BU',
	items: batchUpdatePanel,
	layout: 'fit',
	width: 810,
	height: 300,
	plain: true,
	closeAction: 'hide'
});
batchUpdateWindow.on('hide', afterClose, this);

var billingDetailsIframe = function(recordId){
	var billingDetailsWindow = new Ext.Window({
		title: 'Order Details',
		width:500,
		height: 500,
		minimizable: false,
		maximizable: true,
		maximized: false,
		resizeable: true,
		html: '<iframe src='+ ordersDetailsLink + '' + recordId +' style="width:100%;height:100%;border:none;"><p>Your browser does not support iframes.</p></iframe>'
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
			saveRecords(store,pagingToolbar,jsonURL,mySelectionModel);
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
	stateId : SM.dashboardComboBox.value.toLowerCase()+'EditorGridPanel',
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
	sm: mySelectionModel,
	tbar: [ SM.dashboardComboBox,
			{xtype: 'tbspacer',width: 15},
			{text:'From:'},fromDateTxt,{icon: imgURL + 'calendar.gif', menu: fromDateMenu},
			{text:'To:'},toDateTxt,{icon: imgURL + 'calendar.gif', menu: toDateMenu},
			{xtype: 'tbspacer',width: 15},
			SM.searchTextField,{ icon: imgURL + 'search.png' }
			],
	scrollOffset: 50,
	listeners: {
		cellclick: function(editorGrid,rowIndex, columnIndex, e) {
			try{
				var record  = editorGrid.getStore().getAt(rowIndex);
				cellClicked = true;
				var editLinkColumnIndex       = productsColumnModel.findColumnIndex('edit_url');
				var totalPurchasedColumnIndex = customersColumnModel.findColumnIndex('Total_Purchased');
				var lastOrderColumnIndex      = customersColumnModel.findColumnIndex('Last_Order');
				var nameLinkColumnIndex       = ordersColumnModel.findColumnIndex('name');
				var orderDetailsColumnIndex   = ordersColumnModel.findColumnIndex('details');

				if(SM.activeModule == 'Orders'){
					if(columnIndex == orderDetailsColumnIndex){
						billingDetailsIframe(record.id);
					}else if(columnIndex == nameLinkColumnIndex){
						checkModifiedAndshowDetails(record,rowIndex);
					}
				}else if(columnIndex == editLinkColumnIndex && SM.activeModule == 'Products'){
					var productsDetailsWindow = new Ext.Window({						
						stateId : 'productsDetailsWindow',
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
								var q = new Ext.state.CookieProvider();
								var thisObj =  q.get('productsDetailsWindow');

								if(thisObj != undefined){
									this.setSize(thisObj.width, thisObj.height);
									this.setPagePosition(thisObj.x,thisObj.y);
								}
							}
						},
						html: '<iframe src='+ productsDetailsLink + '' + record.id +' style="width:100%;height:100%;border:none;"><p>Your browser does not support iframes.</p></iframe>'
					});
					productsDetailsWindow.show('editLink');
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
		viewready: function(grid){
			showSelectedModule(SM.dashboardComboBox.value);
			console.debug('pagingToolbar.addProductButton',pagingToolbar.addProductButton);
		},
		reconfigure : function(grid,store,colModel ){
			var editorGridStateId = grid.getStateId();
			var state = Ext.state.Manager.get(editorGridStateId);
			
			grid.fireEvent('beforestaterestore', editorGrid, state);
			grid.applyState(Ext.apply({}, state));
			grid.fireEvent('staterestore', editorGrid, state);
		}
	}
});
for(var i=2;i<=8;i++)
editorGrid.getTopToolbar().get(i).hide();

function afterEdit(e) {
	pagingToolbar.saveButton.enable();
};
editorGrid.on('afteredit', afterEdit, this);
eval(SM.dashboardComboBox.value.toLowerCase()+'Store.load()');

//Products Store onload function.
productsStore.on('load', function () {
	cnt = -1;
	cnt_array = [];
	mySelectionModel.clearSelections();
	pagingToolbar.saveButton.disable();
});

//For pro version check if the required file exists
if(fileExists == 1){
	batchUpdateWindow.title = 'Batch Update';
	pagingToolbar.addProductButton.enable();

}else{
	batchUpdateRecords = function () {
		Ext.notification.msg('Smart Manager', 'Batch Update feature is available only in Pro version');
	};
	
	//disable inline editing for products
	var productsColumnCount = productsColumnModel.getColumnCount();
	for(var i=3; i<productsColumnCount; i++)
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