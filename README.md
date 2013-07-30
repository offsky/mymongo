MyMongo
=======

A PHP wrapper class for MongoDB that makes it a lot easier to work with.  A considerable amount of effort has been put into making it handle replica set failures as efficiently as possible.

This is not a general purpose library.  It was made for our needs and ignores some features of Mongo that we didn't need. We only tested it with 1 or 2 hosts. If you have 3 or more servers in your replica set, or if you are sharding, then this may not work for you, although we would love for you to contribute code back to our library to make it handle these cases.


Requirements
=======
PHP Mongo drivers version 1.3.x or higher.


References
=======
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