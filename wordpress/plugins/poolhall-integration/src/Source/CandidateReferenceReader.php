<?php
declare(strict_types=1);

namespace Poolhall\Integration\Source;

/** Optional ID-based verification; existing CandidateSink/Directory APIs stay compatible. */
interface CandidateReferenceReader extends CandidateDirectory {
	/** @return array{id:string,first_name:string,last_name:string,email:string}|null */
	public function find_candidate_by_id( string $id ): ?array;
}
