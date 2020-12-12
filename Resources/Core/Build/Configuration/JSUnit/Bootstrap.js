'use strict';

var tests = [];
var paths = {
	'jquery-ui': '/base/typo3/sysext/core/Resources/Public/JavaScript/Contrib/jquery-ui',
	'datatables': '/base/typo3/sysext/core/Resources/Public/JavaScript/Contrib/jquery.dataTables',
	'matchheight': '/base/typo3/sysext/core/Resources/Public/JavaScript/Contrib/jquery.matchHeight-min',
	'nprogress': '/base/typo3/sysext/core/Resources/Public/JavaScript/Contrib/nprogress',
	'moment': '/base/typo3/sysext/core/Resources/Public/JavaScript/Contrib/moment',
	'cropper': '/base/typo3/sysext/core/Resources/Public/JavaScript/Contrib/cropper.min',
	'imagesloaded': '/base/typo3/sysext/core/Resources/Public/JavaScript/Contrib/imagesloaded.pkgd.min',
	'bootstrap': '/base/typo3/sysext/core/Resources/Public/JavaScript/Contrib/bootstrap/bootstrap',
	'twbs/bootstrap-datetimepicker': '/base/typo3/sysext/core/Resources/Public/JavaScript/Contrib/bootstrap-datetimepicker',
	'autosize': '/base/typo3/sysext/core/Resources/Public/JavaScript/Contrib/autosize',
	'taboverride': '/base/typo3/sysext/core/Resources/Public/JavaScript/Contrib/taboverride.min',
	'twbs/bootstrap-slider': '/base/typo3/sysext/core/Resources/Public/JavaScript/Contrib/bootstrap-slider.min',
	'jquery/autocomplete': '/base/typo3/sysext/core/Resources/Public/JavaScript/Contrib/jquery.autocomplete'
};

var packages = [
	{
		name: 'lit-html',
		location: '/base/typo3/sysext/core/Resources/Public/JavaScript/Contrib/lit-html',
		main: 'lit-html'
	},
	{
		name: 'lit-element',
		location: '/base/typo3/sysext/core/Resources/Public/JavaScript/Contrib/lit-element',
		main: 'lit-element'
	},
];

/**
 * Collect test files and define namespace mapping for RequireJS config
 */
for (var file in window.__karma__.files) {
	if (window.__karma__.files.hasOwnProperty(file)) {
		// Add dynamic path mapping for requirejs config
		if (/Resources\/Public\/JavaScript\//.test(file)) {
			var parts = file.split('/');
			var extkey = parts[4];
			var extname = extkey.replace(/_([a-z])/g, function(g) {
				return g[1].toUpperCase();
			});
			extname = extname.charAt(0).toUpperCase() + extname.slice(1);
			var namespace = 'TYPO3/CMS/' + extname;
			if (typeof paths[namespace] === 'undefined') {
				paths[namespace] = '/base/typo3/sysext/' + extkey + '/Resources/Public/JavaScript';
			}
		}
		// Find all test files
		var testFilePattern = /Tests\/JavaScript\/(.*)Test\.js$/gi;
		if (testFilePattern.test(file)) {
			tests.push(file);
		}
	}
}

/**
 * Define environment
 * Set global objects and variables
 * @TODO: hopefully we can cleanup the following lines in future
 */
if (typeof TYPO3 === 'undefined') {
	var TYPO3 = TYPO3 || {};
	TYPO3.jQuery = jQuery.noConflict(true);
	TYPO3.settings = {
		'FormEngine': {
			'formName': 'Test'
		},
		'DateTimePicker': {
			'DateFormat': 'd.m.Y'
		},
		'ajaxUrls': {
		}
	};
	TYPO3.lang = {};
}

top.TYPO3 = TYPO3;
var TBE_EDITOR = {};

/**
 * RequireJS setup
 */
requirejs.config({
	// Karma serves files from '/base'
	baseUrl: '/base',

	paths: paths,

	packages: packages,

	shim: {},

	// ask Require.js to load these files (all our tests)
	deps: tests,

	// start test run, once Require.js is done
	callback: window.__karma__.start
});
