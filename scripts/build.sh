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
	echo "Final cleanup"	
else
	echo 'Build Failed'
	grunt clean
fi