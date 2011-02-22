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
            m.slideIn('t').pause(1).ghost("t", {remove:true});
        },

        init : function(){
            var lb = Ext.get('lib-bar');
            if(lb){
                lb.show();
            }
        }
    };
}();// Floating notification end

var actions     = new Array(); //an array for actions combobox in batchupdate window.
var categories  = new Array(); //an array for category combobox in batchupdate window.
var cellClicked = false; //flag to check if any cell is clicked in the editor grid.
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
					
// BOF global components & function
var SM = {
		searchTextField   : '',
		dashboardComboBox : '',
		colModelTimeoutId : '',		
		activeModule      : 'Products' //default module selected.
};
var editorGrid        = '';
var showOrdersView    = '';
var showCustomersView = '';
var weightUnitStore   = '';	
var countriesStore    = '';
// EOF

Ext.onReady(function () {
	//START: Tooltips
	Ext.QuickTips.init();
	Ext.apply(Ext.QuickTips.getQuickTip(), {
		maxWidth: 150,
		minWidth: 100,
		dismissDelay: 9999999,
		trackMouse: true
	});
	//END: Tooltips
	
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
	var fromDateTxt = new Ext.form.TextField({emptyText:'From Date',readOnly: true,width: 80});
	var toDateTxt   = new Ext.form.TextField({emptyText:'To Date',readOnly: true,width: 80});
	var now        = new Date();
	var initDate   = new Date(0);
	var lastMonDate = new Date(now.getFullYear(), now.getMonth()-1, now.getDate()+1);
	
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

//START: Products.
	//START: Products ColumnModel.
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
		},{
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
		},{
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
		},{
			header: 'Inventory',
			sortable: true,
			align: 'right',
			dataIndex: 'quantity',
			tooltip: 'Inventory',
			editor: new fm.NumberField({
				allowBlank: false
			})
		},{
			header: 'SKU',
			sortable: true,
			dataIndex: 'sku',
			tooltip: 'SKU',
			editor: new fm.TextField({
				allowBlank: false
			})
		},{
			header: 'Group',
			sortable: true,
			dataIndex: 'category',
			tooltip: 'Category',
		},{
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
		},{
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
		},{
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
		},{
			header: 'Edit',
			sortable: true,
			tooltip: 'Product Info',
			dataIndex: 'edit_url',
			width: 50,
			id: 'editLink',
			renderer: function (value, metaData, record, rowIndex, colIndex, store) {
				return '<img id=editUrl src="' + imgURL + 'edit.gif"/>';
			}
		}],defaultSortable: true
	});
	//END: Products ColumnModel.
		
	//START: Products JsonReader.
	var productsJsonReader = new Ext.data.JsonReader({
		totalProperty: 'totalCount',
		root: 'items',
		fields: [{name: 'id',type: 'int'},
				 {name: 'name',type: 'string'},
				 {name: 'price',type: 'float'},
				 {name: 'quantity',type: 'int'},
				 {name: 'status',type: 'string'},
				 {name: 'sale_price',type: 'float'},
				 {name: 'sku',type: 'string'},
				 {name: 'category',type: 'string'},
				 {name: 'weight',type: 'float'},
				 {name: 'weight_unit',type: 'string'}]
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
			limit: limit
		},
		dirty: false,
		pruneModifiedRecords: true
	});
	//END: Products Store.
	
	//Products Store onload function.
	productsStore.on('load', function () {
		cnt = -1;
		cnt_array = [];
		mySelectionModel.clearSelections();
		pagingToolbar.saveButton.disable();
	});

	var showProductsView = function(){
		productsStore.baseParams.searchText = ''; //clear the baseParams for productsStore
		SM.searchTextField.reset(); //to reset the searchTextField

		//show all the hidden in the customers view
		for(var i=13;i<=21;i++)
		pagingToolbar.get(i).show();

		for(var i=2;i<=8;i++)
		editorGrid.getTopToolbar().get(i).hide();

		pagingToolbar.addProductButton.show();
		pagingToolbar.batchButton.show();
		pagingToolbar.get(14).show();

		productsStore.load();
		pagingToolbar.bind(productsStore);		
		editorGrid.reconfigure(productsStore,productsColumnModel);
		if(productsFields.totalCount != 0);
		productFieldStore.loadData(productsFields);

		var firstToolbar = batchUpdatePanel.items.items[0].items.items[0];
		var textfield    = firstToolbar.items.items[5];
		var weightUnitDropdown = firstToolbar.items.items[7];

		weightUnitDropdown.hide();
		weightUnitStore.loadData(weightUnits);
		textfield.show();
	};	
