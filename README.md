# cleanurl
Clean-url module for static or php-based websites without CMS.
Rewrites urls, turns
www.example.org/index.php?l=en&page=mysubject
into a speaking url like
www.example.org/index/en/mysubject/title-of-my-subject/
without modifying an existing website.
Transparently modifies all internal links of a processed page into their speaking url alternatives.

## What it does
Rewrites the URLs of your website to clean urls:

    index.php?l=en&page=mysubject  =>   index/en/mysubject/title-of-my-subject/
    my_page.html                   =>   my_page/title-of-my-page/

Original urls are 301-redirected to the normalized ones.
You do not need to modify your existing website.
Automatically adds the &lt;link rel=canonical&gt; tag to produce canonical urls.
Caches the generated urls.
	
	
## What it does not
[tbd]



## Prerequisites

a) All your .html and .php files must be UTF-8 encoded.

b) Add

    <meta http-equiv="content-type" content="text/html; charset=utf-8">

to all your HTML pages.

c) The cleanurl module will search for the following line to generate the verbose uri from the content:

    <meta name="cleanurl" data-details="Text that will be processed to a valid URI"  />
	
If this special meta tag is not found, the module will use the content of the header &lt;title&gt; tags as a fallback.
	
You should set these values BEFORE you install the cleanurl module on a public site - otherwise the generated urls will change
when you update the title.


## Installation

a) Put the sources somewhere reachable from your website root folder

    cleanurl/
     +- class.cleanurl.php
     +- index_cleanurl.php


b) Create a link named "index_cleanurl.php" to "index_cleanurl.php" of the cleanurl package

    ln -s ../cleanurl/index_cleanurl.php index_cleanurl.php


c) Copy htaccess.tmpl from the source tree to .htaccess in your website root folder
  If a .htaccess already exists, merge the content from .htacess.tmpl into the existing .htaccess file.

    cp ../cleanurl/htaccess.tmpl .htaccess


d) Copy cleanurl.conf.php.tmpl from the source tree to cleanurl.conf.php in your website root folder

    cp ../cleanurl/cleanurl.conf.php.tmpl cleanurl.conf.php


e) Edit your copy of cleanurl.conf.php to fit your installation


f) Make sure, the webserver user account has write access to the configured cache file.


g) Finished.



# IMPORTANT NOTES

Cache entries do not have a TTL mechanism.
If once cached associations ever change (e.g. you change the &lt;title&gt; of a document) - truncate the cache file to 0 Bytes length to rebuild the Cache

