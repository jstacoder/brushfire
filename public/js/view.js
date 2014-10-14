bf.view = {
//+	pageSorting {
	///account for defaults, and split order and field
	parseSort: function(sort){
		var order = sort.substring(0,1)
		if(order != '-' && order != '+'){
			order = '+'
			var field = sort
		}else{
			var field = sort.substring(1)
		}
		return {order:order,field:field}
		
	},
	///arrange the arrows according to sorts
	headerArrows: function(){
		//direct  the arrows according to the sorts
		for(i in bf.sorts){
			var sort = bf.view.parseSort(bf.sorts[i])
			var column = $('.sortContainer [data-field="'+sort.field+'"]')
			if(sort.order == '+'){
				column.addClass('sortAsc')
			}else{
				column.addClass('sortDesc')
			}
		}
	},
	///shift clicks on sort header
	appendSort: function(newField){
		for(var i in bf.sorts){
			var sort = bf.view.parseSort(bf.sorts[i])
			if(newField == sort.field){
				sort.order = bf.toggle(sort.order,['+','-'])
				bf.sorts[i] = sort.order + sort.field
				return
			}
		}
		bf.sorts.push('+'+newField)
	},
	///non-shift clicks on sort header
	changeSort: function(newField){
		for(i in bf.sorts){
			var sort = bf.view.parseSort(bf.sorts[i])
			if(newField == sort.field){
				sort.order = bf.toggle(sort.order,['+','-'])
				bf.sorts = [sort.order + sort.field]
				return
			}
		}
		bf.sorts = ['+'+newField]
	},
	
	///reloads page with new sort
	sortPage: function(){
		var url = bf.appendUrl('_sort',bf.sorts.join(','),null,true)
		bf.relocate(url)
	},
	///reloads page with new page
	goToPage: function(page){
		var url = bf.appendUrl('_page',page,null,true)
		bf.relocate(url)
	},
	///gets the paging data on the page
	/**Excepts element with 'data-page="'total"page'"'*/
	getPaging: function(){
		var paging = $('*[data-paging]').attr('data-paging').split(',')
		var total = paging[0]
		var page = paging[1]
		if(bf.getRequestVar('_page')){
			page = bf.getRequestVar('_page')
		}
		if(!page){
			page = 1
		}
		
		total = Number(total); page = Number(page);
		return {total:total,page:page}
	},
//+	}
//+	System Messages {
	///replaces {_FIELD_} in the message with the found title
	/**
	supports following types of field name to title translation:
		. passed object with matching attribute
		. text of element with data-field matching field name and with data-title
		. data-title attribute value of element with 'name' attribute = field, if data-title attribute present
		. label text with for of field
		. placeholder of input with 'name' attribute = field
		. defaults to name of field
	*/
	parseMessage: function(message,map){
		if(message.name){
			var title = bf.view.fieldTitle(message.name,map)
			message.content = message.content.replace(/\{_FIELD_\}/g,'"'+title+'"');
		}
		return message
	},
	fieldTitle: function(field, map){
		while(true){//to avoid large nesting
			if(map){
				if(map[field]){
					title = map[field]
					break
				}
			}
			var holder = $('[data-field="'+field+'"][data-title]')
			if(holder.size()){
				title = holder.text()
				break
			}
			holder = $('[name='+field+'][data-title]')
			if(holder.size()){
				title = holder.attr('data-title')
				break
			}
			holder = $('[for='+field+']')
			if(holder.size()){
				title = holder.text()
				break
			}
			if($('[name='+field+'][placeholder]').size()){
				title = $('[name='+field+'][placeholder]').attr('placeholder')
				break
			}
			title = field
			break
		}
		return title
	},
	/**
	supports 2 types of error containers
		. 'data-field'=fieldName, class has 'messageContainer'
		. 'data-'context'MessageContainer'
	*/
	applyMessage: function(message){
		bf.view.highlightField(message)
		
		//++ add message text{
		//Unfortunately, no browser standard yet
		var messageEle = $('<div data-field="'+message.name+'" data-'+message.type+'Message class="message '+message.type+'"></div>').html(message.content)
		
		if(message.name){
			var messageContainer =  $('.messageContainer[data-field='+message.name+']');
		}
		if(!message.name || !messageContainer.size()){
			messageContainer = $('#'+message.context+'MessageContainer')
		}
		
		messageEle.hide().appendTo(messageContainer).fadeIn({duration:'slow'})
		//++ }
		if(message.expiry || message.closeable){
			bf.view.closeButton(messageEle)
		}
		if(message.expiry){
			if(message.expiry < 86400){//less than a day, it's an offset, not unix time
				var timeout = message.expiry * 1000
				//message.expiry = (new Date()).getTime()/1000 + message.expiry
			}else{
				var timeout = message.expiry - (new Date()).getTime()/1000
			}
			setTimeout((function(element,options){
					element.fadeOut(options)
				}).bind(null,messageEle,{duration:'slow',complete:function(){$(this).remove()}}),timeout)
		}
		
	},
	///highlight input container
	highlightField: function(message){
		if(message.name){
			var container = $('[data-field="'+message.name+'"][data-container]');
			if(!container.size()){
				var container = $('[name="'+message.name+'"]');
			}
			if(container.size()){
				container.addClass(message.type+'Highlight');
			}
		}
	},
	unhighlightFields: function(){
		var types = ['error','success','notice','warning']
		for(i in types){
			var eleClass = types[i]+'Highlight'
			$('.'+eleClass).removeClass(eleClass)
		}
	},
	
	///on inserting messages, these variables will be set
	hasError:false,
	hasContextError:{},
	/**
		messages attributes
			context : prefixed before message name, with '-'.  Used for message container
			name : field name message applies to
			content : message html content
			expiry : when to remove the message.  Unix time or time offset
			closeable : whether message   is closable (adds close button)
			type : what type of message  it is (error,success,notice,warning)
	*/
	insertMessage: function(message){
		//set error status if applicable
		if(message.type == 'error'){
			bf.view.hasError = true
			bf.view.hasContextError[message.context] = true
		}
		
		//message context prefixes field name
		if(message.context !='default'){
			message.name = message.context+'-'+message.name
		}
		
		if(bf.view.customeMessageParser){
			message = bf.view.customMessageParser(message)
		}else{
			message = bf.view.parseMessage(message)
		}
		if(bf.view.customMessageApplier){
			bf.view.customMessageApplier(message)
		}else{
			bf.view.applyMessage(message)
		}
	},
	///it is assumed that, if this function is used for ajax, the poster pre-removes existing messages
	insertMessages: function(messages){
		for(k in messages){
			bf.view.insertMessage(messages[k])
		}
	},
	///attempts to remove all evidence of inserted messages
	uninsertMessages: function(){
		bf.view.hasError = false
		for(context in bf.view.hasContextError){
			bf.view.hasContextError[context] = false
		}
		
		bf.view.unhighlightFields()
		
		$('.messageContainer').empty();
	},
	closeButton: function(ele,hide){
		var closeEle = $('<div class="closeButton"></div>')
		ele.prepend(closeEle)
		if(!hide){
			closeEle.click(function(){
					$(this).parent().fadeOut({complete:function(){$(this).parent().remove()}})
				})
		}else{
			closeEle.click(function(){$(this).parent().fadeOut()})
		}
	},
	form: {
		/**
		For subcategory options, the dependent field (depender) has a data-dependee attribute pointing to the parent category field.  This sets up a call to get the suboptions based on the parent fieldss
		*/
		getOptions:function(field){
			var depender = $('[name="'+field+'"]')
			var dependee = $('[name="'+depender.attr('data-dependee')+'"]')
			var post = {_getSubOptions:true}
			post[dependee.attr('name')] = dependee.val()
			$.post('',post,function(json){
					var formerValue = depender.val()
					bf.view.form.replaceOptions(depender,json.options[field],{value:0,text:'Select '+bf.view.fieldTitle(field)})
				},'json')
		},
		///removes select options and replaces them with those in obj, optionally preserving the value
		replaceOptions: function(select,obj,first,preserveValue){
			preserveValue = preserveValue === false ? false : true
			if(preserveValue){
				var formerValue = select.val()
			}
			$('option',select).remove()
			bf.view.form.addOptions(select,obj,first)
			if(preserveValue){
				if(bf.view.form.selectOption(select,formerValue).size()){
					select.val(formerValue)
				}
			}
		},
		///appends optins from obj into a select
		addOptions: function(select,obj,first){
			if(first){
				$('<option></option>').val(first.value).text(first.text).appendTo(select)
			}
			for(i in obj){
				$('<option></option>').val(i).text(obj[i]).appendTo(select)
			}
		},
		///get an option within a select that has some value
		selectOption: function(select,value){
			match = $('option',select).filter(function() {
				return this.value === value;
				})
			return match
		}
	}
//+	}
}



