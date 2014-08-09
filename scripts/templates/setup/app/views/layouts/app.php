<!DOCTYPE html>
<html>
	<head>
		<title><?= isset($data['title'])? $data['title'] : "Lunch" ?></title>
		<link rel='stylesheet' type='text/css' href='<?=Page::absPath('/')?>inc/bootstrap.min.css' />
		<? if( isset($data['style']) ) {?>
		<link rel='stylesheet' type='text/css' href='<?=Page::absPath('/')?>inc/<?=$data['style']?>.css' />
		<?}?>
		
		<script src="<?=Page::absPath('/')?>js/jquery.js"></script>
		<script src="<?=Page::absPath('/')?>js/jquery.cookie.js"></script>
		<script src="<?=Page::absPath('/')?>js/jquery.storageapi.min.js"></script>
		<script src="<?=Page::absPath('/')?>js/underscore.js"></script>
		<script src="<?=Page::absPath('/')?>js/date.js"></script>
		<script src="<?=Page::absPath('/')?>js/sha1.js"></script>
		<script src="<?=Page::absPath('/')?>js/api.js"></script>
		<script src="<?=Page::absPath('/')?>js/ICanHaz.js"></script>
		<script src="<?=Page::absPath('/')?>js/bootstrap.min.js"></script>
		<script src="<?=Page::absPath('/')?>js/menu.js"></script>
		
		<?
		if( count($data['scripts']) > 0 ) {
			foreach($data['scripts'] as $js) {
				echo "<script type='text/javascript' src='".Page::absPath($js)."'></script>\n";
			}
		}
		?>
		
		<?
		if( count($data['styles']) > 0 ) {
			foreach($data['styles'] as $css) {
				echo "<link rel='stylesheet' type='text/css' href='".Page::absPath($css)."' />";
			}
		}
		?>
		
	</head>
	<body>
		<nav class="navbar navbar-default" role="navigation">
			<!-- Brand and toggle get grouped for better mobile display -->
			<div class="navbar-header">
				<button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
					<span class="sr-only">Toggle navigation</span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
				</button>
				<a class="navbar-brand" href="<?=Page::absPath('/')?>">Lunch</a>
			</div>

			<!-- Collect the nav links, forms, and other content for toggling -->
			<div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
				<ul class="nav navbar-nav">
					<li class="dropdown" id='group-menu'>
						<a href="#" class="dropdown-toggle" data-toggle="dropdown">Groups <b class="caret"></b></a>
						<ul class="dropdown-menu">
							<li class='disabled'><a href="#">Loading...</a></li>
						</ul>
					</li>
					<li class="dropdown" id='event-menu'>
						<a href="#" class="dropdown-toggle" data-toggle="dropdown">Events <b class="caret"></b></a>
						<ul class="dropdown-menu">
							<li class='disabled'><a href="#">Loading ...</a></li>
						</ul>
					</li>
				</ul>
				<ul class="nav navbar-nav navbar-right" style='margin-right: 5px;'>
					<?if( $data['user'] ) {?>
					<p class='navbar-text'>Signed in as 
						<a href='<?=Page::absPath('/user/'.$data['user']->id)?>' class='navbar-link' id='profile-btn'><?=$data['user']->name?></a>
					</p>
					<li><button type="button" class="btn navbar-btn btn-default" id='logout-btn'>Sign out</button></li>
					<?}?>
				</ul>
			</div><!-- /.navbar-collapse -->
		</nav>

		
		<div id='content'>
		<?=$contents?>
		</div>
		
	</body>
</html>
