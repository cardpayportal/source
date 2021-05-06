<?php
/**
 * @var array $params
 * @var array $methods
 */
$this->title = 'Тест Logs'

?>


<style>
	h3 {
		padding: 10px 5px;
		margin: 5px 0;
		background-color: #D2D2D2;
	}

	.spoyler-content {
		display: none;
		margin: 0;
	}

	.spoyler-content p {
		padding: 0;
		margin: 0;
	}
</style>

<div class="spoyler">

	<h3>Спойлер 1</h3>
	<div class="spoyler-content">
		<p>
			Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
		</p>
	</div>

	<h3>Спойлер 2</h3>
	<div class="spoyler-content">
		<p>
			Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
		</p>
	</div>

	<h3>Спойлер 3</h3>
	<div class="spoyler-content">
		<p>
			Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
		</p>
	</div>

</div>

<script>
	$(function(){
		$('h3').click(function(event) {
			event.preventDefault();

			if ( $(this).next('div').is(':visible') ) {
				$(this).next('div').animate({height: 'hide'}, 500);
			} else {
				$('.spoyler-content').animate({height: 'hide'}, 500);
				$(this).next('div').animate({height: 'show'}, 500);
			}

		});
	});
</script>