$(function(){
	if(bf.json){
//+	handle system messages{
		if(bf.json.messages){
			bf.view.insertMessages(bf.json.messages)
		}
//+	}
	}
	//form dependent dynamics
	if($('form').size()){
//+ add message containers on certain conditions {
		var addMessageEle = $('[data-addMessageContainers]')
		if(addMessageEle.size() > 0){
			$('input, select, textarea',addMessageEle).each(function(){
					$(this).after('<div class="messageContainer" data-field="'+$(this).attr('name')+'"></div>')
				})
		}
//+ }
//+ handle dependent data fields {
		$('[data-dependee]').each(function(){
				var dependeeField = $(this).attr('data-dependee')
				var field = $(this).attr('name')
				$('[name="'+dependeeField+'"]').change(function(){bf.view.form.getOptions(field)}).change()
			})
//+ }
	}

//+	handle paging and sorting{
//+		sorting{
	if($('.sortContainer').size()){
		bf.sorts = []
		var sort = bf.getRequestVar('_sort');
		if(sort){//use URL if sort passed, otherwise use html sort data
			$('.sortContainer:not(.inlineSort)').attr('data-sort',sort);
		}else{
			sort = $('.sortContainer:not(.inlineSort)').attr('data-sort');
		}
		if(sort){
			bf.sorts = sort.split(',')
			bf.view.headerArrows();//byproduct is to standardize the sorts
		}
		//add click event to sortable columns
		$('.sortContainer:not(.inlineSort) *[data-field]').click(function(e){
			var field = $(this).attr('data-field')
			//if shift clicked, just append sort
			if(e.shiftKey){
				bf.view.appendSort(field)
			}else{
				bf.view.changeSort(field)
			}
			bf.view.sortPage()
		})
	}
//+		}
//+		paging{
	var pagingContainer = $('*[data-paging]')
	if(pagingContainer.size()){
		var paging = bf.view.getPaging(); var page = paging.page; var total = paging.total
		if(total > 1){
			//+	make the html paginater skeleton {
			if($('.paging').size()){
				var pagingEle = $('.paging')
			}else{
				var pagingEle = $('<div class="paging"></div>')
				pagingContainer.append(pagingEle)
			}
			var paginaterDiv = $("<div class='paginater'></div>")
			pagingEle.append(paginaterDiv)
			//+ }
			
			//+	center the current page if possible {
			var context = 2;//only  show context * 2 + 1 page buttons
			var start = Math.max((page - context),1)
			var end = Math.min((page + context),total)
			var extraContext = context - (page - start)
			if(extraContext){
				end = Math.min(end + extraContext,total)
			}else{
				var extraContext = context - (end - page)
				if(extraContext){
					start = Math.max(start - extraContext,1)
				}
			}
			//+	}
			
			//+ complete the paginater {
			if(page != 1){
				paginaterDiv.append('<div class="clk first">&lt;&lt;</div><div class="clk prev">&nbsp;&lt;&nbsp;</div>')
			}
			
			var pages = []
			for(var i=start;i <= end; i++){
				var current = i == page ? ' current' : ''
				paginaterDiv.append('<div class="clk pg'+current+'">'+i+'</div>')
			}
			if(page != total){
				paginaterDiv.append('<div class="clk next">&nbsp;&gt;&nbsp;</div><div class="clk last">&gt;&gt;</div>')
			}
			paginaterDiv.append("<div class='direct'>\
						<input title='Total of "+total+"' type='text' name='directPg' value='"+page+"'/>\
						<div class='clk go'>Go</div>\
					</div>")
			
			//+	}
			
			//clicks
			$('.clk:not(.disabled)',paginaterDiv).click(function(e){
				var paging = bf.view.getPaging(); var page = paging.page; var total = paging.total
				//var target = $(e.target)
				var target = $(this)
				if(target.hasClass('pg')){
					page = target.text()
				}else if(target.hasClass('next')){
					page = page + 1
				}else if(target.hasClass('last')){
					page = total
				}else if(target.hasClass('first')){
					page = 1
				}else if(target.hasClass('prev')){
					page = page - 1
				}else if(target.hasClass('go')){
					var parent = target.parents('.paginater')
					page = Math.abs($('input',parent).val())
				}
				bf.view.goToPage(page)
			})
			
			//ensure enter on "go" field changes page, not some other form
			$('input',paginaterDiv).keypress(function(e){
				if (e.which == 13) {
					e.preventDefault();
					$('.go',paginaterDiv).click();
				}
			});
			
			
			
		}
	}
//+		}
//+	}
	
//+	tool tips {
	///add [?] to open tool tips from the data-help attribute value
	$('*[data-help]').each(function(){
		var tooltippedElement = $(this)
		var tag = this.nodeName.toLowerCase()
		if(tag == 'input' || tag == 'select' || tag == 'textarea'){
			var field = tooltippedElement.attr('name')
			var relativeElement = $('*[data-fieldDisplay="'+field+'"]').eq(0)
			var relativeName = 'bottom'
		}else{
			var relativeElement = tooltippedElement
			if(tag == 'span'){
				var relativeName = 'after'
			}else{
				var relativeName = 'bottom'
			}
			
		}
		var marker = $('<span class="tooltipMarker">[?]</span>')
		marker.attr('data-tooltip',tooltippedElement.attr('data-help'))
		
		if(relativeName == 'bottom'){
			marker.appendTo(relativeElement)
		}else{
			relativeElement.after(marker)
		}
	})
	var tooltipMakerCount = 0
	///to have both tooltips w/ and w/o [?], this logic is separated
	///tool tip value can be text, html, or formed like: "url:", where in the remain part is a url to go to
	$('*[data-tooltip]').each(function(){
		var tooltipMaker = $(this)
		tooltipMakerCount = tooltipMakerCount + 1
		var markerId = tooltipMaker.attr('id')
		if(!markerId){
			markerId = 'tooltipMaker-'+tooltipMakerCount
			tooltipMaker.attr('id',markerId)
		}
		//either a new tab tooltip or an onpage tooltip
		var toolTipData = tooltipMaker.attr('data-tooltip')
		if(toolTipData.substr(0,4) == 'url:'){
			var url = toolTipData.substr(4)
			tooltipMaker.click(function(){
				bf.relocate(url,null,'tab')
			})
			
		}else{	
			var tooltip = $('<div/>',{html:toolTipData,class:'tooltip',id:'tooltip-'+markerId}).prependTo('body')
			bf.view.closeButton(tooltip,true)
			
			tooltipMaker.click(function(e){
				var tooltipMaker = $(this)
				var tooltip = $('#tooltip-'+tooltipMaker.attr('id'))
				tooltip.css(tooltipMaker.offset())
				tooltip.fadeIn({duration:'slow'})
				tooltip.click(function(e){
					e.stopPropagation()//don't prevent highlighting, just stop propogation
				})
				//e.stopImmediatePropagation()
				e.stopPropagation()
			})
		}
	})
	$('body').click(function(){
		$('.tooltip').hide()
	})
//+	}
	$('[data-redirect]').click(function(event){
			event.preventDefault()
			window.location = $(this).attr('data-redirect')
		})
	
	//handle newTab anchor tabs
	$('a.newTab').click(function(event){
		bf.relocate($(event.target).attr('href'),null,'tab')
		return false
	})
	
//+ handle inline value formatting {
	$('[data-timeFormat]').each(function(){
			var format = $(this).attr('data-timeFormat')
			var date = bf.date.strtotime($(this).text())
			$(this).text(bf.date.format(format,date))
		})
	$('[data-timeAgo]').each(function(){
			var options = $(this).attr('data-timeAgo')
			options = JSON.parse(options ? options : '{}');
			var date = bf.date.strtotime($(this).text())
			$(this).text(bf.date.timeAgo(date,options.round,options.type))
		})
	$('.datepicker').datepicker()
//+ }
});

