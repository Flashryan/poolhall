/* Poolhall UI kit v2 — Single job (§9.4) */

function JobSingle({ go, job }) {
  const D = window.PH_DATA;
  const j = job || D.jobs[0];
  const F = window.PH_FMT;
  const sec = window.PH_SECTOR[j.sector] || { key: "dig" };
  const [saved, setSaved] = useState(false);
  const [applyOpen, setApplyOpen] = useState(false);
  const related = D.jobs.filter(x => x.id !== j.id && x.sector === j.sector).slice(0, 2);

  return (
    <main className="bg-alt">
      <div className="pagehead photo">
        <img src={window.PH_SECTOR[j.sector].img()} alt="" onError={e => e.target.style.display='none'} />
        <div className="container">
          <div className="crumb" style={{ color: "var(--steel-200)", paddingTop: 0 }}>
            <a href="#" onClick={e => { e.preventDefault(); go("home"); }}>Home</a><Icon name="chevron-right" />
            <a href="#" onClick={e => { e.preventDefault(); go("jobs"); }}>Jobs</a><Icon name="chevron-right" /><span>{j.sector}</span>
          </div>
          <div style={{ display: "flex", gap: 10, alignItems: "center", marginBottom: 14 }}>
            <span className={"jc-tag " + sec.key}>{j.sector}</span>
            {j.featured && <span className="jc-feat" style={{ color: "var(--hivis-400)" }}><Icon name="star" /> Featured</span>}
          </div>
          <h1 style={{ maxWidth: "20ch" }}>{j.title}</h1>
          <div style={{ fontFamily: "var(--font-mono)", fontSize: 13.5, color: "var(--fg-on-dark-2)", display: "flex", flexWrap: "wrap", gap: "8px 22px", marginTop: 18 }}>
            <span><Icon name="map-pin" style={{ width: 15, color: "var(--hivis-400)", verticalAlign: -3 }} /> {j.location}, {j.region}</span>
            <span><Icon name="building" style={{ width: 15, color: "var(--hivis-400)", verticalAlign: -3 }} /> {j.work}</span>
            <span><Icon name="banknote" style={{ width: 15, color: "var(--hivis-400)", verticalAlign: -3 }} /> {F.salary(j)}</span>
            <span><Icon name="clock" style={{ width: 15, color: "var(--hivis-400)", verticalAlign: -3 }} /> Posted {j.posted}</span>
          </div>
        </div>
      </div>

      <div className="container">
        <div className="job-layout">
          <div className="job-body">
            <p className="lead" style={{ color: "var(--ink-900)", fontWeight: 500 }}>{j.summary}</p>
            <h2>About the role</h2>
            <p>We're recruiting on behalf of our client for an exceptional candidate to take on this {j.type.toLowerCase()} position. This is a genuine opportunity to join a well-run business that values quality, precision and people who take pride in their work.</p>
            <h2>What you'll do</h2>
            <ul>{j.bullets.map((b, i) => <li key={i}>{b}</li>)}</ul>
            <h2>What we're looking for</h2>
            <ul>
              <li>Experience: {j.experience}</li>
              {j.education && <li>Education: {j.education}</li>}
              <li>A positive, reliable approach and pride in your work</li>
              <li>Right to work in the UK</li>
            </ul>
            <div style={{ background: "var(--steel-50)", border: "1px solid var(--steel-100)", borderRadius: "var(--r)", padding: "16px 18px", marginTop: 26, display: "flex", gap: 12, fontSize: 14.5, color: "var(--slate-800)" }}>
              <Icon name="shield-check" style={{ color: "var(--steel-600)", flex: "none", marginTop: 2 }} />
              <span>By applying you agree to Poolhall's privacy policy. We'll only ever use your details to support your application, and nothing more.</span>
            </div>
          </div>

          <aside className="apply-aside">
            <div className="sal">{F.salary(j)}</div>
            <div className="per">PER YEAR · {j.type.toUpperCase()}</div>
            <div className="aside-meta">
              <div className="r"><span>Sector</span><span>{j.sector}</span></div>
              <div className="r"><span>Location</span><span>{j.location}</span></div>
              <div className="r"><span>Work mode</span><span>{j.work}</span></div>
              <div className="r"><span>Experience</span><span>{j.experience}</span></div>
              <div className="r"><span>Reference</span><span>#{j.id}</span></div>
            </div>
            <Button variant="primary" className="btn-block btn-lg" iconRight="arrow-right" onClick={() => setApplyOpen(true)}>Apply for this role</Button>
            <Button variant={saved ? "steel" : "ghost"} className="btn-block" icon={saved ? "check" : "bookmark"} style={{ marginTop: 10 }} onClick={() => setSaved(s => !s)}>{saved ? "Saved" : "Save job"}</Button>
            <div style={{ textAlign: "center", marginTop: 16, fontFamily: "var(--font-mono)", fontSize: 12.5, color: "var(--mist-400)" }}>or call 0121 516 3000</div>
          </aside>
        </div>

        {related.length > 0 && (
          <section style={{ padding: "0 0 96px" }}>
            <SectionHead idx="//" label="Similar roles" title={"More in " + j.sector} />
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 22 }}>
              {related.map(r => <JobCard key={r.id} job={r} go={go} />)}
            </div>
          </section>
        )}
      </div>

      {/* sticky mobile apply bar */}
      <div className="mobile-apply">
        <div className="ms">
          <div className="v">{F.salary(j)}</div>
          <div className="l">{j.type} · {j.location}</div>
        </div>
        <Button variant="primary" iconRight="arrow-right" onClick={() => setApplyOpen(true)}>Apply</Button>
      </div>

      <ApplyModal job={j} open={applyOpen} onClose={() => setApplyOpen(false)} go={go} />
    </main>
  );
}

window.JobSingle = JobSingle;
