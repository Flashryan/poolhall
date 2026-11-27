<?php
/**
 * Poolhall-branded email shell and sender.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Support;

/**
 * Every email the plugin sends goes out in the same wrapper: navy bar carrying
 * the roundel, a thin gold rule under it, the message on white, then a footer.
 *
 * Nested tables and inline styles throughout. Outlook drops stylesheets and any
 * modern layout, so anything a client has to honour is an attribute rather than
 * a rule. Mail always leaves as multipart — `send_text()` keeps the plain text
 * it was handed as the alternative part, so text-only clients, screen readers
 * and spam filters still see a readable body.
 *
 * Callers that only have plain text (the `Accounts\Mailer` seam, the office
 * notifications) hand it to `send_text()`, which renders paragraphs, turns a
 * lone URL into a button and a run of `Label: value` lines into a table.
 * Callers that want a specific layout build it from the block helpers and pass
 * the result to `send()`.
 */
final class BrandedMail {

	private const NAVY     = '#0B2846';
	private const GOLD     = '#FDBB5D';
	private const GOLD_INK = '#8A5E12';
	private const GOLD_BG  = '#FEF8EC';
	private const PAPER    = '#F7F8FA';
	private const BORDER   = '#E6E9ED';
	private const INK      = '#11161B';
	private const MUTED    = '#4A555F';

	private const SANS = 'Arial,Helvetica,sans-serif';

	/** New R mark, resolved through the shared brand asset module. */
	private const LOGO = 'mark-email.png';

	/**
	 * The supplied Poolhall wordmark, rendered from its original vector paths
	 * as a 3x PNG so Gmail and Outlook need no SVG or web-font support.
	 */
	private const WORDMARK   = 'wordmark-email.png';
	private const WORDMARK_W = 211;
	private const WORDMARK_H = 50;

	/** Line under the name in the header bar. */
	public const STRAPLINE = 'Right people. Right roles. Right here.';

	/**
	 * Where replies go by default. Mail leaves from whatever mailbox the SMTP
	 * connection uses, which is not necessarily one the team reads.
	 * Override with the `poolhall_reply_to` option.
	 */
	public const REPLY_TO = 'jobs@poolhallrecruitment.co.uk';

	/** Plain-text alternative for the send currently in flight. */
	private static string $alt_body = '';

	/**
	 * Send pre-built HTML, keeping `$text` as the plain-text alternative.
	 *
	 * A drop-in for wp_mail: same return, and a transport exception still
	 * reaches the caller, several of which record it. Only the hook cleanup is
	 * guaranteed.
	 *
	 * @param string             $html      Body HTML for the white content area.
	 * @param string             $text      Plain-text alternative.
	 * @param array<int,string>  $headers   Extra headers; a content type is added.
	 * @param \Closure|null      $transport Alternate transport, for tests.
	 */
	public static function send( string $to, string $subject, string $html, string $text, array $headers = array(), ?\Closure $transport = null ): bool {
		$headers = self::with_reply_to( self::with_content_type( $headers ) );
		$body    = self::shell( $html );

		// wp_mail never sets AltBody, so hook it around this one send only.
		self::$alt_body = $text;
		add_action( 'phpmailer_init', array( self::class, 'set_alt_body' ) );

		try {
			return null !== $transport
				? (bool) $transport( $to, $subject, $body, $headers )
				: (bool) wp_mail( $to, $subject, $body, $headers );
		} finally {
			remove_action( 'phpmailer_init', array( self::class, 'set_alt_body' ) );
			self::$alt_body = '';
		}
	}

	/**
	 * Send plain text, rendered into the branded shell. The text goes out
	 * unchanged as the alternative part, so nothing is lost either way.
	 *
	 * @param array<int,string> $headers   Extra headers; a content type is added.
	 * @param \Closure|null     $transport Alternate transport, for tests.
	 */
	public static function send_text( string $to, string $subject, string $text, array $headers = array(), ?\Closure $transport = null ): bool {
		return self::send( $to, $subject, self::from_text( $subject, $text ), $text, $headers, $transport );
	}

	/**
	 * Attach the plain-text alternative to the HTML send in flight. PHPMailer
	 * only builds one from msgHTML(), which wp_mail does not call, so without
	 * this the message would leave HTML-only.
	 *
	 * @param object $phpmailer PHPMailer instance, passed by reference by WP.
	 */
	public static function set_alt_body( $phpmailer ): void {
		if ( '' !== self::$alt_body && is_object( $phpmailer ) ) {
			$phpmailer->AltBody = self::$alt_body;
		}
	}

