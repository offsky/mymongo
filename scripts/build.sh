#!/bin/bash

BASE_DIR=`dirname $0`

echo ""
echo "Starting Production Build Process"
echo "-------------------------------------------------------------------"

echo "Updating node modules from package.json"
npm install


#build
grunt build
if [ $? == 0 ]
then
	echo "Moving things into place"

	# config files
	rm $BASE_DIR/../dist/php/config.php
	mv $BASE_DIR/../dist/php/config_template.php $BASE_DIR/../dist/php/config.php
	
	# copy things from bower_components so paths will be correct. TODO: this should be in grunt
	cp -r $BASE_DIR/../app/bower_components/jsoneditor/img $BASE_DIR/../dist/stylesheets/

else
	echo 'Build Failed'
	grunt clean
fi