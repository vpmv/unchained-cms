Unchained
====

Unchained is a high-level list-keeping CMS for keeping track of your favorite \<insert here\>. With Unchained you can keep a variety of records, maintaining interoperable lists (cross-references) using simple (readable) configurations.
 
[Read the wiki](https://github.com/vpmv/unchained/wiki)

# About
Everyone has hobbies! Keep track of your sports performance, your favorite movies, your best recipes, books and inventory ~ whatever! Unchained allows you to easily create multiple apps, with cross-references, without any programming knowledge. 
No need to get your hands dirty with databases either; Unchained has this on board. All you need to do is create a few YAML files.

# Installation

Installation is easiest using [Docker](https://docs.docker.com). The project comes with a Dockerfile and production ready Compose configurations.

Please read the [Installation Guide](https://github.com/vpmv/unchained/wiki/installation)


# User configuration
The user directory is the starting point of your applications. Unchained will look here first for configurations, templates and extensions.

The user directory is ideal for setting up a nested Git repo. This way you can keep the core updated by pulling this repo whilst maintaining the freedom within your own configurations.

Read the wiki on how to [Configure and use your first app](https://github.com/vpmv/unchained/wiki/applications)


## Customization

Unchained is easily extended with Webpack Encore. The entrypoint is preconfigured at `/user/assets/user.js`. <br> Feel free to add your own scripts and styling.

Unchained comes with the following Icon packs:
* FontAwesome 5
* Bootstrap Icons 1.13 

Templates are easily overridden as well. See [the sources](tree/master/templates) for templates you wish to override and put them in `/user/templates`.

More information is found on the wiki.

# Features
 * [x]  Highly customizable front-end
     * **New in v1.2**: Two built-in styles (textual/blocky)
     * **New in v1.2**: Themes (dark/light/auto)
 * [x]  Public / private views with admin backend
     * Configure which apps / fields are publicly visible
 * [x]  Link applications with each other, cross-referencing values<br>
     * Count foreign records
     * **New in v1.2**: find min/max dates & show averages
 * [x]  Customize data output by referencing values, through user written code
     * **New in v1.2**: Helper functions and example scripts to help you get started 
 * [x]  Extend or replace templates
 * [x]  Native customizable/extendable styling using Webpack standards 
 * [x]  Translation support
 * [x]  Application categorization
     * Easily define categories with automatic routing 
 
 ## Roadmap

 * [ ]  Installation wizard
 * [ ]  Graphs; get a clearer overview of your progression (useful for sports, psychology, etc.)
 * [ ]  Prettier forms