	/**
	 * Render a plain-text body as branded HTML. Blank lines separate blocks; a
	 * block that is nothing but a URL becomes a button, a block of `Label: value`
	 * lines becomes a table, anything else becomes a paragraph.
	 */
	public static function from_text( string $subject, string $text ): string {
		$heading = self::heading_from_subject( $subject );
		$html    = '' !== $heading ? self::heading( esc_html( $heading ) ) : '';

		$normalised = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$opening    = true;
		foreach ( preg_split( '/\n[ \t]*\n/', trim( $normalised ) ) ?: array() as $block ) {
			$block = trim( $block, "\n" );
			if ( '' === trim( $block ) ) {
				continue;
			}

			// A body that opens by restating its subject would print it twice
			// under the heading; the heading already says it.
			$restates = $opening && '' !== $heading && 0 === stripos( $heading, trim( $block ) );
			$opening  = false;
			if ( $restates ) {
				continue;
			}

			$url = trim( $block );
			if ( 1 === preg_match( '#^https?://\S+$#i', $url ) ) {
				$html .= self::button( $url, __( 'Continue', 'poolhall-integration' ) );
				continue;
			}

			$rows = self::as_detail_rows( $block );
			if ( array() !== $rows ) {
				$html .= self::details( $rows );
				continue;
			}

			$html .= self::paragraph( implode( '<br />', array_map( array( self::class, 'linkify' ), explode( "\n", $block ) ) ) );
		}

		return $html;
	}

	/** Section heading. `$html` is already escaped. */
	public static function heading( string $html ): string {
		return '<h1 style="margin:0 0 20px;font-family:Georgia,&quot;Times New Roman&quot;,serif;font-size:26px;'
			. 'line-height:34px;font-weight:normal;color:' . self::NAVY . ';">' . $html . '</h1>';
	}

	/** Body paragraph. `$html` is already escaped. */
	public static function paragraph( string $html ): string {
		return '<p style="margin:0 0 18px;font-family:' . self::SANS . ';font-size:16px;'
			. 'line-height:26px;color:' . self::INK . ';">' . $html . '</p>';
	}

	/** Gold-edged aside. `$title` is escaped here, `$html` already is. */
	public static function callout( string $title, string $html ): string {
		return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 22px;">'
			. '<tr><td bgcolor="' . self::GOLD_BG . '" style="background-color:' . self::GOLD_BG . ';border-left:3px solid ' . self::GOLD . ';'
			. 'padding:16px 18px;font-family:' . self::SANS . ';font-size:15px;line-height:24px;color:' . self::INK . ';">'
			. '<strong style="color:' . self::NAVY . ';">' . esc_html( $title ) . '</strong><br />' . $html
			. '</td></tr></table>';
	}

	/** Inline link in gold-ink, which stays readable on the white content area. */
	public static function link( string $href, string $label ): string {
		return '<a href="' . esc_url( $href, array( 'https', 'http', 'mailto', 'tel' ) ) . '"'
			. ' style="color:' . self::GOLD_INK . ';text-decoration:underline;">' . esc_html( $label ) . '</a>';
	}

	/**
	 * Primary call to action: gold fill, navy label, matching the site. The raw
	 * link follows in small type — a corporate client that strips the button
	 * would otherwise leave the reader with no way through.
	 */
	public static function button( string $href, string $label ): string {
		$safe = esc_url( $href );

		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 14px;">'
			. '<tr><td bgcolor="' . self::GOLD . '" style="background-color:' . self::GOLD . ';">'
			. '<a href="' . $safe . '" style="display:inline-block;padding:13px 28px;font-family:' . self::SANS . ';'
			. 'font-size:15px;line-height:20px;font-weight:bold;color:' . self::NAVY . ';text-decoration:none;">'
			. esc_html( $label ) . '</a></td></tr></table>'
			. '<p style="margin:0 0 20px;font-family:' . self::SANS . ';font-size:12px;line-height:20px;color:' . self::MUTED . ';">'
			. esc_html__( 'If the button does not work, paste this link into your browser:', 'poolhall-integration' ) . '<br />'
			. '<a href="' . $safe . '" style="color:' . self::MUTED . ';word-break:break-all;">' . esc_html( $href ) . '</a></p>';
	}

	/**
	 * Label/value table, for the notifications that go to the office. Labels are
	 * escaped here; values are linkified, which escapes them.
	 *
	 * @param array<int,array{0:string,1:string}> $rows Label/value pairs.
	 */
	public static function details( array $rows ): string {
		$html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"'
			. ' style="margin:4px 0 22px;border-collapse:collapse;">';
		foreach ( $rows as $row ) {
			$value = self::linkify( (string) $row[1] );
			// Fixed label width, so consecutive tables in one email line up.
			$html .= '<tr>'
				. '<td valign="top" width="36%" style="width:36%;padding:8px 16px 8px 0;border-bottom:1px solid ' . self::BORDER . ';'
				. 'font-family:' . self::SANS . ';font-size:13px;line-height:20px;color:' . self::MUTED . ';">'
				. esc_html( (string) $row[0] ) . '</td>'
				. '<td valign="top" style="padding:8px 0;border-bottom:1px solid ' . self::BORDER . ';'
				. 'font-family:' . self::SANS . ';font-size:14px;line-height:20px;color:' . self::INK . ';word-break:break-word;">'
				. ( '' !== trim( $value ) ? $value : '&mdash;' ) . '</td>'
				. '</tr>';
		}

		return $html . '</table>';
	}

