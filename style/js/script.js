$(document).ready(function(){
	
	$('#checkAll').click(function(){
		if($(this).prop('checked'))
			$('input.check').prop('checked', true);
		else
			$('input.check').prop('checked', false);
	});
	
	$('.click2select').click(function(){
		$(this).select();	
	});

    //select element text by click
    $('.selectText').click(function(){
        var r = document.createRange();
        r.selectNode(this);
        document.getSelection().addRange(r);
    });

});

function sendRequest(url,post_data,callback)
{
    $.ajaxSetup({cache: false}); 
    
    var result_content = '';
    
	$.ajax({
	    type: 'POST',
	    url: url,
	    data: post_data,
	    dataType: "text",
	    cache: false,
	    timeout: 0,
	    async: true,
	    success: function(msg){
			result_content = $.parseJSON(msg);
         	callback(result_content);
     	},
     	
	    error: function(msg){
	    }   
     
	});
 
	return result_content;
}

function alertObj(o){var s="";for(k in o){s+=k+": "+o[k]+"\r\n";}alert(s);}