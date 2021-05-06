$(document).ready(function(){
	
	$('#checkAll').click(function(){
		if($(this).prop('checked'))
			$('input.check').prop('checked', true);
		else
			$('input.check').prop('checked', false);
	});


    $('input.short').each(function(){

        var val = $(this).val();

        if(val.length > $(this).attr('size'))
        {
            $(this).attr('title', val);
            $(this).val('...' + val.substr(val.length - $(this).attr('size'), $(this).attr('size')));
        }
    });

	
	$('.click2select').click(function(){

        if($(this).hasClass('short') && $(this).attr('title').length > 0)
        {
            $(this).val($(this).attr('title'));
        }

		$(this).select();	
	});

    $(document).mouseup(function (e){
        var div = $(".short");
        if (!div.is(e.target) && div.has(e.target).length === 0)
        {
            var val = div.val();

            if(val.length > div.attr('size'))
            {
                div.attr('title', val);
                div.val('...' + val.substr(val.length - div.attr('size'), div.attr('size')));
            }
        }
    });

});

function sendRequest(url,post_data,callback){
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

$(document).ready(function(){
    $('.shortContent').click(function(){
        $(this).hide();
        $(this).parent().find('.fullContent').show().select();
    });
});

$(document).mouseup(function (e){
    var div = $(".fullContent");
    if (!div.is(e.target)
        && div.has(e.target).length === 0) {
        div.hide(); // скрываем его
        div.parent().find('.shortContent').show();
    }
});