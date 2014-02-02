module.exports = function(config){
  config.set({
    basePath : '../../',

    frameworks: ['ng-scenario'],

    files : [
        'test/e2e/**/*.js'
    ],

    autoWatch : false,

    // browsers = ['PhantomJS'];
    // browsers = ['Chrome'];
    // browsers = ['Firefox'];
    // browsers = ['Safari'];
    browsers : ['Chrome'],

    singleRun : true,

    proxies : {
      '/': 'http://local.toodledo.com/~jake/angularSeed/'
    },

    urlRoot: '/__e2e/',

    plugins : [
        'karma-chrome-launcher',
        'karma-firefox-launcher',
        'karma-jasmine',
        'karma-ng-scenario'    
        ]
  });
}