	/**
	 * Wrap body copy in the shell. Public so a caller can preview or test the
	 * chrome on its own; `send()` applies it for you.
	 */
	public static function shell( string $content ): string {
		$logo = self::logo_url();
		// Decorative: the name beside it carries the brand for screen readers
		// and for anyone reading with images off.
		$mark = '' !== $logo
			? '<img src="' . esc_url( $logo ) . '" width="68" height="68" alt=""'
				. ' style="display:block;border:0;outline:none;text-decoration:none;width:68px;height:68px;" />'
			: '';

		// Percent width with a cap lets the name shrink on a narrow phone rather
		// than push the bar wider than the screen. If the image never arrives,
		// the alt text and the fallback both read in a serif.
		$wordmark = self::asset_url( self::WORDMARK );
		$name     = '' !== $wordmark
			? '<img src="' . esc_url( $wordmark ) . '" width="' . self::WORDMARK_W . '" height="' . self::WORDMARK_H . '"'
				. ' alt="' . esc_attr__( 'Poolhall Recruitment', 'poolhall-integration' ) . '"'
				. ' style="display:block;border:0;outline:none;text-decoration:none;width:100%;max-width:' . self::WORDMARK_W . 'px;height:auto;'
				. 'font-family:Georgia,&quot;Times New Roman&quot;,serif;font-size:20px;line-height:22px;color:#FFFFFF;" />'
			: '<div style="font-family:Georgia,&quot;Times New Roman&quot;,serif;font-size:21px;line-height:24px;color:#FFFFFF;">Poolhall Recruitment</div>';

		// Join the last two words so a phone never leaves one alone on a line.
		$strapline = (string) preg_replace( '/ (?=\S+$)/', '&nbsp;', esc_html( self::STRAPLINE ) );

		return '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'
			. '<html xmlns="http://www.w3.org/1999/xhtml"><head>'
			. '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />'
			. '<meta name="viewport" content="width=device-width, initial-scale=1" />'
			. '<title>Poolhall Recruitment</title></head>'
			. '<body style="margin:0;padding:0;background-color:' . self::PAPER . ';">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="' . self::PAPER . '"'
			. ' style="background-color:' . self::PAPER . ';">'
			. '<tr><td align="center" style="padding:24px 12px;">'
			// Attribute width for Outlook, which ignores CSS widths; percent plus
			// a cap for everyone else, so the card reflows on a phone.
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="#FFFFFF"'
			. ' style="width:100%;max-width:600px;background-color:#FFFFFF;border:1px solid ' . self::BORDER . ';">'

			// Navy bar: roundel left, name and strapline beside it.
			. '<tr><td bgcolor="' . self::NAVY . '" style="background-color:' . self::NAVY . ';padding:22px 28px;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
			. ( '' !== $mark ? '<td valign="middle" width="68" style="width:68px;">' . $mark . '</td>' : '' )
			. '<td valign="middle" style="padding-left:' . ( '' !== $mark ? '16' : '0' ) . 'px;">'
			. $name
			. '<div style="padding-top:7px;font-family:' . self::SANS . ';font-size:13px;line-height:18px;color:' . self::GOLD . ';letter-spacing:0.02em;">'
			. $strapline . '</div>'
			. '</td></tr></table></td></tr>'

			// Thin gold strip. Zero font-size keeps Outlook from padding it out.
			. '<tr><td bgcolor="' . self::GOLD . '" height="3" style="background-color:' . self::GOLD . ';height:3px;line-height:3px;font-size:0;">&nbsp;</td></tr>'

			. '<tr><td style="padding:32px 28px 12px;">' . $content . '</td></tr>'

			. '<tr><td bgcolor="' . self::PAPER . '" style="background-color:' . self::PAPER . ';border-top:1px solid ' . self::BORDER . ';'
			. 'padding:20px 28px;font-family:' . self::SANS . ';font-size:12px;line-height:20px;color:' . self::MUTED . ';">'
			. 'Poolhall Recruitment Limited &middot; Registered in England &amp; Wales, company no. 13319338<br />'
			. '<a href="tel:+441215163000" style="color:' . self::MUTED . ';text-decoration:none;">0121 516 3000</a> &middot; '
			. '<a href="mailto:jobs@poolhallrecruitment.co.uk" style="color:' . self::MUTED . ';text-decoration:none;">jobs@poolhallrecruitment.co.uk</a>'
			. '</td></tr>'

			. '</table></td></tr></table></body></html>';
	}

