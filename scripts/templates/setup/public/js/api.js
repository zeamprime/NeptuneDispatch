/**
 * @author Everett Morse
 * Copyright (c) 2013 Everett Morse. All rights reserved.
 * www.neptic.com
 *
 * Implements HMAC signing and other required features for our REST API.
 */

var RestAPI = {
	userId: '',
	tokenId: '',
	token: '',
	appKey: '',
	useHMAC: false,
	webRoot: '',
	
	request: function(method, url, data, opts) {
	
		opts = _.defaults(opts? opts : {}, {
			'accepts': 'application/json',
			'userId': RestAPI.userId,
			'tokenId': RestAPI.tokenId,
			'token': RestAPI.token,
			'HMAC': this.useHMAC,
			'appKey': this.appKey
		});
	
		var headers = {
			'Accept': opts.accepts
		};
	
		if( opts.userId.length > 0 ) {
			if( opts.token.length < 1 ) {
				throw new Exception("Missing user token!");
			}
		
			if( opts.HMAC )
				headers['X-USER-KEY'] = ""+opts.userId+":"+opts.tokenId;
			else
				headers['X-USER-KEY'] = ""+opts.userId+":"+opts.token;
		}
		
		if( data !== null && data !== undefined && typeof(data) == 'object' )
			data = JSON.stringify(data);
		
		//console.log(opts);
		console.log(url);
		if( opts.appKey.length > 0 ) {
			if( opts.HMAC ) {
				var appKey = opts.appKey.split(':');
				if( appKey[0].length == 0 || appKey[1].length < 24 )
					throw new Exception("Malformed API Key");
			
				headers['X-APP-ID'] = appKey[0];
			
				var now = new Date();
				var time = now.toString('yyyy-MM-dd HH:mm:ss');
				tz = now.toString().match(/.*\(([A-Z]{3})\).*/)[1];
				time += " " + tz;
			
				var urlParts = url.split('?');
				var query = urlParts[1] === undefined? "" : urlParts[1];
				var text = encodeURI(urlParts[0]+query)+time+appKey[0]+opts.userId+(data !== null? data : "");
			
				var shaObj = new jsSHA(text, "TEXT");
				var key = appKey[1];
				if( opts.userId != "" )
					key += opts.token;
				var hash = shaObj.getHMAC(key, "TEXT", "SHA-1", "B64");
				/*
				console.log(text);
				console.log(key);
				console.log(hash);//*/
			
				headers['X-HASH-TIME'] = time;
				headers['X-HASH'] = hash;
			} else {
				headers['X-APP-ID'] = opts.appKey;
			}
		}
	
		if( !opts.success ) {
			opts.success = function(data, status, xhr) {
				console.log(data);
			};
		}
		if( !opts.error ) {
			opts.error = function(xhr, status, err) {
				var msg = "HTTP ERROR: "+xhr.status+" "+err;
				var text = xhr.responseText;
				if(text != null && text != "")
					msg += "\n-----------------------\n" + text;
				console.log(msg);
			};
		}
	
		$.ajax(url, {
			//'accepts': $('#accepts').val(),
			'headers': headers,
			'type': method,
			'processData': false,
			'data': data,
			'contentType': 'application/json',
		
			'success': opts.success,
			'error': opts.error
		});
	},
	
	setUser: function(userId, tokenId, token) {
		RestAPI.userId = userId;
		RestAPI.tokenId = tokenId;
		RestAPI.token = token;
		
		//This is secret data, so should not be a cookie.
		$.localStorage.set('lunch-sess',userId+':'+tokenId+':'+token);
	},
	loadUser: function() {
		if( $.localStorage.isSet('lunch-sess') ) {
			var vals = $.localStorage.get('lunch-sess').split(':');
			RestAPI.userId = vals[0];
			RestAPI.tokenId = vals[1];
			RestAPI.token = vals[2];
			if( vals[0] && vals[1] && vals[2] )
				return true;
		}
		return false;
	},
	/**
	 * Check if we have a stored session and if so verify that it is still valid.
	 * Only once it is valid do we set the RestAPI user and token variables.
	 * 
	 * For convenience this requests related data in the Login request, which gets saved in local
	 * storage login-data with an updated_at date. This is returned to the cb too.
	 * 
	 * @returns (to CB) false on failure, Login response on success
	 */
	loadAndVerifyUser: function(cb) {
		var userId,tokenId,token;
		if( $.localStorage.isSet('lunch-sess') ) {
			var vals = $.localStorage.get('lunch-sess').split(':');
			userId = vals[0];
			tokenId = vals[1];
			token = vals[2];
			if( !userId || !tokenId || !token )
				return cb(false);
		}
		
		RestAPI.request('GET', RestAPI.webRoot+'/login/'+tokenId+'?rel=true', null, {
			//In order to get the related data in a response we need to successfully authenticate.
			//So let's actually use this token. If it errors then we know it's expired just as sure
			// as it returning to tell us so.
			'userId': userId,
			'tokenId': tokenId,
			'token': token,
			
			'success': function(data, status, xhr) {
				if( data.login ) {
					//Good, so set the vars
					RestAPI.userId = userId;
					RestAPI.tokenId = tokenId;
					RestAPI.token = token;
					
					data.updated_at = new Date();
					$.localStorage.set('login-data',data);
					cb(data);
				}
			},
			'error': function(xhr, status, err) {
				cb(false)
			}
		});
	},
	
	setAppKey: function(appKey, useHMAC) {
		this.appKey = appKey;
		this.useHMAC = useHMAC;
	}
}
