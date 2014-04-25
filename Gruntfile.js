// Generated on 2013-08-28 using generator-angular 0.4.0
'use strict';
var LIVERELOAD_PORT = 35729;
var lrSnippet = require('connect-livereload')({ port: LIVERELOAD_PORT });
var mountFolder = function (connect, dir) {
  return connect.static(require('path').resolve(dir));
};

// # Globbing
// for performance reasons we're only matching one level down:
// 'test/spec/{,*/}*.js'
// use this if you want to recursively match all subfolders:
// 'test/spec/**/*.js'

module.exports = function (grunt) {
  require('load-grunt-tasks')(grunt);
  require('time-grunt')(grunt);

  grunt.initConfig({
    watch: {
      // not used
      // coffee: {
      // },
      // coffeeTest: {
      // },
      styles: {
        files: ['app/stylesheets/{,*/}*.css'],
        tasks: ['copy:styles']
      },
      livereload: {
        options: {
          livereload: LIVERELOAD_PORT
        },
        files: [
          'app/{,*/}*.html',
          '.tmp/stylesheets/{,*/}*.css',
          '{.tmp,app}/javascripts/{,*/}*.js',
          'app/images/{,*/}*.{png,jpg,jpeg,gif,webp,svg}'
        ]
      }
    },

    connect: {
      options: {
        port: 9000,
        // Change this to '0.0.0.0' to access the server from outside.
        hostname: 'localhost'
      },
      livereload: {
        options: {
          middleware: function (connect) {
            return [
              lrSnippet,
              mountFolder(connect, '.tmp'),
              mountFolder(connect, 'app')
            ];
          }
        }
      },
      test: {
        options: {
          middleware: function (connect) {
            return [
              mountFolder(connect, '.tmp'),
              mountFolder(connect, 'test')
            ];
          }
        }
      },
    },
    open: {
      server: {
        url: 'http://localhost:<%= connect.options.port %>'
      }
    },
    clean: {
      dist: {
        files: [{
          dot: true,
          src: [
            '.tmp',
            'dist/*',
            '!dist/.git*'
          ]
        }]
      },
      server: '.tmp'
    },
    jshint: {
      options: {
        jshintrc: '.jshintrc'
      },
      all: [
        'Gruntfile.js',
        'app/javascripts/{,*/}*.js'
      ]
    },
    // not used
    /*coffee: {
      dist: {}
    },*/
    // not used since Uglify task does concat,
    // but still available if needed
    /*concat: {
      dist: {}
    },*/
    rev: {
      dist: {
        files: {
          src: [
            'dist/javascripts/{,*/}*.js',
            'dist/stylesheets/{,*/}*.css',
            'dist/images/{,*/}*.{png,jpg,jpeg,gif,webp,svg}',
            'dist/stylesheets/fonts/*'
          ]
        }
      }
    },
    useminPrepare: {
      html: 'app/index.html',
      options: {
        dest: 'dist'
      }
    },
    usemin: {
      html: ['dist/{,*/}*.html'],
      css: ['dist/stylesheets/{,*/}*.css'],
      options: {
        dirs: ['dist']
      }
    },
    imagemin: {
      dist: {
        files: [{
          expand: true,
          cwd: 'app/images',
          src: '{,*/}*.{png,jpg,jpeg}',
          dest: 'dist/images'
        }]
      }
    },
    svgmin: {
      dist: {
        files: [{
          expand: true,
          cwd: 'app/images',
          src: '{,*/}*.svg',
          dest: 'dist/images'
        }]
      }
    },
    
    // By default, your `index.html` <!-- Usemin Block --> will take care of minification.
    // cssmin: {
    //   
    // },

    //https://www.npmjs.org/package/grunt-html2js
    html2js: {
      options: {
        base: "dist"
      },
      main: {
        src: ['dist/views/*.html'],
        dest: 'dist/javascripts/templates.js'
      },
    },

    htmlmin: {
      dist: {
        // See yeoman generated angular project
        options: {
        },
        files: [{
          expand: true,
          cwd: 'app',
          src: ['views/*.html'],
          dest: 'dist'
        }]
      }
    },
    // Put files not handled in other tasks here
    copy: {
      dist: {
        files: [{
          expand: true,
          dot: true,
          cwd: 'app',
          dest: 'dist',
          src: [
            '*.{ico,txt}',
            '.htaccess',
            'w3c/*',
            'fonts/*',
            'php/*',
            'images/**/*',
            'apple-touch*',
            'stylesheets/fonts/*',
            'labels.rdf',
            'robots.txt',
            '404.html',
            '504.html',
            'index.html',
            'ajax/**/*',
            'template/**/*',
            'views/**/*'
          ]
        }, {
          expand: true,
          cwd: '.tmp/images',
          dest: 'dist/images',
          src: [
            'generated/*'
          ]
        }]
      },
      styles: {
        expand: true,
        cwd: 'app/stylesheets',
        dest: '.tmp/stylesheets/',
        src: '{,*/}*.css'
      }
    },
    karma: {
      unit: {
        configFile: 'test/config/karma.conf.js',
        singleRun: true,
        reporters: ['progress'],
        browsers: ['PhantomJS']
      },
      dist: {
        configFile: 'test/config/karma-build-dist.conf.js',
        singleRun: true
      },
      e2e: {
        configFile: 'test/config/karma-e2e.conf.js',
        singleRun: true
      // },
      // perf: {
      //   configFile: 'test/config/karma-e2e-perf.conf.js',
      //   singleRun: true
      }
    },
    uglify: {
      dist: {
        files: {
          'dist/javascripts/scripts.js': [
            'dist/javascripts/scripts.js'
          ]
        }
      }
    }
  });

  grunt.registerTask('server', function (target) {
    if (target === 'dist') {
      return grunt.task.run(['build', 'open', 'connect:dist:keepalive']);
    }

    grunt.task.run([
      'clean:server',
      'connect:livereload',
      'open',
      'watch'
    ]);
  });

  grunt.registerTask('test', [
    'clean:server',
    'connect:test',
    'karma:unit'
  ]);

  grunt.registerTask('build', [
    'clean:dist',       //cleans out the dist folder
    //'test',           //runs unit tests on app folder using phantom (gut check)
    'useminPrepare',    //looks at index file to find css/js blocks for minification
    'concat',           //concatenates js/css files into one. useminPrepare defines this by scanning index.php
    'copy:dist',        //copies necessary extra files from app to dist
    'html2js',          //compiles templates into js
    'cssmin',           //minifies the styles
    // 'uglify',        //obfuscates and shirinks the js file
    'rev',              //adds unique hash to scripts.js and styles.css to avoid browser caching
    'usemin',           //updates index with minified files and css with revisioned images
    'clean:server'      //cleans .tmp
  ]);

  grunt.registerTask('default', ['build']);
};
