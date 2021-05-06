<?
if(!$this->result)
	$this->result = $_SESSION['msg'];

if(!$this->result)
	$this->result = array();

$_SESSION['msg'] = array();
?>

<?if($this->result){?>
	<div class="row margin-top">
		<div class="col-sm-12">
			<?if($this->result['success']){?>
				<?foreach($this->result['success'] as $msg){?>
					<?if(!$msg) continue;?>
					<div class="alert alert-success">
						<?/*<h4>Ok</h4>*/?>
						<p><?=$msg?></p>
					</div>
				<?}?>
			<?}?>

			<?if($this->result['error']){?>
				<?foreach($this->result['error'] as $msg){?>
					<?if(!$msg) continue;?>
					<div class="alert alert-danger">
						<?/*<h4>Error</h4>*/?>
						<p><?=$msg?></p>
					</div>
				<?}?>
			<?}?>
		</div>
	</div>
<?}?>

