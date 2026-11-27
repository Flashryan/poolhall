<?php
declare(strict_types=1);

namespace Poolhall\Integration\Source;

/** Lookup and profile updates stay behind the ATS adapter boundary. */
interface CandidateDirectory extends CandidateSink {

	/** @return array<int,array{id:string,first_name:string,last_name:string,email:string}> */
	public function find_candidates_by_email( string $email ): array;

	/** @param array<string,string> $address Source-neutral address fields. */
	public function update_profile( string $id, CandidatePayload $profile, array $address ): void;

	public function add_note( string $candidate_id, string $note ): void;
}
