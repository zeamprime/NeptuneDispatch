/**
 * @author Everett Morse
 * Copyright (c) 2014 Everett Morse. All rights reserved.
 * www.neptic.com
 *
 * In-event pseudo-real-time threaded chat.
 * EventController owns the socket, we subscribe to and send chat events.
 */

var ChatController = function(domId, event, user, socket) {
	this.event = event;
	this.id = domId;
	this.user = user;
	this.socket = socket;
	
	//will be given later
	this.users = [];
	this.messages = {};
	this.sortedUsers = null; //null means we haven't loaded the logged-in list yet
	this.commenters = [];
	
	//Prefs (TODO: load from server)
	this.showAbsent = false; //false is like Lunch v1
};
ChatController.prototype = {
	
	/**
	 * Initialization and loading done, now ready to build and connect UI.
	 */
	ready: function() {
		var t = this;
		_.bindAll(this, 'handleReady', 'handleMsg', 'handleLogin', 'handleLogout', 'handleUserList', 
				'handleSendAck');
		
		$('#'+this.id).addClass('chatbox');
		this.render(); //basic outline
		
		this.socket.on('ready',this.handleReady); //wait for connection to chat server to auth
		this.socket.on('msg',this.handleMsg);
		this.socket.on('login',this.handleLogin);
		this.socket.on('logout',this.handleLogout);
		this.socket.on('user-list',this.handleUserList);
		this.socket.on('send-ack',this.handleSendAck);
		this.socket.on('error',function(data) {
			$('#error_'+t.event.id).html(data.message);
			
			if( data.request == 'join' ) {
				//Failed to join, so make all the chat read-only. Might be that chat is closed.
				console.log("Failed to join chat room, so becoming read-only.");
				t.makeReadOnly();
			}
		});
		
		//These event handlers always apply
		$(document).on('click', '#'+this.id+' a.reply-marker', _.bind(this.clickedReply,this));
		$(document).on('dblclick', '#'+this.id+' div.msg-content.editable',
				_.bind(this.clickedEdit,this));
		
		RestAPI.request('GET',RestAPI.webRoot+'/messages?event_id='+this.event.id, null, {
			'success': function(data, status, xhr) {
				if( data.messages !== undefined ) {
					var map = {};
					for(var i in data.messages) {
						map[data.messages[i].id] = data.messages[i];
					}
					t.setMessages(map);
				}
			},
			'error': window.eventController.showError
		});
	},
	makeReadOnly: function() {
		this.clickedReply = function(){};
		this.clickedEdit = function(){};
		$('#'+this.id+' a.reply-marker').hide();
		$('#'+this.id+' div.msg-content').removeClass('editable');
	},
	/**
	 * Connection to chat server is authenticated and ready.
	 * Note that this can be called again if we lost the connection and had to re-connect.
	 */
	handleReady: function() {
		console.log("chat connection ready");
		if( this.event.chat_closed ) {
			$('#error_'+this.event.id).html("Chat is closed for this event.");
			this.makeReadOnly();
			
			//We can still show this, though. (Normally we wait for our login msg)
			this.sortUserList();
			this.renderUserList();
		} else {
			this.socket.emit('join',{"event_id": this.event.id});
		}
	},
	render: function() {
		//Create DOM elements for the user list and message area, but leaves them empty.
		$('#'+this.id).html(ich.chat_ui_tpl({'id': this.event.id}));
	},
	renderUserList: function() {
		//Just updates the user list, after getting a full list of current logged-in users.
		var data = {
			'users': this.sortedUsers
		};
		$('#userlist_'+this.event.id).html(ich.userlist_tpl(data));
	},
	renderMessages: function() {
		//Re-render all current messages
		
		//Generate parent -> child structure for history
		var children = {};
		for(var id in this.messages) {
			var parent_id = this.messages[id].parent_id;
			if( parent_id === null )
				parent_id = 0;
			if( children[parent_id] === undefined )
				children[parent_id] = [];
			children[parent_id].push(id);
		}
		//console.log(children);
		
		//Recursively create DOM elements
		var children_el = $('#thread_root div.children');
		children_el.html("");
		for(var i in children[0]) {
			var cid = children[0][i];
			this.renderMessageTree(cid, children_el, children, 0);
		}
		
		//Adust width
		var userlistWidth = $('#userlist_'+this.event.id).outerWidth();
		var totalWidth = $('#'+this.id).width();
		var threadbox_el = $('#threadbox_'+this.event.id);
		threadbox_el.css('width', totalWidth - userlistWidth - 6);
	},
	/**
	 * Renders a message and the thread tree below it.
	 * 
	 * For new messages there won't be any children. For updating a message don't pass the children
	 * since only the current message's content changes. So the list is passed only when rendering
	 * the history of messages we loaded.
	 */
	renderMessageTree: function(id, parent_el, children, depth) {
		var msg = this.messages[id];
		
		if( depth === undefined ) {
			//Need to compute
			depth = 0;
			var node = msg;
			while( node !== undefined && node.parent_id !== null ) {
				node = this.messages[node.parent_id];
				depth++;
			}
		}
		
		var data = {
			id: id,
			content: this.escapeMessageContent(msg.content),
			modified_at: this.localdate(msg.modified_at),
			created_at: this.localdate(msg.created_at),
			user: this.users[ msg.user_id ],
			depthClass: depth % 3 //alternate 3 colors
		};
		parent_el.append( ich.thread_tpl(data) );
		$('#msg_'+id).html( ich.message_tpl(data) );
		if( this.event.chat_closed ) {
			$('#thread_'+id+' a.reply-marker').hide();
		} else if( msg.user_id == this.user.id ) {
			$('#msg_'+id+' div.msg-content').addClass('editable');
		}
		
		if( children !== undefined && children[id] !== undefined ) {
			var children_el = $('#thread_'+id+' div.children');
			for(var i in children[id]) {
				var cid = children[id][i];
				this.renderMessageTree(cid, children_el, children, depth+1);
			}
		} else if( msg.content == "" && $('#thread_'+id+' div.children div').length == 0 ) {
			//Has no children (neither from input nor already in DOM) and no content.
			// So let's hide this element. This is the only way to "delete" a message.
			if( children !== undefined ) //from send-ack we don't want this hidden.
				$('#thread_'+id).css('display','none');
		}
	},
	
	//Get list of all users in the Group. Mostly we just want their names.
	setUsers: function(users) {
		this.users = {};
		for(var i in users) {
			this.users[users[i].id] = users[i];
		}
	},
	sortUserList: function() {
		//Prepare the list for rendering. We want it partitioned with logged-in users on the top,
		// those who have commented at least once below that, and then all the others who belong to
		// the group below that.
		var loggedIn = [];
		var commenters = [];
		var rest = [];
		
		for(var id in this.users) {
			user = this.users[id];
			if( user.logged_in ) {
				user.status = 'online';
				loggedIn.push(user);
			} else if( this.commenters.indexOf(id) != -1 ) {
				user.status = 'offline';
				commenters.push(user);
			} else if( this.showAbsent ) {
				user.status = 'absent';
				rest.push(user);
			}
		}
		
		this.sortedUsers = loggedIn.concat(commenters,rest);
	},
	/**
	 * Go through all messages to compile a list of users who have commented in this chat.
	 */
	scanCommenters: function() {
		this.commenters = [];
		for(var i in this.messages) {
			var id = this.messages[i].user_id;
			if( this.commenters.indexOf(id) == -1 )
				this.commenters.push(id);
		}
	},
	setMessages: function(messages) {
		this.messages = messages;
		
		//Update history of who has commented
		this.scanCommenters();
		if( this.sortedUsers !== null ) {
			//Already have sorted and displayed once, so update it.
			this.sortUserList();
			this.renderUserList();
		}
		
		this.renderMessages();
	},
	
	/**
	 * Socket connection broke (and could not reconnect), or failed to connect in the first place.
	 */
	lostConnection: function() {
		alert("Lost connection to chat server");
	},
	
	handleLogin: function(data) {
		console.log("Got login: "+data.user_id);
		var user = this.users[data.user_id];
		if( !user ) {
			//Hmm, we don't know about this user. Could be a newly registered one?
			if( data.event_id != this.event.id )
				return; //ah, wrong event
			//Otherwise let's add the user in.
			this.users[data.user_id] = {
				'id': data.user_id,
				'name': data.name,
				'logged_in': true
			};
		} else {
			user.logged_in = true;
			if( user.id == this.user.id ) {
				//Ack of joining the room. Now we can list logged-in users and send messages.
				this.joined = true;
				this.socket.emit('users',{"event_id": this.event.id});
			}
		}
		this.sortUserList();
		this.renderUserList();
	},
	handleLogout: function(data) {
		console.log("Got logout: "+data.user_id);
		var user = this.users[data.user_id];
		if( !user )
			return; //Never knew this user. Oh well, gone now.
		user.logged_in = false;
		if( user.id == this.user.id )
			this.joined = false; //we got logged out. Can't send more messages.
		this.sortUserList();
		this.renderUserList();
	},
	handleUserList: function(data) {
		for(var id in this.users) {
			var user = this.users[id];
			user.logged_in = data.users.indexOf(Number(id)) != -1;
		}
		
		this.sortUserList();
		this.renderUserList();
	},
	handleMsg: function(msg) {
		var mid = msg.id;
		if( this.messages[mid] !== undefined ) {
			//Exists, so update UI
			this.messages[mid] = msg;
			var data = {
				id: msg.id,
				content: this.escapeMessageContent(msg.content),
				modified_at: this.localdate(msg.modified_at),
				created_at: this.localdate(msg.created_at),
				user: this.users[ msg.user_id ]
			};
			$('#msg_'+msg.id).html( ich.message_tpl(data) );
			
			//If the message was empty before it may have been hidden ("deleted")
			// Or, if the message is now empty let's "delete" it by hiding it.
			var threadElem = $('#thread_'+msg.id);
			if( msg.content == "" ) {
				//Only hide if there are no children, though.
				if( $('#thread_'+msg.id+' div.children div').length == 0 )
					threadElem.css('display','none');
			} else if( threadElem.css('display') == 'none' )
				threadElem.css('display','');
		} else {
			//New
			if( this.commenters.indexOf(msg.user_id) ) {
				//Add to list of commenters, but since this user must be currently logged in there 
				// is no need to re-sort and render the user list.
				this.commenters.push(msg.user_id);
			}
			this.messages[mid] = msg; //has all the right fields to be a Message object.
			
			var parent = msg.parent_id;
			if( parent == null ) parent = 'root';
			var el = $('#thread_'+parent).children('div.children');
			this.renderMessageTree(msg.id, el);
		}
	},
	handleSendAck: function(data) {
		console.log('Got message creation ack');
		console.log(data);
		var msg = {
			id: data.id,
			event_id: data.event_id,
			parent_id: data.parent_id,
			user_id: data.user_id,
			content: data.content,
			created_at: data.created_at,
			modified_at: data.modified_at
		};
		this.messages[msg.id] = msg;
		var parent = msg.parent_id;
		if( parent == null )
			parent = 'root';
		var el = $('#thread_'+parent).children('div.children'); //just the div, not whole tree
		this.renderMessageTree(msg.id, el);
		this.editMsg(msg.id);
	},
		
	localdate: function(str) {
		var d;
		if( typeof(str) == "string" ) {
			//Either came from REST API or Chat Server. UTC in either case, but one is MySQL format
			// and the other is ISO
			if( str.substring(str.length-1) != 'Z' )
				str += ' UTC';
			d = new Date(str);
		} else
			d = new Date(str);
		
		if( d == 'Invalid Date' ) {
			//Suddenly Safari's date processing seems really dumb. My old lunch app used to just
			// use d = new Date(mysql_str_here) but now this won't work
		
			var dt = str.split(" ");
			var comps = dt[0].split("-");
			//console.log(comps[1]+"/"+comps[2]+"/"+comps[0]+" "+dt[1]+" UTC");
			d = new Date(comps[1]+"/"+comps[2]+"/"+comps[0]+" "+dt[1]+" UTC");
			//Now d is in browser-local time.
		}
		
		//return d.toDateString()+' '+d.toLocaleTimeString();
		return (d.getMonth()+1)+'/'+d.getDate()+' '+d.toLocaleTimeString();
	},
	escapeMessageContent: function(content) {
		//HTML collapses newlines.  But Mustache escapes all HTML including <br/>
		//So set the template to not escape, then we have to manually do the escaping.
		var escaped = content;
		
		escaped = escaped.replace(/&/g,'&amp;');
		escaped = escaped.replace(/</g,'&lt;');
		escaped = escaped.replace(/>/g,'&gt;');
		escaped = escaped.replace(/"/g,'&quot;');
		
		//Last step, we add in HTML that we want to keep
		return escaped.replace(/\n/g,"<br/>");
	},
	
	clickedReply: function(e) {
		//console.log(e);
		//console.log(e.target);
		//console.log($(e.target).closest('div.thread'));
		var parent_id = $(e.target).closest('div.thread')[0].id.replace(/[^0-9]/g,'');
		if( parent_id == "" ) //no numbers, which is true for root.
			parent_id = null;
		this.socket.emit('send',{'content':"", 'parent_id': parent_id, 'event_id': this.event.id});
		return false;
	},
	
	clickedEdit: function(e) {
		//console.log('clicked edit');
		var el = $(e.target);
		var id = e.target.parentNode.id.replace(/[^0-9]/g,'');
		//console.log(el);
		//console.log(id);
		this.editMsg(id,el);
	},
	editMsg: function(id, el) {
		if( el === undefined )
			el = $('#msg_'+id+' div.msg-content');
		var msg = this.messages[id];
		//console.log(msg);
		if( msg === undefined ) return; //hmm :-/
		if( msg.user_id != this.user.id ) {
			//console.log("You can only edit your own messages");
			return; //btw, shouldn't be able to click this anyway, w/o the user hacking things.
		}
		if( msg.hideTimer ) {
			//We previously edited this and cleared the contents. Now we want to edit before it gets
			// "deleted" which hides the element.
			clearTimeout(msg.hideTimer);
			msg.hideTimer = null;
		}
		//console.log('editing message '+id);
		el.replaceWith("<textarea id='msg_"+id+"' class='msg-content'></textarea>");
		var ta = $('#msg_'+id).children('textarea');
		ta.val(msg.content);
		ta.select();
		ta.focus();
		
		ta.keyup(_.bind(this.handleKeyup, this, id));
		ta.keydown(_.bind(this.handleKeydown, this, id));
		ta.blur(_.bind(this.handleBlur,this, id));
	},
	handleKeyup: function(id, e) {
		//Send updated message
		var ta = $('#msg_'+id).children('textarea');
		if( ta.val() != this.messages[id].content ) {
			this.messages[id].content = ta.val();
			this.sendMessageUpdate(id);
		}
	},
	handleKeydown: function(id, e) {
		//listen for escape and close the edit area.
		if( e.keyCode == 27 ) {
			this.closeEdit(id);
		}
	},
	handleBlur: function(id, e) {
		var ta = $('#msg_'+id).children('textarea');
		if( ta.val() != this.messages[id].content ) {
			this.messages[id].content = ta.val();
			this.sendMessageUpdate(id);
		}
		this.closeEdit(id, ta);
	},
	closeEdit: function(id, ta) {
		//Close
		var scroll = $(window).scrollTop(); //for some reason we get jumped back to top.
		
		if( ta === undefined )
			ta = $('#msg_'+id).children('textarea');
		
		//ta.replaceWith(el);
		//$('#msg_'+id+' div.msg-content').html( ... );
		var editClass = '';
		if( this.messages[id].user_id == this.user.id )
			editClass = ' editable';
		ta.replaceWith($("<div class='msg-content"+editClass+"'>" + 
				this.escapeMessageContent(this.messages[id].content) + "</div>"));
		$('#msg_'+id+' span.date').html(this.localdate(this.messages[id].modified_at));
		
		setTimeout(function(){ //setting it immediately apparently doesn't work. 
			$(window).scrollTop(scroll);
		},10);
		
		//If we just cleared all the content we may intend to delete this. Give the user a few 
		// seconds to start editing it again, otherwise hide it.
		if( this.messages[id].content == "" ) {
			var t = this;
			var to = setTimeout(function() {
				if( t.messages[id].content != "" ) return; //already added back text
				if( $('#msg_'+id).children('textarea').length != 0 ) return; //currently editing
				//Only hide if there are no children. Someone may have responded already.
				if( $('#thread_'+id+' div.children div').length != 0 ) return;
				
				var threadElem = $('#thread_'+id);
				threadElem.css('display','none');
				t.messages[id].hideTimer = null;
			}, 3000);
			this.messages[id].hideTimer = to;
		}
	},
	sendMessageUpdate: function(id) {
		try {
			var msg = this.messages[id];
			this.socket.emit('send',{
				'event_id': this.event.id,
				'content': msg.content,
				'parent_id': msg.parent_id,
				'message_id': id
			});
		} catch(e) {
			console.log("Error trying to send message");
			console.log(e);
		}
	}
	
	
};