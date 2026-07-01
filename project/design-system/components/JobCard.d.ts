import * as React from "react";

export interface Job {
  id: number;
  title: string;
  sector: string;
  location: string;
  type: string;
  work: string;
  salaryMin: number;
  salaryMax: number;
  posted: string;
  featured?: boolean;
  summary?: string;
}

export interface JobCardProps {
  job: Job;
  /** Click handler for the whole card (e.g. navigate to the single job). */
  onOpen?: (job: Job) => void;
}

/** Poolhall job listing card — mono meta row, hi-vis featured edge, salary + CTA foot. */
export function JobCard(props: JobCardProps): JSX.Element;
