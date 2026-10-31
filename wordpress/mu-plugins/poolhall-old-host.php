<?php
/**
 * Plugin Name: Poolhall old host redirect
 * Description: Sends the Hostinger temporary domain to the live site with a 301.
 */
if ( isset( $_SERVER['HTTP_HOST'] ) && 'lightslategrey-hare-335761.hostingersite.com' === strtolower( $_SERVER['HTTP_HOST'] )
	&& 'cli' !== PHP_SAPI && 0 !== strpos( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), '/wp-json/' ) ) {
	header( 'Location: https://www.poolhallrecruitment.co.uk' . ( $_SERVER['REQUEST_URI'] ?? '/' ), true, 301 );
	exit;
}
