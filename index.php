<?php
/**
 * Simple Rage Face fetcher
 */

namespace RageApp;

use Symfony\Component\HttpFoundation\Response;

// get autoloader
require_once __DIR__ . '/vendor/autoload.php';

if ( file_exists( __DIR__ . '/config.php' ) ) {
	require_once __DIR__ . '/config.php';
}

$app = new \Silex\Application();

// get heroku vars
$env_token = getenv('SLACK_TOKEN');
$env_webhook = getenv('SLACK_WEBHOOK');

// slack hooks
// 1. copy the sample config file to config.php
// 2. change the sample array so that your slash command token is the key and you incoming webhook is the value
// 3. add as many as you like

// heroku mode
if(!empty($env_token) && !empty($env_webhook)){
	$app['webhooks'] = [
		$env_token => $env_webhook
	];
} else {
	$app['webhooks'] = isset( $webhooks ) ? $webhooks : [ ];
}

// add fetch service
$app['fetch_rage'] = function ( \Silex\Application $app ) {

	$search = $app['request']->get( 'text' );
	$search = str_replace( ' ', '-', $search );

	// fetch from alltherage
	// $client = new \GuzzleHttp\Client();
	
	// $res    = $client->get('http://alltheragefaces.com/api/search/' . $search , 
	// 	[
	// 		'curl' => [
	// 		        CURLOPT_SSL_VERIFYHOST => false,
	// 		        CURLOPT_VERBOSE => true
	// 		    ],
	// 		'verify' => false, 
	// 		debug => true
	// 	]
	// 	);
	
	$res = \Requests::get('http://alltheragefaces.com/api/search/' . $search);	
	$json   = json_decode( $res->body);
		
	// $json   = json_decode( $res->getBody() );

	if ( $json && is_array( $json ) ) {
		$img = $json[ array_rand( $json ) ];
		return $img;
	}

	$app->abort( 200, 'Failed to fetch anything from the API :(' );
};

$app->get( '/', function ( \Silex\Application $app ) {
	$img = $app['fetch_rage'];
	return sprintf( '<img src="%s" alt="%s" />', $app->escape( $img->png ), $app->escape( $img->title ) );
} );

$app->post( '/', function ( \Silex\Application $app ) {
	$img = $app['fetch_rage'];

	// check for slack data
	$token = $app['request']->get( 'token' );

	if ( $token && isset( $app['webhooks'][ $token ] ) ) {

		// check if user chat or channel
		$channel = $app['request']->get( 'channel_name' ) === 'directmessage' ?
			$app['request']->get( 'channel_id' ) :
			'#' . $app['request']->get( 'channel_name' );

		$payload = json_encode( [
			'text'        => '/rage ' . $app['request']->get( 'text' ),
			'channel'     => $channel,
			'username'    => $app['request']->get( 'user_name' ),
			'icon_url'    => 'http://cdn.alltheragefaces.com/img/faces/png/troll-troll-face.png',
			'attachments' => [
				[
					'title'     => $img->title,
					'fallback'  => $img->title,
					'image_url' => $img->png,
				]
			]
		] );

		// $client  = new \GuzzleHttp\Client();
		
		// $promise = $client->postAsync( $app['webhooks'][ $token ], [ 'body' => $payload ] );
		// $promise->wait();

		// $res = \Requests::post($app['webhooks'][ $token ], [ 'body' => $payload ]);	

		$ch = curl_init($app['webhooks'][ $token ]);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
				'Content-Length: ' . strlen($payload))
			);
 
		$res = curl_exec($ch);    
		
		return '';
	}

	return sprintf( '<img src="%s" alt="%s" />', $app->escape( $img->png ), $app->escape( $img->title ) );
} );

$app->error( function ( \Exception $e, $code ) {
	return new Response( $e->getMessage() );
} );

$app->run();
