Simple helpdesk system
======================

This is a PHP application which implements a simple helpdesk system.

Screenshot
----------

![Screenshot](screenshot.png)


Usage
-----

1. Clone the repository.
2. Within the directory, run `composer install`
3. Download and install the famfamfam icon set in /images/icons/
4. Add the Apache directives in httpd.conf (and restart the webserver) as per the example given in .httpd.conf.extract.txt; the example assumes mod_macro but this can be easily removed.
5. Create a copy of the index.html.template file as index.html, and fill in the parameters.
6. Add a forwarding in /etc/aliases, e.g. `helpdesk: | php /path/to/example.com/computing/helpdesk/index.html ingestmail`
7. Create an /images/ directory alongside the boostrap file, and ensure it is writable by the incoming mail user
8. If using IMAP integration, ensure the PHP IMAP extension is loaded, e.g. `apt-get install php-imap && service apache2 restart`
9. Access the page in a browser at a URL which is served by the webserver.


Dependencies
------------

* [FamFamFam Silk Icons set](http://www.famfamfam.com/lab/icons/silk/)


Author
------

Martin Lucas-Smith, Department of Geography, 2003-24.


License
-------

GPL3.