	/**
	 * The roundel as a hosted PNG. SVG does not render in most mail clients, and
	 * this copy has the navy ground baked in so it sits flush on the header bar
	 * without an alpha channel Outlook would fill with black. Empty when the
	 * file is missing, in which case the bar goes without it.
	 */
	public static function logo_url(): string {
		return self::asset_url( self::LOGO );
	}

	/** Public URL from the shared branding module, or empty if missing. */
	private static function asset_url( string $relative ): string {
		return BrandAssets::url( $relative );
	}

	/**
	 * Escape a line and turn any bare URL in it into a link. Escaping and
	 * linking happen per token so an escaped `&amp;` never lands inside an href.
	 */
	private static function linkify( string $raw ): string {
		$out    = '';
		$offset = 0;
		if ( preg_match_all( '#https?://[^\s<>"\']+#i', $raw, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $match ) {
				[ $url, $at ] = $match;
				// Sentence punctuation runs onto the end of a URL; leave it for the
				// next plain segment rather than the href.
				$trimmed = rtrim( (string) $url, '.,;:)]' );
				$out    .= esc_html( substr( $raw, $offset, $at - $offset ) )
					. '<a href="' . esc_url( $trimmed ) . '" style="color:' . self::GOLD_INK . ';word-break:break-word;">'
					. esc_html( $trimmed ) . '</a>';
				$offset  = $at + strlen( $trimmed );
			}
		}

		return $out . esc_html( substr( $raw, $offset ) );
	}

	/**
	 * A block is a detail table only when every line in it reads `Label: value`,
	 * and there are at least two. Anything looser (a candidate's own message,
	 * a single `Most recent error:` line) stays a paragraph.
	 *
	 * @return array<int,array{0:string,1:string}> Empty when the block is prose.
	 */
	private static function as_detail_rows( string $block ): array {
		$rows = array();
		foreach ( explode( "\n", $block ) as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			// Long enough for "CV retained in the protected candidate record".
			if ( 1 !== preg_match( '/^([A-Z][A-Za-z0-9 \/\-]{0,50}):[ \t]*(.*)$/', trim( $line ), $parts ) ) {
				return array();
			}
			$rows[] = array( $parts[1], $parts[2] );
		}

		return count( $rows ) >= 2 ? $rows : array();
	}

	/**
	 * Subject line reused as the heading, minus the brand — the navy bar above
	 * it already says who this is from, so repeating it just costs a line.
	 */
	private static function heading_from_subject( string $subject ): string {
		$heading = trim( $subject );
		$heading = (string) preg_replace( '/^\[Poolhall\]\s*/i', '', $heading );
		$heading = (string) preg_replace( '/\s*[-–—]\s*Poolhall Recruitment\s*$/iu', '', $heading );
		$heading = (string) preg_replace( '/^Poolhall Recruitment\s*[-–—]\s*/iu', '', $heading );

		return ucfirst( trim( $heading ) );
	}

	/**
	 * Keep whatever headers the caller set (a Reply-To on the office
	 * notifications) and declare HTML, replacing any content type already there.
	 *
	 * @param array<int,string> $headers Caller headers.
	 * @return array<int,string>
	 */
	private static function with_content_type( array $headers ): array {
		$kept = array();
		foreach ( $headers as $header ) {
			if ( is_string( $header ) && 1 !== preg_match( '/^\s*Content-Type\s*:/i', $header ) ) {
				$kept[] = $header;
			}
		}
		$kept[] = 'Content-Type: text/html; charset=UTF-8';

		return $kept;
	}

	/**
	 * Add the office Reply-To unless the caller already set one (the office
	 * notifications reply to the applicant instead).
	 *
	 * @param array<int,string> $headers Headers so far.
	 * @return array<int,string>
	 */
	private static function with_reply_to( array $headers ): array {
		foreach ( $headers as $header ) {
			if ( is_string( $header ) && 1 === preg_match( '/^\s*Reply-To\s*:/i', $header ) ) {
				return $headers;
			}
		}

		$address = sanitize_email( (string) get_option( 'poolhall_reply_to', '' ) );
		/**
		 * Mailbox that replies to Poolhall emails should reach.
		 *
		 * @param string $address Email address; empty sends no Reply-To.
		 */
		$address = (string) apply_filters( 'poolhall_mail_reply_to', '' !== $address ? $address : self::REPLY_TO );
		if ( '' === $address || ! is_email( $address ) ) {
			return $headers;
		}

		$headers[] = 'Reply-To: Poolhall Recruitment <' . $address . '>';

		return $headers;
	}
}
