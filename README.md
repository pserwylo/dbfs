# What is dbfs?

dbfs exposes values from a database (where a value is a particular rows value for a given field) to your filesystem as files.

# Why?

Many website frameworks store templates (HTML/JS/CSS/other?) as values in a database. In order to allow designers to edit them, they typically need to either use dodgey web-based editors, or copy the templates into their favourite editor, and then when finished, copy it back into the databse.

This allows you to edit values from the database directly in your favourite editor, and will save them back into the databse when you save the file.

# TODO

* Wildcard database for folders, so that if their table is in any database, then it can be exposed. Also allow wildcards in table names, such as "*_articles" which would match "news_articles" and "blog_articles".
* Currently I presume we are using a MySQL database, because I append ';dbname=...' to the DSN for the PDO constructor.
* Validate all of the config values for folders and return null if invalid (after logging a message).
* Allow people to configure the authentication mechanism (e.g. Basic/Digest/LDAP)

# Example

## Test Config File

The file dbfs.config contains documentation and examples for each of the config directives.

## Test Database

Below is the SQL to create a test database, that should work well with the example dbfs.config that comes with dbfs.

We will have three fields all exposed from the one table. 

```SQL
CREATE DATABASE dbfs_test;

USE DATABASE dbfs_test;

CREATE TABLE webpage_template (
	id INT AUTO_INCREMENT PRIMARY KEY,
	label CHAR(255) NOT NULL,
	modifiedDate TIMESTAMP,
	css_content TEXT,
	js_content TEXT,
	html_content TEXT
);

INSERT INTO webpage_template ( 
	label, 
	css_content, 
	js_content, 
	html_content 
) VALUES ( 
	"Home",
	"h1 {\n\tcolor: red;\n}",
	"alert( 'Home page loaded' )",
	"<html>\n  <head><title>Home</title>\n  <body>\n  <h1>Home</h1>\n  </body>\n</html>"
), (
	"About",
	"h1 {\n\tcolor: green;\n}",
	"alert( 'About page loaded' )",
	"<html>\n  <head><title>About</title>\n  <body>\n  <h1>About</h1>\n  </body>\n</html>"
), (
	"Contact",
	"h1 {\n\tcolor: blue;\n}",
	"alert( 'Contact page loaded' )",
	"<html>\n  <head><title>Contact</title>\n  <body>\n  <h1>Contact</h1>\n  </body>\n</html>"
);
```

## Test Server

PHP versions 5.4 and up have a built in webserver, that you can switch on by running the commandline php executable with the "-S" parameter.

The run-dev.sh script will start this server on localhost:8000
