<!DOCTYPE html>
<html>
	<head>
		<title><?= isset($data['title'])? $data['title'] : "My App" ?></title>
	</head>
	<body>
		
		<div id='content'>
		<?=$contents?>
		</div>
		
	</body>
</html>
