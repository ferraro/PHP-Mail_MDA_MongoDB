#!/usr/bin/php
<?php
/*
 * PHP-Mail_MDA_MongoDB
 *
 * Postfix compatible MDA for saving messages inside a MongoDB.
 *
 * This is procedural code, as it requires to be executed very fast by postfix.
 *
 * (C) Stephan Ferraro <stephan@ferraro.net>, 2012 Ferraro Ltd., Germany - Stuttgart
 */

# CONFIGURATION
$mongoUrl			= getenv('MONGO_URL') ? getenv('MONGO_URL') : 'mongodb://localhost';
$mongoDbName		= getenv('MONGO_DB_NAME') ? getenv('MONGO_DB_NAME') : 'test';
$mongoCollection	= getenv('MONGO_COLLECTION') ? getenv('MONGO_COLLECTION') : 'test';

# Init mail parse library
require_once __DIR__.'/../vendor/autoload.php';
use MimeMailParser\Parser;

// Connect to test mongo database
$m = new Mongo($mongoUrl);
$db = $m->$mongoDbName;

// Init parser
$parser = new Parser();

# Parse standard input
$parser->setText(file_get_contents('php://stdin'));

$to				= $parser->getHeader('to');
$delivered_to	= $parser->getHeader('delivered-to');

// If $to is empty, use delivered to
if (empty($to)) {
	$delivered_to = $delivered_to;
}

$from = $parser->getHeader('from');
$subject = $parser->getHeader('subject');
$text = $parser->getMessageBody('text');
$html = $parser->getMessageBody('html');
//$attachments = $parser->getAttachments();

# Save message into MongoDB
$msg = array(
	'to'		=> $to,
	'from'		=> $from,
	'subject'	=> $subject,
	'body'		=> array(
		'text'	=> $text,
		'html'	=> $html
	)
);

// Get the collection
$collectionObj = $db->$mongoCollection;

// Insert this new message into the collection
$collectionObj->save($msg);

// End script successfully
exit(0);
