# nICStermin

is a WordPress plugin to subscribe and display ICS calendars.

Calendars are regularly updated and all events of a month can be displayed on a WordPress page or in an article with one of (so far) two shortcodes.

With the help of user-defined filters it is possible to change properties of an event and add HTML markup, for example.

## Usage

After adding a calendar subscription use one of the two available calendar view using a **shortcode**:

### List view

```
[nicstermin view="list"]
```

This shortcodes inserts a list-view of all events of the current month.
The shortcode as an optional **parameter** ```range``` which can either be a single decimal value or a comma separated list of decimal values.

```[nicstermin view="list" range="2"]``` will insert the list of events for two months, starting with the current month.

```[nicstermin view="list" range="-2, 0, 3, 4"]``` shows four months in total, the month before the last month (-2), current month (0), third and fourth month from current one.

### Month view

```
[nicstermin view="month"]
```

This shortcode inserts a typical 2D representation (weeks as rows, weekdays as columns) of the current month. So far this is mostly for demonstration purposes and far from finished.

## Development

### Setup

To build the plugin yourself you need

- [Composer](https://getcomposer.org)
- [Node](https://www.npmjs.com)
- [WP-CLI](https://make.wordpress.org/cli/handbook/)
- make
- git
- propably some more 'standard' software

Composer installs external PHP dependencies like [Twig](https://twig.symfony.com) and [PHP ICS Parser](https://github.com/u01jmg3/ics-parser).
Node installs [tailwindcss](https://tailwindcss.com), needed for styling the HTML calendar views.
Stylesheets are updated during development via

```bash
npx tailwindcss -i ./public/css/input.css -o ./public/css/styles.css --watch
```

### Translations

A ```pot``` file for translations is prepared via

```
make i18n
```

and is found at ```languages/``` afterwards.

### Building

```
make build
```
creates a new folder ```dist/```, compiles everything and copies all files needed for the WordPress plugin into the folder. This folder can than be packed and distributed as the plugin.