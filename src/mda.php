#!/usr/bin/php
<?php
/*
 * PHP-Mail_MDA_MongoDB
 *
 * Postfix compatible MDA for saving messages inside a MongoDB.
 *
 * This is procedural code, as it requires to be executed very fast by postfix.
 *
 * License: GPL 3
 *
 * (C) Stephan Ferraro <stephan@ferraro.net>, 2012 Ferraro Ltd., Germany - Stuttgart
 */

# CONFIGURATION
require_once(__DIR__.'/mda_config.php');

### FUNCTIONS ###
// My die function
function my_die($arg) {
	fprintf(STDERR, "Could not deliver message! ".MDAMongoDbConfig::$MONGO_CONTACT." Error reason: $arg\n");
	exit(1);
}

// Convert all items of the array to UTF-8
// TODO: maybe we should not do it in this way, and only check messages text and html and scan for charset which is defined
// then use iconv to convert. If charset is undefined, then convert from ASCII to UTF-8
function make_utf8_array(&$list) {
	foreach ($list as $key => &$value) {
		if (is_string($value) && mb_check_encoding($value, 'UTF-8') === false) {
			// Convert value to UTF-8
			$value = mb_convert_encoding($value, 'UTF-8', 'auto');
		} else {
			if (is_array($value)) {
				// Call recursively sub-arrays
				make_utf8_array($value);
			}
		}
	}
}

### MAIN ###

use MimeMailParser\Parser;

try {
	# Init mail parse library
	require_once __DIR__.'/../vendor/autoload.php';

	// Connect to test mongo database
	$m = new Mongo(MDAMongoDbConfig::$MONGO_URL);
	$db = $m->selectDB(MDAMongoDbConfig::$MONGO_DB_NAME);
	if (MDAMongoDbConfig::$MONGO_USE_AUTH) {
		$db->authenticate(MDAMongoDbConfig::$MONGO_USER, MDAMongoDbConfig::$MONGO_PASSWORD);
	}

	// Init parser
	$parser = new Parser();

	# Parse standard input
	$parser->setText(file_get_contents('php://stdin'));

	$to				= $parser->getHeader('to');
	$delivered_to	= $parser->getHeader('delivered-to');
	$from			= $parser->getHeader('from');
	$subject		= $parser->getHeader('subject');
	$text			= $parser->getMessageBody('text');
	$html			= $parser->getMessageBody('html');
	$attachments	= @$parser->getAttachments();

	# Save message into MongoDB
	$msgList = array(
		'to'			=> $to,
		'delivered-to'	=> $delivered_to,
		'from'			=> $from,
		'subject'		=> $subject,
		'body'			=> array(
			'text'		=> $text,
			'html'		=> $html
		),
		'attachments'	=> serialize($attachments)
	);

	// Get the collection
	$collectionObj = $db->selectCollection(MDAMongoDbConfig::$MONGO_COLLECTION);

	// MongoDB accepts only UTF-8 strings stored inside, so convert non UTF-8 strings to UTF-8
	make_utf8_array($msgList);

	// Insert this new message into the collection
	if ($collectionObj->save($msgList) == false) {
		my_die('save on collection failed');
	}
	// Check if there was an error
	$lastError = $db->lastError();
	if (!empty($lastError['err'])) {
		my_die($lastError['err']);
	}

	// End script successfully
	exit(0);
} catch (Exception $e) {
	my_die($e->getMessage());
}
exit(1);
