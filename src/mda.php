#!/usr/bin/php
<?php
/*
 * PHP-Mail_MDA_MongoDB
 *
 * Postfix compatible MDA for saving messages inside a MongoDB.
 *
 * This is mostly procedural code, as it requires to be executed very fast by postfix.
 * MongoDB accepts only UTF-8 strings, so each incoming email needs to be base64 encoded.
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

### MAIN ###

use MimeMailParser\Parser;

try {
	# Init mail parse library
	require_once __DIR__.'/../vendor/autoload.php';

	// Connect to test mongo database
	$m = new MongoClient(MDAMongoDbConfig::$MONGO_URL);
	$db = $m->selectDB(MDAMongoDbConfig::$MONGO_DB_NAME);
	if (MDAMongoDbConfig::$MONGO_USE_AUTH) {
		$db->authenticate(MDAMongoDbConfig::$MONGO_USER, MDAMongoDbConfig::$MONGO_PASSWORD);
	}

	// Init parser
	$parser = new Parser();

	# Parse standard input
	$parser->setText(file_get_contents('php://stdin'));

	$deliveredTo	= $parser->getHeader('delivered-to');
	$to				= $parser->getHeader('to');
	$from			= $parser->getHeader('from');
	$date			= $parser->getHeader('date');
	$contentType	= $parser->getHeader('content-type');
	// Use binary data for subject and body message, because it could be a non UTF-8 string.
	$subject		= new MongoBinData($parser->getHeader('subject'));
	$text			= new MongoBinData($parser->getMessageBody('text'));
	$html			= new MongoBinData($parser->getMessageBody('html'));

	// Get file attachments
	$fileList		= array();
	foreach(@$parser->getAttachments() as $attachment) {
		$list							= array();
		$list['filename']				= $attachment->filename;
		$list['content_type']			= $attachment->getContentType();
		$list['content_disposition']	= $attachment->getContentDisposition();

		$data							= '';
		while($bytes = $attachment->read()) {
			$data .= $bytes;
		}
		// Note: MongoDB supports only at max. 4 MB of binary data
		$list['data']	= new MongoBinData($data);

		// Add file to file list
		$fileList[] = $list;
	}

	# Save message into MongoDB
	$msgList = array(
		'delivered_to'	=> $deliveredTo,
		'to'			=> $to,
		'from'			=> $from,
		'date'			=> $date,
		'content_type'	=> $contentType,
		'subject'		=> $subject,
		'body_text'		=> $text,
		'body_html'		=> $html,
		'attachments'	=> $fileList
	);

	// Select the collection
	$collectionObj = $db->selectCollection(MDAMongoDbConfig::$MONGO_COLLECTION);

	// MongoDB accepts only UTF-8 strings stored inside, so convert non UTF-8 strings to UTF-8
	// make_utf8_array($msgList);

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
