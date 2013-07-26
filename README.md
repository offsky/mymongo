MyMongo
=======

A PHP wrapper class for MongoDB that makes it a lot easier to work with.  A considerable amount of effort has been put into making it handle replica set failures as efficiently as possible.

Requirements
=======
PHP Mongo drivers version 1.3.x or higher.

http://php.net/manual/en/book.mongo.php

https://jira.mongodb.org/browse/PHP

Usage
=======

Somewhere set this environment variable

```
$MONGO['myServer'] = array("host"=>"my1.host.com:10009,my2.host.com:10009", "user"=>"myUsername", "pass"=>"myPassword", "db"=>"databaseName", "replicaSet"=>"replicaSetName");
```

When you want to do something

```
$m = new mymongo("myServer","collectionName");
$obj = array( "date" => date('r'));
$m->insert($obj);
```