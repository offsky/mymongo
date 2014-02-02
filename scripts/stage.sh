#!/bin/bash

BASE_DIR=`dirname $0`

echo ""
echo "Starting Staging Build Process"
echo "-------------------------------------------------------------------"

echo "Updating node modules"
npm install

echo "Setting up html and config"
# build setup
# mv $BASE_DIR/../app/index.php $BASE_DIR/../app/index.html  #temporarily rename to html so yeoman can find the css/js references
# mv $BASE_DIR/../app/scripts/config.js $BASE_DIR/../app/scripts/config_dev.js #move the proper js config file into place so they will be minified and concatenated
# mv $BASE_DIR/../app/scripts/config_stage.alt $BASE_DIR/../app/scripts/config.js

#build
grunt build
if [ $? == 0 ]
then
	grunt karma:dist
	if [ $? == 0 ]
	then
		echo "Final cleanup"
		# rename back to php so php will work
		# mv $BASE_DIR/../dist/index.html $BASE_DIR/../dist/index.php
		
		# cleanup
		# rm -r $BASE_DIR/../dist/api/debug
		# Leave index_test to run e2e tests
		## rm dist/index_test.html

		# config files
		# rm -r $BASE_DIR/../dist/config/vars_dist.php
		# mv $BASE_DIR/../dist/config/vars_stage.php $BASE_DIR/../dist/config/vars.php

		# beautify (deminify) javascript (for beta testing errors)
		uglifyjs $BASE_DIR/../dist/javascripts/*.scripts.js --beautify --output $BASE_DIR/../dist/javascripts/*.scripts.js

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
		echo "JS Unit Dist Tests Failed"
		grunt clean
	fi
else
	echo "Build Failed"
	grunt clean
fi

# mv $BASE_DIR/../app/index.html $BASE_DIR/../app/index.php
# mv $BASE_DIR/../app/scripts/config.js $BASE_DIR/../app/scripts/config_stage.alt
# mv $BASE_DIR/../app/scripts/config_dev.js $BASE_DIR/../app/scripts/config.js

