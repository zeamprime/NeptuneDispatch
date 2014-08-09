<div class='container'>
	<div class='row'>
		<div class='col-md-12' id='center-banner'>
			
			<br/><br/><br/>
			<p>
			Hello, this is my <i>shiny</i>, <b>new</b> project
			</p>
			<br/><br/><br/>
			
		</div>
	</div>
	<div class='row'>
		<div class='col-md-4'>
			<a href='<?=Page::absPath('/help')?>'>API documentation</a>
			<div>
			All functionality is accessible via REST API. Contact us to request an API key if
			interested.
			</div>
		</div>
		<div class='col-md-4'>
			<a href='#' onclick='alert("Not available yet"); return false;'>Request an invite</a>
			<div>
			Available only to beta testers. If all goes well you'll get your chance to
			use it.
			</div>
		</div>
		<div class='col-md-4' id='login-box'>
			<div>
			Already a member? Log in:
			</div>
			<div style='color: red' id='errors'>
				<?if( count($data['errors']) > 0) echo implode('<br/>',$data['errors'])?>
			</div>
			<form method='POST' id='login_form'>
				<label for='username'>Username:</label>
				<input type='text' size='30' name='username' id='username' value='<?=$data['username']?>'/><br/>
			
				<label for='password'>Password:</label>
				<input type='password' size='30' name='password' id='password' /><br/>
			
				<input type='submit' name='_submit' value='Login' />
			</form>
		
			<div>
			Login via username or email address.
			</div>
		</div>
	</div>
</div>

<script type='text/javascript'>
//RestAPI.useHMAC = <?=HMAC::usingHMAC()? 'true':'false'?>;
//RestAPI.appKey = "<?=$data['appKey']?>";
RestAPI.webRoot = "<?=Page::fullURL('')?>";
$(function() {
	//TODO: load username cookie, maybe auto-login, etc.
});
</script>
