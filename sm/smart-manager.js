var actions = new Array();
var categories = new Array();
var product_ids = new Array();
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
	var cm = new Ext.grid.ColumnModel({
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
			renderer: 'usMoney',
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
			renderer: 'usMoney',
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
				var prodID = record.get('id');
				return '<a id=editUrl href=' + jsonURL + '?product_id=' + prodID + ' target=_product title=edit><img src="' + imgURL + 'edit.gif"></a>';
			}
		}]
	});
	cm.defaultSortable = true;
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
	var dataStore = new Ext.data.Store({
		reader: jsonReader,
		proxy: new Ext.data.HttpProxy({
			url: jsonURL
		}),
		baseParams: {
			cmd: 'getData',
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
			listeners: { click: function () { addProduct(editorGrid,dataStore,cnt_array,cnt); }}
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
			listeners:{ click : function () { saveRecords(dataStore,pagingToolbar,jsonURL); dataStore.reload();}}
		}],
		pageSize: limit,
		store: dataStore,
		displayInfo: true,
		style: {
			width: '100%'
		},
		hideBorders: true,
		align: 'center',
		displayMsg: 'Displaying {0} - {1} of {2}',
		emptyMsg: 'Product list is empty',
	});
	dataStore.on('load', function () {
		cnt = -1;
		cnt_array = [];
		mySelectionModel.clearSelections();
		pagingToolbar.saveButton.disable();
	});	
	
	var deleteRecords = function () {
		var selected = editorGrid.getSelectionModel();
		var records = selected.selections.keys;
		var getDeletedRecords = function (btn, text) {
			if (btn == 'yes') {
				var o = {
					url: jsonURL,
					method: 'post',
					callback: function (options, success, response) {
						var myJsonObj = Ext.decode(response.responseText);
						var delcnt = myJsonObj.delCnt;
						var totalRecords = jsonReader.jsonData.totalCount;
						var lastPage = Math.ceil(totalRecords / limit);
						var currentPage = pagingToolbar.readPage();
						var lastPageTotalRecords = dataStore.data.length;
						if (true !== success) {
							Ext.showError(response.responseText);
							return;
						}
						try {
							Ext.Msg.show({
								title: 'Success',
								msg: myJsonObj.msg,
								width: 300,
								buttons: Ext.MessageBox.OK,
								animEl: 'batchUpdateToolbar',
								closable: false,
								icon: Ext.MessageBox.INFO
							});
							var afterDeletePageCount = lastPageTotalRecords - delcnt;
							if (currentPage == 1 && afterDeletePageCount == 0) {
								myJsonObj.items = '';
								dataStore.loadData(myJsonObj);
							} else if (currentPage == lastPage && afterDeletePageCount == 0) pagingToolbar.movePrevious();
							else
							pagingToolbar.doRefresh();
						} catch (e) {
							Ext.Msg.show({
								title: 'Warning',
								msg: 'No Records were deleted',
								width: 350,
								buttons: Ext.MessageBox.OK,
								animEl: 'batchUpdateToolbar',
								closable: false,
								icon: Ext.MessageBox.WARNING
							});
							return;
						}
					},
					scope: this,
					params: {
						cmd: 'delData',
						data: Ext.encode(records)
					}
				};
				Ext.Ajax.request(o);
			}
		}
		if (records.length >= 1) {
			Ext.Msg.show({
				title: 'Confirm File Delete',
				msg: 'Are you sure you want to delete the selected record?',
				width: 300,
				buttons: Ext.MessageBox.YESNO,
				fn: getDeletedRecords,
				animEl: 'del',
				closable: false,
				icon: Ext.MessageBox.QUESTION
			})
		}
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
				var dashboard = function (btn, text) {
					if (btn) dashboardComboBox.reset();
				}
				if (this.lastSelectionText == 'Customers') {
					Ext.Msg.show({
						title: 'Coming Soon',
						msg: 'This feature will be added in an upcoming release',
						width: 380,
						buttons: Ext.MessageBox.OK,
						animEl: 'tl',
						fn: dashboard,
						closable: false,
						icon: Ext.MessageBox.INFO
					})
				} else if (this.lastSelectionText == 'Orders') {
					Ext.Msg.show({
						title: 'Coming Soon',
						msg: 'This feature will be added in an upcoming release',
						width: 380,
						buttons: Ext.MessageBox.OK,
						animEl: 'tl',
						fn: dashboard,
						closable: false,
						icon: Ext.MessageBox.INFO
					})
				} else
				dataStore.load();
			}
		},
	});
	var searchTextField = new Ext.form.TextField({
		id: 'tf',
		width: 500,
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
				searchLogic();
			}
		}
	});
	var searchLogic = function () {
		dataStore.setBaseParam('searchText', searchTextField.getValue());
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
					dataStore.loadData(myJsonObj);
				} catch (e) {
					return;
				}
			},
			scope: this,
			params: {
				cmd: 'getData',
				searchText: searchTextField.getValue(),
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
	var mask = new Ext.LoadMask(Ext.getBody(), {
		msg: "Please wait..."
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
					emptyText: 'Select a Product Field...',
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
					emptyText: 'Select an Action...',
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
							if (this.getValue() == 'SET_TO' && (field_name == 'Weight' || field_name == 'Variations: Weight')) comboWeightUnitCmp.show();
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
					emptyText: 'Select a Category...',
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
					store: new Ext.data.ArrayStore({
						id: 0,
						fields: ['id', 'name', 'value'],
						data: [
						[0, 'Pounds', 'pound'],
						[1, 'Ounces', 'ounce'],
						[2, 'Grams', 'gram'],
						[3, 'Kilograms', 'kilogram']
						]
					}),
					style: {
						fontSize: '12px',
						paddingLeft: '2px'
					},
					hidden: true,
					valueField: 'value',
					displayField: 'name',
					mode: 'local',
					cls: 'searchPanel',
					emptyText: 'Select a Unit...',
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
				batchUpdateRecords(batchUpdatePanel,toolbarCount,editorGrid,cnt_array,dataStore,jsonURL,batchUpdateWindow);
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
		for (sb = toolbarCount; sb >= 1; sb--)
		batchUpdatePanel.remove(batchUpdatePanel.get(sb));
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
		weightUnitDropdown.hide();
		values = '';
		ids = '';
		batchUpdateWindow.hide();
	};
	
	/* for full version check if the required file exists */
	var gridPanel = Ext.grid.GridPanel;	
	if(fileExists == 1){
		batchUpdateWindow.title = 'Batch Update';
		pagingToolbar.addProductButton.enable();
		gridPanel = Ext.grid.EditorGridPanel;
	}else{
		batchUpdateRecords = function () {
			Ext.Msg.show({
				title: 'Smart Manager',
				msg: 'Batch Update feature is available only in Pro version',
				width: 400,
				buttons: Ext.MessageBox.OK,
				animEl: 'batchUpdateToolbar',
				closable: false,
				icon: Ext.MessageBox.WARNING
			});
		};
	}
	
	/* Grid panel for the records to display */
	var editorGrid = new gridPanel({
		store: dataStore,
		cm: cm,
		renderTo: 'editor-grid',
		height: 750,
		frame: true,
		loadMask: mask,
		columnLines: true,
		clicksToEdit: 1,
		bbar: [pagingToolbar],
		viewConfig: { forceFit: true },
		sm: mySelectionModel,
		tbar: [dashboardComboBox, ' ', ' ', ' ', ' ', ' ', searchTextField,{ icon: imgURL + 'search.png' }],
		scrollOffset: 50
	});
	editorGrid.on('afteredit', afterEdit, this);
	function afterEdit(e) {	pagingToolbar.saveButton.enable(); };
	dataStore.load();	
});