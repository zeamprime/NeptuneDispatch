# Neptune Dispatch - To Do


## Wishlist

- ORM generates static subclasses rather than always loading DB schema for every model class used in each request.
    This could work by having model classes extend an imaginary subclass which the autoloader will generate and save in `cache/models`. Remember to make this directory not world-writable. 
    
    It could also work by having the autoloader check `cache/models` first and if we end up loading the `app/models` version then we'll use it now but save out a cached one for the future. This has the advantage of working even if we can't write to the cache. But it mixes user-written and generated code together without clearing the cache whenever the user changes his/her part.
- Cache filler.
    It would be nice to have `cache` read-only in production except by your admin user. When deploying changes, run a script which loads every model and every Rest Controller's help file to fill in all cache contents. It could even use modification dates to do so intelligently, but then it would also have to track dependencies. I'm not sure how to make sure every cacheable file is generated, but we can do the obvious ones like help and ORM models.

