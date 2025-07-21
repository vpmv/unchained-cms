Unchained
====

Unchained is a high-level list-keeping CMS for keeping track of your favorite \<insert here\>. With Unchained you can keep a variety of records, maintaining interoperable lists using simple (readable) configurations.
 
[Read the wiki](https://github.com/vpmv/unchained/wiki)

# What can you do with it?
Everyone has hobbies! Keep track of your sports performance, your favorite movies, your best recipes, books and inventory. Whatever you want. Unchained allows you to easily create multiple apps and share data between them, without any programming knowledge. 
No need to get your hands dirty with databases either; Unchained has this on board. All you need to do is create a few YAML files.

# Installation

## Docker
Installation is easiest using [Docker](https://docs.docker.com). The project comes with a Dockerfile and production ready Compose configurations.

## Bare metal

### Requirements

* PHP 8.4
* MySQL / MariaDB
* NodeJS ([NodeSource](https://github.com/nodesource/distributions/blob/master/DEV_README.md) _or_ [NVM](https://github.com/nvm-sh/nvm?tab=readme-ov-file#installing-and-updating)) + [Yarn](https://yarnpkg.com/lang/en/docs/install/)
* [Composer](https://getcomposer.org/download)

### Installation

* Clone this repository:<br> 
```
git clone <this-repo>
```
* Install vendor packages:
```
php composer.phar install
```
* Copy `.env` to `.env.local` and configure your environment & database connection.
* Enable the default administrator by copying `/user/config/framework/security.yaml.dist` to `[..]/framework/security.yaml`
* Install and build front-end:
```
yarn install
yarn encore prod
```
It's recommended to install the sources outside your public mount point (e.g. public_html). When `public/index.php` is nested further, please edit this first line (`require_once __DIR__ .'/../config/bootstrap.php'`) by pointing to the installation directory, e.g. `__DIR__.'/../../unchained/config/bootstrap.php`.

[Read the wiki on how to configure and use the app](https://github.com/vpmv/unchained/wiki/applications)

# User configurations
The user directory is the starting point of your applications. Unchained will look here first for configurations, templates and extensions.

The Docker container relies on the existence of some files within the default `/user` directory:
* `/user/system/config.yaml` for global configuration
* `/user/system/applications.yaml` for application registration
* `/user/config/framework/security.yaml` for the user login
* `/user/assets/user.js` as the front-end build entrypoint

> ¡ It is imperative you don't remove `.dist` files for the container to build !

The user directory is ideal for setting up a nested Git repo. This way you can keep the core updated by pulling this repo whilst maintaining the freedom within your own configurations.


# Features

 * [x]  Admin backend
 * [x]  Link applications with each other, using their values and counting occurrences
 * [x]  Customize data output by referencing values, through user written code
 * [x]  Extend or replace templates
 * [x]  Custom styling using Webpack 
 * [x]  Translation support
 * [x]  Application categorization (needs some tweaking)
 
 ## Roadmap

 * [ ]  Installation wizard
 * [ ]  Multi-user support 
 * [ ]  Database login
 * [ ]  Graphs; get a clearer overview of your progression (useful for sports, psychology, etc.)
 * [ ]  Pretty forms
 * [ ]  Optimize code / Decrease code smell
 
 