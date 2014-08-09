## Overview

Describe your project here.  I left some example stuff.

### Security

Every request requires an app ID and API key.  These are concatenated with a colon 
separator and passed in the `X-API-KEY` request header if HMAC is not used (so it works 
like a password) but with HMAC it should be only the ID to avoid sharing the secret.

If enabled in the server configuration, each request must also have an HMAC signature. The
signature is the sha1 hash of the following items concatenated in order: url, query 
string, time (YYYY-MM-DD HH:II:SS, like MySQL) which cannot be more than a minute old, app
ID, user ID if present, and message body.  If including a user ID, the hashing key is the 
concatenation of the app key and the user's token. The HMAC signature is passed in 
the `X-HASH` request header. The time stamp is passed in the `X-HASH-TIME` header.

It is recommended that the server be configured to only operate over HTTPS.

All routes except the ones for login require a user. User ID and token are concatenated 
with a colon separator and passed in the `X-USER-KEY` request header. Optionally this may
be passed in a `userKey` cookie instead.  If using HMAC then instead of the token send the
UserToken ID since the token is used as part of the signature key.

A user's privileges restrict which web services can be called and what data is returned.


### Summary of Models

A `User` is ...


## Testing

You can use the web client at [`.../apitest.html`](/apitest.html) if you have an App key. Check out the
Javascript library at [`.../js/api.js`](/js/api.js) as well. It depends on 
[jQuery](http://www.jquery.com),
[jQuery.storageapi](https://github.com/julien-maurel/jQuery-Storage-API),
[underscore.js](http://underscorejs.org),
[date.js](http://www.datejs.com/), and
[jsSHA](http://caligatio.github.io/jsSHA/).


## Known Issues

None
