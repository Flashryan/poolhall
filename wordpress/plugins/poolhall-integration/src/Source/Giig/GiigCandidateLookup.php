<?php
declare(strict_types=1);

namespace Poolhall\Integration\Source\Giig;

use Poolhall\Integration\Source\SourceException;

/** Fail closed on unfamiliar or incomplete search results; never infer no match. */
final class GiigCandidateLookup {

	/** @return array<int,array<string,mixed>> */
	public static function rows( array $data ): array {
		if ( array_is_list( $data ) ) {
			foreach ( $data as $row ) {
				if ( ! is_array( $row ) ) {
					throw new SourceException( 'Giig candidate lookup returned an invalid list.', true );
				}
			}
			return $data;
		}
		$keys = array_change_key_case( $data, CASE_LOWER );
		if ( isset( $keys['error'] ) && $keys['error'] || isset( $keys['success'] ) && false === $keys['success'] ) {
			throw new SourceException( 'Giig candidate lookup did not succeed.', true );
		}
		foreach ( array( 'candidates', 'data', 'result', 'items', 'records' ) as $key ) {
			if ( isset( $keys[ $key ] ) && is_array( $keys[ $key ] ) ) {
				$rows = self::rows( $keys[ $key ] );
				foreach ( array( 'total', 'totalcount', 'total_count' ) as $total ) {
					if ( isset( $keys[ $total ] ) && (int) $keys[ $total ] > count( $rows ) ) {
						throw new SourceException( 'Giig candidate lookup is truncated; manual review is required.', true );
					}
				}
				return $rows;
			}
		}
		throw new SourceException( 'Giig candidate lookup returned an unfamiliar response.', true );
	}

	/** @return array{id:string,first_name:string,last_name:string,email:string}|null */
	public static function by_id( array $rows, string $id ): ?array {
		if ( count( $rows ) > 1 ) throw new SourceException( 'Giig ID lookup returned multiple records.', true );
		if ( array() === $rows ) return null;
		$fields = array();
		foreach ( $rows[0] as $key => $value ) {
			if ( is_scalar( $value ) ) $fields[ str_replace( '_', '', strtolower( (string) $key ) ) ] = trim( (string) $value );
		}
		if ( ( $fields['candidateid'] ?? $fields['id'] ?? '' ) !== $id ) throw new SourceException( 'Giig ID lookup returned a different record.', true );
		$email = $fields['emailaddress'] ?? $fields['email'] ?? '';
		$matches = self::exact_email_matches( $rows, $email );
		return 1 === count( $matches ) ? $matches[0] : null;
	}

	/** @return array<int,array{id:string,first_name:string,last_name:string,email:string}> */
	public static function exact_email_matches( array $rows, string $email ): array {
		$matches = array();
		foreach ( $rows as $row ) {
			$fields = array();
			foreach ( $row as $key => $value ) {
				if ( is_scalar( $value ) ) {
					$fields[ str_replace( '_', '', strtolower( (string) $key ) ) ] = trim( (string) $value );
				}
			}
			$id = $fields['candidateid'] ?? $fields['id'] ?? '';
			$remote_email = $fields['emailaddress'] ?? $fields['email'] ?? '';
			if ( ! preg_match( '/^[1-9][0-9]*$/', $id ) || false === filter_var( $remote_email, FILTER_VALIDATE_EMAIL ) ) {
				throw new SourceException( 'Giig candidate lookup omitted identity fields; manual review is required.', true );
			}
			if ( strtolower( $remote_email ) !== strtolower( trim( $email ) ) ) {
				continue;
			}
			$first = $fields['firstname'] ?? '';
			$last = $fields['lastname'] ?? '';
			if ( '' === $first || '' === $last ) {
				throw new SourceException( 'Giig candidate lookup omitted the matching name; manual review is required.', true );
			}
			$match = array( 'id' => $id, 'first_name' => $first, 'last_name' => $last, 'email' => strtolower( $remote_email ) );
			if ( isset( $matches[ $id ] ) && $matches[ $id ] !== $match ) {
				throw new SourceException( 'Giig candidate lookup returned conflicting identities.', true );
			}
			$matches[ $id ] = $match;
		}
		return array_values( $matches );
	}
}
