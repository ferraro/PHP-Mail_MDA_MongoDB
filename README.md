PHP-Mail_MDA_MongoDB
====================

Mail delivery agent, which stores incoming emails from Postfix into MongoDB using PHP.

REQUIREMENTS
============
- php extension mailparse (can be installed by "pecl install mailparse")
- postfix 2.9.x, good for implementation would be the MongoDB version 2.9.4 at
 https://github.com/ferraro/postfix-mongodb, because it supports MongoDB for virtual addresses lookup.

FILES
=====
- mda_config.php Configuration file
- mda.php Mail Delivery Agent called by Postfix
- main.cf Postfix example main configuration file
- master.cf Postfix example master configuration file
- test/* E-Mail MBOX test files, e.g. can be run with "./mda.php < test/attachments.mbox"

INSTALLATION
============

After extracting the GIT repository, run the following commands:

    curl -s https://getcomposer.org/installer | php
    php composer.phar update

Edit /etc/postfix/main.cf file and add the following entries:

	# my10minutemail.com mongodb alias test
	myorigin = my10minutemail.com
	virtual_mailbox_domains = my10minutemail.com
	virtual_mailbox_maps = mongodb:/etc/postfix/mongodb-aliases.cf
	virtual_mailbox_base = /var/mail
	virtual_minimum_uid = 65534
	virtual_uid_maps = static:65534
	virtual_gid_maps = static:65534
	virtual_mailbox_limit = 0
	# *** Use the mongodb  agent for the transport, to store messages inside MongoDB ***
	virtual_transport = mongodb:
	# MongoDB is limited to 4 MB by file attachment, so limit messages completly to 4 MB
	message_size_limit = 4000000

Edit /etc/postfix/master.cf file and add the following entries:

	#
	# MongoDB mail delivery agent
	#
	mongodb unix  -       n       n       -       20      pipe
	  flags=FDRhu user=nobody argv=/var/www/my10minutemail/mda/src/mda.php

Note: In this example the mda.php script has been installed at /var/www/my10minutemail, please update this path where
  your file is located.
Additionally please check if you need "virtual_mailbox_maps = mongodb:/etc/postfix/mongodb-aliases.cf", this is only used
if you use virutal address mapping with mongodb. In this case you need the Postfix version of MongoDB of
https://github.com/ferraro/postfix-mongodb which has an example in its README.md file how looks the /etc/postfix/mongodb-aliases.cf file.