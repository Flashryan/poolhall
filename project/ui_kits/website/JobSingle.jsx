/* Poolhall UI kit — Single job page (with apply aside + schema note) */

function JobSingle({ go, job }) {
  const D = window.PH_DATA;
  const j = job || D.jobs[0];
  const F = window.PH_FMT;
  const related = D.jobs.filter(x => x.id !== j.id && x.sector === j.sector).slice(0, 2);
  return (
    <main className="bg-paper">
      <div className="container">
        <div className="crumb">
          <a href="#" onClick={e => { e.preventDefault(); go("home"); }}>Home</a>
          <Icon name="chevron-right" />
          <a href="#" onClick={e => { e.preventDefault(); go("jobs"); }}>Find a Job</a>
          <Icon name="chevron-right" /> <span>{j.title}</span>
        </div>
      </div>

      {/* job header */}
      <div className="bg-navy" style={{ padding: "40px 0 44px" }}>
        <div className="container">
          {j.featured && <span className="jc-feat" style={{ marginBottom: 12 }}><Icon name="star" /> Featured role</span>}
          <p className="eyebrow on-dark" style={{ marginTop: j.featured ? 12 : 0 }}>{j.sector}</p>
          <h1 className="h-display on-dark" style={{ fontSize: "clamp(2rem,3.5vw,2.7rem)", maxWidth: "18ch" }}>{j.title}</h1>
          <div className="jc-meta" style={{ marginTop: 18 }}>
            <span style={{ color: "var(--fg-on-dark-2)" }}><Icon name="map-pin" style={{ color: "var(--orange-400)" }} /> {j.location}, {j.region}</span>
            <span style={{ color: "var(--fg-on-dark-2)" }}><Icon name="briefcase" style={{ color: "var(--orange-400)" }} /> {j.type}</span>
            <span style={{ color: "var(--fg-on-dark-2)" }}><Icon name="house" style={{ color: "var(--orange-400)" }} /> {j.work}</span>
            <span style={{ color: "var(--fg-on-dark-2)" }}><Icon name="clock" style={{ color: "var(--orange-400)" }} /> Posted {j.posted}</span>
          </div>
        </div>
      </div>

      <div className="container" style={{ padding: "44px 24px 0" }}>
        <div className="job-layout">
          <div className="job-body">
            <p className="lead" style={{ color: "var(--fg-1)" }}>{j.summary}</p>
            <h2 className="h3">About the role</h2>
            <p>Poolhall Recruitment are working on behalf of our client to find an exceptional candidate for this {j.type.toLowerCase()} position. This is a genuine opportunity to join a well-run business that values quality, ethics and long-term careers.</p>
            <h2 className="h3">What you'll do</h2>
            <ul>{j.bullets.map((b, i) => <li key={i}>{b}</li>)}</ul>
            <h2 className="h3">What we're looking for</h2>
            <ul>
              <li>Experience: {j.experience}</li>
              {j.education && <li>Education: {j.education}</li>}
              <li>A positive, reliable approach and pride in your work</li>
              <li>Right to work in the UK</li>
            </ul>
            <div style={{ background: "var(--blue-50)", border: "1px solid var(--blue-200)", borderRadius: "var(--r-md)", padding: "16px 18px", marginTop: 24, display: "flex", gap: 12, fontSize: 14, color: "var(--navy-700)" }}>
              <Icon name="info" style={{ color: "var(--blue-600)", flex: "none", marginTop: 2 }} />
              <span>By applying you agree to Poolhall Recruitment's Data Protection Policy. We'll only ever use your details to support your application.</span>
            </div>

            {/* schema/dev note */}
            <details style={{ marginTop: 28, background: "var(--mist)", borderRadius: "var(--r-md)", padding: "14px 18px", fontSize: 13 }}>
              <summary style={{ cursor: "pointer", fontWeight: 700, color: "var(--fg-1)" }}>JobPosting structured data (Google for Jobs)</summary>
              <pre style={{ fontFamily: "var(--font-mono)", fontSize: 12, color: "var(--fg-2)", whiteSpace: "pre-wrap", marginTop: 12 }}>{`{
  "@type": "JobPosting",
  "title": "${j.title}",
  "datePosted": "2026-05-29",
  "validThrough": "2026-06-28",
  "employmentType": "FULL_TIME",
  "hiringOrganization": "Poolhall Recruitment",
  "jobLocation": "${j.location}, ${j.region}",
  "baseSalary": { "min": ${j.salaryMin}, "max": ${j.salaryMax}, "unit": "YEAR" }
}`}</pre>
            </details>
          </div>

          {/* apply aside */}
          <aside className="apply-aside">
            <div className="sal">{F.salary(j)}</div>
            <div className="ph-meta">per year</div>
            <div className="aside-meta">
              <div className="r"><span>Sector</span><span>{j.sector}</span></div>
              <div className="r"><span>Location</span><span>{j.location}</span></div>
              <div className="r"><span>Type</span><span>{j.type}</span></div>
              <div className="r"><span>Working</span><span>{j.work}</span></div>
              <div className="r"><span>Reference</span><span>#{j.id}</span></div>
            </div>
            <Button variant="primary" className="btn-block btn-lg" iconRight="arrow-right" onClick={() => go("apply", j)}>Apply for this role</Button>
            <Button variant="ghost" className="btn-block" icon="bookmark" style={{ marginTop: 10 }}>Save job</Button>
            <div style={{ textAlign: "center", marginTop: 16, fontSize: 13, color: "var(--fg-3)" }}>or call <strong style={{ color: "var(--fg-1)" }}>0121 516 3000</strong></div>
          </aside>
        </div>

        {/* related */}
        {related.length > 0 && (
          <section style={{ padding: "56px 0 84px" }}>
            <h2 className="h2" style={{ fontSize: "1.5rem", marginBottom: 22 }}>Similar roles</h2>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16 }}>
              {related.map(r => <JobCard key={r.id} job={r} go={go} />)}
            </div>
          </section>
        )}
      </div>
    </main>
  );
}

window.JobSingle = JobSingle;
