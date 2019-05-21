Unchained
====

A Symfony based CMS inspired by Django; Unchained. 

Goal of the project is maintaining interoperable lists using simple readable configurations.
 
[Read the wiki](https://github.com/vpmv/unchained/wiki)

# What can you do with it?
Do you have hobbies, other than writing code? Keep track of your sports performance, your favorite movies, your best recipes, books and inventory. Whatever you want. 

## But I can create an app that does that and more myself!
Sure you can and so can I, but that's not as quick and easy as keeping track of things with lists. Especially when you know the values of either list can be referenced by the other. No need to get your hands dirty with an ORM, Unchained has this on board. All you need to do is create a YAML file.

# Installation

* Install using `git clone <this-repo>` and running `composer install`. 
* Now copy `.env` to `.env.local` and configure your database.
* Enable the default administrator by copying `/user/config/framework/security.yaml.dist` to `[..]/framework/security.yaml`
* Run `yarn install` / `yarn encore prod` to build the frontend. [Get Yarn](https://yarnpkg.com/lang/en/docs/install/)

It's recommended to install the sources outside of your public mount point (e.g. public_html). When `public/index.php` is nested furhter, please edit this first line (`require_once __DIR__ .'/../config/bootstrap.php'`) by pointing to the installation directory, e.g. `__DIR__.'/../../unchained/config/bootstrap.php`.

[Read the wiki on how to configure and use the app](https://github.com/vpmv/unchained/wiki)

# Features

 * [x]  Admin backend
 * [x]  Link applications with each other, using their values and counting occurrences
 * [x]  Customize data output by referencing values, through user written code
 * [x]  Extend or replace templates
 * [x]  Custom styling using Webpack 
 * [x]  Translation support
 * [x]  Application categorization (needs some tweaking)
 
 ## Roadmap
 * [ ]  Secure login
 * [ ]  Graphs; get a clearer overview of your progression (useful for sports, psychology, etc.)
 * [ ]  Pretty forms
 * [ ]  Optimize code / Decrease code smell
 
 # Keeping track of your configurations
 The user directory is ideal for setting up a nested Git repo. This way you can keep the core updated by pulling this repo whilst maintaining the freedom within your own configurations. 