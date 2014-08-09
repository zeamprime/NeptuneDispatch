<!DOCTYPE html>
<html>
	<head>
		<title><?= isset($data['title'])? $data['title'] : "Lunch" ?></title>
		<link rel='stylesheet' type='text/css' href='inc/bootstrap.min.css' />
		<?
		if( count($data['scripts']) > 0 ) {
			foreach($data['scripts'] as $js) {
				echo "<script type='text/javascript' src='$js'></script>\n";
			}
		}
		?>
		<style type='text/css'>
			body { background-image: url('<?=Page::absPath('/')?>img/diag-stripes.png'); }
			/*body { background-color: #a9ead0; }*/
			#center-banner { 
				/*background-color: #ffe0; 
				border-radius: 10px;*/
				margin: 5px 0px;
			}
			div.container { 
				background-color: #fff; /*rgba(255,255,246,0.8);*/
				border-radius: 10px;
				margin-top: 10px;
				padding-bottom: 30px;
				box-shadow: #cdc 2px 5px 10px;
			}
		</style>
	</head>
	<body>
		
		<div id='content'>
		<?=$contents?>
		</div>
		
	</body>
</html>
