# Neptune Dispatch

This is a PHP framework for building web apps. Yes, there are a million. This is mine, built up from a simple front-controller dispatch file and a database utility while over time adding more features.

Features:
- Directory structure Ruby-on-rails with Models, Views, Controllers, Migrations, etc.
- REST API support. It also builds the MD and HTML help files from your API controller classes.
- ORM to quickly get a project going. Dynamically reads the database (or subclass and tell it statically). Supports relationships.
- Migration support to install and manage your database schema.
- PHP Console in the framework's environment.
- Generator script to create new models, controllers, etc.

## Geting Started

Download the repository or set it as a Git Submodule. Place this in `lib/engine` in your project. For example:

    mkdir myproject
    cd myproject
    git init
    git submodule add https://github.com/zeamprime/NeptuneDispatch.git lib/engine`

Then:

`./lib/engine/scripts/setup`

This will create the directory structure and install some example files. It links scripts into `./scripts/` and prints out a few instructions for your next steps.

