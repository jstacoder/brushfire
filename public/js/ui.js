bf.ui = {
//+	pageSorting {
	///account for defaults, and split order and field
	parseSort: function(sort){
		var order = sort.substring(0,1)
		if(order != '-' && order != '+'){
			order = '+'
			field = sort
		}else{
			field = sort.substring(1)
		}
		return [order,field]
		
	},
	///arrange the arrows according to sorts
	headerArrows: function(){
		//direct  the arrows according to the sorts
		for(i in bf.sorts){
			var [order,field] = bf.ui.parseSort(bf.sorts[i])
			var column = $('.sortContainer *[data-field="'+field+'"]')
			if(order == '+'){
				column.addClass('sortAsc')
			}else{
				column.addClass('sortDesc')
			}
		}
	},
	///shift clicks on sort header
	appendSort: function(newField){
		for(i in bf.sorts){
			var [order,field] = bf.ui.parseSort(bf.sorts[i])
			if(newField == field){
				order = bf.toggle(order,['+','-'])
				bf.sorts[i] = order+field
				return
			}
		}
		bf.sorts.push('+'+newField)
	},
	///non-shift clicks on sort header
	changeSort: function(newField){
		for(i in bf.sorts){
			var [order,field] = bf.ui.parseSort(bf.sorts[i])
			if(newField == field){
				order = bf.toggle(order,['+','-'])
				bf.sorts = [order+field]
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
	getPaging: function(){
		var [total,page] = $('*[data-paging]').attr('data-paging').split(',')
		if(bf.getRequestVar('_page')){
			page = bf.getRequestVar('_page')
		}		
		total = Number(total); page = Number(page);
		return [total,page]
	},
//+	}
//+	System Messages {
	insertMessage: function(message){
		if(message.name){
			var fieldDisplayElement = $('*[data-fieldDisplay='+message.name+']')
			
			if(fieldDisplayElement.length > 0){
				var fieldDisplay = $('*[data-fieldDisplay='+message.name+']').text()
			}else{
				var fieldDisplay = message.name
			}
			
			message.content = message.content.replace(/\{_FIELD_\}/g,'"'+fieldDisplay+'"');
			
			var container = $('*[data-fieldContainer='+message.name+']');
			if(container.size() > 0){
				container.addClass(message.type+'Highlight');
				if(!container.attr('title')){
					container.attr('title',message.content)
				}
			}
		}
		
		var messageEle = $('<div class="message '+message.type+'"></div>').html(message.content)
		messageEle.hide().appendTo('#'+message.context+'MsgBox').fadeIn({duration:'slow'})
		bf.ui.closeButton(messageEle)
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
	insertMessages: function(messages){
		for(k in messages){
			bf.ui.insertMessage(messages[k])
		}
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
	}
//+	}
}



$(function(){
	if(bf.json){
//+	handle system messages{
		if(bf.json.messages){
			bf.ui.insertMessages(bf.json.messages)
		}
//+	}
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
				bf.ui.headerArrows();//byproduct is to standardize the sorts
			}
			//add click event to sortable columns
			$('.sortContainer:not(.inlineSort) *[data-field]').click(function(e){
				var field = $(this).attr('data-field')
				//if shift clicked, just append sort
				if(e.shiftKey){
					bf.ui.appendSort(field)
				}else{
					bf.ui.changeSort(field)
				}
				bf.ui.sortPage()
			})
		}
//+		}
//+		paging{
		var pagingContainer = $('*[data-paging]')
		if(pagingContainer.size()){
			var [total,page] = bf.ui.getPaging()
			if(total > 1){
				//+	make the html paginater skeleton {
				if($('.paging')){
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
					var [total,page] = bf.ui.getPaging()
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
					bf.ui.goToPage(page)
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
	}
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
			bf.ui.closeButton(tooltip,true)
			
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
	
	//handle newTab anchor tabs
	$('a.newTab').click(function(event){
		bf.relocate($(event.target).attr('href'),null,'tab')
		return false
	})
	
});

