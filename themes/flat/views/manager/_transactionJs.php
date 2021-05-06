<script>
	$(document).ready(function(){
		<?//показать все транзакции аккаунта?>
		$(document).on( "click", ".showTransactions", function(){
			$(this).parent().find('tr[data-param=toggleRow]').slideToggle();
			$(this).hide();
			$(this).parent().find('button.hideTransactions').show();
		});

		<?//скрыть старые транзакции?>
		$(document).on( "click", ".hideTransactions", function() {
			$(this).parent().find('tr[data-param=toggleRow]').slideToggle();
			$(this).hide();
			$(this).parent().find('button.showTransactions').show();
		});

		<?//изменить метку кошелька?>
		$(document).on('click', '.changeLabel', function(){
			$(this).parent().append('<input type="text" data-id="'+$(this).attr('data-id')+'" class="changeLabelText" value="'+$(this).parent().find('strong').text()+'"/>');
			$(this).parent().find('strong,a').hide();
		});

		$(document).on('keyup', '.changeLabelText', function(event){
			if(event.keyCode==13)
			{
				var obj = $(this);

				var postData = 'id='+$(this).attr('data-id')+'&label='+$(this).val();

				sendRequest('<?=url('manager/ajaxChangeLabel')?>', postData, function(response){

					if(response)
					{
						if(response.error==0)
						{
							obj.parent().find('strong').text(obj.val());
							obj.parent().find('strong,a').show();
							obj.remove();
						}
						else
							alert(response.error);
					}
					else
						alert('ошибка запроса 1');
				});
			}

		});
	});
</script>