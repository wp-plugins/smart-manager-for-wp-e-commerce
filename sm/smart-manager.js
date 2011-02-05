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
            m.slideIn('t').pause(3).ghost("t", {remove:true});
        },

        init : function(){
            var lb = Ext.get('lib-bar');
            if(lb){
                lb.show();
            }
        }
    };
}();
// Floating notification end

var actions = new Array();
var categories = new Array();
var product_ids = new Array();
var search_timeout_id = 0;
var limit = 100;
actions['blob'] = [{
'id': 0,
'name': 'set to',
'value': 'SET_TO'
},
{
'id': 1,
'name': 'append',
'value': 'APPEND'
},
{
'id': 2,
'name': 'prepend',
'value': 'PREPEND'
}];

actions['bigint'] = [{
'id': 0,
'name': 'set to',
'value': 'SET_TO'
}];

actions['real'] = [{
'id': 0,
'name': 'set to',
'value': 'SET_TO'
},
{
'id': 1,
'name': 'increase by %',
'value': 'INCREASE_BY_%'
},
{
'id': 1,
'name': 'decrease by %',
'value': 'DECREASE_BY_%'
},
{
'id': 2,
'name': 'increase by number',
'value': 'INCREASE_BY_NUMBER'
},
{
'id': 3,
'name': 'decrease by number',
'value': 'DECREASE_BY_NUMBER'
}];
actions['int'] = [{
'id': 0,
'name': 'set to',
'value': 'SET_TO'
},
{
'id': 1,
'name': 'increase by number',
'value': 'INCREASE_BY_NUMBER'
},
{
'id': 2,
'name': 'decrease by number',
'value': 'DECREASE_BY_NUMBER'
}];
actions['float'] = [{
'id': 0,
'name': 'set to',
'value': 'SET_TO'
},
{
'id': 1,
'name': 'increase by number',
'value': 'INCREASE_BY_NUMBER'
},
{
'id': 2,
'name': 'decrease by number',
'value': 'DECREASE_BY_NUMBER'
}];
actions['string'] = [{
'id': 0,
'name': 'Yes',
'value': 'YES'
},
{
'id': 1,
'name': 'No',
'value': 'NO'
}];
actions['category_actions'] = [{
'id': 0,
'name': 'set to',
'value': 'SET_TO'
},
{
'id': 1,
'name': 'add to',
'value': 'ADD_TO'
},
{
'id': 2,
'name': 'remove from',
'value': 'REMOVE_FROM'
}];
Ext.onReady(function () {
	Ext.QuickTips.init();
	Ext.apply(Ext.QuickTips.getQuickTip(), {
		maxWidth: 150,
		minWidth: 100,
		dismissDelay: 9999999,
		trackMouse: true
	});
	var fm = Ext.form;
	var toolbarCount = 1;
	var cnt = -1;
	var cnt_array = [];	
	var activeModule = 'Products';
	var newRecordAdded = 'false';
	var amountRenderer = Ext.util.Format.numberRenderer('0,0.00');
	
	var fromDateTxt = new Ext.form.TextField({emptyText:'From Date',readOnly: true,width: 80});
	var toDateTxt   = new Ext.form.TextField({emptyText:'To Date',readOnly: true,width: 80});
	
	//BOF setting fromDate  & lastDate
	var now         = new Date();
	var lastMonDate = new Date(now.getFullYear(), now.getMonth()-1, now.getDate()+1);
	
	fromDateTxt.setValue(lastMonDate.format('M j Y'));
	toDateTxt.setValue(now.format('M j Y'));
	//EOF setting fromDate  & lastDate
	
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
	});
	var objRegExp = /(^-?\d\d*\.\d*$)|(^-?\d\d*$)|(^-?\.\d\d*$)/;
	var regexError = 'Only numbers are allowed';
	var productsColumnModel = new Ext.grid.ColumnModel({
		columns: [mySelectionModel,
		{
			header: 'Name',
			sortable: true,
			dataIndex: 'name',
			tooltip: 'Product Name',
			width: 300,
			editor: new fm.TextField({
				allowBlank: false
			})
		},
		{
			header: 'Price',
			type: 'float',
			align: 'right',
			sortable: true,
			dataIndex: 'price',
			tooltip: 'Price',
			renderer: amountRenderer,
			editor: new fm.NumberField({
				allowBlank: false,
				allowNegative: false
			})
		},
		{
			header: 'Sale Price',
			sortable: true,
			align: 'right',
			dataIndex: 'sale_price',
			renderer: amountRenderer,
			tooltip: 'Sale Price',
			editor: new fm.NumberField({
				allowBlank: false,
				allowNegative: false
			})
		},
		{
			header: 'Inventory',
			sortable: true,
			align: 'right',
			dataIndex: 'quantity',
			tooltip: 'Inventory',
			editor: new fm.NumberField({
				allowBlank: false
			})
		},
		{
			header: 'SKU',
			sortable: true,
			dataIndex: 'sku',
			tooltip: 'SKU',
			editor: new fm.TextField({
				allowBlank: false
			})
		},
		{
			header: 'Group',
			sortable: true,
			dataIndex: 'category',
			tooltip: 'Category',
		},
		{
			header: 'Weight',
			colSpan: 2,
			sortable: true,
			align: 'right',
			dataIndex: 'weight',
			tooltip: 'Weight',
			editor: new fm.NumberField({
				allowBlank: false,
				allowNegative: false
			})
		},
		{
			header: 'Unit',
			sortable: true,
			dataIndex: 'weight_unit',
			tooltip: 'Weight Unit',
			editor: new fm.ComboBox({
				typeAhead: true,
				triggerAction: 'all',
				transform: 'weight_unit',
				displayField: 'weightUnit',
				valueField: 'weight_unit',
				lazyRender: true,
				listClass: 'x-combo-list-small'
			})
		},
		{
			header: 'Status',
			sortable: true,
			dataIndex: 'status',
			tooltip: 'Status',
			editor: new fm.ComboBox({
				typeAhead: true,
				triggerAction: 'all',
				transform: 'status',
				lazyRender: true,
				listClass: 'x-combo-list-small'
			})
		},
		{
			header: 'Edit',
			sortable: true,
			tooltip: 'Product Info',
			dataIndex: 'edit_url',
			width: 50,
			renderer: function (value, metaData, record, rowIndex, colIndex, store) {
				return '<img id=editUrl src="' + imgURL + 'edit.gif"/>';
			}
		}]
	});
	productsColumnModel.defaultSortable = true;
	var jsonReader = new Ext.data.JsonReader({
		totalProperty: 'totalCount',
		root: 'items',
		fields: [{
			name: 'id',
			type: 'int'
		},
		{
			name: 'name',
			type: 'string'
		},
		{
			name: 'price',
			type: 'float'
		},
		{
			name: 'quantity',
			type: 'int'
		},
		{
			name: 'status',
			type: 'string'
		},
		{
			name: 'sale_price',
			type: 'float'
		},
		{
			name: 'sku',
			type: 'string'
		},
		{
			name: 'category',
			type: 'string'
		},
		{
			name: 'weight',
			type: 'float'
		},
		{
			name: 'weight_unit',
			type: 'string'
		}, ]
	});
	var productsStore = new Ext.data.Store({
		reader: jsonReader,
		proxy: new Ext.data.HttpProxy({
			url: jsonURL
		}),
		baseParams: {
			cmd: 'getData',
			active_module: activeModule,
			start: 0,
			limit: limit
		},
		dirty: false,
		pruneModifiedRecords: true
	});
	
	var pagingToolbar = new Ext.PagingToolbar({
		items: ['->', '-',
		{
			text: 'Add Product',
			tooltip: 'Add a new product',
			icon: imgURL + 'add.png',
			disabled: true,
			ref : 'addProductButton',
			listeners: { click: function () { addProduct(editorGrid,productsStore,cnt_array,cnt); newRecordAdded = 'true';}}
		}, '-',
		{
			text: 'Batch Update',
			tooltip: 'Update selected items',
			icon: imgURL + 'batch_update.png',
			id: 'BU',
			disabled: true,
			ref: 'batchButton',
			scope: this,
			listeners: {
				click: function () {
					batchUpdateWindow.show();
				}
			}
		}, '-',
		{
			text: 'Delete',
			icon: imgURL + 'delete.png',
			disabled: false,
			id: 'del',
			tooltip: 'Delete the selected items',
			ref: 'deleteButton',
			disabled: true,
			listeners: {
				click: function () {
					deleteRecords();
				}
			}
		}, '-',
		{
			text: 'Save',
			tooltip: 'Save all Changes',
			icon: imgURL + 'save.png',
			disabled: true,
			scope: this,
			ref: 'saveButton',
			id: 'save',
			listeners:{ click : function () {				
				if(activeModule == 'Orders') 
				store = ordersStore;
				else
				store = productsStore;
				saveRecords(store,pagingToolbar,jsonURL,activeModule,mySelectionModel);
			}}
		}],
		pageSize: limit,
		store: productsStore,
		displayInfo: true,
		style: {
			width: '100%'
		},
		hideBorders: true,
		align: 'center',
		displayMsg: 'Displaying {0} - {1} of {2}',
		emptyMsg: 'Product list is empty',
	});

	productsStore.on('load', function () {
		cnt = -1;
		cnt_array = [];
		mySelectionModel.clearSelections();
		pagingToolbar.saveButton.disable();		
	});
		
	// Function to save modified records
	var saveRecords = function(store,pagingToolbar,jsonURL,activeModule,mySelectionModel){

		// Gets all records modified since the last commit.
		// Modified records are persisted across load operations like pagination or store reload.
		var modifiedRecords = store.getModifiedRecords();
		if(!modifiedRecords.length) {
			return;
		}
		var edited  = [];
//		var selectedRecords = mySelectionModel.getSelections();
		Ext.each(modifiedRecords, function(r, i){
			if(r.data.category){
				var categoryName = r.data.category;
				r.data.category = new_cat_id;
			}
			edited.push(r.data);
			r.data.category = categoryName;
		});

		var o = {
			url:jsonURL
			,method:'post'
			,callback: function(options, success, response)	{
				var myJsonObj = Ext.decode(response.responseText);
				if(true !== success){
					Ext.showError(response.responseText);
					return;
				}try{					
					pagingToolbar.saveButton.disable();
					store.commitChanges();
					mySelectionModel.clearSelections();
					if(newRecordAdded == 'true'){
						store.load();
						newRecordAdded = 'false';
					}
					Ext.notification.msg('Success', myJsonObj.msg);					
					return;
				}catch(e){
					Ext.notification.msg('Warning', 'No Records were updated');
					return;
				}
			}
			,scope:this
			,params:
			{
				cmd:'saveData',
				active_module: activeModule,
				edited:Ext.encode(edited)
			}};
			Ext.Ajax.request(o);
	};

	// Function to delete selected records
	var deleteRecords = function () {
		var selected = editorGrid.getSelectionModel();
		var records = selected.selections.keys;
		var getDeletedRecords = function (btn, text) {
			if (btn == 'yes') {
				var o = {
					url: jsonURL,
					method: 'post',
					callback: function (options, success, response) {

						if(activeModule == 'Products')
						store = productsStore;
						else
						store = ordersStore;

						var myJsonObj    = Ext.decode(response.responseText);
						var delcnt       = myJsonObj.delCnt;
						var totalRecords = jsonReader.jsonData.totalCount;
						var lastPage     = Math.ceil(totalRecords / limit);
						var currentPage  = pagingToolbar.readPage();
						var lastPageTotalRecords = store.data.length;

						if (true !== success) {
							Ext.showError(response.responseText);
							return;
						}try {							
							var afterDeletePageCount = lastPageTotalRecords - delcnt;
							if (currentPage == 1 && afterDeletePageCount == 0){
								myJsonObj.items = '';
								store.loadData(myJsonObj);
							} else if (currentPage == lastPage && afterDeletePageCount == 0) pagingToolbar.movePrevious();
							else
							store.load();
							Ext.notification.msg('Success', myJsonObj.msg);							
						} catch (e) {
							Ext.notification.msg('Warning', 'No Records were deleted');
							return;
						}
					},
					scope: this,
					params: {
						cmd: 'delData',
						active_module: activeModule,
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

	var dashboardComboBox = new Ext.form.ComboBox({
		store: new Ext.data.ArrayStore({
			autoDestroy: true,
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
		width: 135,
		listeners: {
			select: function () {
				if (this.value == 'Customers') {
					Ext.notification.msg('Coming Soon', 'This feature will be added in an upcoming release');
					dashboardComboBox.setValue(activeModule);
				}else if (this.value == 'Orders') {
					activeModule = this.value;
					searchTextField.setValue('');
					ordersStore.load();
					pagingToolbar.bind(ordersStore);
					pagingToolbar.addProductButton.hide();
					pagingToolbar.get(14).hide();
					pagingToolbar.get(20).hide();
					editorGrid.reconfigure(ordersStore,ordersColumnModel);
					productFieldStore.loadData(ordersfields);						
					
					for(var i=2;i<=8;i++)
					editorGrid.getTopToolbar().get(i).show();
				    
					var firstToolbar = batchUpdatePanel.items.items[0].items.items[0];
					var textfield = firstToolbar.items.items[5];
					var weightUnitDropdown = firstToolbar.items.items[7];
					
					weightUnitDropdown.show();
					weightUnitDropdown.store = ordersStatusStore;
					textfield.hide();					
				}else{
					activeModule = this.value;
					searchTextField.setValue('');
					productsStore.load();
					pagingToolbar.bind(productsStore);
					pagingToolbar.addProductButton.show();
					pagingToolbar.batchButton.show();

					pagingToolbar.get(14).show();
					pagingToolbar.get(20).show();
					editorGrid.reconfigure(productsStore,productsColumnModel);					
					productFieldStore.loadData(fields);
					
					for(var i=2;i<=8;i++)
					editorGrid.getTopToolbar().get(i).hide();
					
					var firstToolbar = batchUpdatePanel.items.items[0].items.items[0];
					var textfield    = firstToolbar.items.items[5];
					var weightUnitDropdown = firstToolbar.items.items[7];
					
					weightUnitDropdown.hide();
					weightUnitDropdown.store = weightUnitStore;
					textfield.show();
				}
			}
		}
	});

	//Orders Start.
	var ordersColumnModel = new Ext.grid.ColumnModel
	({
		columns:[
		mySelectionModel, //checkbox for
		{
			header: 'Order Id',
			dataIndex: 'id',
			tooltip: 'Order Id'
		},{
			header: 'Date / Time',
			dataIndex: 'date',
			tooltip: 'Date / Time',
			width: 250
		},{
			header: 'Name',
			dataIndex: 'name',
			tooltip: 'Customer Name',
			width: 250
		},{
			header: 'Amount',
			dataIndex: 'amount',
			tooltip: 'Amount',
			align: 'right',
			renderer: amountRenderer,
			width: 150
		},{
			header: 'Details',
			id: 'details',
			dataIndex: 'details',
			tooltip: 'Details',
			width: 150
		},{
			header: 'Track Id',
			dataIndex: 'track_id',
			tooltip: 'Track Id',
			align: 'left',
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 150
		},{
			header: 'Status',
			dataIndex: 'order_status',
			tooltip: 'Status',
			width: 200,
			editor: new fm.ComboBox({
				typeAhead: true,
				triggerAction: 'all',
				transform: 'order_status',
				lazyRender: true,
				listClass: 'x-combo-list-small'
			})
		},{
			header: 'Orders Notes',
			dataIndex: 'notes',
			tooltip: 'Orders Notes',
			width: 200,
			editor: new fm.TextArea({				
				autoHeight: true
			})
		}]
	});

	ordersColumnModel.defaultSortable = true;

	// Data reader class to create an Array of Records objects from a JSON packet.
	var ordersJsonReader = new Ext.data.JsonReader
	({
		totalProperty: 'totalCount',
		root: 'items',
		fields:
		[
		{name:'id',type:'int'},
		{name:'date',type:'string'},
		{name:'name',type:'string'},
		{name:'amount', type:'int'},
		{name:'details', type:'string'},
		{name:'track_id',type:'string'},
		{name:'order_status', type:'string'},
		{name:'notes', type:'string'}
		]
	});

	var productsStore = new Ext.data.Store({
		reader: jsonReader,
		proxy: new Ext.data.HttpProxy({
			url: jsonURL
		}),
		baseParams: {
			cmd: 'getData',
			active_module: activeModule,
			start: 0,
			limit: limit
		},
		dirty: false,
		pruneModifiedRecords: true
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
	//Orders End

	var searchTextField = new Ext.form.TextField({
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
				// make server request after some time - let people finish typing their keyword
				clearTimeout(search_timeout_id);
				search_timeout_id = setTimeout(function () { searchLogic() }, 300);
			}
		}
	});
	var searchLogic = function () {
		var cmdParams            = '';
		
		//BOF setting the params to store if search fields are with values (refresh event)
		switch(activeModule) {
			case 'Products':
				productsStore.setBaseParam('searchText', searchTextField.getValue());
				break;
			case 'Orders':
				ordersStore.setBaseParam('searchText', searchTextField.getValue());
				ordersStore.setBaseParam('fromDate', fromDateTxt.getValue());
				ordersStore.setBaseParam('toDate', toDateTxt.getValue());
				break;
		}//EOF setting the params to store if search fields are with values (refresh event)
		
		var o = {
			url: jsonURL,
			method: 'post',
			callback: function (options, success, response) {
				var myJsonObj = Ext.decode(response.responseText);
				if (true !== success) {
					Ext.showError(response.responseText);
					return;
				}
				try {
					var records_cnt = myJsonObj.totalCount;
					if (records_cnt == 0) myJsonObj.items = '';
					(activeModule == 'Products') ? productsStore.loadData(myJsonObj) : ordersStore.loadData(myJsonObj);
				} catch (e) {
					return;
				}
			},
			scope: this,
			params: {
				cmd: 'getData',
				active_module: activeModule,
				searchText: searchTextField.getValue(),
				fromDate: fromDateTxt.getValue(),
			    toDate: toDateTxt.getValue(),
				start: 0,
				limit: limit
			}
		};
		Ext.Ajax.request(o)
	};
	var productFieldStore = new Ext.data.Store({
		reader: new Ext.data.JsonReader({
			idProperty: 'id',
			totalProperty: 'totalCount',
			root: 'items',
			fields: [{
				name: 'id'
			},
			{
				name: 'name'
			},
			{
				name: 'type'
			},
			{
				name: 'value'
			}]
		}),
		autoDestroy: false,
		dirty: false
	});
	productFieldStore.loadData(fields);

	var actionStore = new Ext.data.ArrayStore({
		fields: ['id', 'name', 'value'],
		autoDestroy: false
	});
	actionStore.loadData(actions);

	var categoryStore = new Ext.data.ArrayStore({
		fields: ['id', 'name'],
		autoDestroy: false
	});

	var weightUnitStore = new Ext.data.ArrayStore({
		id: 0,
		fields: ['id', 'name', 'value'],
		data: [
		[0, 'Pounds', 'pound'],
		[1, 'Ounces', 'ounce'],
		[2, 'Grams', 'gram'],
		[3, 'Kilograms', 'kilogram']
		],
		autoDestroy: false
	});

	var ordersStatusStore = new Ext.data.ArrayStore({
		id: 0,
		fields: ['id', 'name', 'value'],
		data: [
		[0, 'Order Received', '1'],
		[1, 'Accepted Payment', '2'],
		[2, 'Job Dispatched', '3'],
		[3, 'Closed Order', '4']
		],
		autoDestroy: false
	});

	
	var mask = new Ext.LoadMask(Ext.getBody(), {
		msg: "Please wait..."
		//		msg: "Loading..."
	});

	var batchUpdateToolbarInstance = Ext.extend(Ext.Toolbar, {
		cls: 'batchtoolbar',
		constructor: function (config) {
			config = Ext.apply({
				items: [{
					xtype: 'combo',
					allowBlank: false,
					align: 'center',
					store: productFieldStore,
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
							var field_type = this.store.reader.jsonData.items[selectedFieldIndex].type;
							var field_name = this.store.reader.jsonData.items[selectedFieldIndex].name;
							var actionsData = new Array();
							var toolbarParent = this.findParentByType(batchUpdateToolbarInstance, true);
							var comboCategoriesActionCmp = toolbarParent.get(4);
							var setTextfield = toolbarParent.get(5);
							var comboActionCmp = toolbarParent.get(2);
							var comboWeightUnitCmp = toolbarParent.get(7);
							objRegExp = /(^-?\d\d*\.\d*$)|(^-?\d\d*$)|(^-?\.\d\d*$)/;;
							regexError = 'Only numbers are allowed';
							if (field_type == 'category') {
								setTextfield.hide();
								comboWeightUnitCmp.hide();
								comboCategoriesActionCmp.show();
								actions_index = field_type + '_actions';
								categoryStore.loadData(categories[this.getValue()]);
							} else if (field_name == 'Stock: Quantity Limited' || field_name == 'Publish' || field_name == 'Stock: Inform When Out Of Stock') {
								setTextfield.hide();
								comboWeightUnitCmp.hide();
								comboCategoriesActionCmp.hide();
								actions_index = field_type;
							} else if (field_name == 'Weight' || field_name == 'Variations: Weight') {
								comboWeightUnitCmp.hide();
								setTextfield.show();
								comboCategoriesActionCmp.hide();
								actions_index = field_type;
							}else if(field_name == 'Orders Status'){
								actions_index = field_type;
							} else {
								setTextfield.show();
								if (field_type == 'blob') {
									objRegExp = '';
									regexError = '';
								}
								comboWeightUnitCmp.hide();
								comboCategoriesActionCmp.hide();
								actions_index = field_type;
							}
							for (j = 0; j < actions[actions_index].length; j++) {
								actionsData[j] = new Array();
								actionsData[j][0] = actions[actions_index][j].id;
								actionsData[j][1] = actions[actions_index][j].name;
								actionsData[j][2] = actions[actions_index][j].value;
							}
							actionStore.loadData(actionsData);
							setTextfield.reset();
							comboActionCmp.reset();
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
							if (this.lastSelectionText != undefined) {
								var actionsData = new Array();
								var toolbarParent = this.findParentByType(batchUpdateToolbarInstance, true);
								var comboFieldCmp = toolbarParent.get(0);
								var selectedFieldIndex = comboFieldCmp.selectedIndex;
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
							}
						},
						select: function () {
							var toolbarParent = this.findParentByType(batchUpdateToolbarInstance, true);
							var comboFieldCmp = toolbarParent.get(0);
							var selectedFieldIndex = comboFieldCmp.selectedIndex;
							var field_name = comboFieldCmp.store.reader.jsonData.items[selectedFieldIndex].name;
							var comboWeightUnitCmp = toolbarParent.get(7);
							if (this.getValue() == 'SET_TO' && (field_name == 'Weight' || field_name == 'Variations: Weight' || field_name == 'Orders Status'))
							comboWeightUnitCmp.show();
							else
							comboWeightUnitCmp.hide();
						}
					}
				}, '',
				{
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
					hidden: true,
					selectOnFocus: true,
					listeners: {
						focus: function () {
							var actionsData = new Array();
							var toolbarParent = this.findParentByType(batchUpdateToolbarInstance, true);
							var comboFieldCmp = toolbarParent.get(0);
							var selectedFieldIndex = comboFieldCmp.selectedIndex;
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
						}
					}
				},
				{
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
					selectOnFocus: true,
				}, '',
				{
					xtype: 'combo',
					allowBlank: false,
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
					editable: false,
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

				if(activeModule == 'Orders'){
					newBatchUpdateToolbar.items.items[5].hide();
					newBatchUpdateToolbar.items.items[7].show();
					newBatchUpdateToolbar.items.items[7].store = ordersStatusStore;
				}
			}
		}]
	});
	batchUpdateToolbar.get(0).get(9).hide();

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
				if(activeModule == 'Orders'){
				store = ordersStore;
				cm = ordersColumnModel;
				}
				else{
				store = productsStore;
				cm = productsColumnModel;
				}
		batchUpdateRecords(batchUpdatePanel,toolbarCount,editorGrid,cnt_array,store,jsonURL,batchUpdateWindow)
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
		width: 800,
		height: 300,
		plain: true,
		closeAction: 'hide',
	});
	batchUpdateWindow.on('hide', afterClose, this);

	function afterClose(e) {
		for (sb = toolbarCount; sb >= 1; sb--){
			if(batchUpdatePanel.get(sb) != undefined)
			batchUpdatePanel.remove(batchUpdatePanel.get(sb));
		}
		var firstToolbar = batchUpdatePanel.items.items[0].items.items[0];
		var fieldDropdown = firstToolbar.items.items[0];
		var actionDropdown = firstToolbar.items.items[2];
		var categoryDropdown = firstToolbar.items.items[4];
		var textValueDropdown = firstToolbar.items.items[5];
		var weightUnitDropdown = firstToolbar.items.items[7];
		fieldDropdown.reset();
		actionDropdown.reset();
		categoryDropdown.reset();
		textValueDropdown.reset();
		weightUnitDropdown.reset();
		if(activeModule != 'Orders')
		weightUnitDropdown.hide();
		values = '';
		ids = '';
		batchUpdateWindow.hide();
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

	/* Grid panel for the records to display */
	var editorGrid = new Ext.grid.EditorGridPanel({
		store: productsStore,
		cm: productsColumnModel,
		renderTo: 'editor-grid',
		height: 700,
		stripeRows: true,		
		frame: true,
		loadMask: mask,
		columnLines: true,
		clicksToEdit: 1,
		bbar: [pagingToolbar],
		viewConfig: { forceFit: true },
		sm: mySelectionModel,
		tbar: [ dashboardComboBox,
				{xtype: 'tbspacer',width: 15},
				{text:'From:'},fromDateTxt,{icon: imgURL + 'calendar.gif', menu: fromDateMenu},
		 		{text:'To:'},toDateTxt,{icon: imgURL + 'calendar.gif', menu: toDateMenu},
		 		{xtype: 'tbspacer',width: 15},
		 		searchTextField,{ icon: imgURL + 'search.png' }
		 	   ],
		scrollOffset: 50,
		listeners: {
			cellclick: function(editorGrid,rowIndex, columnIndex, e) {
				var record = editorGrid.getStore().getAt(rowIndex);
				var rec_details_link ='';
				if(columnIndex == 5 && activeModule == 'Orders') {					
					var billingDetailsWindow = new Ext.Window({  
					     title: 'Order Details',
					     width:500,
					     height: 600,
					     minimizable: false,  
					     maximizable: true,  
					     maximized: false,
					     resizeable: true,
					     html: '<iframe src='+ orders_details_link + '' + record.id +' style="width:100%;height:100%;border:none;"><p>Your browser does not support iframes.</p></iframe>'  
					 });  					
					billingDetailsWindow.show();
				}else if(columnIndex == 10 && activeModule == 'Products'){ 					
					var productsDetailsWindow = new Ext.Window({  
					     title: 'Products Details', 
					     width:500,
					     height: 600,
					     minimizable: false,  
					     maximizable: true,  
					     maximized: false,
					     resizeable: true,
					     html: '<iframe src='+ products_details_link + '' + record.id +' style="width:100%;height:100%;border:none;"><p>Your browser does not support iframes.</p></iframe>'  
					 });  					
					productsDetailsWindow.show();
				}
			}
		}
	});
	
	for(var i=2;i<=8;i++)
	editorGrid.getTopToolbar().get(i).hide();
	
	editorGrid.on('afteredit', afterEdit, this);
	function afterEdit(e) {
		pagingToolbar.saveButton.enable();
	};
	productsStore.load();
	
	/* for full version check if the required file exists */
	if(fileExists == 1){
		batchUpdateWindow.title = 'Batch Update';
		pagingToolbar.addProductButton.enable();
	}else{
		batchUpdateRecords = function () {
			Ext.notification.msg('Smart Manager', 'Batch Update feature is available only in Pro version');
		};		
		for(var i=3; i<=9; i++){
			if(i != 6)
			productsColumnModel.columns[i].editor.disabled = true;
		}
	}
});
