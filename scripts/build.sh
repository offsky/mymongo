#!/bin/bash

BASE_DIR=`dirname $0`

echo ""
echo "Starting Production Build Process"
echo "-------------------------------------------------------------------"

echo "Updating node modules from package.json"
npm install

#check for proper config files
if [ -f $BASE_DIR/../app/config/vars_dist.php ];
then
	echo "vars_dist.php exists [OK]"
else
	echo "FAILED: vars_dist.php DOES NOT EXIST"
	echo ""
	exit 1
fi
if [ -f $BASE_DIR/../app/scripts/config_dist.alt ];
then
	echo "config_dist.alt exists [OK]"
else
	echo "FAILED: config_dist.alt DOES NOT EXIST"
	echo ""
	exit 1
fi
if [ -d $BASE_DIR/../../libraries/php ];
then
	echo "libraries exists [OK]"
else
	echo "FAILED: libraries DOES NOT EXIST"
	echo ""
	exit 1
fi


echo "Setting up html and config"
# build setup
mv $BASE_DIR/../app/index.php $BASE_DIR/../app/index.html  #temporarily rename to html so grunt can find the css/js references
mv $BASE_DIR/../app/scripts/config.js $BASE_DIR/../app/scripts/config_dev.js #move the proper js config file into place so they will be minified and concatenated
mv $BASE_DIR/../app/scripts/config_dist.alt $BASE_DIR/../app/scripts/config.js

#build
grunt build
if [ $? == 0 ]
then
	grunt karma:dist
	if [ $? == 0 ]
	then
		echo "Final cleanup"
		# rename back to php so php will work
		mv $BASE_DIR/../dist/index.html $BASE_DIR/../dist/index.php
	
		# cleanup
		rm -r $BASE_DIR/../dist/api/debug
		rm $BASE_DIR/../dist/index_test.html

		# config files
		rm -r $BASE_DIR/../dist/config/vars_stage.php
		mv $BASE_DIR/../dist/config/vars_dist.php $BASE_DIR/../dist/config/vars.php
		mv $BASE_DIR/../dist/admin/.htaccess_dist $BASE_DIR/../dist/admin/.htaccess

		# copy php libraries
		cp -r $BASE_DIR/../../libraries/php $BASE_DIR/../dist/

		# beautify (deminify) javascript (for beta testing errors)
		uglifyjs $BASE_DIR/../dist/scripts/*.modules.js --beautify --output $BASE_DIR/../dist/scripts/*.modules.js
		uglifyjs $BASE_DIR/../dist/scripts/*.scripts.js --beautify --output $BASE_DIR/../dist/scripts/*.scripts.js

		# Run e2e tests based on arguments
		#   ./scripts/stage.sh -test e2e (all)
		if [ $1 == "-e2e" ]
		then
			grunt karma:e2e

			if [ $? == 0 ]
			then
				echo "e2e tests passed"
			else
				echo "e2e tests failed"
				grunt clean
			fi
		else
			echo "Not running e2e tests"
		fi
	else
		echo 'JS Unit Dist Tests Failed'
		grunt clean
	fi
else
	echo 'Build Failed'
	grunt clean
fi

mv $BASE_DIR/../app/index.html $BASE_DIR/../app/index.php
mv $BASE_DIR/../app/scripts/config.js $BASE_DIR/../app/scripts/config_dist.alt
mv $BASE_DIR/../app/scripts/config_dev.js $BASE_DIR/../app/scripts/config.js
