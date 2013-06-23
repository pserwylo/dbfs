
== TODO ==

* Currently I presume we are using a MySQL database, because I append ';dbname=...' to the DSN for the PDO constructor.
* Validate all of the config values for folders and return null if invalid (after logging a message).
* Authentication using HTTP realm authentication.
* TODO: Page things into folders (e.g. "Row 1-100") if there is too many results.

== Example ==

=== Test Config File ===

dbfs.config contains documentation and examples for each of the config directives.

=== Test Database ===

CREATE DATABASE templates;

CREATE TABLE template (
	id INT AUTO_INCREMENT PRIMARY KEY,
	label CHAR(255) NOT NULL,
	modifiedDate TIMESTAMP,
	css_content TEXT,
	js_content TEXT,
	html_content TEXT
);

INSERT INTO template ( label ) VALUES ( "Home", "About Us", "Contact Us" );