//END: Products.
	
//START: Components (pagingToolbar).
	var pagingToolbar = new Ext.PagingToolbar({
		items: ['->', '-',
		{
			text: 'Add Product',
			tooltip: 'Add a new product',
			icon: imgURL + 'add.png',
			disabled: true,
			ref : 'addProductButton',
			listeners: {click: function () { addProduct(productsStore,cnt_array,cnt); }}
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
				store.load();
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
//END: Components (pagingToolbar).

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
		//		var selectedRecords = mySelectionModel.getSelections();		
		Ext.each(modifiedRecords, function(r, i){
			if(r.data.category)
			r.data.category = newCatId;
			edited.push(r.data); //made changes since removed the saveRecords from afteredit event. @todo:remove this comment
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

							//if all the records on the first page are deleted & 
							//there are no more records to populate in the grid.
							if (currentPage == 1 && afterDeletePageCount == 0 && totalPages == 1){							
									myJsonObj.items = '';
									store.loadData(myJsonObj);									
							}
							
							//if all the records on the last page are deleted
							else if (currentPage == lastPage && afterDeletePageCount == 0)
							pagingToolbar.movePrevious();
							
							else
							pagingToolbar.doRefresh();							
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
//END: Functions.

//END: Components (ComboBox).
SM.dashboardComboBox = new Ext.form.ComboBox({
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
		}
	}
});
//END: Components (ComboBox).
	
//START: Customers
	
//	Ext.util.Format.comboRenderer = function(combo){
//	    return function(value){
//	        var record = combo.findRecord(combo.valueField, value);
//	        var result = '';
//	        if(record) {
//	        	result = record.get(combo.displayField);
//			}
//	        else
//	        	result = combo.valueNotFoundText;
//	        return result;
//	        
//	    }
//	}

	countriesStore = new Ext.data.Store({
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
	
	var countryCombo = new Ext.form.ComboBox({
		typeAhead: true,
	    triggerAction: 'all',
	    lazyRender:true,
	    mode: 'local',
//	    valueNotFoundText: 'no match',
	    store:countriesStore,
	    value: 'value',
	    valueField: 'name',
	    value: 'value',
	    displayField: 'name',
//	    hiddenValue: 'value',
//	    hiddenName: 'countryValue'
	});
	
	var customersColumnModel = new Ext.grid.ColumnModel({	
		columns:[mySelectionModel, //checkbox for
		{
			header: 'First Name',
			dataIndex: '2B_First_Name',
			tooltip: 'Billing First Name',
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 150
		},{
			header: 'Last Name',
			dataIndex: '3B_Last_Name',
			tooltip: 'Billing Last Name',
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 150
		},{
			header: 'Email',
			dataIndex: '8B_Email',
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
			dataIndex: '4B_Address',
			tooltip: 'Billing Address',
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 200
		},{
			header: 'Postal Code',
			dataIndex: '7B_Postal_Code',
			tooltip: 'Billing Postal Code',
			editor: new fm.TextField({
				allowBlank: true,
				allowNegative: false
			}),
			width: 150
		},{
			header: 'Country',
			dataIndex: '6B_Country',
			tooltip: 'Billing Country',
			editor:countryCombo,
//			renderer: Ext.util.Format.comboRenderer(countryCombo),
			width: 150
		},{
			header: 'City',
			dataIndex: '5B_City',
			tooltip: 'Billing City',
			align: 'left',			 
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 150
		},{
			header: 'Total Purchased',
			id: 'total_purchased',
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
			header: 'Shipping First Name',
			dataIndex: '10S_First_Name',
			tooltip: 'Shipping First Name',
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 200
		},{   
			header: 'Shipping Last Name',
			dataIndex: '11S_Last_Name',
			tooltip: 'Shipping Last Name',
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 200
		},{   
			header: 'Shipping Address',
			dataIndex: '12S_Address',
			tooltip: 'Shipping Address',
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 200		
		},{
			header: 'Shipping Postal Code',
			dataIndex: '16S_Postal_Code',
			tooltip: 'Shipping Postal Code',
			editor: new fm.TextField({
					allowBlank: true,
					allowNegative: false
			}),
				width: 200
		},{   
			header: 'Shipping City',
			dataIndex: '13S_City',
			tooltip: 'Shipping City',
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 200		
		},{   
			header: 'Shipping Country',
			dataIndex: '15S_Country',
			tooltip: 'Shipping Country',
			editor:countryCombo,
			width: 200		
		},{   
			header: 'Phone Number',
			dataIndex: '17S_Phone',
			tooltip: 'Shipping Phone Number',
			editor: new fm.TextField({
				allowBlank: true,
				allowNegative: false
			}),
			width: 180		
		}],defaultSortable: true
	});
	var customersColumnCount = customersColumnModel.getColumnCount();
	for(var i=10;i<customersColumnCount;i++)
	customersColumnModel.setHidden(i, true);
	
	var totPurDataType = '';	
	if (fileExists != 1) { 
		totPurDataType = 'string';
		customersColumnModel.columns[8].align = 'center';
		customersColumnModel.columns[9].align = 'center';
	}else{
		totPurDataType = 'int';
		customersColumnModel.setRenderer(8,amountRenderer);		
	}
	
	// Data reader class to create an Array of Records objects from a JSON packet.
	var customersJsonReader = new Ext.data.JsonReader
	({
		totalProperty: 'totalCount',
		root: 'items',
		fields:
		[
		{name:'id',type:'int'},		
		{name:'2B_First_Name',type:'string'},		
		{name:'3B_Last_Name',type:'string'},		
		{name:'8B_Email',type:'string'},
		{name:'4B_Address',type:'string'},
		{name:'7B_Postal_Code',type:'string'},
		{name:'6B_Country', type:'string'},
		{name:'5B_City', type:'string'},
		{name:'Total_Purchased',type:totPurDataType},		
		{name:'Last_Order', type:'string'},
		{name:'10S_First_Name', type:'string'},
		{name:'11S_Last_Name', type:'string'},
		{name:'12S_Address', type:'string'},
		{name:'16S_Postal_Code',type:'string'},
		{name:'13S_City', type:'string'},
		{name:'15S_Country', type:'string'},		
		{name:'17S_Phone', type:'string'},
		{name: 'Old_Email_Id', type: 'string'}
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
		//initial steps when store: customers is laoded
		SM.activeModule = 'Customers';
		SM.dashboardComboBox.setValue(SM.activeModule);

		if(cellClicked == false){
		ordersStore.baseParams.searchText = ''; //clear the baseParams for ordersStore
		SM.searchTextField.reset(); //to reset the searchTextField
		}
		
		for(var i=13;i<=17;i++)
		pagingToolbar.get(i).hide();		
		pagingToolbar.get(11).show();
		pagingToolbar.get(15).show();		
		pagingToolbar.get(18).show();
		pagingToolbar.get(19).show();

		for(var i=2;i<=8;i++)
		editorGrid.getTopToolbar().get(i).hide();
				
		try{
		if(customersFields.totalCount != 0){
		productFieldStore.loadData(customersFields); //@todo: use a common name fieldStore and load respective fields in it.
		}
		weightUnitStore.loadData(countries);
		customersStore.setBaseParam('searchText',emailId);
		customersStore.load();
		pagingToolbar.bind(customersStore);		
		editorGrid.reconfigure(customersStore,customersColumnModel);
		}catch(e){
				var err = e.toString();
				Ext.notification.msg('Error', err);			
		}
		
		var firstToolbar = batchUpdatePanel.items.items[0].items.items[0];
		var textfield    = firstToolbar.items.items[5];
		var countriesDropdown = firstToolbar.items.items[7];
		textfield.show();
		countriesDropdown.hide();
		weightUnitStore.loadData(countries);		
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

//START: Orders.	
	var ordersColumnModel = new Ext.grid.ColumnModel({	
		columns:[mySelectionModel, //checkbox for
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
			width: 350
		},{
			header: 'Amount',
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
			dataIndex: 'track_id',
			tooltip: 'Track Id',
			align: 'left',
			editor: new fm.TextField({
				allowBlank: false,
				allowNegative: false
			}),
			width: 110
		},{
			header: 'Status',
			dataIndex: 'order_status',
			tooltip: 'Status',
			width: 150,
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
			width: 180,
			editor: new fm.TextArea({				
				autoHeight: true
			})
		}],defaultSortable: true
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
		{name:'amount', type:'int'},
		{name:'details', type:'string'},
		{name:'track_id',type:'string'},
		{name:'order_status', type:'string'},
		{name:'notes', type:'string'}
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
			}			

			if(ordersFields.totalCount != 0) {
				productFieldStore.loadData(ordersFields); //@todo: use a common name fieldStore and load respective fields in it.
			}
			for(var i=13;i<=21;i++)
			pagingToolbar.get(i).show();

			for(var i=2;i<=8;i++)
			editorGrid.getTopToolbar().get(i).show();

			pagingToolbar.addProductButton.hide();
			pagingToolbar.get(14).hide();
			ordersStore.load();
			editorGrid.reconfigure(ordersStore,ordersColumnModel);
			pagingToolbar.bind(ordersStore);

			var firstToolbar = batchUpdatePanel.items.items[0].items.items[0];
			var textfield = firstToolbar.items.items[5];
			var weightUnitDropdown = firstToolbar.items.items[7];
			weightUnitDropdown.show();
			weightUnitStore.loadData(orderStatus);
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
		}, 700);
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
	}//END setting the params to store if search fields are with values (refresh event)

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
			limit: limit
		}
	};
	Ext.Ajax.request(o)
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
		{ name: 'value'}
		]
	}),
	autoDestroy: false,
	dirty: false
});
weightUnitStore.loadData(weightUnits);
// countries's store

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
						}else if(field_name == 'Orders Status' || field_name == 'Country' || field_name == 'Shipping Country'){
							actions_index = field_type;
							comboWeightUnitCmp.show();
							setTextfield.hide();
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
						comboWeightUnitCmp.reset();
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
						if (this.getValue() == 'SET_TO' && (field_name == 'Weight' || field_name == 'Variations: Weight' || field_name == 'Orders Status' || field_name == 'Country' || field_name == 'Shipping Country'))
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
				typeAhead: true,
				editable: true,
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
//				editable: false,
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
			batchUpdateRecords(batchUpdatePanel,toolbarCount,cnt_array,store,jsonURL,batchUpdateWindow)
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
	firstToolbar.items.items[0].reset();
	firstToolbar.items.items[2].reset();

	firstToolbar.items.items[4].reset();
	firstToolbar.items.items[4].hide();

	firstToolbar.items.items[5].reset();

	firstToolbar.items.items[7].reset();
	firstToolbar.items.items[7].hide();
	values = '';
	ids = '';
	batchUpdateWindow.hide();
};

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
}


	// Grid panel for the records to display
	editorGrid = new Ext.grid.EditorGridPanel({
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
				var record = editorGrid.getStore().getAt(rowIndex);
				cellClicked = true;
				if(SM.activeModule == 'Orders'){
					if(columnIndex == 5){
						billingDetailsIframe(record.id);
					}else if(columnIndex == 3){
						checkModifiedAndshowDetails(record,rowIndex);
					}
				}else if(columnIndex == 10 && SM.activeModule == 'Products'){
					var productsDetailsWindow = new Ext.Window({
						title: 'Products Details',
						width:500,
						height: 600,
						minimizable: false,
						maximizable: true,
						maximized: false,
						resizeable: true,
						animateTarget:'editLink',
						html: '<iframe src='+ productsDetailsLink + '' + record.id +' style="width:100%;height:100%;border:none;"><p>Your browser does not support iframes.</p></iframe>'
					});
					productsDetailsWindow.show();
				}else if(SM.activeModule == 'Customers'){
					if(fileExists == 1){
					if(columnIndex == 8){
						checkModifiedAndshowDetails(record,rowIndex);
					}else if(columnIndex == 9){
						billingDetailsIframe(record.json.id);
					}}
				}
			}catch(e) {
				var err = e.toString();
				Ext.notification.msg('Error', err);
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
	
//for pro version check if the required file exists
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
	for(var i=1; i<customersColumnCount; i++)
	customersColumnModel.setEditable(i,false);
}
});