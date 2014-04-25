angular.module('templates-main', []);

/*

This will be a cache of all templates used in the project.
    * Optimizes number of requets to get app up and running
    * Prevents old templates from being cached

During development this file is empty so all the actual templates will be used.
Be sure to clear your cache after editing a template. 

Since user's can't be expected to clear their caches, you do this:

Install html2js grunt plugin: https://www.npmjs.org/package/grunt-html2js

Add to your gruntfile: 
    html2js: {
      options: {
        base: "app"
      },
      main: {
        src: ['app/views/*.html'],
        dest: 'dist/js/templates.js'
      },
    }

Inject "templates-main" into your app
    angular.module('MyApp', ['templates-main' ...])

Because this file is empty, nothing changes for development.
When you build, this file will be filled with the templates and then get minified and concatenated
into the main js file.

*/