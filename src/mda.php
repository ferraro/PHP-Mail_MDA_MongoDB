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

// My die function
function my_die($arg) {
	fprintf(STDERR, "Could not deliver message! ".MDAMongoDbConfig::$MONGO_CONTACT." Error reason: $arg\n");
	exit(1);
}

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
	//$attachments = $parser->getAttachments();

	# Save message into MongoDB
	$msg = array(
		'to'			=> $to,
		'delivered-to'	=> $delivered_to,
		'from'			=> $from,
		'subject'		=> $subject,
		'body'			=> array(
			'text'		=> $text,
			'html'		=> $html
		)
	);

	// Get the collection
	$collectionObj = $db->selectCollection(MDAMongoDbConfig::$MONGO_COLLECTION);

	// Insert this new message into the collection
	if ($collectionObj->save($msg) == false) {
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
	fprintf(STDERR, "Error: Mail could not be delivered. ".$e->getMessage()."\n");
}
exit(1);
